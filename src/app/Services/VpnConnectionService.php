<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\View;
use SergiX44\Nutgram\Nutgram;

final readonly class VpnConnectionService
{
    public function sendWelcomeMessage(Nutgram $bot, ?string $username = null): void
    {
        $message = View::make('telegram.welcome', [
            'username' => $username,
        ])->render();

        $bot->sendMessage(trim($message));
    }

    public function sendVpnConnectionMessages(Nutgram $bot): void
    {
        $congratsMessage = View::make('telegram.vpn-congratulations')->render();
        $bot->sendMessage(trim($congratsMessage));

        $keyMessage = View::make('telegram.vpn-key', [
            'key' => $this->generateVpnKey(),
        ])->render();
        $bot->sendMessage(trim($keyMessage));

        $instructionsMessage = View::make('telegram.vpn-instructions')->render();
        $bot->sendMessage(trim($instructionsMessage));
    }

    public function generateVpnKey(): string
    {
        return 'КЛЮЧ_ЗАГЛУШКА';
    }
}
