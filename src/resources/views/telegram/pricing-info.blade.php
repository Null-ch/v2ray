{{ $name }}, выберите тарифный план для VPN {{ $tag }}

@foreach($pricings as $pricing)
📦 {{ $pricing->title }}
⏱ Длительность: {{ $pricing->duration }} {{ $pricing->duration == 1 ? 'день' : ($pricing->duration < 5 ? 'дня' : 'дней') }}
💰 Цена: {{ number_format($pricing->price, 2, '.', ' ') }} ₽

@endforeach

Выберите тарифный план для оплаты:

