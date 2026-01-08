@extends('cockpit.layout')

@section('title', 'Подписки')
@section('page-title', 'Подписки')

@section('content')
<div class="page-header d-print-none">
  <div class="row align-items-center">
    <div class="col">
      <h2 class="page-title">Подписки</h2>
      <div class="text-secondary">Управление VPN подписками</div>
    </div>
    <div class="col-auto ms-auto d-print-none">
      <a href="{{ route('cockpit.subscription.create') }}" class="btn btn-primary">
        <i class="ti ti-plus"></i> Добавить подписку
      </a>
    </div>
  </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Список подписок</h3>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-vcenter">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Пользователь</th>
                                <th>UUID</th>
                                <th>XUI</th>
                                <th>Истекает</th>
                                <th>Создана</th>
                                <th class="w-1">Действия</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($subscriptions as $subscription)
                                <tr>
                                    <td>{{ $subscription->id }}</td>
                                    <td>{{ $subscription->user?->name }}</td>
                                    <td><code>{{ $subscription->uuid }}</code></td>
                                    <td>
                                        @if($subscription->xui)
                                            {{ $subscription->xui->tag->label() }} (ID: {{ $subscription->xui->id }})
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($subscription->expires_at)
                                            {{ $subscription->expires_at->format('d.m.Y H:i') }}
                                        @else
                                            <span class="text-muted">Без срока</span>
                                        @endif
                                    </td>
                                    <td>{{ $subscription->created_at?->format('d.m.Y H:i') }}</td>
                                    <td>
                                        <div class="btn-list">
                                            <a href="{{ route('cockpit.subscription.show', $subscription) }}" class="btn btn-info btn-sm btn-glass" title="Просмотр">
                                                <i class="ti ti-eye"></i>
                                            </a>
                                            <a href="{{ route('cockpit.subscription.edit', $subscription) }}" class="btn btn-warning btn-sm btn-glass" title="Редактировать">
                                                <i class="ti ti-edit"></i>
                                            </a>
                                            <form action="{{ route('cockpit.subscription.destroy', $subscription) }}" method="POST" class="d-inline" data-confirm="Вы уверены, что хотите удалить эту подписку?" data-ajax="delete">
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
                                    <td colspan="7" class="text-center">Нет подписок</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer">
                {{ $subscriptions->links() }}
            </div>
        </div>
    </div>
</div>
@endsection
