<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\XuiTag;
use App\Models\Referral;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use App\Services\XuiService;
use Illuminate\Bus\Queueable;
use SergiX44\Nutgram\Nutgram;
use App\Services\UserTagService;
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
        UserTagService $userTagService
    ): void {
        try {
            $token = config('services.telegram.bot_token');
            $bot = new Nutgram($token);
            if (empty($token)) {
                throw new \RuntimeException('Telegram bot token is not configured');
            }

            Log::info('ProcessAcceptTermsJob started', [
                'telegram_id' => $this->telegramId,
                'referrer_id' => $this->referrerId,
                'username' => $this->username,
                'name' => $this->name,
            ]);

            $user = $userService->findUserByTelegramId($this->telegramId);
            if (!$user) {
                Log::info('User not found, creating new user', [
                    'telegram_id' => $this->telegramId,
                    'referrer_id' => $this->referrerId,
                ]);
                
                $user = $userService->createUser($this->telegramId, $this->username, $this->name, $this->referrerId);

                Log::info('User created', [
                    'user_id' => $user?->id,
                    'telegram_id' => $this->telegramId,
                    'referrer_id' => $this->referrerId,
                ]);

                // Создаем запись в таблице referrals, если пользователь был приглашен
                if ($user && $this->referrerId) {
                    Log::info('Creating referral record', [
                        'referrer_id' => $this->referrerId,
                        'referred_user_id' => $user->id,
                    ]);
                    
                    try {
                        Referral::create([
                            'user_id' => $this->referrerId,
                            'referred_user_id' => $user->id,
                        ]);
                        
                        Log::info('Referral record created successfully', [
                            'referrer_id' => $this->referrerId,
                            'referred_user_id' => $user->id,
                        ]);
                    } catch (\Throwable $e) {
                        Log::error('Failed to create referral record', [
                            'error' => $e->getMessage(),
                            'referrer_id' => $this->referrerId,
                            'referred_user_id' => $user->id,
                            'trace' => $e->getTraceAsString(),
                        ]);
                    }

                    // Добавляем 2 дня к выбранной конфигурации реферера
                    $referrer = $userService->findUserById($this->referrerId);
                    Log::info('Looking for referrer', [
                        'referrer_id' => $this->referrerId,
                        'referrer_found' => $referrer !== null,
                        'referral_tag' => $referrer?->referral_tag,
                    ]);
                    
                    if ($referrer && $referrer->referral_tag) {
                        try {
                            $tag = $referrer->referral_tag;
                            $uuid = $referrer->uuid;
                            
                            Log::info('Adding 2 days to referrer subscription', [
                                'referrer_id' => $this->referrerId,
                                'tag' => $tag,
                                'uuid' => $uuid,
                            ]);
                            
                            $clientDataResponse = $xuiService->getClientTrafficByUserUuid($tag, $uuid);
                            $clientDataArray = Arr::get($clientDataResponse, 'data');

                            Log::info('Client data retrieved', [
                                'referrer_id' => $this->referrerId,
                                'tag' => $tag,
                                'data_count' => count($clientDataArray ?? []),
                            ]);

                            if (count($clientDataArray) > 0) {
                                $client = $clientDataArray[0];
                                $oldExpiryTime = $client['expiryTime'] ?? 0;
                                
                                // Добавляем 2 дня в миллисекундах
                                $twoDaysInMs = MillisecondsHelper::daysToMilliseconds(2);
                                $client['expiryTime'] += $twoDaysInMs;
                                $client['id'] = $uuid;
                                $inboundId = Arr::get($client, 'inboundId');
                                
                                Log::info('Updating referrer client', [
                                    'referrer_id' => $this->referrerId,
                                    'tag' => $tag,
                                    'inbound_id' => $inboundId,
                                    'old_expiry_time' => $oldExpiryTime,
                                    'new_expiry_time' => $client['expiryTime'],
                                    'days_added_ms' => $twoDaysInMs,
                                ]);
                                
                                $xuiService->updateClient($tag, $inboundId, $uuid, $client);
                                
                                Log::info('Successfully added 2 days to referrer subscription', [
                                    'referrer_id' => $this->referrerId,
                                    'tag' => $tag,
                                ]);
                            } else {
                                Log::warning('No client data found for referrer', [
                                    'referrer_id' => $this->referrerId,
                                    'tag' => $tag,
                                    'uuid' => $uuid,
                                ]);
                            }
                        } catch (\Throwable $e) {
                            Log::error('Ошибка при добавлении 2 дней рефереру', [
                                'referrer_id' => $this->referrerId,
                                'tag' => $referrer->referral_tag ?? null,
                                'error' => $e->getMessage(),
                                'trace' => $e->getTraceAsString(),
                            ]);
                        }
                    } else {
                        Log::warning('Referrer not found or no referral_tag set', [
                            'referrer_id' => $this->referrerId,
                            'referrer_found' => $referrer !== null,
                            'referral_tag' => $referrer?->referral_tag,
                        ]);
                    }
                } else {
                    Log::info('Skipping referral processing', [
                        'user_created' => $user !== null,
                        'referrer_id' => $this->referrerId,
                    ]);
                }
            } else {
                Log::info('User already exists, skipping creation', [
                    'user_id' => $user->id,
                    'telegram_id' => $this->telegramId,
                ]);
            }

            if (!$user) {
                $bot->sendMessage('❌ Ошибка создания пользователя', (string) $this->telegramId);
                return;
            }

            $xuiModel = $xuiService->getXuiModelByTag('NL');
            $expiryTimeMs = MillisecondsHelper::daysToMilliseconds(7);
            $inboundId = $xuiModel->inbound_id;
            $subscriptionName = Str::substr($user->uuid, 0, 6) . $user->id;

            $createResult = $xuiService->addClient('NL', $inboundId, [
                'id' => $user->uuid,
                'email' => $subscriptionName,
                'expiryTime' => $expiryTimeMs,
                'subId' => $user->uuid,
            ]);

            $xuiService->updateClient('NL', $inboundId,  $user->uuid, [
                'id' => $user->uuid,
                'email' => $subscriptionName,
                'expiryTime' => $expiryTimeMs,
                'subId' => $user->uuid,
            ]);

            $userTagService->addTagToUser($user->id, XuiTag::NL);

            if (!$createResult['ok']) {
                throw new \RuntimeException('Не удалось создать конфигурацию: ' . ($createResult['message'] ?? 'Unknown error'));
            }

            $userConfig = $xuiService->getSubLink('NL', $user->uuid);
            $userConfigImportLink = $xuiService->getSubLink('NL', $user->uuid, 'import');

            $instructionsKeyboard = InlineKeyboardMarkup::make()
                ->addRow(InlineKeyboardButton::make('🤖 Приложение для Android', url: 'https://play.google.com/store/apps/details?id=com.v2raytun.android&pcampaignid=web_share'),)
                ->addRow(InlineKeyboardButton::make('🍎 Приложение для iPhone/iOS', url: 'https://apps.apple.com/ru/app/v2raytun/id6476628951'))
                ->addRow(InlineKeyboardButton::make('🖥️ Инструкция для Windows', url: 'https://telegra.ph/Instrukciya-po-ustanovke-V2raytun-na-PK--Windows-1011-01-02'))
                ->addRow(InlineKeyboardButton::make('📲 Перенести в приложение', url: "$userConfigImportLink"))
                ->addRow(InlineKeyboardButton::make('🏠 Вернуться в главное меню', callback_data: 'main_menu'));

            $vpnConnectionService->sendVpnConnectionMessagesToChat(
                $bot,
                $this->telegramId,
                $instructionsKeyboard,
                $userConfig
            );
        } catch (\Throwable $e) {
            Log::error('Ошибка при обработке accept_terms в Job: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'telegram_id' => $this->telegramId,
                'chat_id' => $this->telegramId,
            ]);

            try {
                $bot->sendMessage('❌ Произошла ошибка при создании VPN конфигурации. Пожалуйста, попробуйте позже.', (string) $this->telegramId);
            } catch (\Throwable $sendError) {
                Log::error('Не удалось отправить сообщение об ошибке: ' . $sendError->getMessage());
            }

            throw $e;
        }
    }
}
