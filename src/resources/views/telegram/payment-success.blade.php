✅ Платеж успешно завершен!

💳 Сумма: {{ number_format((float) $amount, 0, '.', ' ') }} ₽
📝 Описание: {{ $description }}

@if($expiryInfo)
Текущий срок действия подписки: {{ $expiryInfo }}
@endif
