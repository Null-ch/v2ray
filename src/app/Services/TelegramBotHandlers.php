<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\XuiService;
use App\Jobs\ProcessAcceptTermsJob;
use SergiX44\Nutgram\Nutgram;
use App\Services\User\UserService;
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
        private XuiService $xuiService
    ) {}

    public function registerHandlers(): void
    {
        // Обработчик команды /start
        $this->bot->onCommand('start', function (Nutgram $bot) {
            $telegramId = $bot->userId();
            $user = $this->userService->findUserByTelegramId($telegramId);

            if (!$user) {
                $keyboard = InlineKeyboardMarkup::make()
                    ->addRow(InlineKeyboardButton::make('✅Принять', callback_data: 'accept_terms'));

                $this->vpnConnectionService->sendWelcomeMessageForNewUser($bot, $keyboard);
            } else {
                // Существующий пользователь - показываем главное меню
                $keyboard = InlineKeyboardMarkup::make()
                    ->addRow(InlineKeyboardButton::make('ПОДКЛЮЧИТЬ ВПН', callback_data: 'connect_vpn'));

                $this->vpnConnectionService->sendMainMenu($bot, $user, $keyboard);
            }
        });

        // Обработчик нажатия на кнопку "Принять" для нового пользователя
        $this->bot->onCallbackQueryData('accept_terms', function (Nutgram $bot) {
            // СРАЗУ отвечаем на callback, чтобы убрать "часики" и избежать timeout
            $bot->answerCallbackQuery();

            try {
                $telegramId = $bot->userId();
                $username = $bot->user()->username;
                $name = $bot->user()->first_name;

                Log::info('Callback query accept_terms: ' . json_encode($bot->callbackQuery()->toArray()));
                // Отправляем уведомление о начале обработки
                $bot->sendMessage('⏳ Создаю VPN конфигурацию, пожалуйста, подождите...');

                // Ставим задачу в очередь для асинхронной обработки
                ProcessAcceptTermsJob::dispatch($bot, $telegramId, $username, $name);
            } catch (\Throwable $e) {
                Log::error('Ошибка при постановке задачи accept_terms в очередь: ' . $e->getMessage(), [
                    'trace' => $e->getTraceAsString(),
                ]);
                // Отправляем ошибку пользователю
                try {
                    $bot->sendMessage('❌ Произошла ошибка при обработке запроса. Пожалуйста, попробуйте позже.');
                } catch (\Throwable $sendError) {
                    Log::error('Не удалось отправить сообщение об ошибке: ' . $sendError->getMessage());
                }
            }
        });

        // Обработчик нажатия на кнопку "ПОДКЛЮЧИТЬ ВПН" для существующих пользователей
        $this->bot->onCallbackQueryData('connect_vpn', function (Nutgram $bot) {
            //нужно добавить проброс тега (выбран пользователем) и полученный по тегу инбаунд, пользовательский айди (юзер айди)
            $telegramId = $bot->userId();
            $user = $this->userService->findUserByTelegramId($telegramId);
            if (!$user) {
                $bot->answerCallbackQuery('Пользователь не найден', show_alert: true);
                return;
            }
            // $xuiModel = $this->xuiService->getXuiModelByTag('NL');
            // $inboundId = $xuiModel->inbound_id;
            // $userConfig = $this->xuiService->getUserConfig($xuiModel->tag->value, $inboundId, $user->__get('id'));
            // $vpnKey = $this->formatVpnConfig($userConfig['data']);
            $vpnKey = $this->vpnConnectionService->generateVpnKey();
            $messageIds = $this->vpnConnectionService->sendVpnConnectionMessages($bot, $this->getInstructionsKeyboard(), $vpnKey);

            // Сохраняем ID сообщений в глобальные данные пользователя
            $bot->setGlobalData('vpn_message_ids', $messageIds);

            // Отвечаем на callback, чтобы убрать "часики" на кнопке
            $bot->answerCallbackQuery();
        });

        // Обработчик кнопки "Перенести в приложение"
        $this->bot->onCallbackQueryData('export_config', function (Nutgram $bot) {
            // TODO: Генерация экспортной ссылки с конфигами
            // Пример: https://www.sigmalink.org/redirect/?redirect_to=www.sigmalink.org&token=TOKEN&scheme=v2raytun

            $exportUrl = 'https://www.sigmalink.org/redirect/?redirect_to=www.sigmalink.org&token=PLACEHOLDER_TOKEN&scheme=v2raytun';

            $bot->sendMessage(
                "📲 Экспортная ссылка для переноса конфигов:\n\n$exportUrl\n\n⚠️ Внимание: это заглушка, функционал в разработке",
                parse_mode: 'HTML'
            );

            $bot->answerCallbackQuery('Ссылка отправлена');
        });

        // Обработчик кнопки "Вернуться в главное меню"
        $this->bot->onCallbackQueryData('main_menu', function (Nutgram $bot) {
            // Получаем ID сообщений для удаления
            $messageIds = $bot->getGlobalData('vpn_message_ids', []);

            // Удаляем сообщения (поздравление, ключ, инструкция)
            foreach ($messageIds as $messageId) {
                try {
                    $bot->deleteMessage($bot->chatId(), $messageId);
                } catch (\Throwable $e) {
                    // Игнорируем ошибки удаления
                }
            }

            // Получаем пользователя из БД
            $telegramId = $bot->userId();
            $user = $this->userService->findUserByTelegramId($telegramId);

            if ($user) {
                // Отправляем главное меню с балансом
                $keyboard = InlineKeyboardMarkup::make()
                    ->addRow(InlineKeyboardButton::make('ПОДКЛЮЧИТЬ ВПН', callback_data: 'connect_vpn'));

                $this->vpnConnectionService->sendMainMenu($bot, $user, $keyboard);
            }

            // Отвечаем на callback
            $bot->answerCallbackQuery();
        });

        // Пример обработчика команды /help
        $this->bot->onCommand('help', function (Nutgram $bot) {
            $bot->sendMessage('Доступные команды:' . PHP_EOL . '/start - Начать работу' . PHP_EOL . '/help - Помощь');
        });
    }

    private function getInstructionsKeyboard(): InlineKeyboardMarkup
    {
        return InlineKeyboardMarkup::make()
            ->addRow(
                InlineKeyboardButton::make('Приложение для Android', url: 'https://play.google.com/store/apps/details?id=com.v2raytun.android&pcampaignid=web_share')
            )
            ->addRow(
                InlineKeyboardButton::make('Приложение для iPhone/iOS', url: 'https://apps.apple.com/ru/app/v2raytun/id6476628951')
            )
            ->addRow(
                InlineKeyboardButton::make('Инструкция для Windows', url: 'https://telegra.ph/Instrukciya-po-ustanovke-V2raytun-na-PK--Windows-1011-01-02')
            )
            ->addRow(
                InlineKeyboardButton::make('📲 Перенести в приложение', callback_data: 'export_config')
            )
            ->addRow(
                InlineKeyboardButton::make('Вернуться в главное меню', callback_data: 'main_menu')
            );
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

    /**
     * Format VPN configuration data to string representation
     *
     * @param array|object $configData Configuration data from getUserConfig
     * @return string Formatted VPN config string
     */
    private function formatVpnConfig(mixed $configData): string
    {
        // Безопасное получение значений (работает и с массивом, и с объектом)
        $protocol = $this->getValue($configData, 'protocol', 'unknown');
        $client = $this->getValue($configData, 'client');
        $listen = $this->getValue($configData, 'listen', '0.0.0.0');
        $port = $this->getValue($configData, 'port', 0);

        // Формируем базовую информацию о конфигурации
        $configParts = [
            "Protocol: {$protocol}",
            "Server: {$listen}:{$port}",
        ];

        if ($client && in_array($protocol, ['vmess', 'vless'])) {
            $uuid = $client->id ?? 'N/A';
            $configParts[] = "UUID: {$uuid}";
        } elseif ($client && $protocol === 'trojan') {
            $password = $client->password ?? 'N/A';
            $configParts[] = "Password: {$password}";
        } elseif ($client && $protocol === 'shadowsocks') {
            $password = $client->password ?? 'N/A';
            $method = $client->method ?? 'N/A';
            $configParts[] = "Method: {$method}";
            $configParts[] = "Password: {$password}";
        }

        // Добавляем информацию о сроке действия
        if ($client && isset($client->expiryTime) && $client->expiryTime > 0) {
            $expiryDate = date('Y-m-d H:i:s', $client->expiryTime / 1000);
            $configParts[] = "Expires: {$expiryDate}";
        }

        return implode("\n", $configParts);
    }
}
