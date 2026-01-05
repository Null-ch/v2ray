<?php

declare(strict_types=1);

namespace App\Clients;

use YooKassa\Client;
use Illuminate\Support\Facades\Log;
use YooKassa\Model\Payment\PaymentInterface;
use YooKassa\Request\Payments\CreateCaptureRequest;
use YooKassa\Request\Payments\CreatePaymentRequest;
use YooKassa\Request\Payments\Payment\CreateCancelRequest;

final class YooKassaClient
{
    private Client $client;

    public function __construct(string $shopId, string $secretKey)
    {
        $this->client = new Client();
        $this->client->setAuth($shopId, $secretKey);
    }

    /**
     * Создает платеж в YooKassa и возвращает PaymentInterface
     *
     * @param float $amount
     * @param string $description
     * @param array $metadata
     * @param string|null $returnUrl
     * @return PaymentInterface
     * @throws \RuntimeException
     */
    public function createPayment(
        float $amount,
        string $description,
        array $metadata = [],
        ?string $returnUrl = null
    ): PaymentInterface {
        try {
            $builder = CreatePaymentRequest::builder();

            // Устанавливаем сумму и валюту через Amount
            $builder->setAmount([
                'value' => number_format($amount, 2, '.', ''),
                'currency' => 'RUB',
            ]);

            // Метаданные (можно передать id пользователя, заказа и т.д.)
            $builder->setMetadata($metadata);

            // Подтверждение через редирект
            $builder->setConfirmation([
                'type' => 'redirect',
                'return_url' => $returnUrl ?? route('payment.return'),
            ]);

            $builder->setCapture(true); // Автозахват

            // В SDK 3.x description передаётся как поле в массиве параметров
            $builder->setDescription($description); // ❌ вот этого больше нет

            // Строим запрос
            $request = $builder->build();

            // Метод createPayment сразу возвращает PaymentInterface
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
     * Получает платеж по ID
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
     * @param float|null $amount
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
     * @param float|null $amount
     * @return PaymentInterface|null
     */
    public function cancelPayment(string $paymentId, ?float $amount = null): ?PaymentInterface
    {
        try {
            // SDK 3.x позволяет просто вызвать cancelPayment на клиенте
            $payment = $this->client->cancelPayment($paymentId, $amount);

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
