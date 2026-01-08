@extends('cockpit.layout')

@section('title', 'Управление ключами')
@section('page-title', 'Управление ключами')

@push('styles')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
@endpush

@section('content')
<div class="page-header d-print-none">
  <div class="row align-items-center">
    <div class="col">
      <h2 class="page-title">Ключи</h2>
      <div class="text-secondary">Управление VPN ключами пользователей</div>
    </div>
  </div>
</div>

<div class="row">
    <div class="col-12">
        <!-- Форма создания ключа -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Создать новый ключ</h3>
            </div>
            <div class="card-body">
                <form id="create-key-form" action="{{ route('cockpit.key.store') }}" method="POST">
                    @csrf
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label for="user_id" class="form-label">Пользователь</label>
                            <select class="form-select" id="user_id" name="user_id" required>
                                <option value="">Выберите пользователя...</option>
                                @foreach($users as $user)
                                    <option value="{{ $user->id }}">{{ $user->name }} (#{{ $user->id }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="xui_id" class="form-label">XUI Сервер</label>
                            <select class="form-select" id="xui_id" name="xui_id" required>
                                <option value="">Выберите сервер...</option>
                                @foreach($xuis as $xui)
                                    <option value="{{ $xui->id }}">{{ $xui->name }} ({{ $xui->tag->label() }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="days" class="form-label">Срок действия (дни)</label>
                            <input type="number" class="form-control" id="days" name="days" value="30" min="1" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">&nbsp;</label>
                            <div>
                                <button type="submit" class="btn btn-primary w-100">Создать ключ</button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Таблица ключей -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Список ключей</h3>
                <div class="card-tools">
                    <form action="{{ route('cockpit.key.sweep-expired') }}" method="POST" class="d-inline" data-confirm="Удалить все истёкшие ключи?" data-ajax="delete">
                        @csrf
                        <button type="submit" class="btn btn-danger btn-sm btn-glass">
                            <i class="ti ti-broom"></i> Удалить истёкшие
                        </button>
                    </form>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-vcenter">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Пользователь</th>
                                <th>Локация</th>
                                <th>Email</th>
                                <th>UUID</th>
                                <th>Истекает</th>
                                <th>Создан</th>
                                <th class="w-1">Действия</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($keys as $key)
                                <tr>
                                    <td>{{ $key->id }}</td>
                                    <td>
                                        @if($key->user)
                                            <a href="{{ route('cockpit.user.show', $key->user->id) }}">
                                                {{ $key->user->name }} (#{{ $key->user->id }})
                                            </a>
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($key->xui)
                                            {{ $key->xui->tag->labelWithFlag() }}
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                    <td><code>{{ $key->user->getVpnEmail() ?? '-' }}</code></td>
                                    <td><code>{{ $key->uuid }}</code></td>
                                    <td>
                                        @if($key->expires_at)
                                            @if($key->expires_at->isPast())
                                                <span class="badge bg-red">Истёк</span>
                                            @else
                                                {{ $key->expires_at->format('d.m.Y H:i') }}
                                            @endif
                                        @else
                                            <span class="text-muted">Без срока</span>
                                        @endif
                                    </td>
                                    <td>{{ $key->created_at->format('d.m.Y H:i') }}</td>
                                    <td>
                                        <div class="btn-list">
                                            <form action="{{ route('cockpit.key.destroy', $key->id) }}" method="POST" class="d-inline" data-confirm="Удалить ключ #{{ $key->id }}?" data-ajax="delete" data-refresh-target="keys-tbody">
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
                                    <td colspan="8" class="text-center">Нет ключей</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer clearfix">
                {{ $keys->links() }}
            </div>
        </div>
    </div>
</div>

@endsection

