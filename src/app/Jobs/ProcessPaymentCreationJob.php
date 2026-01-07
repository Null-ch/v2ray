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
use App\Services\SettingService;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;
use SergiX44\Nutgram\Telegram\Types\Payment\LabeledPrice;
use App\Models\Payment;

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
        SettingService $settingService,
        YooKassaService $yooKassaService,
    ): void {
        /** @var Payment|null $createdTelegramInvoicePayment */
        $createdTelegramInvoicePayment = null;
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
                // Если это "зависший" локальный платеж (invoice не успели отправить), авто-отменяем и продолжаем
                $isStaleLocal = !$activePayment->yookassa_payment_id
                    && !$activePayment->telegram_message_id
                    && $activePayment->created_at !== null
                    && $activePayment->created_at->lt(now()->subMinutes(5));

                if ($isStaleLocal) {
                    $activePayment->update([
                        'status' => \App\Models\Payment::STATUS_CANCELED,
                        'yookassa_status' => 'canceled',
                    ]);
                    $activePayment = null;
                }
            }

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

            $useTelegramInvoice = (bool)$settingService->getBool('payments.use_telegram_invoice', true)
                && !empty(config('services.telegram.provider_token'));

            if ($useTelegramInvoice) {
                // Создаем локальную запись платежа со статусом pending
                $payment = Payment::create([
                    'user_id' => $user->id,
                    'amount' => (float)$pricing->price,
                    'description' => $description,
                    'status' => Payment::STATUS_PENDING,
                    'metadata' => $metadata,
                ]);
                $createdTelegramInvoicePayment = $payment;

                // Удаляем предыдущие сообщения
                $messageIds = $bot->getGlobalData('vpn_message_ids', []);
                $this->clearChat($messageIds, $bot, (string)$this->telegramId);

                // Конвертируем цену в копейки (минимальные единицы валюты RUB)
                // Используем точную арифметику для избежания проблем с плавающей точкой
                $priceStr = (string)$pricing->price;
                $priceParts = explode('.', $priceStr);
                $rubles = (int)($priceParts[0] ?? 0);
                $kopecks = 0;
                
                if (isset($priceParts[1])) {
                    $decimalPart = substr($priceParts[1] . '00', 0, 2);
                    $kopecks = (int)$decimalPart;
                }
                
                $amountMinor = $rubles * 100 + $kopecks;
                
                // Telegram требует минимум 1 единицу минимальной валюты (1 копейка = 0.01 RUB)
                // Также проверяем, что сумма не превышает максимальное значение для int32
                if ($amountMinor < 1) {
                    throw new \InvalidArgumentException("Сумма платежа слишком мала. Минимальная сумма: 0.01 RUB");
                }
                
                if ($amountMinor > 2147483647) {
                    throw new \InvalidArgumentException("Сумма платежа слишком велика");
                }
                
                Log::info('Telegram invoice amount', [
                    'price' => $pricing->price,
                    'price_string' => $priceStr,
                    'rubles' => $rubles,
                    'kopecks' => $kopecks,
                    'amount_minor' => $amountMinor,
                ]);
                $invoiceKeyboard = InlineKeyboardMarkup::make()
                    // Pay-кнопка должна быть первой в первой строке
                    ->addRow(
                        InlineKeyboardButton::make('💳 Заплатить', pay: true),
                        InlineKeyboardButton::make('❌ Отменить платеж', callback_data: "payment:cancel:{$payment->id}")
                    )
                    ->addRow(InlineKeyboardButton::make('🏠 Главное меню', callback_data: 'main_menu'));

                $message = $bot->sendInvoice(
                    chat_id: (string)$this->telegramId,
                    title: "Оплата подписки CapyVPN {$tag->labelWithFlag()}",
                    description: $description,
                    payload: "payment:{$payment->id}",
                    provider_token: (string)config('services.telegram.provider_token'),
                    currency: 'RUB',
                    prices: [LabeledPrice::make('К оплате', $amountMinor)],
                    start_parameter: 'vpn_payment',
                    is_flexible: false,
                    reply_markup: $invoiceKeyboard,
                );

                if ($message && $message->message_id) {
                    $payment->update(['telegram_message_id' => $message->message_id]);
                    $bot->setGlobalData('vpn_message_ids', [$message->message_id]);
                }
            } else {
                // Старый сценарий: создаем платеж YooKassa и отправляем ссылку
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

                    $msgText = sprintf(
                        "💳 Создан платеж на сумму %s ₽\n\n%s\n\nНажмите кнопку ниже для перехода к оплате:",
                        number_format((float) $pricing->price, 2, '.', ' '),
                        $description
                    );

                    $sentMessage = $bot->sendMessage($msgText, chat_id: (string) $this->telegramId, reply_markup: $keyboard);

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
            }
        } catch (\Throwable $e) {
            // Если упали после создания локального платежа для invoice — не оставляем pending
            try {
                if ($createdTelegramInvoicePayment instanceof Payment && $createdTelegramInvoicePayment->isPending()) {
                    $metadata = $createdTelegramInvoicePayment->metadata ?? [];
                    $metadata['invoice_error'] = $e->getMessage();
                    $createdTelegramInvoicePayment->update([
                        'status' => Payment::STATUS_CANCELED,
                        'yookassa_status' => 'canceled',
                        'metadata' => $metadata,
                    ]);
                }
            } catch (\Throwable) {
                // игнорируем вторичные ошибки
            }

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

