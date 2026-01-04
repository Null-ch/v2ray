<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Enums\XuiTag;
use Illuminate\Support\Arr;
use App\Services\XuiService;
use SergiX44\Nutgram\Nutgram;
use App\Services\User\UserService;
use Illuminate\Support\Facades\View;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

final readonly class VpnConnectionService
{
    public function __construct(
        private UserService $userService,
        private XuiService $xuiService
    ) {}

    public function sendWelcomeMessageForNewUser(
        Nutgram $bot,
        ?InlineKeyboardMarkup $keyboard = null
    ): void {
        $message = View::make('telegram.welcome', [
            'monthlyCost' => $this->userService->getMonthlyCost(),
        ])->render();

        $bot->sendMessage(trim($message), parse_mode: 'HTML', reply_markup: $keyboard);
    }

    public function sendMainMenu(
        Nutgram $bot,
        User $user,
        ?InlineKeyboardMarkup $keyboard = null
    ): void {
        //TODO:Переписать приветственное меню, Сделать кнопку "Мои конфигурации" по нажатию будут показаны конфигурации и кнопки, чтобы перейти к ним
        $balance = $user->balance?->balance ?? 0;
        $dailyCost = $this->userService->getDailyCost();
        $activeKeysCount = $user->configurations()->count();

        $daysRemaining = $balance > 0 ? (int)floor($balance / $dailyCost) : 0;

        $daysWord = $this->getDaysWord($daysRemaining);

        $name = $bot->user()->first_name ?? $user->name ?? $user->tg_tag ?? 'пользователь';

        $message = View::make('telegram.welcome-back', [
            'name' => $name,
            // 'activeKeysCount' => $activeKeysCount,
            // 'balance' => $balance,
            // 'daysRemaining' => $daysRemaining,
            // 'daysWord' => $daysWord,
            // 'dailyCost' => $dailyCost,
        ])->render();

        $bot->sendMessage(trim($message), parse_mode: 'HTML', reply_markup: $keyboard);
    }

    public function sendConnectVpnMenu(
        Nutgram $bot,
        ?InlineKeyboardMarkup $keyboard = null
    ): void {
        $bot->sendMessage('Выберите страну VPN', reply_markup: $keyboard);
    }

    public function sendGuideMenu(
        Nutgram $bot,
        ?InlineKeyboardMarkup $keyboard = null
    ): void {
        $bot->sendMessage('Ниже представлены пользовательские инструкции ↓', reply_markup: $keyboard);
    }

    public function sendChoosingActiveVpnMenu(
        Nutgram $bot,
        ?InlineKeyboardMarkup $keyboard = null
    ): void {
        $bot->sendMessage('Выберете интересующий VPN', reply_markup: $keyboard);
    }

    public function sendSubscriptionInfo(
        Nutgram $bot,
        User $user,
        string $tag,
        ?InlineKeyboardMarkup $keyboard = null
    ): void {
        $subscriptionInfoArray = $this->xuiService->getSubscriptionInfo($tag, $user->uuid);
        $tag = XuiTag::from($tag);

        $message = View::make('telegram.subscription-info', [
            'enable' => Arr::get($subscriptionInfoArray, 'enable'),
            'expiryTime' => Arr::get($subscriptionInfoArray, 'expiryTime'),
            'tag' => $tag->labelWithFlag(),
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

    public function sendVpnConnectionMessagesToChat(
        Nutgram $bot,
        int $chatId,
        ?InlineKeyboardMarkup $instructionsKeyboard,
        string $vpnKey
    ): array {
        $congratsMessage = View::make('telegram.vpn-congratulations')->render();

        $congratsMsg = $bot->sendMessage(
            text: trim($congratsMessage),
            chat_id: $chatId
        );

        // $keyMessage = View::make('telegram.vpn-key', [
        //     'key' => $vpnKey,
        // ])->render();

        // $keyMsg = $bot->sendMessage(
        //     text: trim($keyMessage),
        //     chat_id: $chatId
        // );

        $instructionsMessage = View::make('telegram.vpn-instructions')->render();

        $instructionsMsg = $bot->sendMessage(
            text: trim($instructionsMessage),
            chat_id: $chatId,
            reply_markup: $instructionsKeyboard
        );

        return [
            'congrats' => $congratsMsg->message_id,
            // 'key' => $keyMsg->message_id,
            'instructions' => $instructionsMsg->message_id,
        ];
    }
}
