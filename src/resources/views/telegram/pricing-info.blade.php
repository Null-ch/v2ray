{{ $name }}, выберите тарифный план для VPN {{ $tag }}

@foreach($pricings as $pricing)
⏱ Длительность: {{ $pricing->title }}
💰 Цена: {{ number_format($pricing->price) }} ₽

@endforeach
Выберите тарифный план для оплаты:

