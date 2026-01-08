<!DOCTYPE html>
<html lang="ru" data-bs-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Панель управления') - {{ config('app.name') }}</title>

    <!-- Tabler CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/core@1.0.0-beta20/dist/css/tabler.min.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/core@1.0.0-beta20/dist/css/tabler-flags.min.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/core@1.0.0-beta20/dist/css/tabler-payments.min.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/core@1.0.0-beta20/dist/css/tabler-vendors.min.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css" />
    
    <!-- Custom Cockpit Styles -->
    <link rel="stylesheet" href="{{ asset('css/cockpit.css') }}" />
    
    <!-- Charts -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-zoom@2.0.1/dist/chartjs-plugin-zoom.umd.min.js"></script>
    
    @stack('styles')
</head>
<body data-page="{{ request()->route()->getName() }}" class="layout-boxed">
    <div class="container">
        <header class="main-header">
            <div class="brand">
                <a href="{{ route('cockpit.dashboard') }}" class="brand-link">
                    <span id="brand-title" class="ms-2">{{ config('app.name', 'Cockpit') }}</span>
                </a>
            </div>
            <button type="button" class="nav-toggle d-md-none" id="nav-toggle" aria-label="Toggle navigation">
                <span></span>
                <span></span>
                <span></span>
            </button>
            <div class="nav-overlay" id="nav-overlay"></div>
            <nav class="navigation justify-content-center" id="main-navigation">
                <a href="{{ route('cockpit.dashboard') }}" class="nav-link {{ request()->routeIs('cockpit.dashboard') ? 'active' : '' }}">
                    Главная
                </a>
                <a href="{{ route('cockpit.user.index') }}" class="nav-link {{ request()->routeIs('cockpit.user.*') ? 'active' : '' }}">
                    Пользователи
                </a>
                <a href="{{ route('cockpit.key.index') }}" class="nav-link {{ request()->routeIs('cockpit.key.*') ? 'active' : '' }}">
                    Ключи
                </a>
                <a href="{{ route('cockpit.xui.index') }}" class="nav-link {{ request()->routeIs('cockpit.xui.*') ? 'active' : '' }}">
                    XUI Серверы
                </a>
                <a href="{{ route('cockpit.server.monitor.index') }}" class="nav-link {{ request()->routeIs('cockpit.server.*') ? 'active' : '' }}">
                    Мониторинг серверов
                </a>
                <a href="{{ route('cockpit.pricing.index') }}" class="nav-link {{ request()->routeIs('cockpit.pricing.*') ? 'active' : '' }}">
                    Тарифы
                </a>
                <a href="{{ route('cockpit.balance.index') }}" class="nav-link {{ request()->routeIs('cockpit.balance.*') ? 'active' : '' }}">
                    Балансы
                </a>
                <a href="{{ route('cockpit.referral.index') }}" class="nav-link {{ request()->routeIs('cockpit.referral.*') ? 'active' : '' }}">
                    Рефералы
                </a>
                <a href="{{ route('cockpit.setting.index') }}" class="nav-link {{ request()->routeIs('cockpit.setting.*') ? 'active' : '' }}">
                    Настройки
                </a>
            </nav>
            <div class="header-controls">
                <div class="user-session-controls">
                    <span>Вы вошли в систему.</span>
                    <form action="{{ route('cockpit.logout') }}" method="POST" style="display: inline">
                        @csrf
                        <button type="submit" class="btn btn-outline-secondary">Выйти</button>
                    </form>
                    <button type="button" id="theme-toggle" class="btn btn-outline-primary ms-2" title="Переключить тему">
                        <span class="theme-label">Тёмная</span>
                    </button>
                </div>
            </div>
        </header>

        <!-- Toast container -->
        <div id="toast-container" class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1080;"></div>

        <main>@yield('content')</main>
        
        <footer class="main-footer"></footer>
    </div>

    <!-- Tabler Core -->
    <script src="https://cdn.jsdelivr.net/npm/@tabler/core@1.0.0-beta20/dist/js/tabler.min.js"></script>
    <script src="{{ asset('js/cockpit.js') }}"></script>
    <script>
    // Показываем flash сообщения как toast
    @if(session('success'))
        window.showToast('success', '{{ session('success') }}');
    @endif

    @if(session('warning'))
        window.showToast('warning', '{{ session('warning') }}');
    @endif

    @if(session('error') || $errors->any())
        @if(session('error'))
            window.showToast('danger', '{{ session('error') }}');
        @endif
        @if($errors->any())
            @foreach($errors->all() as $error)
                window.showToast('danger', '{{ $error }}');
            @endforeach
        @endif
    @endif
    </script>
    @stack('scripts')
</body>
</html>
