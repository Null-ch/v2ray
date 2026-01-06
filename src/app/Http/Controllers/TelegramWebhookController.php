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
            
            $bot = $this->telegramService->getBot();
            Log::info('Обрабатываю обновление', [
                'update_id' => $update->update_id,
                'message' => $update->message ? 'yes' : 'no',
                'callback_query' => $update->callback_query ? 'yes' : 'no',
            ]);
            
            $bot->processUpdate($update);
            Log::info('Обновление обработано', ['update_id' => $update->update_id]);
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
