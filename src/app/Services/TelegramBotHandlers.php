<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\User\UserService;
use Illuminate\Support\Facades\Log;

final class TelegramBotHandlers
{
    private TelegramApiService $api;
    private array $userData = []; // Хранилище данных пользователей (замена setGlobalData)

    public function __construct(
        private VpnConnectionService $vpnConnectionService,
        private UserService $userService,
        private XuiService $xuiService
    ) {
    }

    public function registerHandlers(TelegramApiService $api): void
    {
        $this->api = $api;
        Log::info('Telegram bot handlers ready');
    }

    public function handleUpdate(array $update): void
    {
        try {
            // Обработка сообщений
            if (isset($update['message'])) {
                $this->handleMessage($update['message']);
            }

            // Обработка callback query
            if (isset($update['callback_query'])) {
                $this->handleCallbackQuery($update['callback_query']);
            }
        } catch (\Throwable $e) {
            Log::error('Error handling update', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    private function handleMessage(array $message): void
    {
        $chatId = $message['chat']['id'] ?? null;
        $text = $message['text'] ?? '';
        $from = $message['from'] ?? [];

        if (!$chatId) {
            return;
        }

        // Обработка команд
        if (str_starts_with($text, '/')) {
            $command = explode(' ', $text)[0];
            $command = str_replace('/', '', $command);

            match ($command) {
                'start' => $this->handleStartCommand($chatId, $from),
                'help' => $this->handleHelpCommand($chatId),
                default => null,
            };
        }
    }

    private function handleStartCommand(int|string $chatId, array $from): void
    {
        $telegramId = $from['id'] ?? null;
        
        if (!$telegramId) {
            return;
        }

        Log::info('Start command received', ['user_id' => $telegramId]);
        
        $user = $this->userService->findUserByTelegramId($telegramId);

        if (!$user) {
            // Новый пользователь
            $keyboard = $this->createInlineKeyboard([
                [['text' => '✅Принять', 'callback_data' => 'accept_terms']]
            ]);

            $this->vpnConnectionService->sendWelcomeMessageForNewUser($chatId, $keyboard, $this->api);
        } else {
            // Существующий пользователь
            $keyboard = $this->createInlineKeyboard([
                [['text' => 'ПОДКЛЮЧИТЬ ВПН', 'callback_data' => 'connect_vpn']]
            ]);

            $this->vpnConnectionService->sendMainMenu($chatId, $user, $keyboard, $this->api, $from);
        }
    }

    private function handleHelpCommand(int|string $chatId): void
    {
        $text = 'Доступные команды:' . PHP_EOL . '/start - Начать работу' . PHP_EOL . '/help - Помощь';
        $this->api->sendMessage($chatId, $text);
    }

    private function handleCallbackQuery(array $callbackQuery): void
    {
        $callbackQueryId = $callbackQuery['id'] ?? null;
        $data = $callbackQuery['data'] ?? null;
        $from = $callbackQuery['from'] ?? [];
        $message = $callbackQuery['message'] ?? [];
        $chatId = $message['chat']['id'] ?? null;

        if (!$callbackQueryId || !$data || !$chatId) {
            return;
        }

        Log::info('Callback query received', [
            'callback_query_id' => $callbackQueryId,
            'data' => $data,
            'user_id' => $from['id'] ?? null,
            'chat_id' => $chatId,
        ]);

        // Обрабатываем в зависимости от callback_data
        match ($data) {
            'accept_terms' => $this->handleAcceptTerms($callbackQueryId, $chatId, $from),
            'connect_vpn' => $this->handleConnectVpn($callbackQueryId, $chatId, $from),
            'export_config' => $this->handleExportConfig($callbackQueryId, $chatId),
            'main_menu' => $this->handleMainMenu($callbackQueryId, $chatId, $from),
            default => $this->handleUnknownCallback($callbackQueryId),
        };
    }

    private function handleAcceptTerms(string $callbackQueryId, int|string $chatId, array $from): void
    {
        error_log('=== accept_terms CALLBACK TRIGGERED === ' . date('Y-m-d H:i:s'));
        
        Log::info('=== accept_terms callback triggered ===', [
            'user_id' => $from['id'] ?? null,
            'chat_id' => $chatId,
            'callback_query_id' => $callbackQueryId,
            'timestamp' => now()->toDateTimeString(),
        ]);

        // Отвечаем на callback query СРАЗУ
        try {
            $this->api->answerCallbackQuery($callbackQueryId, 'Обработка...');
        } catch (\Throwable $e) {
            Log::warning('Failed to answer callback query immediately', [
                'message' => $e->getMessage(),
            ]);
        }

        try {
            $telegramId = $from['id'] ?? null;
            if (!$telegramId) {
                throw new \RuntimeException('Telegram ID not found');
            }

            Log::info('Getting user data', ['telegram_id' => $telegramId]);
            
            $username = $from['username'] ?? null;
            $name = $from['first_name'] ?? null;
            Log::info('User data retrieved', ['username' => $username, 'name' => $name]);

            // Проверяем, не существует ли уже пользователь
            $existingUser = $this->userService->findUserByTelegramId($telegramId);
            if ($existingUser) {
                Log::info('User already exists, using existing user', [
                    'user_id' => $existingUser->id,
                    'telegram_id' => $telegramId,
                ]);
                $user = $existingUser;
            } else {
                // Создаем пользователя в БД
                Log::info('Creating user in database', [
                    'telegram_id' => $telegramId,
                    'username' => $username,
                    'name' => $name,
                ]);
                $user = $this->userService->createUser($telegramId, $username, $name);

                if (!$user) {
                    Log::error('Failed to create user', [
                        'telegram_id' => $telegramId,
                        'username' => $username,
                        'name' => $name,
                    ]);
                    $this->api->sendMessage(
                        $chatId,
                        '❌ Ошибка создания пользователя. Попробуйте позже или обратитесь в поддержку.'
                    );
                    return;
                }
                Log::info('User created successfully', [
                    'user_id' => $user->id,
                    'telegram_id' => $telegramId,
                ]);
            }

            // Получаем модель Xui для тега NL
            Log::info('Getting Xui model for tag NL');
            $xuiModel = $this->xuiService->getXuiModelByTag('NL');
            Log::info('Xui model retrieved', ['xui_id' => $xuiModel->id, 'inbound_id' => $xuiModel->inbound_id]);
            
            // Создаем конфигурацию с длительностью 7 дней (604800 секунд)
            $expiryTime = 7 * 24 * 60 * 60; // 7 дней в секундах
            $inboundId = $xuiModel->inbound_id;
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
            $keyboard = $this->getInstructionsKeyboard();
            $messageIds = $this->vpnConnectionService->sendVpnConnectionMessages($chatId, $keyboard, $vpnKey, $this->api);
            Log::info('Messages sent', ['message_ids' => $messageIds]);

            // Сохраняем ID сообщений
            $this->setUserData($chatId, 'vpn_message_ids', $messageIds);

            Log::info('accept_terms callback completed successfully');
        } catch (\Throwable $e) {
            Log::error('Error in accept_terms callback', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            $errorMessage = mb_substr($e->getMessage(), 0, 200);
            $this->api->sendMessage($chatId, '❌ Произошла ошибка: ' . $errorMessage);
        }
    }

    private function handleConnectVpn(string $callbackQueryId, int|string $chatId, array $from): void
    {
        Log::info('connect_vpn callback triggered');
        
        // Отвечаем на callback сразу
        try {
            $this->api->answerCallbackQuery($callbackQueryId);
        } catch (\Throwable $e) {
            Log::warning('Failed to answer callback query', ['message' => $e->getMessage()]);
        }
        
        $keyboard = $this->getInstructionsKeyboard();
        $messageIds = $this->vpnConnectionService->sendVpnConnectionMessages($chatId, $keyboard, 'КЛЮЧ_ЗАГЛУШКА', $this->api);

        // Сохраняем ID сообщений
        $this->setUserData($chatId, 'vpn_message_ids', $messageIds);
    }

    private function handleExportConfig(string $callbackQueryId, int|string $chatId): void
    {
        // Отвечаем на callback сразу
        try {
            $this->api->answerCallbackQuery($callbackQueryId, 'Ссылка отправлена');
        } catch (\Throwable $e) {
            Log::warning('Failed to answer callback query', ['message' => $e->getMessage()]);
        }
        
        $exportUrl = 'https://www.sigmalink.org/redirect/?redirect_to=www.sigmalink.org&token=PLACEHOLDER_TOKEN&scheme=v2raytun';

        $this->api->sendMessage(
            $chatId,
            "📲 Экспортная ссылка для переноса конфигов:\n\n$exportUrl\n\n⚠️ Внимание: это заглушка, функционал в разработке",
            'HTML'
        );
    }

    private function handleMainMenu(string $callbackQueryId, int|string $chatId, array $from): void
    {
        // Отвечаем на callback сразу
        try {
            $this->api->answerCallbackQuery($callbackQueryId);
        } catch (\Throwable $e) {
            Log::warning('Failed to answer callback query', ['message' => $e->getMessage()]);
        }
        
        // Получаем ID сообщений для удаления
        $messageIds = $this->getUserData($chatId, 'vpn_message_ids', []);

        // Удаляем сообщения
        foreach ($messageIds as $messageId) {
            try {
                $this->api->deleteMessage($chatId, $messageId);
            } catch (\Throwable $e) {
                // Игнорируем ошибки удаления
            }
        }

        // Получаем пользователя из БД
        $telegramId = $from['id'] ?? null;
        if ($telegramId) {
            $user = $this->userService->findUserByTelegramId($telegramId);

            if ($user) {
                // Отправляем главное меню с балансом
                $keyboard = $this->createInlineKeyboard([
                    [['text' => 'ПОДКЛЮЧИТЬ ВПН', 'callback_data' => 'connect_vpn']]
                ]);

                $this->vpnConnectionService->sendMainMenu($chatId, $user, $keyboard, $this->api, $from);
            }
        }
    }

    private function handleUnknownCallback(string $callbackQueryId): void
    {
        Log::info('Unknown callback query', ['callback_query_id' => $callbackQueryId]);
        try {
            $this->api->answerCallbackQuery($callbackQueryId);
        } catch (\Throwable $e) {
            // Игнорируем ошибки
        }
    }

    private function getInstructionsKeyboard(): array
    {
        return $this->createInlineKeyboard([
            [['text' => 'Приложение для Android', 'url' => 'https://play.google.com/store/apps/details?id=com.v2raytun.android&pcampaignid=web_share']],
            [['text' => 'Приложение для iPhone/iOS', 'url' => 'https://apps.apple.com/ru/app/v2raytun/id6476628951']],
            [['text' => 'Инструкция для Windows', 'url' => 'https://telegra.ph/Instrukciya-po-ustanovke-V2raytun-na-PK--Windows-1011-01-02']],
            [['text' => '📲 Перенести в приложение', 'callback_data' => 'export_config']],
            [['text' => 'Вернуться в главное меню', 'callback_data' => 'main_menu']],
        ]);
    }

    private function createInlineKeyboard(array $buttons): array
    {
        $keyboard = [];
        foreach ($buttons as $row) {
            $keyboardRow = [];
            foreach ($row as $button) {
                $keyboardButton = ['text' => $button['text']];
                if (isset($button['callback_data'])) {
                    $keyboardButton['callback_data'] = $button['callback_data'];
                }
                if (isset($button['url'])) {
                    $keyboardButton['url'] = $button['url'];
                }
                $keyboardRow[] = $keyboardButton;
            }
            $keyboard[] = $keyboardRow;
        }
        return ['inline_keyboard' => $keyboard];
    }

    private function setUserData(int|string $chatId, string $key, mixed $value): void
    {
        $this->userData[$chatId][$key] = $value;
    }

    private function getUserData(int|string $chatId, string $key, mixed $default = null): mixed
    {
        return $this->userData[$chatId][$key] ?? $default;
    }

    private function formatVpnConfig(array $configData): string
    {
        $protocol = $configData['protocol'] ?? 'unknown';
        $client = $configData['client'] ?? [];
        $listen = $configData['listen'] ?? '0.0.0.0';
        $port = $configData['port'] ?? 0;
        
        $configParts = [
            "Protocol: {$protocol}",
            "Server: {$listen}:{$port}",
        ];
        
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
        
        if (isset($client['expiryTime']) && $client['expiryTime'] > 0) {
            $expiryDate = date('Y-m-d H:i:s', $client['expiryTime'] / 1000);
            $configParts[] = "Expires: {$expiryDate}";
        }
        
        return implode("\n", $configParts);
    }
}
