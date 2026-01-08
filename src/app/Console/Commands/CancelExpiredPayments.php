<?php

namespace App\Console\Commands;

use App\Models\Payment;
use Illuminate\Console\Command;
use App\Services\YooKassaService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use YooKassa\Model\Payment\PaymentStatus;

class CancelExpiredPayments extends Command
{
    protected $signature = 'payments:cancel-expired';
    protected $description = 'Cancel pending payments older than 10 minutes';

    public function __construct(
        private readonly YooKassaService $yooKassaService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $payments = DB::transaction(function () {
            $payments = Payment::expiredPending()
                ->lockForUpdate()
                ->get();

            foreach ($payments as $payment) {
                $payment->update([
                    'status' => Payment::STATUS_CANCELING,
                ]);
            }

            return $payments;
        });

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
