<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Xui;
use App\Enums\XuiTag;
use App\Enums\Callback;
use App\Models\Pricing;
use SergiX44\Nutgram\Nutgram;
use App\Services\SettingService;
use App\Services\YooKassaService;
use App\Services\User\UserService;
use App\Jobs\ProcessAcceptTermsJob;
use Illuminate\Support\Facades\Log;
use App\Jobs\ProcessPaymentCreationJob;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

/**
 * Пример обработчиков команд для Telegram бота
 *
 * Использование:
 * В AppServiceProvider или в отдельном сервис-провайдере зарегистрируйте обработчики:
 *
 * $bot = app(Nutgram::class);
 * $bot->onCommand('start', function (Nutgram $bot) {
 *     $bot->sendMessage('Привет! Я бот.');
 * });
 *
 * Или используйте этот класс для организации обработчиков
 */
final class TelegramBotHandlers
{
    public function __construct(
        private readonly Nutgram $bot,
        private readonly VpnConnectionService $vpnConnectionService,
        private readonly UserService $userService,
        private readonly PricingService $pricingService,
        private readonly SettingService $settingService,
    ) {}

    public function registerHandlers(): void
    {
        $this->bot->onMessage(function (Nutgram $bot) {
            $text = $bot->message()?->text ?? '';
            if (preg_match('/^\/start(\s|$)/i', $text)) {
                Log::info('Start command detected via onMessage', [
                    'text' => $text,
                    'telegram_id' => $bot->userId(),
                ]);
                $this->handleStartCommand($bot);
            }
        });

        $this->bot->onCommand('start', function (Nutgram $bot) {
            $this->handleStartCommand($bot);
        });

        // Ответ на Shipping Query (для цифровых товаров доставка не требуется)
        $this->bot->onShippingQuery(function (Nutgram $bot) {
            try {
                $bot->answerShippingQuery(ok: true, shipping_options: []);
            } catch (\Throwable $e) {
                Log::error('Failed to answerShippingQuery', ['error' => $e->getMessage()]);
            }
        });

        // Ответ на PreCheckout Query (обязателен в течение 10 секунд)
        $this->bot->onPreCheckoutQuery(function (Nutgram $bot) {
            try {
                $bot->answerPreCheckoutQuery(ok: true);
            } catch (\Throwable $e) {
                Log::error('Failed to answerPreCheckoutQuery', ['error' => $e->getMessage()]);
            }
        });

        // Отмена платежа (кнопка на invoice)
        $this->bot->onCallbackQueryData('payment:cancel:{payment_id}', function (Nutgram $bot, string $paymentId) {
            $bot->answerCallbackQuery();

            $telegramId = (string)$bot->userId();
            /** @var \App\Models\User|null $user */
            $user = $this->userService->findUserByTelegramId((int)$telegramId);
            if (!$user) {
                $bot->sendMessage('❌ Пользователь не найден');
                return;
            }

            /** @var \App\Models\Payment|null $payment */
            $payment = \App\Models\Payment::find((int)$paymentId);
            if (!$payment) {
                $bot->sendMessage('❌ Платеж не найден');
                return;
            }

            if ((int)$payment->user_id !== (int)$user->id) {
                $bot->sendMessage('❌ Нет доступа к этому платежу');
                return;
            }

            // Удаляем invoice-сообщение (берем message_id из callback либо из payment)
            $msgId = $bot->callbackQuery()?->message?->message_id ?? $payment->telegram_message_id;
            if ($msgId) {
                try {
                    $bot->deleteMessage($bot->chatId(), $msgId);
                } catch (\Throwable $e) {
                    Log::debug('Failed to delete invoice message', [
                        'payment_id' => $payment->id,
                        'message_id' => $msgId,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Отмечаем платеж отмененным (только если он еще pending) — защищаемся от гонок
            $updated = \App\Models\Payment::where('id', (int)$paymentId)
                ->where('user_id', (int)$user->id)
                ->where('status', \App\Models\Payment::STATUS_PENDING)
                ->update([
                    'status' => \App\Models\Payment::STATUS_CANCELED,
                    'yookassa_status' => 'canceled',
                ]);

            // Обновляем экземпляр для правильного ответа пользователю
            $payment->refresh();

            // Чистим сохраненные message ids и отправляем подтверждение + главное меню
            $messageIds = $bot->getGlobalData('vpn_message_ids', []);
            $this->clearChat($messageIds, $bot, $telegramId);

            $keyboard = InlineKeyboardMarkup::make()
                ->addRow($this->getMainMenuButton());

            $text = match ($payment->status) {
                \App\Models\Payment::STATUS_CANCELING => $updated > 0 ? '✅ Платеж отменен' : '⚠️ Платеж уже отменен',
                \App\Models\Payment::STATUS_CANCELED => $updated > 0 ? '✅ Платеж отменен' : '⚠️ Платеж уже отменен',
                \App\Models\Payment::STATUS_SUCCEEDED => '✅ Платеж уже оплачен',
                default => '⚠️ Этот платеж уже не активен',
            };

            $sent = $bot->sendMessage($text, reply_markup: $keyboard);
            if ($sent && $sent->message_id) {
                $bot->setGlobalData('vpn_message_ids', [$sent->message_id]);
            }
        });

        // Обработка успешной оплаты через Telegram Payments
        $this->bot->onMessage(function (Nutgram $bot) {
            $successfulPayment = $bot->message()?->successful_payment;
            if ($successfulPayment === null) {
                return;
            }

            try {
                $payload = (string)($successfulPayment->invoice_payload ?? '');
                $paymentId = null;
                if (str_starts_with($payload, 'payment:')) {
                    $paymentId = (int)substr($payload, strlen('payment:'));
                }

                if (!$paymentId) {
                    Log::warning('SuccessfulPayment received without valid payload', ['payload' => $payload]);
                    return;
                }

                /** @var \App\Models\Payment|null $payment */
                $payment = \App\Models\Payment::find($paymentId);
                if (!$payment) {
                    Log::warning('Payment not found for SuccessfulPayment', ['payment_id' => $paymentId]);
                    return;
                }

                // Валидация владельца (tg_id)
                $tgId = (string)$bot->userId();
                if ((string)($payment->user?->tg_id ?? '') !== $tgId) {
                    Log::warning('Telegram user does not match payment owner', [
                        'payment_id' => $payment->id,
                        'payload_tg_id' => $tgId,
                        'owner_tg_id' => $payment->user?->tg_id,
                    ]);
                }

                // Обновляем статус и сохраняем идентификаторы транзакций провайдера
                $payment->update([
                    'status' => \App\Models\Payment::STATUS_SUCCEEDED,
                    'yookassa_status' => 'succeeded',
                    'provider_payment_charge_id' => (string)($successfulPayment->provider_payment_charge_id ?? ''),
                    'telegram_payment_charge_id' => (string)($successfulPayment->telegram_payment_charge_id ?? ''),
                ]);

                // Финализация: продление/подключение услуги и уведомление
                try {
                    /** @var \App\Services\YooKassaService $yk */
                    $yk = app(\App\Services\YooKassaService::class);
                    $yk->handleSuccessfulPayment($payment->fresh());
                } catch (\Throwable $e) {
                    Log::error('Failed to finalize successful Telegram payment', [
                        'payment_id' => $payment->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            } catch (\Throwable $e) {
                Log::error('Error handling SuccessfulPayment', ['error' => $e->getMessage()]);
            }
        });

        /**
         * Обработка команды /invite
         */
        $this->bot->onCommand('invite', function (Nutgram $bot) {
            $telegramId = $bot->userId();
            $messageIds = $bot->getGlobalData('vpn_message_ids', []);
            $this->clearChat($messageIds, $bot, $telegramId);
            $user = $this->userService->findUserByTelegramId($telegramId);

            if (!$user) {
                $bot->sendMessage('❌ Пользователь не найден');
                return;
            }

            $userTags = $user->subscriptions
                ->loadMissing('xui')
                ->pluck('xui.tag')
                ->filter()
                ->all();

            if (empty($userTags)) {
                $bot->sendMessage('❌ У вас нет активных подписок для реферальной программы');
                return;
            }

            $keyboard = $this->createReferralTagKeyboard($userTags, 2);
            $keyboard->addRow($this->getMainMenuButton());

            $messageId = $this->vpnConnectionService->sendChoosingActiveVpnMenu($bot, $keyboard);
            $bot->setGlobalData('vpn_message_ids', [$messageId]);
        });

        /**
         * Обработка кнопки "Пригласить друга"
         */
        $this->bot->onCallbackQueryData('invite', function (Nutgram $bot) {
            $bot->answerCallbackQuery();
            $telegramId = $bot->userId();
            $messageIds = $bot->getGlobalData('vpn_message_ids', []);
            $this->clearChat($messageIds, $bot, $telegramId);
            $user = $this->userService->findUserByTelegramId($telegramId);

            if (!$user) {
                $bot->sendMessage('❌ Пользователь не найден');
                return;
            }

            $userTags = $user->subscriptions
                ->loadMissing('xui')
                ->pluck('xui.tag')
                ->filter()
                ->all();

            if (empty($userTags)) {
                $bot->sendMessage('❌ У вас нет активных подписок для реферальной программы');
                return;
            }

            $keyboard = $this->createReferralTagKeyboard($userTags, 2);
            $keyboard->addRow($this->getMainMenuButton());

            $messageId = $this->vpnConnectionService->sendChoosingActiveVpnMenu($bot, $keyboard, true);
            $bot->setGlobalData('vpn_message_ids', [$messageId]);
        });

        /**
         * Обработка принятия соглашений (без реферального кода)
         */
        $this->bot->onCallbackQueryData('accept_terms', function (Nutgram $bot) {
            $bot->answerCallbackQuery($bot->callbackQuery()->id, '⏳ Создаю VPN конфигурацию, пожалуйста, подождите...');

            try {
                $telegramId = $bot->userId();
                $username = $bot->user()->username;
                $name = $bot->user()->first_name;

                ProcessAcceptTermsJob::dispatch(
                    telegramId: $telegramId,
                    username: $username,
                    name: $name,
                    referrerId: null
                );

                Log::info('ProcessAcceptTermsJob dispatched (no referrer)', [
                    'telegram_id' => $telegramId,
                ]);
            } catch (\Throwable $e) {
                Log::error('Ошибка при постановке задачи accept_terms (без кода) в очередь: ' . $e->getMessage(), [
                    'trace' => $e->getTraceAsString(),
                ]);
                try {
                    $bot->sendMessage('❌ Произошла ошибка при обработке запроса. Пожалуйста, попробуйте позже.');
                } catch (\Throwable $sendError) {
                    Log::error('Не удалось отправить сообщение об ошибке: ' . $sendError->getMessage());
                }
            }
        });

        /**
         * Обработка принятия соглашений (с реферальным кодом)
         */
        $this->bot->onCallbackQueryData('accept_terms:{code}', function (Nutgram $bot, string $code) {
            $bot->answerCallbackQuery($bot->callbackQuery()->id, '⏳ Создаю VPN конфигурацию, пожалуйста, подождите...');

            try {
                $telegramId = $bot->userId();
                $username = $bot->user()->username;
                $name = $bot->user()->first_name;
                $referrerId = (int)$code;

                ProcessAcceptTermsJob::dispatch(
                    telegramId: $telegramId,
                    username: $username,
                    name: $name,
                    referrerId: $referrerId
                );

                Log::info('ProcessAcceptTermsJob dispatched', [
                    'telegram_id' => $telegramId,
                    'referrer_id' => $referrerId,
                ]);
            } catch (\Throwable $e) {
                Log::error('Ошибка при постановке задачи accept_terms в очередь: ' . $e->getMessage(), [
                    'trace' => $e->getTraceAsString(),
                ]);
                try {
                    $bot->sendMessage('❌ Произошла ошибка при обработке запроса. Пожалуйста, попробуйте позже.');
                } catch (\Throwable $sendError) {
                    Log::error('Не удалось отправить сообщение об ошибке: ' . $sendError->getMessage());
                }
            }
        });

        /**
         * Обработка кнопки "Подключить VPN". Предлагает список стран на выбор
         */
        $this->bot->onCallbackQueryData('connect_vpn', function (Nutgram $bot) {
            $messageIds = $bot->getGlobalData('vpn_message_ids', []);
            $this->clearChat($messageIds, $bot, $bot->userId());
            $user = $this->userService->findUserByTelegramId($bot->userId());

            // Получаем активные теги пользователя
            $activeTags = [];
            if ($user) {
                $activeTags = $user->subscriptions()
                    ->with('xui')
                    ->get()
                    ->pluck('xui.tag')
                    ->filter()
                    ->map(fn(XuiTag $tag) => $tag->value)
                    ->toArray();
            }

            $buttons = Xui::activeButtons($activeTags);
            $keyboard = InlineKeyboardMarkup::make();

            foreach (array_chunk($buttons, 2) as $row) {
                $keyboard->addRow(...$row);
            }

            $keyboard->addRow(InlineKeyboardButton::make('🏠 Главное меню', callback_data: 'main_menu'));
            $messageId = $this->vpnConnectionService->sendConnectVpnMenu($bot, $keyboard);
            $bot->setGlobalData('vpn_message_ids', [$messageId]);
            $bot->answerCallbackQuery();

            return;
        });

        /**
         * Обработка кнопки "Главное меню"
         */
        $this->bot->onCallbackQueryData('main_menu', function (Nutgram $bot) {
            $messageIds = $bot->getGlobalData('vpn_message_ids', []);
            $telegramId = $bot->userId();
            $this->clearChat($messageIds, $bot, $telegramId);
            $user = $this->userService->findUserByTelegramId($telegramId);

            if (!$user) {
                $bot->answerCallbackQuery();
                return;
            }

            $userTags = $user->subscriptions()
                ->with('xui')
                ->get()
                ->pluck('xui.tag')
                ->filter()
                ->map(fn(XuiTag $tag) => $tag->value)
                ->toArray();

            $keyboard = InlineKeyboardMarkup::make();

            if (!empty($userTags)) {
                $keyboard->addRow(InlineKeyboardButton::make('Мои VPN', callback_data: 'user_vpn'));
            }

            if (Xui::activeNotOwnedByUser($userTags)->isNotEmpty()) {
                $keyboard->addRow(InlineKeyboardButton::make('Подключить VPN', callback_data: 'connect_vpn'));
            }

            $keyboard->addRow(InlineKeyboardButton::make('Инструкции', callback_data: 'guidelines'));
            $keyboard->addRow(InlineKeyboardButton::make('👥 Пригласить друга', callback_data: 'invite'));
            $messageId = $this->vpnConnectionService->sendMainMenu($bot, $user, $keyboard);
            $bot->setGlobalData('vpn_message_ids', [$messageId]);
            $bot->answerCallbackQuery();

            return;
        });

        /**
         * Обработка кнопки "Инструкции"
         */
        $this->bot->onCallbackQueryData('guidelines', function (Nutgram $bot) {
            $bot->answerCallbackQuery();
            $messageIds = $bot->getGlobalData('vpn_message_ids', []);
            $keyboard = $this->getInstructionsKeyboard();
            $this->clearChat($messageIds, $bot, $bot->userId());
            $messageId = $this->vpnConnectionService->sendGuideMenu($bot, $keyboard);
            $bot->setGlobalData('vpn_message_ids', [$messageId]);

            return;
        });

        /**
         * Обработка кнопки "Мои VPN". Ищет активные VPN, показывает сначала страну для выбора
         */
        $this->bot->onCallbackQueryData('user_vpn', function (Nutgram $bot) {
            $bot->answerCallbackQuery();
            $telegramId = $bot->userId();
            $messageIds = $bot->getGlobalData('vpn_message_ids', []);
            $this->clearChat($messageIds, $bot, $telegramId);
            
            $user = $this->userService->findUserByTelegramId($telegramId);

            $keyboard = XuiTag::keyboardFromValues(
                $user->subscriptions()
                    ->with('xui')
                    ->get()
                    ->pluck('xui.tag')
                    ->filter()
                    ->all(),
                2
            );

            $keyboard->addRow($this->getMainMenuButton());
            $messageId = $this->vpnConnectionService->sendChoosingActiveVpnMenu($bot, $keyboard);
            $bot->setGlobalData('vpn_message_ids', [$messageId]);
            $bot->answerCallbackQuery();

            return;
        });

        /**
         * Обработка кнопки "Мои VPN -> страна". Ищет VPN по тегу страны и отображает информацию о нем
         */
        $this->bot->onCallbackQueryData(
            'vpn:tag:{code}',
            function (Nutgram $bot, string $code) {
                $messageIds = $bot->getGlobalData('vpn_message_ids', []);
                $this->clearChat($messageIds, $bot, $bot->userId());
                $user = $this->userService->findUserByTelegramId($bot->userId());

                if (!$user) {
                    $bot->answerCallbackQuery();
                    return;
                }

                try {
                    $tag = XuiTag::from($code);
                } catch (\ValueError) {
                    $bot->answerCallbackQuery('Неизвестный VPN');
                    return;
                }

                $userConfigImportLink = $this->vpnConnectionService->getUserConfigImportLink($user, $tag->value);

                $keyboard = InlineKeyboardMarkup::make()
                    ->addRow(InlineKeyboardButton::make('🔃 Продлить VPN', callback_data: Callback::VPN_PRICING->with($code)))
                    ->addRow(InlineKeyboardButton::make('📲 Перенести в приложение', url: $userConfigImportLink))
                    ->addRow(InlineKeyboardButton::make('⬅️ Назад к VPN', callback_data: Callback::VPN_BACK->value))
                    ->addRow($this->getMainMenuButton());

                $messageId = $this->vpnConnectionService->sendSubscriptionInfo($bot, $user, $tag->value, $keyboard);
                $bot->setGlobalData('vpn_message_ids', [$messageId]);
                $bot->answerCallbackQuery();

                return;
            }
        );

        /**
         * Обработка кнопки "Назад" к списку активных стран-vpn пользователя (на экране информации о текущемм выбранном vpn)
         */
        $this->bot->onCallbackQueryData(
            'vpn:back',
            function (Nutgram $bot) {
                $bot->answerCallbackQuery();
                $messageIds = $bot->getGlobalData('vpn_message_ids', []);
                $this->clearChat($messageIds, $bot, $bot->userId());
                $user = $this->userService->findUserByTelegramId($bot->userId());

                if (!$user) {
                    $bot->answerCallbackQuery();
                    return;
                }

                $keyboard = XuiTag::keyboardFromValues(
                    $user->subscriptions()
                        ->with('xui')
                        ->get()
                        ->pluck('xui.tag')
                        ->filter()
                        ->all(),
                    2
                );

                $keyboard->addRow($this->getMainMenuButton());
                $messageId = $this->vpnConnectionService->sendChoosingActiveVpnMenu($bot, $keyboard);
                $bot->setGlobalData('vpn_message_ids', [$messageId]);
                $bot->answerCallbackQuery();

                return;
            }
        );

        /**
         * Обработка кнопки "Продлить\Подключить" отображает список тарифов
         */
        $this->bot->onCallbackQueryData(
            'vpn:pricing:{code}',
            function (Nutgram $bot, string $code) {
                $bot->answerCallbackQuery();
                $messageIds = $bot->getGlobalData('vpn_message_ids', []);
                $this->clearChat($messageIds, $bot, $bot->userId());
                $user = $this->userService->findUserByTelegramId($bot->userId());

                if (!$user) {
                    $bot->answerCallbackQuery();
                    return;
                }

                try {
                    $tag = XuiTag::from($code);
                } catch (\ValueError) {
                    $bot->answerCallbackQuery('Неизвестный VPN');
                    return;
                }

                $pricings = $this->pricingService->getAll();

                if ($pricings->isEmpty()) {
                    $bot->answerCallbackQuery('Тарифные планы временно недоступны');
                    return;
                }

                $keyboard = InlineKeyboardMarkup::make();

                foreach ($pricings as $pricing) {
                    $buttonText = sprintf(
                        '%s - %s ₽',
                        $pricing->title,
                        number_format((float) $pricing->price, 0, '.', ' ')
                    );

                    $keyboard->addRow(
                        InlineKeyboardButton::make(
                            $buttonText,
                            callback_data: Callback::PAYMENT_PRICING->withMultiple((string) $pricing->id, $code)
                        )
                    );
                }

                $keyboard->addRow($this->getMainMenuButton());
                $messageId = $this->vpnConnectionService->sendPricingInfo($bot, $user, $pricings, $tag->value, $keyboard);
                $bot->setGlobalData('vpn_message_ids', [$messageId]);
                $bot->answerCallbackQuery();

                return;
            }
        );

        /**
         * Обработка кнопки "Оплатить" выбранный тариф
         */
        $this->bot->onCallbackQueryData(
            'payment:pricing:{pricing_id}:{code}',
            function (Nutgram $bot, string $pricingId, string $code) {
                $bot->answerCallbackQuery($bot->callbackQuery()->id, '⏳ Создаю платеж, пожалуйста, подождите...');

                try {
                    $telegramId = $bot->userId();
                    $user = $this->userService->findUserByTelegramId($telegramId);

                    if (!$user) {
                        try {
                            $bot->sendMessage('❌ Пользователь не найден', (string) $telegramId);
                        } catch (\Throwable $sendError) {
                            Log::error('Не удалось отправить сообщение об ошибке: ' . $sendError->getMessage());
                        }
                        return;
                    }

                    // Защита от повторных нажатий: проверяем наличие активного pending платежа
                    $activePayment = \App\Models\Payment::where('user_id', $user->id)
                        ->where('status', \App\Models\Payment::STATUS_PENDING)
                        ->latest()
                        ->first();

                    if ($activePayment) {
                        try {
                            $bot->sendMessage('⚠️ У вас уже есть активный платеж. Дождитесь завершения оплаты.', (string) $telegramId);
                        } catch (\Throwable $sendError) {
                            Log::error('Не удалось отправить сообщение об ошибке: ' . $sendError->getMessage());
                        }
                        return;
                    }

                    try {
                        XuiTag::from($code);
                    } catch (\ValueError) {
                        try {
                            $bot->sendMessage('❌ Неизвестный VPN', (string) $telegramId);
                        } catch (\Throwable $sendError) {
                            Log::error('Не удалось отправить сообщение об ошибке: ' . $sendError->getMessage());
                        }
                        return;
                    }

                    $pricing = Pricing::find($pricingId);

                    if (!$pricing) {
                        try {
                            $bot->sendMessage('❌ Тарифный план не найден', (string) $telegramId);
                        } catch (\Throwable $sendError) {
                            Log::error('Не удалось отправить сообщение об ошибке: ' . $sendError->getMessage());
                        }
                        return;
                    }

                    // Удаляем сообщение с тарифами
                    $callbackQuery = $bot->callbackQuery();
                    if ($callbackQuery && $callbackQuery->message) {
                        try {
                            $bot->deleteMessage($bot->chatId(), $callbackQuery->message->message_id);
                        } catch (\Throwable $e) {
                            // Игнорируем ошибки удаления сообщения
                            Log::debug('Failed to delete pricing message', [
                                'message_id' => $callbackQuery->message->message_id,
                                'error' => $e->getMessage(),
                            ]);
                        }
                    }

                    ProcessPaymentCreationJob::dispatch(
                        telegramId: $telegramId,
                        pricingId: $pricingId,
                        code: $code
                    );
                } catch (\Throwable $e) {
                    Log::error('Ошибка при постановке задачи создания платежа в очередь: ' . $e->getMessage(), [
                        'trace' => $e->getTraceAsString(),
                        'telegram_id' => $bot->userId(),
                        'pricing_id' => $pricingId,
                    ]);
                    try {
                        $bot->sendMessage('❌ Произошла ошибка при обработке запроса. Пожалуйста, попробуйте позже.', (string) $bot->userId());
                    } catch (\Throwable $sendError) {
                        Log::error('Не удалось отправить сообщение об ошибке: ' . $sendError->getMessage());
                    }
                }
            }
        );

        /**
         * Обработка выбора тега для реферальной программы
         */
        $this->bot->onCallbackQueryData(
            'referral:tag:{code}',
            function (Nutgram $bot, string $code) {
                $bot->answerCallbackQuery();
                $telegramId = $bot->userId();
                $messageIds = $bot->getGlobalData('vpn_message_ids', []);
                $this->clearChat($messageIds, $bot, $telegramId);
                $user = $this->userService->findUserByTelegramId($telegramId);

                if (!$user) {
                    $bot->sendMessage('❌ Пользователь не найден');
                    return;
                }

                if (!$user->referral_code) {
                    $bot->sendMessage('❌ Не удалось получить реферальный код');
                    return;
                }

                try {
                    $tag = XuiTag::from($code);
                } catch (\ValueError) {
                    $bot->sendMessage('❌ Неизвестный VPN');
                    return;
                }

                // Проверяем, что у пользователя есть этот тег
                $userHasTag = $user->subscriptions()
                    ->with('xui')
                    ->get()
                    ->contains(function ($subscription) use ($code) {
                        return $subscription->xui && $subscription->xui->tag?->value === $code;
                    });

                if (!$userHasTag) {
                    $bot->sendMessage('❌ У вас нет активной подписки для этой страны');
                    return;
                }

                // Сохраняем выбранный тег для реферальной программы
                $user->update(['referral_tag' => $code]);

                // Получаем имя бота
                $botUsername = config('services.telegram.bot_username');
                if (!$botUsername) {
                    try {
                        $botInfo = $bot->getMe();
                        $botUsername = $botInfo->username;
                    } catch (\Throwable $e) {
                        Log::error('Не удалось получить имя бота: ' . $e->getMessage());
                        $bot->sendMessage('❌ Не удалось сформировать реферальную ссылку');
                        return;
                    }
                }

                $trialDuration = (int) $this->settingService->getInt('trial.duration');
                $refBonusDuration = (int) $this->settingService->getInt('ref.bonus.duration');
                $defaultMonthlyCost = (int) $this->settingService->getInt('default.monthly.cost');
                $referralLink = "https://t.me/{$botUsername}?start={$user->referral_code}";
                $shareUrl = "https://t.me/share/url?" . "text=" . urlencode("Дешевый VPN! {$trialDuration} дней бесплатно, подписка на месяц {$defaultMonthlyCost}Р!") . '&url=' . urlencode($referralLink);
                $message = "За каждого, кто подключит VPN, Вы получите на баланс {$refBonusDuration} дня подписки, а все приглашенные {$trialDuration} дней бесплатного VPN";

                $keyboard = InlineKeyboardMarkup::make()
                    ->addRow(InlineKeyboardButton::make('Пригласить', url: $shareUrl))
                    ->addRow($this->getMainMenuButton());

                $sentMessage = $bot->sendMessage($message, reply_markup: $keyboard);
                if ($sentMessage && $sentMessage->message_id) {
                    $bot->setGlobalData('vpn_message_ids', [$sentMessage->message_id]);
                }
            }
        );
    }

    private function handleStartCommand(Nutgram $bot): void
    {
        $telegramId = $bot->userId();
        $text = $bot->message()?->text ?? '';
        $payload = null;

        if (preg_match('/^\/start[@\w]*\s+(.+)$/i', $text, $matches)) {
            $payload = trim($matches[1]);
        } elseif (preg_match('/^\/start\s+(.+)$/i', $text, $matches)) {
            $payload = trim($matches[1]);
        } else {
            $parts = explode(' ', trim($text));
            if (count($parts) > 1 && $parts[0] === '/start') {
                $payload = trim(implode(' ', array_slice($parts, 1)));
            }
        }

        $user = $this->userService->findUserByTelegramId($telegramId);
        $referrerId = null;

        if ($payload && $payload !== '') {
            if (!$user) {
                $referrer = $this->userService->findUserByReferralCode($payload);
                if ($referrer) {
                    $referrerId = $referrer->id;
                }
            }
        }

        try {
            if (!$user) {
                $messageIds = $bot->getGlobalData('vpn_message_ids', []);
                $this->clearChat($messageIds, $bot, $telegramId);

                $callbackData = 'accept_terms';
                if ($referrerId) {
                    $callbackData = "accept_terms:{$referrerId}";
                }

                $keyboard = InlineKeyboardMarkup::make()->addRow(InlineKeyboardButton::make('✅Принять', callback_data: $callbackData));
                $messageId = $this->vpnConnectionService->sendWelcomeMessageForNewUser($bot, $keyboard);
                $bot->setGlobalData('vpn_message_ids', [$messageId]);
            } else {
                $messageIds = $bot->getGlobalData('vpn_message_ids', []);
                $this->clearChat($messageIds, $bot, $telegramId);

                $userTags = $user->subscriptions()
                    ->with('xui')
                    ->get()
                    ->pluck('xui.tag')
                    ->filter()
                    ->map(fn(XuiTag $tag) => $tag->value)
                    ->toArray();

                $keyboard = InlineKeyboardMarkup::make();

                if (!empty($userTags)) {
                    $keyboard->addRow(InlineKeyboardButton::make('Мои VPN', callback_data: 'user_vpn'));
                }

                if (Xui::activeNotOwnedByUser($userTags)->isNotEmpty()) {
                    $keyboard->addRow(InlineKeyboardButton::make('Подключить VPN', callback_data: 'connect_vpn'));
                }

                $keyboard->addRow(InlineKeyboardButton::make('👥 Пригласить друга', callback_data: 'invite'));
                $keyboard->addRow($this->getMainMenuButton());
                $messageId = $this->vpnConnectionService->sendMainMenu($bot, $user, $keyboard);
                $bot->setGlobalData('vpn_message_ids', [$messageId]);

                return;
            }
        } catch (\Throwable $e) {
            Log::error('Error in start command handler', [
                'telegram_id' => $telegramId,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            try {
                $bot->sendMessage('❌ Произошла ошибка. Пожалуйста, попробуйте позже.', chat_id: $telegramId);
            } catch (\Throwable $sendError) {
                Log::error('Failed to send error message', [
                    'error' => $sendError->getMessage(),
                    'telegram_id' => $telegramId,
                ]);
            }
        }
    }


    private function getMainMenuButton(): InlineKeyboardButton
    {
        return InlineKeyboardButton::make('🏠 Главное меню', callback_data: 'main_menu');
    }

    private function getInstructionsKeyboard(): InlineKeyboardMarkup
    {
        return InlineKeyboardMarkup::make()
            ->addRow(InlineKeyboardButton::make('🤖 Приложение для Android', url: 'https://telegra.ph/Podklyuchenie-k-VPN-04-02-2'))
            ->addRow(InlineKeyboardButton::make('🍏 Инструкция для MacOS\iPhone', url: 'https://telegra.ph/Podklyuchenie-k-VPN-04-02'))
            ->addRow(InlineKeyboardButton::make('🖥️ Инструкция для Windows', url: 'https://telegra.ph/Instrukciya-po-ustanovke-V2raytun-na-PK--Windows-1011-01-02'))
            ->addRow(InlineKeyboardButton::make('🏠 Главное меню', callback_data: 'main_menu'));
    }

    /**
     * Создает клавиатуру для выбора тега реферальной программы
     *
     * @param array $tags
     * @param int $perRow
     * @return InlineKeyboardMarkup
     */
    private function createReferralTagKeyboard(array $tags, int $perRow = 2): InlineKeyboardMarkup
    {
        $keyboard = InlineKeyboardMarkup::make();
        $row = [];

        foreach ($tags as $tag) {
            try {
                $tagEnum = XuiTag::from($tag->value);
            } catch (\ValueError) {
                continue;
            }

            $row[] = InlineKeyboardButton::make(
                $tagEnum->labelWithFlag(),
                callback_data: Callback::REFERRAL_TAG->with($tagEnum->value)
            );

            if (count($row) === $perRow) {
                $keyboard->addRow(...$row);
                $row = [];
            }
        }

        if ($row) {
            $keyboard->addRow(...$row);
        }

        return $keyboard;
    }

    private function clearChat(array $messageIds, Nutgram $bot, int|string $chatId): void
    {
        foreach ($messageIds as $messageId) {
            try {
                $bot->deleteMessage($chatId, $messageId);
            } catch (\Throwable $e) {
                // Игнорируем ошибки удаления
            }
        }
    }
}
