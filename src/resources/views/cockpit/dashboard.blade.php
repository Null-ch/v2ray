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
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Доход по тарифам</h3>
                </div>
                <div class="card-body table-responsive p-0">
                    <table class="table table-hover text-nowrap">
                        <thead>
                            <tr>
                                <th>Название тарифа</th>
                                <th>Количество платежей</th>
                                <th>Доход по тарифу</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($pricingTableData as $pricing)
                                <tr>
                                    <td>{{ $pricing['title'] }}</td>
                                    <td>{{ $pricing['payments_count'] }}</td>
                                    <td>{{ number_format($pricing['total_amount'], 2, '.', ' ') }} ₽</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="text-center">Пока нет данных по тарифам.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection

