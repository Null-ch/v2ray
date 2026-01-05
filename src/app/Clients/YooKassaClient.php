<?php

declare(strict_types=1);

namespace App\Clients;

use YooKassa\Client;
use YooKassa\Model\PaymentInterface;
use YooKassa\Model\PaymentStatus;
use YooKassa\Request\Payments\CreatePaymentRequest;
use YooKassa\Request\Payments\CreatePaymentRequestBuilder;
use YooKassa\Request\Payments\Payment\CreateCaptureRequest;
use YooKassa\Request\Payments\Payment\CreateCancelRequest;
use Illuminate\Support\Facades\Log;

final class YooKassaClient
{
    private Client $client;

    public function __construct(string $shopId, string $secretKey)
    {
        $this->client = new Client();
        $this->client->setAuth($shopId, $secretKey);
    }

    /**
     * Создает платеж
     *
     * @param float $amount Сумма платежа
     * @param string $description Описание платежа
     * @param array $metadata Метаданные платежа
     * @param string|null $returnUrl URL для возврата после оплаты
     * @return PaymentInterface
     * @throws \Exception
     */
    public function createPayment(
        float $amount,
        string $description,
        array $metadata = [],
        ?string $returnUrl = null
    ): PaymentInterface {
        try {
            $builder = CreatePaymentRequest::builder();
            $builder->setAmount($amount)
                ->setCurrency('RUB')
                ->setDescription($description)
                ->setMetadata($metadata);

            if ($returnUrl) {
                $builder->setConfirmation([
                    'type' => 'redirect',
                    'return_url' => $returnUrl,
                ]);
            } else {
                $builder->setConfirmation([
                    'type' => 'redirect',
                    'return_url' => route('payment.return'),
                ]);
            }

            $request = $builder->build();
            $payment = $this->client->createPayment($request);

            Log::info('YooKassa payment created', [
                'payment_id' => $payment->getId(),
                'amount' => $amount,
                'status' => $payment->getStatus(),
            ]);

            return $payment;
        } catch (\Exception $e) {
            Log::error('YooKassa payment creation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new \RuntimeException('Failed to create payment: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Получает информацию о платеже
     *
     * @param string $paymentId
     * @return PaymentInterface|null
     */
    public function getPayment(string $paymentId): ?PaymentInterface
    {
        try {
            $payment = $this->client->getPaymentInfo($paymentId);

            Log::info('YooKassa payment retrieved', [
                'payment_id' => $paymentId,
                'status' => $payment->getStatus(),
            ]);

            return $payment;
        } catch (\Exception $e) {
            Log::error('YooKassa payment retrieval failed', [
                'payment_id' => $paymentId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Подтверждает платеж (capture)
     *
     * @param string $paymentId
     * @param float|null $amount Сумма для подтверждения (если null - подтверждается вся сумма)
     * @return PaymentInterface|null
     */
    public function capturePayment(string $paymentId, ?float $amount = null): ?PaymentInterface
    {
        try {
            $builder = CreateCaptureRequest::builder();
            
            if ($amount !== null) {
                $builder->setAmount($amount);
            }

            $request = $builder->build();
            $payment = $this->client->capturePayment($request, $paymentId);

            Log::info('YooKassa payment captured', [
                'payment_id' => $paymentId,
                'amount' => $amount,
                'status' => $payment->getStatus(),
            ]);

            return $payment;
        } catch (\Exception $e) {
            Log::error('YooKassa payment capture failed', [
                'payment_id' => $paymentId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Отменяет платеж
     *
     * @param string $paymentId
     * @return PaymentInterface|null
     */
    public function cancelPayment(string $paymentId): ?PaymentInterface
    {
        try {
            $builder = CreateCancelRequest::builder();
            $request = $builder->build();
            $payment = $this->client->cancelPayment($request, $paymentId);

            Log::info('YooKassa payment cancelled', [
                'payment_id' => $paymentId,
                'status' => $payment->getStatus(),
            ]);

            return $payment;
        } catch (\Exception $e) {
            Log::error('YooKassa payment cancellation failed', [
                'payment_id' => $paymentId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}

