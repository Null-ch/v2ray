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
        $this->bot->onCommand('start', function (Nutgram $bot) {
            $telegramId = $bot->userId();
            $user = $this->userService->findUserByTelegramId($telegramId);

            //TODO:Реализация рефералов тут
            // if ($payload && !$user) {
            //     $bot->sendMessage("Привет! Ваш стартовый параметр: $payload");
            // }

            if (!$user) {
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

                $keyboard->addRow($this->getMainMenuButton());
                $messageId = $this->vpnConnectionService->sendMainMenu($bot, $user, $keyboard);
                $bot->setGlobalData('vpn_message_ids', [$messageId]);

                return;
            }
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

                ProcessAcceptTermsJob::dispatch(
                    telegramId: $telegramId,
                    username: $username,
                    name: $name
                );
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
                // Сразу отвечаем на callback query, чтобы избежать ошибки "query is too old"
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
                        $tag = XuiTag::from($code);
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

                    // Выносим создание платежа в джобу
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
         * TODO: Реализовать обработку кнопки "Перенести в приложение"
         */
        $this->bot->onCallbackQueryData('export_config', function (Nutgram $bot) {
            // TODO: Получить экспортную ссылку по тегу и юиду
            $exportUrl = 'https://www.sigmalink.org/redirect/?redirect_to=www.sigmalink.org&token=PLACEHOLDER_TOKEN&scheme=v2raytun';
            $bot->sendMessage(
                "📲 Экспортная ссылка для переноса конфигов:\n\n$exportUrl\n\n⚠️ Внимание: это заглушка, функционал в разработке",
                parse_mode: 'HTML'
            );

            $bot->answerCallbackQuery('Ссылка отправлена');
        });
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
