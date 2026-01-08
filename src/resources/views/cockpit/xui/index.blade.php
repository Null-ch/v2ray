@extends('cockpit.layout')

@section('title', 'XUI Серверы')
@section('page-title', 'XUI Серверы')

@section('content')
<div class="page-header d-print-none">
  <div class="row align-items-center">
    <div class="col">
      <h2 class="page-title">Список XUI серверов</h2>
      <div class="text-secondary">Управление VPN серверами и мониторинг</div>
    </div>
    <div class="col-auto ms-auto d-print-none">
      <a href="{{ route('cockpit.xui.create') }}" class="btn btn-primary">
        <i class="ti ti-plus"></i> Добавить сервер
      </a>
    </div>
  </div>
</div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Серверы</h3>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-vcenter">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Тег</th>
                                    <th>Хост</th>
                                    <th>Порт</th>
                                    <th>Путь</th>
                                    <th>Пользователь</th>
                                    <th>SSL</th>
                                    <th>Активен</th>
                                    <th>Inbound ID</th>
                                    <th>Состояние</th>
                                    <th>Создан</th>
                                    <th class="w-1">Действия</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($xuis as $xui)
                                    <tr>
                                        <td>{{ $xui->id }}</td>
                                        <td>
                                            <span class="badge bg-blue">{{ $xui->tag->label() }}</span>
                                        </td>
                                        <td><code>{{ $xui->host }}</code></td>
                                        <td>{{ $xui->port }}</td>
                                        <td><code>{{ $xui->path }}</code></td>
                                        <td>{{ $xui->username }}</td>
                                        <td>
                                            @if($xui->ssl)
                                                <span class="badge bg-green">Да</span>
                                            @else
                                                <span class="badge bg-secondary">Нет</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($xui->is_active)
                                                <span class="badge bg-green">Активен</span>
                                            @else
                                                <span class="badge bg-red">Неактивен</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($xui->inbound_id)
                                                <span class="badge bg-blue">{{ $xui->inbound_id }}</span>
                                            @else
                                                <span class="text-muted">-</span>
                                            @endif
                                        </td>
                                        <td>
                                            <span class="status-indicator" data-xui-id="{{ $xui->id }}" data-check-url="{{ route('cockpit.xui.status', $xui->id) }}">
                                                <span class="status-dot"></span>
                                                <span class="status-text">Проверка...</span>
                                            </span>
                                        </td>
                                        <td>{{ $xui->created_at->format('d.m.Y H:i') }}</td>
                                        <td>
                                            <div class="btn-list">
                                                <a href="{{ route('cockpit.server.monitor', $xui->id) }}" class="btn btn-primary btn-sm btn-glass" title="Мониторинг">
                                                    <i class="ti ti-chart-line"></i>
                                                </a>
                                                <a href="{{ route('cockpit.xui.show', $xui) }}" class="btn btn-info btn-sm btn-glass" title="Просмотр">
                                                    <i class="ti ti-eye"></i>
                                                </a>
                                                <a href="{{ route('cockpit.xui.edit', $xui) }}" class="btn btn-warning btn-sm btn-glass" title="Редактировать">
                                                    <i class="ti ti-edit"></i>
                                                </a>
                                                <form action="{{ route('cockpit.xui.destroy', $xui) }}" method="POST" class="d-inline" data-confirm="Вы уверены, что хотите удалить этот сервер?" data-ajax="delete" data-refresh-target="xui-table-body">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-danger btn-sm btn-glass" title="Удалить">
                                                        <i class="ti ti-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="12" class="text-center">Нет серверов</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer">
                    {{ $xuis->links() }}
                </div>
            </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Проверка состояния серверов
    function checkServerStatus() {
        document.querySelectorAll('.status-indicator').forEach(function(el) {
            const xuiId = el.getAttribute('data-xui-id');
            const url = el.getAttribute('data-check-url');
            if (!url) return;
            
            fetch(url, {
                headers: { 'Accept': 'application/json' },
                credentials: 'same-origin'
            })
            .then(resp => resp.json())
            .then(data => {
                const dot = el.querySelector('.status-dot');
                const text = el.querySelector('.status-text');
                if (data.ok && data.status === 'online') {
                    dot.className = 'status-dot status-dot-animated bg-green';
                    text.textContent = 'Онлайн';
                } else {
                    dot.className = 'status-dot bg-red';
                    text.textContent = 'Офлайн';
                }
            })
            .catch(() => {
                const dot = el.querySelector('.status-dot');
                const text = el.querySelector('.status-text');
                dot.className = 'status-dot bg-red';
                text.textContent = 'Ошибка';
            });
        });
    }
    
    // Первоначальная загрузка
    checkServerStatus();
    
    // Автообновление каждые 30 секунд
    setInterval(checkServerStatus, 30000);
});
</script>
@endpush

