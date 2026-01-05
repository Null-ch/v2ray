<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Models\Pricing;
use App\Enums\XuiTag;
use Illuminate\Support\Arr;
use Illuminate\Database\Eloquent\Collection;
use App\Services\XuiService;
use SergiX44\Nutgram\Nutgram;
use App\Services\User\UserService;
use Illuminate\Support\Facades\Log;
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
    ): int {
        $message = View::make('telegram.welcome', [
            'monthlyCost' => $this->userService->getMonthlyCost(),
        ])->render();

        $sentMessage = $bot->sendMessage(trim($message), parse_mode: 'HTML', reply_markup: $keyboard);
        return $sentMessage->message_id;
    }

    public function sendMainMenu(
        Nutgram $bot,
        User $user,
        ?InlineKeyboardMarkup $keyboard = null
    ): int {
        //TODO:Переписать приветственное меню, Сделать кнопку "Мои конфигурации" по нажатию будут показаны конфигурации и кнопки, чтобы перейти к ним
        $balance = $user->balance?->balance ?? 0;
        $dailyCost = $this->userService->getDailyCost();
        $activeKeysCount = $user->configurations()->count();

        $daysRemaining = $balance > 0 ? (int)floor($balance / $dailyCost) : 0;

        $daysWord = $this->getDaysWord($daysRemaining);

        $name = $bot->user()->first_name ?? $user->name ?? $user->tg_tag ?? 'пользователь';

        // Генерируем реферальную ссылку
        $referralLink = null;
        if ($user->referral_code) {
            $botUsername = config('services.telegram.bot_username');
            if (!$botUsername) {
                try {
                    $botInfo = $bot->getMe();
                    $botUsername = $botInfo->username;
                } catch (\Throwable $e) {
                    // Если не удалось получить имя бота, оставляем ссылку пустой
                }
            }
            if ($botUsername) {
                $referralLink = "https://t.me/{$botUsername}?start={$user->referral_code}";
            }
        }

        $message = View::make('telegram.welcome-back', [
            'name' => $name,
            'referralLink' => $referralLink,
            // 'activeKeysCount' => $activeKeysCount,
            // 'balance' => $balance,
            // 'daysRemaining' => $daysRemaining,
            // 'daysWord' => $daysWord,
            // 'dailyCost' => $dailyCost,
        ])->render();

        $sentMessage = $bot->sendMessage(trim($message), parse_mode: 'HTML', reply_markup: $keyboard);
        return $sentMessage->message_id;
    }

    public function sendConnectVpnMenu(
        Nutgram $bot,
        ?InlineKeyboardMarkup $keyboard = null
    ): int {
        $sentMessage = $bot->sendMessage('Выберите страну VPN', reply_markup: $keyboard);
        return $sentMessage->message_id;
    }

    public function sendGuideMenu(
        Nutgram $bot,
        ?InlineKeyboardMarkup $keyboard = null
    ): int {
        $sentMessage = $bot->sendMessage('Ниже представлены пользовательские инструкции ↓', reply_markup: $keyboard);
        return $sentMessage->message_id;
    }

    public function sendChoosingActiveVpnMenu(
        Nutgram $bot,
        ?InlineKeyboardMarkup $keyboard = null
    ): int {
        $sentMessage = $bot->sendMessage('Выберете интересующий VPN', reply_markup: $keyboard);
        return $sentMessage->message_id;
    }

    public function sendSubscriptionInfo(
        Nutgram $bot,
        User $user,
        string $tag,
        ?InlineKeyboardMarkup $keyboard = null
    ): int {
        $subscriptionInfoArray = $this->xuiService->getSubscriptionInfo($tag, $user->uuid);
        $tag = XuiTag::from($tag);
        $name = $bot->user()->first_name ?? $user->name ?? $user->tg_tag ?? 'Пользователь';
        $expiryTime = $this->formatExpiryTime(
            Arr::get($subscriptionInfoArray, 'expiryTime', [])
        );

        $message = View::make('telegram.subscription-info', [
            'enable' => Arr::get($subscriptionInfoArray, 'enable') ? 'Да' : 'Нет',
            'expiryTime' => $expiryTime,
            'tag' => $tag->labelWithFlag(),
            'name' => $name
        ])->render();

        $sentMessage = $bot->sendMessage(trim($message), parse_mode: 'HTML', reply_markup: $keyboard);
        return $sentMessage->message_id;
    }

    public function sendPricingInfo(
        Nutgram $bot,
        User $user,
        Collection $pricings,
        string $tag,
        ?InlineKeyboardMarkup $keyboard = null
    ): int {
        $tag = XuiTag::from($tag);
        $name = $bot->user()->first_name ?? $user->name ?? $user->tg_tag ?? 'Пользователь';

        $message = View::make('telegram.pricing-info', [
            'pricings' => $pricings,
            'tag' => $tag->labelWithFlag(),
            'name' => $name
        ])->render();

        $sentMessage = $bot->sendMessage(trim($message), parse_mode: 'HTML', reply_markup: $keyboard);
        return $sentMessage->message_id;
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

    private function formatExpiryTime(array $expiryTime): string
    {
        $parts = [];

        if (!empty($expiryTime['days'])) {
            $parts[] = $expiryTime['days'] . ' ' . $this->pluralize(
                (int) $expiryTime['days'],
                ['день', 'дня', 'дней']
            );
        }

        if (!empty($expiryTime['hours'])) {
            $parts[] = $expiryTime['hours'] . ' ' . $this->pluralize(
                (int) $expiryTime['hours'],
                ['час', 'часа', 'часов']
            );
        }

        if (!empty($expiryTime['minutes'])) {
            $parts[] = $expiryTime['minutes'] . ' ' . $this->pluralize(
                (int) $expiryTime['minutes'],
                ['минута', 'минуты', 'минут']
            );
        }

        return $parts !== [] ? implode(' ', $parts) : 'Менее минуты';
    }

    private function pluralize(int $number, array $forms): string
    {
        $cases = [2, 0, 1, 1, 1, 2];

        return $forms[($number % 100 > 4 && $number % 100 < 20)
            ? 2
            : $cases[min($number % 10, 5)]];
    }

    /**
     * Получает ссылку для импорта конфигурации пользователя
     *
     * @param User $user
     * @param string $tag
     * @return string
     */
    public function getUserConfigImportLink(User $user, string $tag): string
    {
        return $this->xuiService->getSubLink($tag, $user->uuid, 'import');
    }
}
