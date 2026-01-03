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
        try {
            $this->telegramService->getBot()->handleUpdate($request);
        } catch (\Throwable $e) {
            Log::error('Telegram webhook error: ' . $e->getMessage());

            return response('Error', 500);
        }

        return response('OK', 200);
    }
}
