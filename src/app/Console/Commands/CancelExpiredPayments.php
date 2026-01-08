<?php

namespace App\Console\Commands;

use App\Models\Payment;
use Illuminate\Console\Command;
use App\Services\TelegramService;
use App\Services\YooKassaService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use YooKassa\Model\Payment\PaymentStatus;

class CancelExpiredPayments extends Command
{
    protected $signature = 'payments:cancel-expired';
    protected $description = 'Cancel pending payments older than 10 minutes';

    public function __construct(
        private readonly YooKassaService $yooKassaService,
        private readonly TelegramService $telegramService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $bot = $this->telegramService->getBot();
        $payments = Payment::expiredPending()->with('user')->get();

        foreach ($payments as $payment) {
            try {
                if ($payment->yookassa_payment_id) {
                    $this->yooKassaService->cancelPayment(
                        $payment->yookassa_payment_id
                    );
                }

                $payment->update([
                    'status' => Payment::STATUS_CANCELED,
                    'processed_at' => now(),
                    'yookassa_status' => PaymentStatus::CANCELED,
                ]);

                $paymentId = $payment->getProviderPaimentChargeId() ?? $payment->getTelegramPaimentChargeId();
                $description = $payment->getDescription();
                $amount = $payment->getAmount();
                $message = "Платеж (ID:{$paymentId}). Описание: {$description}. Сумма: {$amount} отменен ✅";
                $chatId = $payment->user->getTgId();
                $msgId = $payment->getTelegramMessageId();

                if (!empty($msgId)) {
                    $bot->deleteMessage($chatId, $msgId);
                }

                $bot->sendMessage(text: $message, chat_id: $chatId);
            } catch (\Throwable $e) {
                Log::error('Failed to cancel payment in YooKassa', [
                    'payment_id' => $payment->id,
                    'error' => $e->getMessage(),
                ]);

                $payment->update([
                    'status' => Payment::STATUS_PENDING,
                ]);
            }
        }

        return Command::SUCCESS;
    }
}
