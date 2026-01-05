<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Xui;
use App\Enums\XuiTag;
use App\Enums\Callback;
use App\Models\Pricing;
use SergiX44\Nutgram\Nutgram;
use App\Services\YooKassaService;
use App\Services\User\UserService;
use App\Jobs\ProcessAcceptTermsJob;
use App\Jobs\ProcessPaymentCreationJob;
use Illuminate\Support\Facades\Log;
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
final readonly class TelegramBotHandlers
{
    public function __construct(
        private Nutgram $bot,
        private VpnConnectionService $vpnConnectionService,
        private UserService $userService,
        private PricingService $pricingService,
    ) {}

    public function registerHandlers(): void
    {
        $this->bot->onCommand('start', function (Nutgram $bot, ?string $payload = null) {
            $telegramId = $bot->userId();

            // Если payload не передан как параметр, пытаемся извлечь из текста сообщения
            if ($payload === null || $payload === '') {
                $text = $bot->message()?->text ?? '';
                Log::info('Start command received', [
                    'telegram_id' => $telegramId,
                    'text' => $text,
                    'payload_param' => $payload,
                ]);

                // Парсим параметр из команды /start PAYLOAD
                if (preg_match('/^\/start\s+(.+)$/i', $text, $matches)) {
                    $payload = trim($matches[1]);
                    Log::info('Payload extracted from text', [
                        'telegram_id' => $telegramId,
                        'payload' => $payload,
                    ]);
                }
            }

            $user = $this->userService->findUserByTelegramId($telegramId);

            $referrerId = null;

            if ($payload && $payload !== '') {
                if (!$user) {
                    // Пытаемся найти пользователя по реферальному коду
                    $referrer = $this->userService->findUserByReferralCode($payload);
                    if ($referrer) {
                        $referrerId = $referrer->id;
                        Log::info('Referrer found', [
                            'telegram_id' => $telegramId,
                            'referral_code' => $payload,
                            'referrer_id' => $referrerId,
                        ]);
                    } else {
                        Log::warning('Referrer not found for referral code', [
                            'telegram_id' => $telegramId,
                            'referral_code' => $payload,
                        ]);
                    }
                }
            }

            try {
                if (!$user) {
                    // Сохраняем referrerId в глобальных данных бота для использования в ProcessAcceptTermsJob
                    if ($referrerId) {
                        $bot->setGlobalData('referrer_id', $referrerId);
                        $bot->setGlobalData('referral_code', $payload);
                    }

                    $messageIds = $bot->getGlobalData('vpn_message_ids', []);
                    $this->clearChat($messageIds, $bot);
                    $keyboard = InlineKeyboardMarkup::make()->addRow(InlineKeyboardButton::make('✅Принять', callback_data: 'accept_terms'));
                    $messageId = $this->vpnConnectionService->sendWelcomeMessageForNewUser($bot, $keyboard);
                    $bot->setGlobalData('vpn_message_ids', [$messageId]);
                } else {
                    $messageIds = $bot->getGlobalData('vpn_message_ids', []);
                    $this->clearChat($messageIds, $bot);

                    $userTags = $user->tags()
                        ->pluck('tag')
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
                    'trace' => $e->getTraceAsString(),
                ]);

                try {
                    $bot->sendMessage('❌ Произошла ошибка. Пожалуйста, попробуйте позже.');
                } catch (\Throwable $sendError) {
                    Log::error('Failed to send error message', [
                        'error' => $sendError->getMessage(),
                    ]);
                }
            }
        });

        /**
         * Обработка команды /invite
         */
        $this->bot->onCommand('invite', function (Nutgram $bot) {
            $messageIds = $bot->getGlobalData('vpn_message_ids', []);
            $this->clearChat($messageIds, $bot);
            $telegramId = $bot->userId();
            $user = $this->userService->findUserByTelegramId($telegramId);

            if (!$user) {
                $bot->sendMessage('❌ Пользователь не найден');
                return;
            }

            $userTags = $user->tags->pluck('tag')->all();

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
            $messageIds = $bot->getGlobalData('vpn_message_ids', []);
            $this->clearChat($messageIds, $bot);
            $telegramId = $bot->userId();
            $user = $this->userService->findUserByTelegramId($telegramId);

            if (!$user) {
                $bot->sendMessage('❌ Пользователь не найден');
                return;
            }

            $userTags = $user->tags->pluck('tag')->all();

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
         * Обработка принятия соглашений
         */
        $this->bot->onCallbackQueryData('accept_terms', function (Nutgram $bot) {
            $bot->answerCallbackQuery($bot->callbackQuery()->id, '⏳ Создаю VPN конфигурацию, пожалуйста, подождите...');

            try {
                $telegramId = $bot->userId();
                $username = $bot->user()->username;
                $name = $bot->user()->first_name;
                $referrerId = $bot->getGlobalData('referrer_id');

                ProcessAcceptTermsJob::dispatch(
                    telegramId: $telegramId,
                    username: $username,
                    name: $name,
                    referrerId: $referrerId
                );

                // Очищаем сохраненный referrer_id после использования
                $bot->unsetGlobalData('referrer_id');
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
            $this->clearChat($messageIds, $bot);
            $user = $this->userService->findUserByTelegramId($bot->userId());

            // Получаем активные теги пользователя
            $activeTags = [];
            if ($user) {
                $activeTags = $user->tags->pluck('tag')->map(fn($tag) => $tag->value)->toArray();
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
            $this->clearChat($messageIds, $bot);
            $telegramId = $bot->userId();
            $user = $this->userService->findUserByTelegramId($telegramId);

            if (!$user) {
                $bot->answerCallbackQuery();
                return;
            }

            $userTags = $user->tags()
                ->pluck('tag')
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
            $this->clearChat($messageIds, $bot);
            $messageId = $this->vpnConnectionService->sendGuideMenu($bot, $keyboard);
            $bot->setGlobalData('vpn_message_ids', [$messageId]);

            return;
        });

        /**
         * Обработка кнопки "Мои VPN". Ищет активные VPN, показывает сначала страну для выбора
         */
        $this->bot->onCallbackQueryData('user_vpn', function (Nutgram $bot) {
            $bot->answerCallbackQuery();
            $messageIds = $bot->getGlobalData('vpn_message_ids', []);
            $this->clearChat($messageIds, $bot);
            $telegramId = $bot->userId();
            $user = $this->userService->findUserByTelegramId($telegramId);

            $keyboard = XuiTag::keyboardFromValues(
                $user->tags->pluck('tag')->all(),
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
                $this->clearChat($messageIds, $bot);
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
                    ->addRow(InlineKeyboardButton::make('⬅️ Назад к VPN', callback_data: Callback::VPN_BACK->value));

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
                $this->clearChat($messageIds, $bot);
                $user = $this->userService->findUserByTelegramId($bot->userId());

                if (!$user) {
                    $bot->answerCallbackQuery();
                    return;
                }

                $keyboard = XuiTag::keyboardFromValues(
                    $user->tags->pluck('tag')->all(),
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
                $this->clearChat($messageIds, $bot);
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
                $messageIds = $bot->getGlobalData('vpn_message_ids', []);
                $this->clearChat($messageIds, $bot);
                $telegramId = $bot->userId();
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
                $userHasTag = $user->tags->contains(function ($userTag) use ($code) {
                    return $userTag->tag->value === $code;
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

                $referralLink = "https://t.me/{$botUsername}?start={$user->referral_code}";
                $shareUrl = "https://t.me/share/url?" . "text=" . urlencode("Дешевый VPN! 7 дней бесплатно, подписка на месяц 80Р!") . '&url=' . urlencode($referralLink);
                $message = "За каждого, кто подключит VPN, Вы получите на баланс 2 дня подписки, а все приглашенные 7 дней бесплатного VPN";

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

    private function getMainMenuButton(): InlineKeyboardButton
    {
        return InlineKeyboardButton::make('🏠 Главное меню', callback_data: 'main_menu');
    }

    private function getInstructionsKeyboard(): InlineKeyboardMarkup
    {
        return InlineKeyboardMarkup::make()
            ->addRow(InlineKeyboardButton::make('🤖 Приложение для Android', url: 'https://play.google.com/store/apps/details?id=com.v2raytun.android&pcampaignid=web_share'))
            ->addRow(InlineKeyboardButton::make('🖥️ Инструкция для Windows', url: 'https://telegra.ph/Instrukciya-po-ustanovke-V2raytun-na-PK--Windows-1011-01-02'))
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

    private function clearChat(array $messageIds, Nutgram $bot): void
    {
        foreach ($messageIds as $messageId) {
            try {
                $bot->deleteMessage($bot->chatId(), $messageId);
            } catch (\Throwable $e) {
            }
        }
    }
}
