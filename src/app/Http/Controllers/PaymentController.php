<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Payment;
use Illuminate\View\View;
use Illuminate\Http\Request;
use App\Services\YooKassaService;
use App\Http\Resources\FailResource;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\RedirectResponse;
use App\Http\Resources\SuccessResource;
use App\Http\Requests\PaymentCreateRequest;
use Illuminate\Http\Resources\Json\JsonResource;

final class PaymentController
{
    public function __construct(
        private readonly YooKassaService $yooKassaService
    ) {}

    /**
     * Создает новый платеж
     */
    public function create(PaymentCreateRequest $request): JsonResource|RedirectResponse
    {
        try {
            $user = Auth::user();

            if (!$user) {
                if ($request->expectsJson()) {
                    return FailResource::make(['message' => 'Пользователь не авторизован']);
                }

                return redirect()->route('login');
            }

            $payment = $this->yooKassaService->createPayment(
                $user,
                (float) $request->input('amount'),
                $request->input('description'),
                $request->except(['amount', 'description'])
            );

            if ($request->expectsJson()) {
                return SuccessResource::make([
                    'payment_id' => $payment->id,
                    'confirmation_url' => $payment->confirmation_url,
                    'status' => $payment->status,
                ]);
            }

            if ($payment->confirmation_url) {
                return redirect($payment->confirmation_url);
            }

            return redirect()->back()->with('error', 'Не удалось создать платеж');
        } catch (\Exception $e) {
            if ($request->expectsJson()) {
                return FailResource::make(['message' => $e->getMessage()]);
            }

            return redirect()->back()->with('error', 'Ошибка при создании платежа: ' . $e->getMessage());
        }
    }

    /**
     * Страница возврата после оплаты
     */
    public function return(Request $request, Payment $payment): View|RedirectResponse
    {
        // Обновляем статус платежа
        $this->yooKassaService->updatePaymentStatus($payment);
        $payment->refresh();

        return view('payment.return', [
            'payment' => $payment,
        ]);
    }

    /**
     * Получает статус платежа
     */
    public function status(Payment $payment): JsonResource
    {
        try {
            // Обновляем статус из YooKassa
            $this->yooKassaService->updatePaymentStatus($payment);
            $payment->refresh();

            return SuccessResource::make([
                'payment_id' => $payment->id,
                'status' => $payment->status,
                'yookassa_status' => $payment->yookassa_status,
                'amount' => $payment->amount,
                'description' => $payment->description,
                'created_at' => $payment->created_at,
            ]);
        } catch (\Exception $e) {
            return FailResource::make(['message' => $e->getMessage()]);
        }
    }

    /**
     * Webhook для обработки уведомлений от YooKassa
     */
    public function webhook(Request $request): JsonResource
    {
        try {
            $data = $request->all();

            $payment = $this->yooKassaService->handleWebhook($data);

            if (!$payment) {
                return FailResource::make(['message' => 'Payment not found']);
            }

            return SuccessResource::make([
                'payment_id' => $payment->id,
                'status' => $payment->status,
            ]);
        } catch (\Exception $e) {
            return FailResource::make(['message' => $e->getMessage()]);
        }
    }

    /**
     * Страница оплаты
     */
    public function show(): View
    {
        return view('payment.create');
    }
}

