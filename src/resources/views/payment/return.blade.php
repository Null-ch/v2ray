<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Статус платежа</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .payment-status-container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            padding: 40px;
            max-width: 500px;
            width: 100%;
            text-align: center;
        }

        .status-icon {
            width: 80px;
            height: 80px;
            margin: 0 auto 20px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 40px;
        }

        .status-icon.success {
            background: #d4edda;
            color: #155724;
        }

        .status-icon.pending {
            background: #fff3cd;
            color: #856404;
        }

        .status-icon.canceled {
            background: #f8d7da;
            color: #721c24;
        }

        h1 {
            color: #333;
            margin-bottom: 20px;
            font-size: 28px;
        }

        .status-badge {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 600;
            margin-bottom: 20px;
        }

        .status-badge.success {
            background: #d4edda;
            color: #155724;
        }

        .status-badge.pending {
            background: #fff3cd;
            color: #856404;
        }

        .status-badge.canceled {
            background: #f8d7da;
            color: #721c24;
        }

        .payment-info {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
            text-align: left;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e0e0e0;
        }

        .info-row:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }

        .info-label {
            color: #666;
            font-weight: 500;
        }

        .info-value {
            color: #333;
            font-weight: 600;
        }

        .btn {
            display: inline-block;
            padding: 12px 24px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            margin-top: 20px;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.4);
        }

        .message {
            margin-top: 20px;
            padding: 15px;
            border-radius: 8px;
            color: #666;
        }

        .message.success {
            background: #d4edda;
            color: #155724;
        }

        .message.pending {
            background: #fff3cd;
            color: #856404;
        }

        .message.canceled {
            background: #f8d7da;
            color: #721c24;
        }
    </style>
</head>

<body>
    <div class="payment-status-container">
        @if ($payment->isSucceeded())
            <div class="status-icon success">✓</div>
            <h1>Платеж успешно выполнен</h1>
            <span class="status-badge success">Оплачено</span>
            <div class="message success">
                Ваш баланс был пополнен на сумму {{ number_format($payment->amount, 2, '.', ' ') }} руб.
            </div>
        @elseif($payment->isCanceled())
            <div class="status-icon canceled">✗</div>
            <h1>Платеж отменен</h1>
            <span class="status-badge canceled">Отменен</span>
            <div class="message canceled">
                Платеж был отменен. Если вы считаете, что это ошибка, обратитесь в поддержку.
            </div>
        @else
            <div class="status-icon pending">⏳</div>
            <h1>Платеж в обработке</h1>
            <span class="status-badge pending">Ожидание</span>
            <div class="message pending">
                Ваш платеж обрабатывается. Пожалуйста, подождите.
            </div>
        @endif

        <div class="payment-info">
            <div class="info-row">
                <span class="info-label">ID платежа:</span>
                <span class="info-value">#{{ $payment->id }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Сумма:</span>
                <span class="info-value">{{ number_format($payment->amount, 2, '.', ' ') }} руб.</span>
            </div>
            <div class="info-row">
                <span class="info-label">Описание:</span>
                <span class="info-value">{{ $payment->description }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Дата создания:</span>
                <span class="info-value">{{ $payment->created_at->format('d.m.Y H:i') }}</span>
            </div>
            @if ($payment->yookassa_payment_id)
                <div class="info-row">
                    <span class="info-label">YooKassa ID:</span>
                    <span class="info-value">{{ $payment->yookassa_payment_id }}</span>
                </div>
            @endif
        </div>

        <a href="https://t.me/blackpill_guru_bot" class="btn">Перейти в наш бот</a>
    </div>
</body>

</html>
