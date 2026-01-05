<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Services\TelegramService;
use SergiX44\Nutgram\Telegram\Types\Common\Update;
use Illuminate\Support\Facades\Log;

final readonly class TelegramWebhookController
{
    public function __construct(
        private TelegramService $telegramService
    ) {
    }

    public function handle(Request $request): Response
    {
        try {
            $data = $request->all();
            Log::info('Telegram webhook received', [
                'update_id' => $data['update_id'] ?? null,
                'message_id' => $data['message']['message_id'] ?? null,
                'text' => $data['message']['text'] ?? null,
                'chat_id' => $data['message']['chat']['id'] ?? null,
                'from_id' => $data['message']['from']['id'] ?? null,
            ]);
            
            $update = Update::fromArray($data);
            $this->telegramService->getBot()->processUpdate($update);
            
            Log::info('Telegram update processed successfully');
        } catch (\Throwable $e) {
            Log::error('Telegram webhook error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all(),
            ]);

            return response('Error', 500);
        }

        return response('OK', 200);
    }
}
