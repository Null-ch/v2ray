<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Services\TelegramService;
use Illuminate\Support\Facades\Log;

final readonly class TelegramWebhookController
{
    public function __construct(
        private TelegramService $telegramService
    ) {
    }

    public function handle(Request $request): Response
    {
        // Логируем каждый запрос, даже если он пустой
        Log::info('Webhook endpoint called', [
            'method' => $request->method(),
            'has_content' => $request->hasContent(),
            'content_length' => strlen($request->getContent()),
        ]);

        try {
            $update = $request->all();
            
            // Логируем детали обновления
            Log::info('Telegram webhook received', [
                'update_id' => $update['update_id'] ?? null,
                'has_message' => isset($update['message']),
                'has_callback_query' => isset($update['callback_query']),
                'callback_data' => $update['callback_query']['data'] ?? null,
                'callback_query_id' => $update['callback_query']['id'] ?? null,
                'from_id' => $update['callback_query']['from']['id'] ?? $update['message']['from']['id'] ?? null,
                'raw_update' => json_encode($update, JSON_UNESCAPED_UNICODE),
            ]);
            
            if (empty($update)) {
                Log::warning('Empty update received');
                return response('Empty update', 200);
            }
            
            Log::info('Processing update with Nutgram');
            $this->telegramService->getBot()->processUpdate($update);
            Log::info('Update processed successfully');
        } catch (\Throwable $e) {
            Log::error('Telegram webhook error', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return response('Error', 500);
        }

        return response('OK', 200);
    }
}

