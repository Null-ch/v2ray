{{ $name }}, выберите тарифный план для VPN {{ $tag }}

@foreach($pricings as $pricing)

⏱ Длительность: {{ $pricing->title }}
💰 Цена: {{ number_format($pricing->price, 2, '.', ' ') }} ₽

@endforeach

Выберите тарифный план для оплаты:

