<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\User\UserService;
use Illuminate\Support\Facades\Log;
use SergiX44\Nutgram\Nutgram;
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
    ) {
    }

    public function registerHandlers(): void
    {
        Log::info('Registering Telegram bot handlers');
        
        // Обработчик команды /start
        $this->bot->onCommand('start', function (Nutgram $bot) {
            Log::info('Start command received', ['user_id' => $bot->userId()]);
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
        Log::info('Registering accept_terms callback handler');
        
        // Регистрируем специфичные обработчики ПЕРЕД общим
        // ВАЖНО: В Nutgram обработчики onCallbackQueryData имеют приоритет над onCallbackQuery
        $this->bot->onCallbackQueryData('accept_terms', function (Nutgram $bot) {
            error_log('=== accept_terms CALLBACK TRIGGERED === ' . date('Y-m-d H:i:s'));
            Log::info('=== accept_terms callback triggered ===', [
                'user_id' => $bot->userId(),
                'chat_id' => $bot->chatId(),
                'callback_query_id' => $bot->callbackQuery()?->id,
                'callback_data' => $bot->callbackQuery()?->data,
                'timestamp' => now()->toDateTimeString(),
            ]);

            try {
                $telegramId = $bot->userId();
                Log::info('Getting user data', ['telegram_id' => $telegramId]);
                
                $username = $bot->user()->username;
                $name = $bot->user()->first_name;
                Log::info('User data retrieved', ['username' => $username, 'name' => $name]);

                // Создаем пользователя в БД
                Log::info('Creating user in database');
                $user = $this->userService->createUser($telegramId, $username, $name);

                if (!$user) {
                    Log::error('Failed to create user');
                    $bot->answerCallbackQuery('Ошибка создания пользователя', show_alert: true);
                    return;
                }
                Log::info('User created successfully', ['user_id' => $user->id]);

                // Получаем модель Xui для тега NL
                Log::info('Getting Xui model for tag NL');
                $xuiModel = $this->xuiService->getXuiModelByTag('NL');
                Log::info('Xui model retrieved', ['xui_id' => $xuiModel->id, 'inbound_id' => $xuiModel->inbound_id]);
                
                // Создаем конфигурацию с длительностью 7 дней (604800 секунд)
                $expiryTime = 7 * 24 * 60 * 60; // 7 дней в секундах
                $inboundId = $xuiModel->inbound_id; // Используем inbound_id из модели, если указан
                Log::info('Creating config', [
                    'user_id' => $user->id,
                    'inbound_id' => $inboundId,
                    'expiry_time' => $expiryTime,
                ]);
                
                $createResult = $this->xuiService->createConfig('NL', $user, $inboundId, $expiryTime);
                Log::info('Config creation result', ['ok' => $createResult['ok'] ?? false, 'data' => $createResult['data'] ?? null]);
                
                if (!$createResult['ok']) {
                    throw new \RuntimeException('Failed to create config: ' . ($createResult['message'] ?? 'Unknown error'));
                }
                
                // Получаем созданную конфигурацию
                $inboundId = $createResult['data']['inbound_id'];
                Log::info('Getting user config', ['inbound_id' => $inboundId, 'user_id' => $user->id]);
                $userConfig = $this->xuiService->getUserConfig('NL', $inboundId, $user->id);
                Log::info('User config retrieved', ['ok' => $userConfig['ok'] ?? false]);
                
                if (!$userConfig['ok']) {
                    throw new \RuntimeException('Failed to get user config: ' . ($userConfig['message'] ?? 'Unknown error'));
                }
                
                // Формируем ключ/URI из конфигурации
                Log::info('Formatting VPN config');
                $vpnKey = $this->formatVpnConfig($userConfig['data']);
                Log::info('VPN key formatted', ['length' => strlen($vpnKey)]);

                Log::info('Sending VPN connection messages');
                $messageIds = $this->vpnConnectionService->sendVpnConnectionMessages($bot, $this->getInstructionsKeyboard(), $vpnKey);
                Log::info('Messages sent', ['message_ids' => $messageIds]);

                $bot->setGlobalData('vpn_message_ids', $messageIds);

                Log::info('Answering callback query');
                $bot->answerCallbackQuery();
                Log::info('accept_terms callback completed successfully');
            } catch (\Throwable $e) {
                Log::error('Error in accept_terms callback', [
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString(),
                ]);
                
                $errorMessage = mb_substr($e->getMessage(), 0, 200); // Telegram ограничение на длину сообщения
                $bot->answerCallbackQuery('Произошла ошибка: ' . $errorMessage, show_alert: true);
                throw $e;
            }
        });

        // Обработчик нажатия на кнопку "ПОДКЛЮЧИТЬ ВПН" для существующих пользователей
        $this->bot->onCallbackQueryData('connect_vpn', function (Nutgram $bot) {
            Log::info('connect_vpn callback triggered');
            $messageIds = $this->vpnConnectionService->sendVpnConnectionMessages($bot, $this->getInstructionsKeyboard(), 'КЛЮЧ_ЗАГЛУШКА');

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
        
        // Общий обработчик для всех остальных callback_query (регистрируется ПОСЛЕ специфичных)
        // ВАЖНО: Этот обработчик должен быть последним и не должен блокировать специфичные обработчики
        // В Nutgram специфичные обработчики (onCallbackQueryData) имеют приоритет над общими (onCallbackQuery)
        // Но на всякий случай логируем только необработанные запросы
        $this->bot->onCallbackQuery(function (Nutgram $bot) {
            $callbackData = $bot->callbackQuery()?->data;
            
            // Логируем все callback-запросы для отладки
            Log::info('General callback query handler triggered', [
                'data' => $callbackData,
                'user_id' => $bot->userId(),
                'chat_id' => $bot->chatId(),
                'message_id' => $bot->callbackQuery()?->message?->message_id,
            ]);
            
            // Если это необработанный callback, отвечаем на него
            // Но не блокируем специфичные обработчики - они должны сработать первыми
        });
        
        Log::info('All Telegram bot handlers registered');
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
     * Format VPN configuration data to string representation
     * 
     * @param array $configData Configuration data from getUserConfig
     * @return string Formatted VPN config string
     */
    private function formatVpnConfig(array $configData): string
    {
        $protocol = $configData['protocol'] ?? 'unknown';
        $client = $configData['client'] ?? [];
        $listen = $configData['listen'] ?? '0.0.0.0';
        $port = $configData['port'] ?? 0;
        
        // Формируем базовую информацию о конфигурации
        $configParts = [
            "Protocol: {$protocol}",
            "Server: {$listen}:{$port}",
        ];
        
        // Добавляем информацию о клиенте в зависимости от протокола
        if (in_array($protocol, ['vmess', 'vless'])) {
            $uuid = $client['id'] ?? 'N/A';
            $configParts[] = "UUID: {$uuid}";
        } elseif ($protocol === 'trojan') {
            $password = $client['password'] ?? 'N/A';
            $configParts[] = "Password: {$password}";
        } elseif ($protocol === 'shadowsocks') {
            $password = $client['password'] ?? 'N/A';
            $method = $client['method'] ?? 'N/A';
            $configParts[] = "Method: {$method}";
            $configParts[] = "Password: {$password}";
        }
        
        // Добавляем информацию о сроке действия
        if (isset($client['expiryTime']) && $client['expiryTime'] > 0) {
            $expiryDate = date('Y-m-d H:i:s', $client['expiryTime'] / 1000);
            $configParts[] = "Expires: {$expiryDate}";
        }
        
        return implode("\n", $configParts);
    }
}

