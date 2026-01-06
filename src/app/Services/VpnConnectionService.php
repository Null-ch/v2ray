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
        private XuiService $xuiService,
        private SettingService $settingService,
    ) {}

    public function sendWelcomeMessageForNewUser(
        Nutgram $bot,
        ?InlineKeyboardMarkup $keyboard = null
    ): int {
        $message = View::make('telegram.welcome', [
            'monthlyCost' => $this->settingService->getInt('default.monthly.cost'),
            'trialDuration' => $this->settingService->getInt('trial.duration'),
        ])->render();

        $sentMessage = $bot->sendMessage(trim($message), parse_mode: 'HTML', reply_markup: $keyboard);
        return $sentMessage->message_id;
    }

    public function sendMainMenu(
        Nutgram $bot,
        User $user,
        ?InlineKeyboardMarkup $keyboard = null
    ): int {
        $name = $bot->user()->first_name ?? $user->name ?? $user->tg_tag ?? 'пользователь';
        $referralLink = null;
        if ($user->referral_code) {
            $botUsername = config('services.telegram.bot_username');
            if (!$botUsername) {
                try {
                    $botInfo = $bot->getMe();
                    $botUsername = $botInfo->username;
                } catch (\Throwable $e) {
                }
            }

            if ($botUsername) {
                $referralLink = "https://t.me/{$botUsername}?start={$user->referral_code}";
            }
        }

        $hasVpn = $user->subscriptions()->exists();
        $message = View::make('telegram.welcome-back', [
            'name' => $name,
            'referralLink' => $referralLink,
            'hasVpn' => $hasVpn,
            'refBonusDuration' => $this->settingService->getInt('ref.bonus.duration'),
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
        ?InlineKeyboardMarkup $keyboard = null,
        ?bool $isReferral = false
    ): int {
        if ($isReferral) {
            $sentMessage = $bot->sendMessage('Выберете VPN на который будут начислены дополнительные дни подписки за приглашение', reply_markup: $keyboard);
            return $sentMessage->message_id;
        }
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
        $expiryTime = $this->xuiService->formatExpiryTime(
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

        $instructionsMessage = View::make('telegram.vpn-instructions')->render();

        $instructionsMsg = $bot->sendMessage(
            text: trim($instructionsMessage),
            chat_id: $chatId,
            reply_markup: $instructionsKeyboard
        );

        return [
            'congrats' => $congratsMsg->message_id,
            'instructions' => $instructionsMsg->message_id,
        ];
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
