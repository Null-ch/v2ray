<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Log;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\RunningMode\Polling;

final class TelegramService
{
    private readonly Nutgram $bot;

    public function __construct()
    {
        $token = config('services.telegram.bot_token');
        
        if (empty($token)) {
            throw new \RuntimeException('Telegram bot token is not configured');
        }

        $this->bot = new Nutgram($token);
        
        // Явно устанавливаем режим polling (long polling)
        // Это гарантирует, что бот будет использовать getUpdates вместо вебхука
        $this->bot->setRunningMode(Polling::class);
    }

    public function getBot(): Nutgram
    {
        return $this->bot;
    }

    public function run(): void
    {
        // Убеждаемся, что вебхук удален перед запуском polling
        // Это критично, так как нельзя использовать polling и webhook одновременно
        try {
            // Сначала пытаемся проверить статус вебхука (если метод доступен)
            if (method_exists($this->bot, 'getWebhookInfo')) {
                $webhookInfo = $this->bot->getWebhookInfo();
                if (!empty($webhookInfo->url ?? null)) {
                    Log::info('Webhook found, deleting before starting polling', ['url' => $webhookInfo->url]);
                } else {
                    Log::info('No webhook is set');
                }
            }
            
            // Всегда пытаемся удалить вебхук (безопасно, даже если его нет)
            $this->bot->deleteWebhook();
            Log::info('Webhook deletion completed (webhook was deleted or did not exist)');
        } catch (\Throwable $e) {
            // Логируем ошибку, но продолжаем - возможно вебхук уже удален или метод недоступен
            Log::warning('Error checking/deleting webhook', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            // Продолжаем работу - если вебхук не удален, Telegram вернет ошибку при попытке getUpdates
        }

        // Регистрируем обработчики на этом экземпляре Nutgram
        $handlers = app(TelegramBotHandlers::class);
        $handlers->registerHandlers();

        // Запускаем long polling (явно установлен через setRunningMode)
        Log::info('Starting Telegram bot in polling mode');
        $this->bot->run();
    }
}

