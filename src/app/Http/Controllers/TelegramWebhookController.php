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
            Log::info('token' . config('services.telegram.bot_token'));
            Log::info('Пришел реквест: ' . json_encode($data));
            $update = Update::fromArray($data);
            $this->telegramService->getBot()->processUpdate($update);
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
