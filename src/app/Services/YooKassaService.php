<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Models\Payment;
use Illuminate\Support\Arr;
use App\Clients\YooKassaClient;
use App\Services\TelegramService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use YooKassa\Model\Payment\PaymentStatus;

final class YooKassaService
{
    public function __construct(
        private readonly YooKassaClient $client,
        private readonly TelegramService $telegramService,
        private readonly XuiService $xuiService
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

        if ($status === Payment::STATUS_SUCCEEDED && $oldStatus !== Payment::STATUS_SUCCEEDED) {
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
        try {
            DB::beginTransaction();

            $user = $payment->user;
            $balance = $user->balance;

            if (!$balance) {
                $balance = $user->balance()->create(['balance' => 0]);
            }

            $balance->increment('balance', $payment->amount);

            Log::info('User balance updated after successful payment', [
                'payment_id' => $payment->id,
                'user_id' => $user->id,
                'amount' => $payment->amount,
                'new_balance' => $balance->fresh()->balance,
            ]);

            $tag = $payment->getVpnTag();
            $clientDataResponse = $this->xuiService->getClientTrafficByUserUuid($tag, $user->uuid);
            $clientDataArray = Arr::get($clientDataResponse, 'data');
            $client = $clientDataArray[0];
            Log::info('ClientData vefore update: ' . json_encode($client));
            $client['expiryTime'] += $payment->getDuration();
            $inbloundId = Arr::get($client, 'inboundId');
            $uuid = $user->uuid;
            Log::info('ClientData to update: ' . json_encode($client));
            $this->xuiService->updateClient($tag, $inbloundId, $uuid, $client);
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

            // Отправляем сообщение об успешном платеже
            $successMessage = sprintf(
                "✅ Платеж успешно завершен!\n\n💳 Сумма: %s ₽\n📝 Описание: %s\n\nСредства зачислены на ваш баланс.",
                number_format((float) $payment->amount),
                $payment->description
            );

            $bot->sendMessage($successMessage, chat_id: $user->tg_id);

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
}
