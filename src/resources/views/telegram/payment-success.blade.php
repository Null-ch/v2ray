✅ Платеж успешно завершен!

💳 Сумма: {{ number_format((float) $amount, 0, '.', ' ') }} ₽
📝 Описание: {{ $description }}

@if($totalDays)
Текущий срок действия подписки: {{ $expiryInfo }}
@endif
