<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Services\User\UserService;
use Illuminate\Support\Facades\View;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

final readonly class VpnConnectionService
{
    public function __construct(private UserService $userService)
    {
    }

    public function sendWelcomeMessageForNewUser(
        Nutgram $bot,
        ?InlineKeyboardMarkup $keyboard = null
    ): void {
        $message = View::make('telegram.welcome', [
            'dailyCost' => $this->userService->getDailyCost(),
            'initialBalance' => $this->userService->getInitialBalance(),
        ])->render();

        $bot->sendMessage(trim($message), parse_mode: 'HTML', reply_markup: $keyboard);
    }

    public function sendMainMenu(
        Nutgram $bot,
        User $user,
        ?InlineKeyboardMarkup $keyboard = null
    ): void {
        $balance = $user->balance?->balance ?? 0;
        $dailyCost = $this->userService->getDailyCost();
        $activeKeysCount = $user->configurations()->count();

        $daysRemaining = $balance > 0 ? (int)floor($balance / $dailyCost) : 0;

        $daysWord = $this->getDaysWord($daysRemaining);

        $name = $bot->user()->first_name ?? $user->name ?? $user->tg_tag ?? 'пользователь';

        $message = View::make('telegram.welcome-back', [
            'name' => $name,
            'activeKeysCount' => $activeKeysCount,
            'balance' => $balance,
            'daysRemaining' => $daysRemaining,
            'daysWord' => $daysWord,
            'dailyCost' => $dailyCost,
        ])->render();

        $bot->sendMessage(trim($message), parse_mode: 'HTML', reply_markup: $keyboard);
    }

    private function getDaysWord(int $days): string
    {
        $cases = [2, 0, 1, 1, 1, 2];
        $titles = ['день', 'дня', 'дней'];

        return $titles[($days % 100 > 4 && $days % 100 < 20) ? 2 : $cases[min($days % 10, 5)]];
    }

    public function sendVpnConnectionMessages(Nutgram $bot, ?InlineKeyboardMarkup $instructionsKeyboard = null, string $vpnKey): array
    {
        $congratsMessage = View::make('telegram.vpn-congratulations')->render();
        $congratsMsg = $bot->sendMessage(trim($congratsMessage));

        $keyMessage = View::make('telegram.vpn-key', [
            'key' => $vpnKey,
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
}
