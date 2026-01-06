@extends('cockpit.layout')

@section('title', 'Dashboard')
@section('page-title', 'Dashboard')

@section('content')
    <div class="row">
        <div class="col-lg-3 col-6">
            <!-- small box -->
            <div class="small-box bg-info">
                <div class="inner">
                    <h3>{{ $totalUsers }}</h3>
                    <p>Пользователи</p>
                </div>
                <div class="icon">
                    <i class="fas fa-users"></i>
                </div>
                <a href="{{ route('cockpit.user.index') }}" class="small-box-footer">
                    Подробнее <i class="fas fa-arrow-circle-right"></i>
                </a>
            </div>
        </div>
        <!-- ./col -->
        <div class="col-lg-3 col-6">
            <!-- small box -->
            <div class="small-box bg-success">
                <div class="inner">
                    @php
                        $activePercent = $totalUsers > 0 ? round($activeUsers / $totalUsers * 100) : 0;
                    @endphp
                    <h3>{{ $activeUsers }}<sup style="font-size: 16px"> ({{ $activePercent }}%)</sup></h3>
                    <p>Активные пользователи (с непрошедшей подпиской)</p>
                </div>
                <div class="icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <a href="{{ route('cockpit.subscription.index') }}" class="small-box-footer">
                    Подробнее <i class="fas fa-arrow-circle-right"></i>
                </a>
            </div>
        </div>
        <!-- ./col -->
        <div class="col-lg-3 col-6">
            <!-- small box -->
            <div class="small-box bg-warning">
                <div class="inner">
                    <h3>{{ $totalSubscriptions }}</h3>
                    <p>Всего подписок</p>
                </div>
                <div class="icon">
                    <i class="fas fa-cog"></i>
                </div>
                <a href="{{ route('cockpit.subscription.index') }}" class="small-box-footer">
                    Подробнее <i class="fas fa-arrow-circle-right"></i>
                </a>
            </div>
        </div>
        <!-- ./col -->
        <div class="col-lg-3 col-6">
            <!-- small box -->
            <div class="small-box bg-danger">
                <div class="inner">
                    <h3>{{ $activeXui }}/{{ $totalXui }}</h3>
                    <p>Активные XUI сервера</p>
                </div>
                <div class="icon">
                    <i class="fas fa-server"></i>
                </div>
                <a href="{{ route('cockpit.xui.index') }}" class="small-box-footer">
                    Подробнее <i class="fas fa-arrow-circle-right"></i>
                </a>
            </div>
        </div>
        <!-- ./col -->
    </div>

    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Добро пожаловать в Cockpit</h3>
                </div>
                <div class="card-body">
                    <p>Панель управления успешно настроена и готова к использованию.</p>
                    <p>Вы можете настраивать ключевые параметры проекта через раздел <strong>Настройки</strong>, а также отслеживать пользователей, VPN-конфигурации и платежи.</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Аналитика по платежам</h3>
                </div>
                <div class="card-body">
                    <ul class="list-unstyled mb-0">
                        <li>
                            <strong>Всего платежей:</strong>
                            {{ $totalPayments }}
                        </li>
                        <li>
                            <strong>Успешных платежей:</strong>
                            {{ $succeededPayments }}
                        </li>
                        <li>
                            <strong>Общий доход (успешные):</strong>
                            {{ number_format($totalRevenue, 2, '.', ' ') }} ₽
                        </li>
                        <li>
                            <strong>Доход за сегодня:</strong>
                            {{ number_format($todayRevenue, 2, '.', ' ') }} ₽
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Динамика выручки (7 дней)</h3>
                </div>
                <div class="card-body">
                    <canvas id="revenueChart" height="180"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Доход по тарифам</h3>
                </div>
                <div class="card-body">
                    @if(!empty($pricingLabels))
                        <canvas id="pricingChart" height="180"></canvas>
                    @else
                        <p class="mb-0 text-muted">Пока нет данных по тарифам.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script>
        (function () {
            const dailyRevenue = @json($dailyRevenue->pluck('total'));
            const dailyLabels = @json($dailyRevenue->pluck('date')->map(fn($d) => \Illuminate\Support\Carbon::parse($d)->format('d.m')));

            const pricingLabels = @json($pricingLabels);
            const pricingAmounts = @json($pricingAmounts);

            const ctxRevenue = document.getElementById('revenueChart');
            if (ctxRevenue && dailyLabels.length) {
                new Chart(ctxRevenue, {
                    type: 'line',
                    data: {
                        labels: dailyLabels,
                        datasets: [{
                            label: 'Выручка, ₽',
                            data: dailyRevenue,
                            borderColor: 'rgba(54, 162, 235, 1)',
                            backgroundColor: 'rgba(54, 162, 235, 0.2)',
                            tension: 0.3,
                            fill: true,
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            legend: {display: true},
                        },
                        scales: {
                            y: {
                                beginAtZero: true
                            }
                        }
                    }
                });
            }

            const ctxPricing = document.getElementById('pricingChart');
            if (ctxPricing && pricingLabels.length) {
                new Chart(ctxPricing, {
                    type: 'doughnut',
                    data: {
                        labels: pricingLabels,
                        datasets: [{
                            data: pricingAmounts,
                            backgroundColor: [
                                'rgba(75, 192, 192, 0.6)',
                                'rgba(255, 159, 64, 0.6)',
                                'rgba(153, 102, 255, 0.6)',
                                'rgba(255, 205, 86, 0.6)',
                                'rgba(201, 203, 207, 0.6)',
                            ],
                            borderColor: 'rgba(255, 255, 255, 1)',
                            borderWidth: 1,
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            legend: {position: 'bottom'},
                        },
                    }
                });
            }
        })();
    </script>
@endpush

