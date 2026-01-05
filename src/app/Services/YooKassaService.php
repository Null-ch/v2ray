<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Models\Payment;
use App\Enums\XuiTag;
use App\Helpers\MillisecondsHelper;
use Illuminate\Support\Arr;
use App\Clients\YooKassaClient;
use App\Services\TelegramService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;
use SergiX44\Nutgram\Nutgram;
use YooKassa\Model\Payment\PaymentStatus;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

final class YooKassaService
{
    public function __construct(
        private readonly YooKassaClient $client,
        private readonly TelegramService $telegramService,
        private readonly XuiService $xuiService,
        private readonly UserTagService $userTagService,
    ) {}

    /**
     * Создает платеж для пользователя
     *
     * @param User $user
     * @param float $amount
     * @param string $description
     * @param array $metadata
     * @return Payment
     * @throws \Exception
     */
    public function createPayment(
        User $user,
        float $amount,
        string $description,
        array $metadata = []
    ): Payment {
        try {
            DB::beginTransaction();

            // Создаем запись о платеже в БД
            $payment = Payment::create([
                'user_id' => $user->id,
                'amount' => $amount,
                'description' => $description,
                'status' => Payment::STATUS_PENDING,
                'metadata' => $metadata,
            ]);

            // Создаем платеж в YooKassa
            $yooKassaPayment = $this->client->createPayment(
                $amount,
                $description,
                array_merge($metadata, [
                    'payment_id' => $payment->id,
                    'user_id' => $user->id,
                ]),
                route('payment.return', ['payment' => $payment->id])
            );

            // Обновляем запись с данными из YooKassa
            $payment->update([
                'yookassa_payment_id' => $yooKassaPayment->getId(),
                'yookassa_status' => $yooKassaPayment->getStatus(),
                'confirmation_url' => $yooKassaPayment->getConfirmation()?->getConfirmationUrl(),
            ]);

            DB::commit();

            Log::info('Payment created successfully', [
                'payment_id' => $payment->id,
                'yookassa_payment_id' => $yooKassaPayment->getId(),
                'user_id' => $user->id,
            ]);

            return $payment->fresh();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create payment', [
                'user_id' => $user->id,
                'amount' => $amount,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Обновляет статус платежа из YooKassa
     *
     * @param Payment $payment
     * @return Payment
     */
    public function updatePaymentStatus(Payment $payment): Payment
    {
        if (!$payment->yookassa_payment_id) {
            Log::warning('Payment has no YooKassa payment ID', [
                'payment_id' => $payment->id,
            ]);

            return $payment;
        }

        $yooKassaPayment = $this->client->getPayment($payment->yookassa_payment_id);

        if (!$yooKassaPayment) {
            Log::warning('Failed to retrieve payment from YooKassa', [
                'payment_id' => $payment->id,
                'yookassa_payment_id' => $payment->yookassa_payment_id,
            ]);

            return $payment;
        }

        $yooKassaStatus = $yooKassaPayment->getStatus();
        $status = $this->mapYooKassaStatus($yooKassaStatus);
        $oldStatus = $payment->status;

        $payment->update([
            'yookassa_status' => $yooKassaStatus,
            'status' => $status,
        ]);

        // Обрабатываем успешный платеж только если он еще не был обработан
        if ($status === Payment::STATUS_SUCCEEDED && $oldStatus !== Payment::STATUS_SUCCEEDED && !$payment->processed_at) {
            $this->processSuccessfulPayment($payment);
        }

        Log::info('Payment status updated', [
            'payment_id' => $payment->id,
            'old_status' => $oldStatus,
            'new_status' => $status,
            'yookassa_status' => $yooKassaStatus,
        ]);

        return $payment->fresh();
    }

    /**
     * Обрабатывает успешный платеж
     *
     * @param Payment $payment
     * @return void
     */
    private function processSuccessfulPayment(Payment $payment): void
    {
        // Дополнительная проверка на случай параллельных запросов
        // Используем блокировку строки для предотвращения повторной обработки
        $paymentId = $payment->id;
        $payment = Payment::where('id', $paymentId)
            ->whereNull('processed_at')
            ->lockForUpdate()
            ->first();

        if (!$payment) {
            Log::info('Payment already processed, skipping', [
                'payment_id' => $paymentId,
            ]);
            return;
        }

        try {
            DB::beginTransaction();

            $user = $payment->user;
            $balance = $user->balance;

            if (!$balance) {
                $balance = $user->balance()->create(['balance' => 0]);
            }

            $balance->increment('balance', $payment->amount);
            $balance->fresh()->balance;

            $tag = $payment->getVpnTag();
            $uuid = $user->uuid;
            $clientDataResponse = $this->xuiService->getClientTrafficByUserUuid($tag, $user->uuid);
            $clientDataArray = Arr::get($clientDataResponse, 'data');

            if (count($clientDataArray) > 0) {
                $client = $clientDataArray[0];
                $client['expiryTime'] += $payment->getDuration();
                $client['id'] = $uuid;
                $inbloundId = Arr::get($client, 'inboundId');
                $this->xuiService->updateClient($tag, $inbloundId, $uuid, $client);
            } else {
                $client = [
                    "id" => $uuid,
                    "email" => $user->getVpnEmail(),
                    "subId" => $uuid,
                    "expiryTime" => MillisecondsHelper::getNowInMilliseconds() + $payment->getDuration(),
                ];

                $xuiModel = $this->xuiService->getXuiModelByTag($tag);
                $this->xuiService->addClient($tag, $xuiModel->inbound_id, $client);
                $this->userTagService->addTagToUser($user->id, XuiTag::from($tag));
            }

            $payment->update(['processed_at' => now()]);
            DB::commit();
            $this->notifyPaymentSuccess($payment);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to process successful payment', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Отправляет уведомление об успешном платеже в Telegram
     *
     * @param Payment $payment
     * @return void
     */
    private function notifyPaymentSuccess(Payment $payment): void
    {
        // Проверяем, не было ли уже отправлено уведомление
        // Используем метаданные для отслеживания отправки уведомления
        $metadata = $payment->metadata ?? [];
        if (isset($metadata['notification_sent']) && $metadata['notification_sent'] === true) {
            Log::info('Payment success notification already sent, skipping', [
                'payment_id' => $payment->id,
            ]);
            return;
        }

        try {
            $user = $payment->user;
            if (!$user->tg_id) {
                Log::debug('User has no telegram ID for payment notification', [
                    'user_id' => $user->id,
                    'payment_id' => $payment->id,
                ]);
                return;
            }

            $bot = $this->telegramService->getBot();

            // Удаляем сообщение с кнопкой оплаты, если оно есть
            if ($payment->telegram_message_id) {
                try {
                    $bot->deleteMessage($user->tg_id, $payment->telegram_message_id);
                } catch (\Throwable $e) {
                    Log::debug('Failed to delete payment message', [
                        'message_id' => $payment->telegram_message_id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Удаляем предыдущие сообщения с клавиатурами
            $messageIds = $bot->getGlobalData('vpn_message_ids', []);
            $this->clearChat($messageIds, $bot, $user->tg_id);

            // Получаем информацию о подписке, если есть тег VPN
            $vpnTag = $payment->getVpnTag();
            $totalDays = null;
            $daysWord = null;

            if ($vpnTag) {
                try {
                    $clientDataResponse = $this->xuiService->getClientTrafficByUserUuid($vpnTag, $user->uuid);
                    $clientDataArray = Arr::get($clientDataResponse, 'data');
                    $expiryTimeMs = Arr::get($clientDataArray, '0.expiryTime');

                    if ($expiryTimeMs) {
                        $expiryTime = MillisecondsHelper::millisecondsToDaysHours($expiryTimeMs);
                        $totalDays = Arr::get($expiryTime, 'days', 0);
                        if ($totalDays > 0) {
                            $daysWord = $this->getDaysWord($totalDays);
                        }
                    }
                } catch (\Throwable $e) {
                    Log::debug('Failed to get subscription info for payment notification', [
                        'payment_id' => $payment->id,
                        'vpn_tag' => $vpnTag,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Формируем сообщение с помощью blade шаблона
            $successMessage = View::make('telegram.payment-success', [
                'amount' => $payment->amount,
                'description' => $payment->description,
                'totalDays' => $totalDays,
                'daysWord' => $daysWord,
            ])->render();

            $keyboard = InlineKeyboardMarkup::make();

            // Добавляем кнопку "Перенести в приложение" если есть тег VPN
            if ($vpnTag) {
                $userConfigImportLink = $this->xuiService->getSubLink($vpnTag, $user->uuid, 'import');
                $keyboard->addRow(InlineKeyboardButton::make('📲 Перенести в приложение', url: $userConfigImportLink));
            }

            $keyboard->addRow(InlineKeyboardButton::make('🏠 Главное меню', callback_data: 'main_menu'));
            $sentMessage = $bot->sendMessage(trim($successMessage), chat_id: $user->tg_id, reply_markup: $keyboard);

            // Сохраняем ID нового сообщения
            if ($sentMessage && $sentMessage->message_id) {
                $bot->setGlobalData('vpn_message_ids', [$sentMessage->message_id]);
            }

            // Помечаем в метаданных, что уведомление отправлено
            $metadata['notification_sent'] = true;
            $payment->update(['metadata' => $metadata]);

            Log::info('Payment success notification sent to Telegram', [
                'payment_id' => $payment->id,
                'user_id' => $user->id,
                'telegram_id' => $user->tg_id,
            ]);
        } catch (\Throwable $e) {
            // Не прерываем процесс, если не удалось отправить уведомление
            Log::error('Failed to send payment success notification to Telegram', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Маппит статус YooKassa в статус платежа в БД
     *
     * @param string $yooKassaStatus
     * @return string
     */
    private function mapYooKassaStatus(string $yooKassaStatus): string
    {
        return match ($yooKassaStatus) {
            PaymentStatus::PENDING => Payment::STATUS_PENDING,
            PaymentStatus::WAITING_FOR_CAPTURE => Payment::STATUS_PENDING,
            PaymentStatus::SUCCEEDED => Payment::STATUS_SUCCEEDED,
            PaymentStatus::CANCELED => Payment::STATUS_CANCELED,
            default => Payment::STATUS_PENDING,
        };
    }

    /**
     * Обрабатывает webhook от YooKassa
     *
     * @param array $data
     * @return Payment|null
     */
    public function handleWebhook(array $data): ?Payment
    {
        Log::info('Payment Data from webhook: ' . json_encode($data));
        $event = $data['event'] ?? null;
        $paymentData = $data['object'] ?? null;

        if ($event !== 'payment.succeeded' && $event !== 'payment.canceled' && $event !== 'payment.waiting_for_capture') {
            Log::info('Unhandled webhook event', ['event' => $event]);
            return null;
        }

        if (!$paymentData || !isset($paymentData['id'])) {
            Log::warning('Invalid webhook data', ['data' => $data]);
            return null;
        }

        $yooKassaPaymentId = $paymentData['id'];
        $payment = Payment::where('yookassa_payment_id', $yooKassaPaymentId)->first();

        if (!$payment) {
            Log::warning('Payment not found for webhook', [
                'yookassa_payment_id' => $yooKassaPaymentId,
            ]);
            return null;
        }

        return $this->updatePaymentStatus($payment);
    }

    /**
     * Удаляет сообщения из чата
     *
     * @param array $messageIds
     * @param Nutgram $bot
     * @param int|string $chatId
     * @return void
     */
    private function clearChat(array $messageIds, Nutgram $bot, int|string $chatId): void
    {
        foreach ($messageIds as $messageId) {
            try {
                $bot->deleteMessage($chatId, $messageId);
            } catch (\Throwable $e) {
                // Игнорируем ошибки удаления
            }
        }
    }

    /**
     * Получает правильное склонение слова "день"
     *
     * @param int $days
     * @return string
     */
    private function getDaysWord(int $days): string
    {
        $cases = [2, 0, 1, 1, 1, 2];
        $titles = ['день', 'дня', 'дней'];

        return $titles[($days % 100 > 4 && $days % 100 < 20) ? 2 : $cases[min($days % 10, 5)]];
    }
}
