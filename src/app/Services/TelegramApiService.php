<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class TelegramApiService
{
    private readonly string $token;
    private readonly string $baseUrl;

    public function __construct()
    {
        $token = config('services.telegram.bot_token');
        
        if (empty($token)) {
            throw new \RuntimeException('Telegram bot token is not configured');
        }

        $this->token = $token;
        $this->baseUrl = "https://api.telegram.org/bot{$token}";
    }

    /**
     * Send HTTP request to Telegram Bot API
     */
    private function request(string $method, array $params = []): array
    {
        try {
            $response = Http::timeout(10)->post("{$this->baseUrl}/{$method}", $params);
            
            if (!$response->successful()) {
                Log::error('Telegram API request failed', [
                    'method' => $method,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return ['ok' => false, 'error' => $response->body()];
            }

            $data = $response->json();
            
            if (!($data['ok'] ?? false)) {
                Log::error('Telegram API returned error', [
                    'method' => $method,
                    'error' => $data['description'] ?? 'Unknown error',
                ]);
            }

            return $data;
        } catch (\Throwable $e) {
            Log::error('Telegram API request exception', [
                'method' => $method,
                'error' => $e->getMessage(),
            ]);
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get updates from Telegram (for polling)
     */
    public function getUpdates(?int $offset = null, ?int $limit = 100, ?int $timeout = 0): array
    {
        $params = [];
        if ($offset !== null) {
            $params['offset'] = $offset;
        }
        if ($limit !== null) {
            $params['limit'] = $limit;
        }
        if ($timeout !== null) {
            $params['timeout'] = $timeout;
        }

        return $this->request('getUpdates', $params);
    }

    /**
     * Send message
     */
    public function sendMessage(
        int|string $chatId,
        string $text,
        ?string $parseMode = null,
        ?array $replyMarkup = null,
        ?int $replyToMessageId = null
    ): array {
        $params = [
            'chat_id' => $chatId,
            'text' => $text,
        ];

        if ($parseMode) {
            $params['parse_mode'] = $parseMode;
        }
        if ($replyMarkup) {
            $params['reply_markup'] = json_encode($replyMarkup);
        }
        if ($replyToMessageId) {
            $params['reply_to_message_id'] = $replyToMessageId;
        }

        return $this->request('sendMessage', $params);
    }

    /**
     * Answer callback query
     */
    public function answerCallbackQuery(
        string $callbackQueryId,
        ?string $text = null,
        bool $showAlert = false,
        ?string $url = null,
        ?int $cacheTime = null
    ): array {
        $params = [
            'callback_query_id' => $callbackQueryId,
        ];

        if ($text !== null) {
            $params['text'] = $text;
        }
        if ($showAlert) {
            $params['show_alert'] = true;
        }
        if ($url !== null) {
            $params['url'] = $url;
        }
        if ($cacheTime !== null) {
            $params['cache_time'] = $cacheTime;
        }

        return $this->request('answerCallbackQuery', $params);
    }

    /**
     * Delete message
     */
    public function deleteMessage(int|string $chatId, int $messageId): array
    {
        return $this->request('deleteMessage', [
            'chat_id' => $chatId,
            'message_id' => $messageId,
        ]);
    }

    /**
     * Get webhook info
     */
    public function getWebhookInfo(): array
    {
        return $this->request('getWebhookInfo');
    }

    /**
     * Delete webhook
     */
    public function deleteWebhook(?bool $dropPendingUpdates = null): array
    {
        $params = [];
        if ($dropPendingUpdates !== null) {
            $params['drop_pending_updates'] = $dropPendingUpdates;
        }

        return $this->request('deleteWebhook', $params);
    }

    /**
     * Set webhook
     */
    public function setWebhook(string $url, ?array $allowedUpdates = null): array
    {
        $params = ['url' => $url];
        if ($allowedUpdates !== null) {
            $params['allowed_updates'] = $allowedUpdates;
        }

        return $this->request('setWebhook', $params);
    }

    /**
     * Get bot info
     */
    public function getMe(): array
    {
        return $this->request('getMe');
    }
}

