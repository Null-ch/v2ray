<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Log;

final class TelegramService
{
    private readonly TelegramApiService $api;

    public function __construct()
    {
        $this->api = new TelegramApiService();
    }

    public function getApi(): TelegramApiService
    {
        return $this->api;
    }

    public function run(): void
    {
        // Убеждаемся, что вебхук удален перед запуском polling
        try {
            $webhookInfo = $this->api->getWebhookInfo();
            if (!empty($webhookInfo['result']['url'] ?? null)) {
                Log::info('Webhook found, deleting before starting polling', [
                    'url' => $webhookInfo['result']['url'],
                ]);
            } else {
                Log::info('No webhook is set');
            }
            
            // Всегда пытаемся удалить вебхук (безопасно, даже если его нет)
            $this->api->deleteWebhook();
            Log::info('Webhook deletion completed (webhook was deleted or did not exist)');
        } catch (\Throwable $e) {
            Log::warning('Error checking/deleting webhook', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
        }

        // Регистрируем обработчики
        $handlers = app(TelegramBotHandlers::class);
        $handlers->registerHandlers($this->api);

        // Запускаем long polling
        Log::info('Starting Telegram bot in polling mode');
        $this->startPolling();
    }

    private function startPolling(): void
    {
        $offset = null;
        
        while (true) {
            try {
                // Получаем обновления с таймаутом 60 секунд (long polling)
                $response = $this->api->getUpdates($offset, 100, 60);
                
                if (!($response['ok'] ?? false)) {
                    Log::error('Failed to get updates', ['response' => $response]);
                    sleep(5); // Ждем перед следующей попыткой
                    continue;
                }

                $updates = $response['result'] ?? [];
                
                if (empty($updates)) {
                    continue;
                }

                // Обрабатываем каждое обновление
                foreach ($updates as $update) {
                    $updateId = $update['update_id'] ?? null;
                    
                    if ($updateId !== null) {
                        $offset = $updateId + 1; // Следующий offset
                    }

                    // Передаем обновление обработчикам
                    $handlers = app(TelegramBotHandlers::class);
                    $handlers->handleUpdate($update);
                }
            } catch (\Throwable $e) {
                Log::error('Error in polling loop', [
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString(),
                ]);
                sleep(5); // Ждем перед следующей попыткой
            }
        }
    }
}
