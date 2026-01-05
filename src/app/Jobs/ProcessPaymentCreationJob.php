<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Pricing;
use App\Enums\XuiTag;
use App\Enums\Callback;
use Illuminate\Bus\Queueable;
use SergiX44\Nutgram\Nutgram;
use App\Services\YooKassaService;
use App\Services\User\UserService;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

final class ProcessPaymentCreationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private readonly int $telegramId,
        private readonly string $pricingId,
        private readonly string $code,
    ) {}

    public function handle(
        UserService $userService,
        YooKassaService $yooKassaService,
    ): void {
        try {
            $token = config('services.telegram.bot_token');
            $bot = new Nutgram($token);
            
            if (empty($token)) {
                throw new \RuntimeException('Telegram bot token is not configured');
            }

            $user = $userService->findUserByTelegramId($this->telegramId);

            if (!$user) {
                $bot->sendMessage('❌ Пользователь не найден', (string) $this->telegramId);
                return;
            }

            // Защита от повторных нажатий: проверяем наличие активного pending платежа
            $activePayment = \App\Models\Payment::where('user_id', $user->id)
                ->where('status', \App\Models\Payment::STATUS_PENDING)
                ->latest()
                ->first();

            if ($activePayment) {
                $bot->sendMessage('⚠️ У вас уже есть активный платеж. Дождитесь завершения оплаты.', (string) $this->telegramId);
                return;
            }

            try {
                $tag = XuiTag::from($this->code);
            } catch (\ValueError) {
                $bot->sendMessage('❌ Неизвестный VPN', (string) $this->telegramId);
                return;
            }

            $pricing = Pricing::find($this->pricingId);

            if (!$pricing) {
                $bot->sendMessage('❌ Тарифный план не найден', (string) $this->telegramId);
                return;
            }

            $description = sprintf(
                'Оплата VPN %s: %s',
                $tag->labelWithFlag(),
                $pricing->title,
            );

            $metadata = [
                'pricing_id' => $pricing->id,
                'vpn_tag' => $this->code,
                'duration' => $pricing->duration,
            ];

            $payment = $yooKassaService->createPayment(
                $user,
                (float)$pricing->price,
                $description,
                $metadata
            );

            if ($payment->confirmation_url) {
                // Удаляем предыдущие сообщения с клавиатурами
                $messageIds = $bot->getGlobalData('vpn_message_ids', []);
                $this->clearChat($messageIds, $bot, (string) $this->telegramId);

                $keyboard = InlineKeyboardMarkup::make()
                    ->addRow(InlineKeyboardButton::make('💳 Перейти к оплате', url: $payment->confirmation_url))
                    ->addRow(InlineKeyboardButton::make('🏠 Главное меню', callback_data: 'main_menu'));

                $message = sprintf(
                    "💳 Создан платеж на сумму %s ₽\n\n%s\n\nНажмите кнопку ниже для перехода к оплате:",
                    number_format((float) $pricing->price, 2, '.', ' '),
                    $description
                );

                $sentMessage = $bot->sendMessage($message, chat_id: (string) $this->telegramId, reply_markup: $keyboard);

                // Сохраняем ID сообщения с кнопкой оплаты в платеже
                if ($sentMessage && $sentMessage->message_id) {
                    $payment->update(['telegram_message_id' => $sentMessage->message_id]);
                    // Сохраняем ID в global data для последующего удаления
                    $bot->setGlobalData('vpn_message_ids', [$sentMessage->message_id]);
                }
            } else {
                $bot->sendMessage('❌ Ошибка при создании платежа', (string) $this->telegramId);
                Log::error('Payment created but no confirmation URL', [
                    'payment_id' => $payment->id,
                    'user_id' => $user->id,
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('Ошибка при создании платежа в Job: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'telegram_id' => $this->telegramId,
                'pricing_id' => $this->pricingId,
                'code' => $this->code,
            ]);

            try {
                $token = config('services.telegram.bot_token');
                $bot = new Nutgram($token);
                $bot->sendMessage('❌ Произошла ошибка при создании платежа. Пожалуйста, попробуйте позже.', (string) $this->telegramId);
            } catch (\Throwable $sendError) {
                Log::error('Не удалось отправить сообщение об ошибке: ' . $sendError->getMessage());
            }

            throw $e;
        }
    }

    /**
     * Удаляет сообщения из чата
     *
     * @param array $messageIds
     * @param Nutgram $bot
     * @param string $chatId
     * @return void
     */
    private function clearChat(array $messageIds, Nutgram $bot, string $chatId): void
    {
        foreach ($messageIds as $messageId) {
            try {
                $bot->deleteMessage($chatId, $messageId);
            } catch (\Throwable $e) {
                // Игнорируем ошибки удаления
            }
        }
    }
}

