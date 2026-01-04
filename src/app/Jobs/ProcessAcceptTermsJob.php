<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\XuiTag;
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

            $user = $userService->findUserByTelegramId($this->telegramId);
            if (!$user) {
                $user = $userService->createUser($this->telegramId, $this->username, $this->name);
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

            // Создаем клавиатуру с инструкциями
            $instructionsKeyboard = InlineKeyboardMarkup::make()
                ->addRow(
                    InlineKeyboardButton::make('Приложение для Android', url: 'https://play.google.com/store/apps/details?id=com.v2raytun.android&pcampaignid=web_share')
                )
                ->addRow(
                    InlineKeyboardButton::make('Приложение для iPhone/iOS', url: 'https://apps.apple.com/ru/app/v2raytun/id6476628951')
                )
                ->addRow(
                    InlineKeyboardButton::make('Инструкция для Windows', url: 'https://telegra.ph/Instrukciya-po-ustanovke-V2raytun-na-PK--Windows-1011-01-02')
                )
                ->addRow(
                    InlineKeyboardButton::make('📲 Перенести в приложение', url: "$userConfigImportLink")
                )
                ->addRow(
                    InlineKeyboardButton::make('Вернуться в главное меню', callback_data: 'main_menu')
                );

            // Отправляем пользователю сообщения с VPN
            $vpnConnectionService->sendVpnConnectionMessagesToChat(
                $bot,
                $this->telegramId,
                $instructionsKeyboard,
                $userConfig
            );

            // Сохраняем ID сообщений (опционально, если нужно)
            // Можно использовать кеш или БД для хранения messageIds по telegramId
        } catch (\Throwable $e) {
            Log::error('Ошибка при обработке accept_terms в Job: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'telegram_id' => $this->telegramId,
                'chat_id' => $this->telegramId,
            ]);

            // Отправляем ошибку пользователю
            try {
                $bot->sendMessage('❌ Произошла ошибка при создании VPN конфигурации. Пожалуйста, попробуйте позже.', (string) $this->telegramId);
            } catch (\Throwable $sendError) {
                Log::error('Не удалось отправить сообщение об ошибке: ' . $sendError->getMessage());
            }

            throw $e;
        }
    }
}
