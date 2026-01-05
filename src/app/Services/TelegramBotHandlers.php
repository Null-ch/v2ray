<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Xui;
use App\Enums\XuiTag;
use App\Services\XuiService;
use SergiX44\Nutgram\Nutgram;
use App\Services\TelegramService;
use App\Services\User\UserService;
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
        private TelegramService $telegramService,
        private XuiService $xuiService,
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
            $tags = Xui::activeLabelsWithIcons();

            $tagButtons = array_map(function ($item) {
                return [
                    'label' => $item['label'],
                    'callback_data' => $item['tag'],
                ];
            }, $tags);

            $extraRows = [
                [InlineKeyboardButton::make('🏠 Главное меню', callback_data: 'main_menu')]
            ];

            $keyboard = $this->telegramService::buildInlineKeyboard($tagButtons, 2, $extraRows);
            $this->vpnConnectionService->sendConnectVpnMenu($bot, $keyboard);

            $bot->answerCallbackQuery();
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

            $keyboard->addRow(InlineKeyboardButton::make('Назад', callback_data: 'vpn:back'));

            $this->vpnConnectionService->sendChoosingActiveVpnMenu($bot, $keyboard);
            $bot->answerCallbackQuery();

            return;
        });

        $this->bot->onCallbackQueryData(
            'vpn:tag:{code}',
            function (Nutgram $bot, string $code) {
                $user = $this->userService
                    ->findUserByTelegramId($bot->userId());

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

                $keyboard = InlineKeyboardMarkup::make()->addRow(
                    InlineKeyboardButton::make(
                        '⬅️ Назад к VPN',
                        callback_data: 'vpn:back'
                    )
                );

                $this->vpnConnectionService
                    ->sendSubscriptionInfo($bot, $user, $tag->value, $keyboard);

                $bot->answerCallbackQuery();
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
            }
        );
    }

    private function getInstructionsKeyboard(): InlineKeyboardMarkup
    {
        return InlineKeyboardMarkup::make()
            ->addRow(
                InlineKeyboardButton::make('Приложение для Android', url: 'https://play.google.com/store/apps/details?id=com.v2raytun.android&pcampaignid=web_share'),
                InlineKeyboardButton::make('Приложение для iPhone/iOS', url: 'https://apps.apple.com/ru/app/v2raytun/id6476628951')
            )
            ->addRow(InlineKeyboardButton::make('Инструкция для Windows', url: 'https://telegra.ph/Instrukciya-po-ustanovke-V2raytun-na-PK--Windows-1011-01-02'))
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

    /**
     * Безопасно получить значение из массива или объекта
     */
    private function getValue(mixed $data, string $key, mixed $default = null): mixed
    {
        if (is_array($data)) {
            return $data[$key] ?? $default;
        }
        if (is_object($data)) {
            return $data->$key ?? $default;
        }
        return $default;
    }
}
