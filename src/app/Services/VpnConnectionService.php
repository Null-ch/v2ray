<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\View;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

final readonly class VpnConnectionService
{
    public function sendWelcomeMessage(Nutgram $bot, ?string $username = null, ?InlineKeyboardMarkup $keyboard = null): void
    {
        $message = View::make('telegram.welcome', [
            'username' => $username,
        ])->render();

        $bot->sendMessage(trim($message), reply_markup: $keyboard);
    }

    public function sendVpnConnectionMessages(Nutgram $bot, ?InlineKeyboardMarkup $instructionsKeyboard = null): array
    {
        $congratsMessage = View::make('telegram.vpn-congratulations')->render();
        $congratsMsg = $bot->sendMessage(trim($congratsMessage));

        $keyMessage = View::make('telegram.vpn-key', [
            'key' => $this->generateVpnKey(),
        ])->render();
        $keyMsg = $bot->sendMessage(trim($keyMessage));

        $instructionsMessage = View::make('telegram.vpn-instructions')->render();
        $instructionsMsg = $bot->sendMessage(trim($instructionsMessage), reply_markup: $instructionsKeyboard);

        return [
            'congrats' => $congratsMsg->message_id,
            'key' => $keyMsg->message_id,
            'instructions' => $instructionsMsg->message_id,
        ];
    }

    public function sendWelcomeBackMessage(Nutgram $bot, ?string $username = null, ?InlineKeyboardMarkup $keyboard = null): void
    {
        $message = View::make('telegram.welcome-back', [
            'username' => $username,
        ])->render();

        $bot->sendMessage(trim($message), reply_markup: $keyboard);
    }

    public function generateVpnKey(): string
    {
        return 'КЛЮЧ_ЗАГЛУШКА';
    }
}
