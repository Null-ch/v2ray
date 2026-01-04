<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\User\UserService;
use App\Services\VpnConnectionService;
use App\Services\XuiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

final class ProcessAcceptTermsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private readonly int $telegramId,
        private readonly ?string $username,
        private readonly ?string $name,
        private readonly string $chatId
    ) {}

    public function handle(
        UserService $userService,
        XuiService $xuiService,
        VpnConnectionService $vpnConnectionService
    ): void {
        try {
            // Создаем экземпляр бота для отправки сообщений
            $token = config('services.telegram.bot_token');
            if (empty($token)) {
                throw new \RuntimeException('Telegram bot token is not configured');
            }
            $bot = new Nutgram($token);

            // Создаем пользователя в БД, если его нет
            $user = $userService->findUserByTelegramId($this->telegramId);
            if (!$user) {
                $user = $userService->createUser($this->telegramId, $this->username, $this->name);
            }

            if (!$user) {
                $bot->sendMessage($this->chatId, '❌ Ошибка создания пользователя');
                return;
            }

            // Получаем модель Xui
            $xuiModel = $xuiService->getXuiModelByTag('NL');

            // Создаем конфигурацию на 7 дней
            // Текущее время в миллисекундах
            $nowMs = round(microtime(true) * 1000);

            // Добавляем 7 дней в миллисекундах
            $expiryTimeMs = $nowMs + 7 * 24 * 60 * 60 * 1000;
            $inboundId = $xuiModel->inbound_id;

            $createResult = $xuiService->addClient('NL', $inboundId, [
                'id' => $user->uuid,
                'email' => $user->id,
                'expiryTime' => $expiryTimeMs,
                'subId' => $user->uuid,
            ]);

            if (!$createResult['ok']) {
                throw new \RuntimeException('Не удалось создать конфигурацию: ' . ($createResult['message'] ?? 'Unknown error'));
            }

            $userConfig = $xuiService->getSubLink('NL', $user->uuid);

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
                    InlineKeyboardButton::make('📲 Перенести в приложение', callback_data: 'export_config')
                )
                ->addRow(
                    InlineKeyboardButton::make('Вернуться в главное меню', callback_data: 'main_menu')
                );

            // Отправляем пользователю сообщения с VPN
            $messageIds = $vpnConnectionService->sendVpnConnectionMessagesToChat(
                $bot,
                $this->chatId,
                $instructionsKeyboard,
                $userConfig
            );

            // Сохраняем ID сообщений (опционально, если нужно)
            // Можно использовать кеш или БД для хранения messageIds по chatId
        } catch (\Throwable $e) {
            Log::error('Ошибка при обработке accept_terms в Job: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'telegram_id' => $this->telegramId,
                'chat_id' => $this->chatId,
            ]);

            // Отправляем ошибку пользователю
            try {
                $token = config('services.telegram.bot_token');
                if (!empty($token)) {
                    $bot = new Nutgram($token);
                    $bot->sendMessage($this->chatId, '❌ Произошла ошибка при создании VPN конфигурации. Пожалуйста, попробуйте позже.');
                }
            } catch (\Throwable $sendError) {
                Log::error('Не удалось отправить сообщение об ошибке: ' . $sendError->getMessage());
            }

            throw $e;
        }
    }
}

