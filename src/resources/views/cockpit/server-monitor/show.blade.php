@extends('cockpit.layout')

@section('title', 'Мониторинг сервера')
@section('page-title', 'Мониторинг сервера')

@section('content')
    <div class="page-header d-print-none">
        <div class="row align-items-center">
            <div class="col">
                <h2 class="page-title">Мониторинг: {{ $xui->name ?? $xui->tag->label() }}</h2>
                <div class="text-secondary">Детальная информация о состоянии сервера</div>
            </div>
            <div class="col-auto ms-auto d-print-none">
                <a href="{{ route('cockpit.server.monitor.index') }}" class="btn btn-outline-secondary">
                    <i class="ti ti-arrow-left"></i> Назад к списку
                </a>
            </div>
        </div>
    </div>

    <div class="row row-cards" id="server-monitor-data" data-status-url="{{ route('cockpit.server.monitor.status', $xui->id) }}">
        <!-- CPU -->
        <div class="col-sm-6 col-lg-3">
            <div class="card">
                <div class="card-body">
                    <div class="subheader">CPU</div>
                    <div class="h1 mb-0" id="cpu-usage">-</div>
                    <div class="text-secondary small" id="cpu-info">-</div>
                </div>
            </div>
        </div>

        <!-- Memory -->
        <div class="col-sm-6 col-lg-3">
            <div class="card">
                <div class="card-body">
                    <div class="subheader">Память</div>
                    <div class="h1 mb-0" id="mem-usage">-</div>
                    <div class="text-secondary small" id="mem-info">-</div>
                </div>
            </div>
        </div>

        <!-- Disk -->
        <div class="col-sm-6 col-lg-3">
            <div class="card">
                <div class="card-body">
                    <div class="subheader">Диск</div>
                    <div class="h1 mb-0" id="disk-usage">-</div>
                    <div class="text-secondary small" id="disk-info">-</div>
                </div>
            </div>
        </div>

        <!-- Network -->
        <div class="col-sm-6 col-lg-3">
            <div class="card">
                <div class="card-body">
                    <div class="subheader">Сеть</div>
                    <div class="h1 mb-0" id="net-io">-</div>
                    <div class="text-secondary small" id="net-traffic">-</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row row-cards mt-3">
        <!-- Xray Status -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Xray Status</h3>
                </div>
                <div class="card-body">
                    <div id="xray-status">
                        <div class="text-muted">Загрузка...</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- System Info -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Системная информация</h3>
                </div>
                <div class="card-body">
                    <div id="system-info">
                        <div class="text-muted">Загрузка...</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row row-cards mt-3">
        <!-- Connections -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Подключения</h3>
                </div>
                <div class="card-body">
                    <div id="connections-info">
                        <div class="text-muted">Загрузка...</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Application Stats -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Статистика приложения</h3>
                </div>
                <div class="card-body">
                    <div id="app-stats">
                        <div class="text-muted">Загрузка...</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const statusUrl = document.getElementById('server-monitor-data')
                    .getAttribute('data-status-url');

                function formatBytes(bytes) {
                    if (bytes === 0) return '0 B';
                    const k = 1024;
                    const sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
                    const i = Math.floor(Math.log(bytes) / Math.log(k));
                    return (bytes / Math.pow(k, i)).toFixed(2) + ' ' + sizes[i];
                }

                function formatSpeed(bytesPerSecond) {
                    if (!bytesPerSecond || bytesPerSecond <= 0) return '0 B/s';
                    if (bytesPerSecond >= 1024 ** 2) {
                        return (bytesPerSecond / (1024 ** 2)).toFixed(2) + ' MB/s';
                    }
                    if (bytesPerSecond >= 1024) {
                        return (bytesPerSecond / 1024).toFixed(2) + ' KB/s';
                    }
                    return bytesPerSecond.toFixed(0) + ' B/s';
                }

                function formatUptime(seconds) {
                    const days = Math.floor(seconds / 86400);
                    const hours = Math.floor((seconds % 86400) / 3600);
                    const minutes = Math.floor((seconds % 3600) / 60);

                    if (days > 0) return `${days}д ${hours}ч ${minutes}м`;
                    if (hours > 0) return `${hours}ч ${minutes}м`;
                    return `${minutes}м`;
                }

                function updateStatus() {
                    fetch(statusUrl, {
                            headers: {
                                'Accept': 'application/json'
                            },
                            credentials: 'same-origin'
                        })
                        .then(resp => resp.json())
                        .then(data => {
                            if (!data.ok || !data.data) return;

                            const d = data.data;

                            // ===== CPU =====
                            document.getElementById('cpu-usage').textContent =
                                (d.cpu || 0).toFixed(1) + '%';
                            document.getElementById('cpu-info').textContent =
                                `${d.cpuCores || 0} ядер, ${(d.cpuSpeedMhz || 0).toFixed(0)} MHz`;

                            // ===== Memory =====
                            const memCurrent = d.mem?.current || 0;
                            const memTotal = d.mem?.total || 0;
                            document.getElementById('mem-usage').textContent =
                                memTotal ? ((memCurrent / memTotal) * 100).toFixed(1) + '%' : '0%';
                            document.getElementById('mem-info').textContent =
                                `${formatBytes(memCurrent)} / ${formatBytes(memTotal)}`;

                            // ===== Disk =====
                            const diskCurrent = d.disk?.current || 0;
                            const diskTotal = d.disk?.total || 0;
                            document.getElementById('disk-usage').textContent =
                                diskTotal ? ((diskCurrent / diskTotal) * 100).toFixed(1) + '%' : '0%';
                            document.getElementById('disk-info').textContent =
                                `${formatBytes(diskCurrent)} / ${formatBytes(diskTotal)}`;

                            // ===== Network =====
                            const now = Date.now();

                            const netSent = d.netTraffic?.sent ?? 0;
                            const netRecv = d.netTraffic?.recv ?? 0;

                            let upSpeed = d.netIO?.up || 0;
                            let downSpeed = d.netIO?.down || 0;

                            // fallback если netIO отсутствует или 0
                            if (!upSpeed || !downSpeed) {
                                const prev = JSON.parse(localStorage.getItem('netTrafficPrev') || '{}');

                                if (prev.sent !== undefined && prev.recv !== undefined && prev.time) {
                                    const deltaTime = (now - prev.time) / 1000;
                                    if (deltaTime > 0) {
                                        upSpeed = (netSent - prev.sent) / deltaTime;
                                        downSpeed = (netRecv - prev.recv) / deltaTime;
                                    }
                                }
                            }

                            document.getElementById('net-io').textContent =
                                `↑ ${formatSpeed(upSpeed)} ↓ ${formatSpeed(downSpeed)}`;

                            document.getElementById('net-traffic').textContent =
                                `Отправлено: ${formatBytes(netSent)}, Получено: ${formatBytes(netRecv)}`;

                            localStorage.setItem('netTrafficPrev', JSON.stringify({
                                sent: netSent,
                                recv: netRecv,
                                time: now
                            }));

                            // ===== Xray =====
                            const xrayState = d.xray?.state || 'unknown';
                            const xrayVersion = d.xray?.version || 'N/A';
                            const xrayBadge = xrayState === 'running' ? 'bg-green' : 'bg-red';

                            document.getElementById('xray-status').innerHTML = `
                <span class="badge ${xrayBadge}">${xrayState}</span>
                <span class="ms-2">Версия: ${xrayVersion}</span>
            `;

                            // ===== System =====
                            document.getElementById('system-info').innerHTML = `
                <div><strong>Uptime:</strong> ${formatUptime(d.uptime || 0)}</div>
                <div><strong>Load:</strong> ${(d.loads || [0,0,0]).map(v => v.toFixed(2)).join(', ')}</div>
                <div><strong>Public IPv4:</strong> ${d.publicIP?.ipv4 || 'N/A'}</div>
                ${d.publicIP?.ipv6 ? `<div><strong>Public IPv6:</strong> ${d.publicIP.ipv6}</div>` : ''}
            `;

                            // ===== Connections =====
                            document.getElementById('connections-info').innerHTML = `
                <div><strong>TCP:</strong> ${d.tcpCount || 0}</div>
                <div><strong>UDP:</strong> ${d.udpCount || 0}</div>
            `;

                            // ===== App =====
                            document.getElementById('app-stats').innerHTML = `
                <div><strong>Потоки:</strong> ${d.appStats?.threads || 0}</div>
                <div><strong>Память:</strong> ${formatBytes(d.appStats?.mem || 0)}</div>
                <div><strong>Uptime:</strong> ${formatUptime(d.appStats?.uptime || 0)}</div>
            `;
                        });
                }

                updateStatus();
                setInterval(updateStatus, 10000);
            });
        </script>
    @endpush

@endsection
