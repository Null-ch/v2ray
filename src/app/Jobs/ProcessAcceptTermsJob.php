<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Referral;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use App\Services\XuiService;
use Illuminate\Bus\Queueable;
use SergiX44\Nutgram\Nutgram;
use App\Services\SettingService;
use App\Services\User\UserService;
use App\Helpers\MillisecondsHelper;
use Illuminate\Support\Facades\Log;
use App\Services\VpnConnectionService;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

final class ProcessAcceptTermsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private SettingService $settingService;

    public function __construct(
        private readonly int $telegramId,
        private readonly ?string $username,
        private readonly ?string $name,
        private readonly ?int $referrerId = null,
    ) {}

    public function handle(
        UserService $userService,
        XuiService $xuiService,
        VpnConnectionService $vpnConnectionService,
        SettingService $settingService,
    ): void {
        $this->settingService = $settingService;
        $bot = $this->createBot();

        try {
            $user = $this->getOrCreateUser($userService);

            if (!$user) {
                $bot->sendMessage('❌ Ошибка создания пользователя', (string)$this->telegramId);
                return;
            }

            $this->processReferrerBonus($user, $userService, $xuiService);
            $this->createUserSubscription($user, $xuiService);
            $this->sendInstructions($bot, $vpnConnectionService, $xuiService, $user);

            $bot->deleteGlobalData('referrer_id');
            $bot->deleteGlobalData('referral_code');
        } catch (\Throwable $e) {
            Log::error('Ошибка при обработке accept_terms в Job', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'telegram_id' => $this->telegramId,
            ]);

            try {
                $bot->sendMessage('❌ Произошла ошибка при создании VPN конфигурации. Пожалуйста, попробуйте позже.', (string)$this->telegramId);
            } catch (\Throwable $sendError) {
                Log::error('Не удалось отправить сообщение об ошибке', [
                    'error' => $sendError->getMessage(),
                ]);
            }

            throw $e;
        }
    }

    protected function createBot(): Nutgram
    {
        $token = config('services.telegram.bot_token');
        if (empty($token)) {
            throw new \RuntimeException('Telegram bot token is not configured');
        }

        return new Nutgram($token);
    }

    protected function getOrCreateUser(UserService $userService): ?\App\Models\User
    {
        $user = $userService->findUserByTelegramId($this->telegramId);

        if (!$user) {
            $user = $userService->createUser($this->telegramId, $this->username, $this->name, $this->referrerId);
        }

        return $user;
    }

    protected function processReferrerBonus($user, UserService $userService, XuiService $xuiService): void
    {
        if (!$this->referrerId) {
            return;
        }

        $this->createReferral($user->id);

        $referrer = $userService->findUserById($this->referrerId);
        if (!$referrer || !$referrer->referral_tag) {
            return;
        }

        $this->extendReferrerSubscription($referrer, $xuiService, $user->id);
    }

    protected function createReferral(int $referredUserId): void
    {
        try {
            Referral::create([
                'user_id' => $this->referrerId,
                'referred_user_id' => $referredUserId,
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to create referral record', [
                'error' => $e->getMessage(),
                'referrer_id' => $this->referrerId,
                'referred_user_id' => $referredUserId,
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    protected function extendReferrerSubscription($referrer, XuiService $xuiService, int $userId): void
    {
        try {
            $tag = $referrer->referral_tag;
            $uuid = $referrer->uuid;

            $clientDataArray = Arr::get(
                $xuiService->getClientTrafficByUserUuid($tag, $uuid),
                'data',
                []
            );

            if (empty($clientDataArray)) {
                return;
            }

            $client = $clientDataArray[0];
            $this->addDaysToClient($client, $this->settingService->getInt('ref.bonus.duration'), $tag, $uuid, $xuiService);
        } catch (\Throwable $e) {
            Log::error('Failed to extend referrer subscription', [
                'error' => $e->getMessage(),
                'referrer_id' => $this->referrerId,
            ]);
        }
    }

    protected function addDaysToClient(array $client, int $days, string $tag, string $uuid, XuiService $xuiService): void
    {
        $msToAdd = $days * 24 * 60 * 60 * 1000;
        $oldExpiry = $client['expiryTime'] ?? 0;
        $client['expiryTime'] = ($oldExpiry ?? 0) + $msToAdd;
        $client['id'] = $uuid;
        $inboundId = Arr::get($client, 'inboundId');

        $xuiService->updateClient($tag, $inboundId, $uuid, $client);
    }

    protected function createUserSubscription($user, XuiService $xuiService): void
    {
        $trialTag = $this->settingService->getString('trial.tag');
        $trialDuration = (int) $this->settingService->getInt('trial.duration');
        $xuiModel = $xuiService->getXuiModelByTag($trialTag);
        $expiryTimeMs = MillisecondsHelper::addDaysInMillisecondsToNow($trialDuration);
        $inboundId = $xuiModel->inbound_id;
        $subscriptionName = Str::substr($user->uuid, 0, 6) . $user->id;
        $createResult = $xuiService->addClient($trialTag, $inboundId, [
            'id' => $user->uuid,
            'email' => $subscriptionName,
            'expiryTime' => $expiryTimeMs,
            'subId' => $user->uuid,
        ], $user->id);

        if (!$createResult['ok']) {
            throw new \RuntimeException('Не удалось создать конфигурацию: ' . ($createResult['message'] ?? 'Unknown error'));
        }
    }

    protected function sendInstructions(Nutgram $bot, VpnConnectionService $vpnConnectionService, XuiService $xuiService, $user): void
    {
        $trialTag = $this->settingService->getString('trial.tag');
        $userConfig = $xuiService->getSubLink($trialTag, $user->uuid);
        $userConfigImportLink = $xuiService->getSubLink($trialTag, $user->uuid, 'import');

        $instructionsKeyboard = InlineKeyboardMarkup::make()
            ->addRow(InlineKeyboardButton::make('🤖 Приложение для Android', url: 'https://play.google.com/store/apps/details?id=com.v2raytun.android&pcampaignid=web_share'))
            ->addRow(InlineKeyboardButton::make('🍎 Приложение для iPhone/iOS', url: 'https://apps.apple.com/ru/app/v2raytun/id6476628951'))
            ->addRow(InlineKeyboardButton::make('🖥️ Инструкция для Windows', url: 'https://telegra.ph/Instrukciya-po-ustanovke-V2raytun-na-PK--Windows-1011-01-02'))
            ->addRow(InlineKeyboardButton::make('📲 Перенести в приложение', url: $userConfigImportLink))
            ->addRow(InlineKeyboardButton::make('🏠 Вернуться в главное меню', callback_data: 'main_menu'));

        $vpnConnectionService->sendVpnConnectionMessagesToChat(
            $bot,
            $this->telegramId,
            $instructionsKeyboard,
            $userConfig
        );
    }
}
