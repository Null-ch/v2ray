<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Xui;
use App\Models\Pricing;
use App\Enums\XuiTag;
use App\Enums\Callback;
use App\Services\XuiService;
use SergiX44\Nutgram\Nutgram;
use App\Services\TelegramService;
use App\Services\User\UserService;
use App\Services\YooKassaService;
use App\Jobs\ProcessAcceptTermsJob;
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
        private TelegramService $telegramService,
        private XuiService $xuiService,
        private YooKassaService $yooKassaService,
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
                $keyboard = InlineKeyboardMarkup::make()->addRow(InlineKeyboardButton::make('✅Принять', callback_data: 'accept_terms'));
                $this->vpnConnectionService->sendWelcomeMessageForNewUser($bot, $keyboard);
            } else {
                $activeConfigs = $user->tags();
                if ($activeConfigs) {
                    $keyboard = InlineKeyboardMarkup::make()
                        ->addRow(
                            InlineKeyboardButton::make('Подключить VPN', callback_data: 'connect_vpn'),
                            InlineKeyboardButton::make('Мои VPN', callback_data: 'user_vpn')
                        )
                        ->addRow(InlineKeyboardButton::make('🏠 Главное меню', callback_data: 'main_menu'));
                } else {
                    $keyboard = InlineKeyboardMarkup::make()
                        ->addRow(InlineKeyboardButton::make('Подключить VPN', callback_data: 'connect_vpn'))
                        ->addRow(InlineKeyboardButton::make('🏠 Главное меню', callback_data: 'main_menu'));
                }

                $this->vpnConnectionService->sendMainMenu($bot, $user, $keyboard);
            }
        });

        $this->bot->onCallbackQueryData('accept_terms', function (Nutgram $bot) {
            $bot->answerCallbackQuery($bot->callbackQuery()->id, '⏳ Создаю VPN конфигурацию, пожалуйста, подождите...');

            try {
                $telegramId = $bot->userId();
                $username = $bot->user()->username;
                $name = $bot->user()->first_name;
                // $bot->sendMessage('⏳ Создаю VPN конфигурацию, пожалуйста, подождите...');

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

        $this->bot->onCallbackQueryData('connect_vpn', function (Nutgram $bot) {
            $buttons = Xui::activeButtons();
            $keyboard = InlineKeyboardMarkup::make();

            foreach (array_chunk($buttons, 2) as $row) {
                $keyboard->addRow(...$row);
            }

            $keyboard->addRow(InlineKeyboardButton::make('🏠 Главное меню', callback_data: 'main_menu'));
            $this->vpnConnectionService->sendConnectVpnMenu($bot, $keyboard);
            $bot->answerCallbackQuery();

            return;
        });

        // Обработчик кнопки "Перенести в приложение"
        $this->bot->onCallbackQueryData('export_config', function (Nutgram $bot) {
            // TODO: Получить экспортную ссылку по тегу и юиду
            $exportUrl = 'https://www.sigmalink.org/redirect/?redirect_to=www.sigmalink.org&token=PLACEHOLDER_TOKEN&scheme=v2raytun';
            $bot->sendMessage(
                "📲 Экспортная ссылка для переноса конфигов:\n\n$exportUrl\n\n⚠️ Внимание: это заглушка, функционал в разработке",
                parse_mode: 'HTML'
            );

            $bot->answerCallbackQuery('Ссылка отправлена');
        });


        $this->bot->onCallbackQueryData('main_menu', function (Nutgram $bot) {
            $messageIds = $bot->getGlobalData('vpn_message_ids', []);
            $this->clearChat($messageIds, $bot);
            $telegramId = $bot->userId();
            $user = $this->userService->findUserByTelegramId($telegramId);

            if (!$user) {
                $bot->answerCallbackQuery();
                return;
            }

            if ($user->tags->isNotEmpty()) {
                $keyboard = InlineKeyboardMarkup::make()
                    ->addRow(
                        InlineKeyboardButton::make('Мои VPN', callback_data: 'user_vpn'),
                        InlineKeyboardButton::make('Подключить VPN', callback_data: 'connect_vpn')
                    )
                    ->addRow(InlineKeyboardButton::make('Инструкции', callback_data: 'guidelines'));

                $this->vpnConnectionService->sendMainMenu($bot, $user, $keyboard);
                $bot->answerCallbackQuery();
                return;
            }

            $keyboard = InlineKeyboardMarkup::make()
                ->addRow(InlineKeyboardButton::make('Подключить VPN', callback_data: 'connect_vpn'))
                ->addRow(InlineKeyboardButton::make('Инструкции', callback_data: 'guidelines'));
            $this->vpnConnectionService->sendMainMenu($bot, $user, $keyboard);
            $bot->answerCallbackQuery();

            return;
        });

        $this->bot->onCallbackQueryData('guidelines', function (Nutgram $bot) {
            $messageIds = $bot->getGlobalData('vpn_message_ids', []);
            $keyboard = $this->getInstructionsKeyboard();
            $this->clearChat($messageIds, $bot);
            $this->vpnConnectionService->sendGuideMenu($bot, $keyboard);
            $bot->answerCallbackQuery();

            return;
        });

        $this->bot->onCallbackQueryData('user_vpn', function (Nutgram $bot) {
            $messageIds = $bot->getGlobalData('vpn_message_ids', []);
            $this->clearChat($messageIds, $bot);
            $telegramId = $bot->userId();
            $user = $this->userService->findUserByTelegramId($telegramId);

            $keyboard = XuiTag::keyboardFromValues(
                $user->tags->pluck('tag')->all(),
                2
            );

            $keyboard->addRow(
                InlineKeyboardButton::make(
                    '🏠 Главное меню',
                    callback_data: 'main_menu'
                )
            );

            $this->vpnConnectionService->sendChoosingActiveVpnMenu($bot, $keyboard);
            $bot->answerCallbackQuery();

            return;
        });

        $this->bot->onCallbackQueryData(
            'vpn:tag:{code}',
            function (Nutgram $bot, string $code) {
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

                $keyboard = InlineKeyboardMarkup::make()
                    ->addRow(InlineKeyboardButton::make('🔃 Продлить VPN', callback_data: Callback::VPN_PRICING->with($code)))
                    ->addRow(InlineKeyboardButton::make('⬅️ Назад к VPN', callback_data: Callback::VPN_BACK->value));

                $this->vpnConnectionService->sendSubscriptionInfo($bot, $user, $tag->value, $keyboard);
                $bot->answerCallbackQuery();

                return;
            }
        );

        $this->bot->onCallbackQueryData(
            'vpn:back',
            function (Nutgram $bot) {
                $user = $this->userService
                    ->findUserByTelegramId($bot->userId());

                if (!$user) {
                    $bot->answerCallbackQuery();
                    return;
                }

                $keyboard = XuiTag::keyboardFromValues(
                    $user->tags->pluck('tag')->all(),
                    2
                );

                $keyboard->addRow(
                    InlineKeyboardButton::make(
                        '🏠 Главное меню',
                        callback_data: 'main_menu'
                    )
                );

                $this->vpnConnectionService
                    ->sendChoosingActiveVpnMenu($bot, $keyboard);

                $bot->answerCallbackQuery();
                return;
            }
        );

        $this->bot->onCallbackQueryData(
            'vpn:pricing:{code}',
            function (Nutgram $bot, string $code) {
                $bot->answerCallbackQuery();
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
                        '%s - %s ₽ (%s %s)',
                        $pricing->title,
                        number_format($pricing->price, 0, '.', ' '),
                        $pricing->duration,
                        $pricing->duration == 1 ? 'день' : ($pricing->duration < 5 ? 'дня' : 'дней')
                    );
                    $keyboard->addRow(
                        InlineKeyboardButton::make(
                            $buttonText,
                            callback_data: Callback::PAYMENT_PRICING->withMultiple((string)$pricing->id, $code)
                        )
                    );
                }

                $keyboard->addRow(
                    InlineKeyboardButton::make('🏠 Главное меню', callback_data: 'main_menu')
                );

                $this->vpnConnectionService
                    ->sendPricingInfo($bot, $user, $pricings, $tag->value, $keyboard);

                $bot->answerCallbackQuery();
                return;
            }
        );

        $this->bot->onCallbackQueryData(
            'payment:pricing:{pricing_id}:{code}',
            function (Nutgram $bot, string $pricingId, string $code) {
                $bot->answerCallbackQuery();
                $user = $this->userService
                    ->findUserByTelegramId($bot->userId());

                if (!$user) {
                    $bot->answerCallbackQuery('Пользователь не найден');
                    return;
                }

                try {
                    $tag = XuiTag::from($code);
                } catch (\ValueError) {
                    $bot->answerCallbackQuery('Неизвестный VPN');
                    return;
                }

                $pricing = Pricing::find($pricingId);

                if (!$pricing) {
                    $bot->answerCallbackQuery('Тарифный план не найден');
                    return;
                }

                try {
                    $description = sprintf(
                        'Оплата VPN %s: %s (%s %s)',
                        $tag->labelWithFlag(),
                        $pricing->title,
                        $pricing->duration,
                        $pricing->duration == 1 ? 'день' : ($pricing->duration < 5 ? 'дня' : 'дней')
                    );

                    $metadata = [
                        'pricing_id' => $pricing->id,
                        'vpn_tag' => $code,
                        'duration' => $pricing->duration,
                    ];

                    $payment = $this->yooKassaService->createPayment(
                        $user,
                        (float)$pricing->price,
                        $description,
                        $metadata
                    );

                    if ($payment->confirmation_url) {
                        $keyboard = InlineKeyboardMarkup::make()
                            ->addRow(
                                InlineKeyboardButton::make(
                                    '💳 Перейти к оплате',
                                    url: $payment->confirmation_url
                                )
                            )
                            ->addRow(
                                InlineKeyboardButton::make('🏠 Главное меню', callback_data: 'main_menu')
                            );

                        $message = sprintf(
                            "💳 Создан платеж на сумму %s ₽\n\n%s\n\nНажмите кнопку ниже для перехода к оплате:",
                            number_format($pricing->price, 2, '.', ' '),
                            $description
                        );

                        $bot->sendMessage($message, reply_markup: $keyboard);
                        $bot->answerCallbackQuery('Платеж создан');
                    } else {
                        $bot->answerCallbackQuery('Ошибка при создании платежа');
                        Log::error('Payment created but no confirmation URL', [
                            'payment_id' => $payment->id,
                            'user_id' => $user->id,
                        ]);
                    }
                } catch (\Exception $e) {
                    Log::error('Failed to create payment from Telegram', [
                        'user_id' => $user->id,
                        'pricing_id' => $pricingId,
                        'error' => $e->getMessage(),
                    ]);
                    $bot->answerCallbackQuery('Ошибка при создании платежа. Попробуйте позже.');
                }
            }
        );
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
