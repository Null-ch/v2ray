<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Services\User\UserService;
use Illuminate\Support\Facades\View;

final readonly class VpnConnectionService
{
    public function __construct(private UserService $userService)
    {
    }

    public function sendWelcomeMessageForNewUser(
        int|string $chatId,
        ?array $keyboard = null,
        TelegramApiService $api
    ): void {
        $message = View::make('telegram.welcome', [
            'dailyCost' => $this->userService->getDailyCost(),
            'initialBalance' => $this->userService->getInitialBalance(),
        ])->render();

        $api->sendMessage($chatId, trim($message), 'HTML', $keyboard);
    }

    public function sendMainMenu(
        int|string $chatId,
        User $user,
        ?array $keyboard = null,
        TelegramApiService $api,
        array $from = []
    ): void {
        $balance = $user->balance?->balance ?? 0;
        $dailyCost = $this->userService->getDailyCost();
        $activeKeysCount = $user->configurations()->count();

        $daysRemaining = $balance > 0 ? (int)floor($balance / $dailyCost) : 0;

        $daysWord = $this->getDaysWord($daysRemaining);

        $name = $from['first_name'] ?? $user->name ?? $user->tg_tag ?? 'пользователь';

        $message = View::make('telegram.welcome-back', [
            'name' => $name,
            'activeKeysCount' => $activeKeysCount,
            'balance' => $balance,
            'daysRemaining' => $daysRemaining,
            'daysWord' => $daysWord,
            'dailyCost' => $dailyCost,
        ])->render();

        $api->sendMessage($chatId, trim($message), 'HTML', $keyboard);
    }

    private function getDaysWord(int $days): string
    {
        $cases = [2, 0, 1, 1, 1, 2];
        $titles = ['день', 'дня', 'дней'];

        return $titles[($days % 100 > 4 && $days % 100 < 20) ? 2 : $cases[min($days % 10, 5)]];
    }

    public function sendVpnConnectionMessages(
        int|string $chatId,
        ?array $instructionsKeyboard = null,
        string $vpnKey,
        TelegramApiService $api
    ): array {
        $congratsMessage = View::make('telegram.vpn-congratulations')->render();
        $congratsResponse = $api->sendMessage($chatId, trim($congratsMessage));
        $congratsMsgId = $congratsResponse['result']['message_id'] ?? null;

        $keyMessage = View::make('telegram.vpn-key', [
            'key' => $vpnKey,
        ])->render();
        $keyResponse = $api->sendMessage($chatId, trim($keyMessage));
        $keyMsgId = $keyResponse['result']['message_id'] ?? null;

        $instructionsMessage = View::make('telegram.vpn-instructions')->render();
        $instructionsResponse = $api->sendMessage($chatId, trim($instructionsMessage), null, $instructionsKeyboard);
        $instructionsMsgId = $instructionsResponse['result']['message_id'] ?? null;

        return [
            'congrats' => $congratsMsgId,
            'key' => $keyMsgId,
            'instructions' => $instructionsMsgId,
        ];
    }
}
