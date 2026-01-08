@extends('cockpit.layout')

@section('title', 'Мониторинг серверов')
@section('page-title', 'Мониторинг серверов')

@section('content')
<div class="page-header d-print-none">
  <div class="row align-items-center">
    <div class="col">
      <h2 class="page-title">Мониторинг серверов</h2>
      <div class="text-secondary">Выберите сервер для просмотра детальной информации</div>
    </div>
  </div>
</div>

<div class="row row-cards">
    @forelse($xuis as $xui)
    <div class="col-md-6 col-lg-4">
        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-fill">
                        <div class="h3 mb-0">{{ $xui->name ?? $xui->tag->label() }}</div>
                        <div class="text-secondary">
                            <span class="badge bg-blue">{{ $xui->tag->label() }}</span>
                            @if($xui->is_active)
                                <span class="badge bg-green">Активен</span>
                            @else
                                <span class="badge bg-red">Неактивен</span>
                            @endif
                        </div>
                    </div>
                </div>
                <div class="mt-3">
                    <div class="text-secondary small mb-1">Хост</div>
                    <div><code>{{ $xui->host }}:{{ $xui->port }}</code></div>
                </div>
            </div>
            <div class="card-footer">
                <a href="{{ route('cockpit.server.monitor', $xui->id) }}" class="btn btn-primary w-100">
                    <i class="ti ti-chart-line"></i> Просмотр мониторинга
                </a>
            </div>
        </div>
    </div>
    @empty
    <div class="col-12">
        <div class="card">
            <div class="card-body text-center">
                <p class="text-muted">Нет серверов для мониторинга</p>
            </div>
        </div>
    </div>
    @endforelse
</div>
@endsection

