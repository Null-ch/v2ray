@extends('cockpit.layout')

@section('title', 'Подписки')
@section('page-title', 'Подписки')

@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Список подписок</h3>
                    <div class="card-tools">
                        <a href="{{ route('cockpit.subscription.create') }}" class="btn btn-primary btn-sm">
                            <i class="fas fa-plus"></i> Добавить подписку
                        </a>
                    </div>
                </div>
                <div class="card-body table-responsive p-0">
                    <table class="table table-hover text-nowrap">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Пользователь</th>
                                <th>UUID</th>
                                <th>XUI</th>
                                <th>Истекает</th>
                                <th>Создана</th>
                                <th>Действия</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($subscriptions as $subscription)
                                <tr>
                                    <td>{{ $subscription->id }}</td>
                                    <td>{{ $subscription->user?->name }}</td>
                                    <td>{{ $subscription->uuid }}</td>
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
                                        <div class="btn-group">
                                            <a href="{{ route('cockpit.subscription.show', $subscription) }}" class="btn btn-info btn-sm" title="Просмотр">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="{{ route('cockpit.subscription.edit', $subscription) }}" class="btn btn-warning btn-sm" title="Редактировать">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <form action="{{ route('cockpit.subscription.destroy', $subscription) }}" method="POST" class="d-inline" onsubmit="return confirm('Вы уверены, что хотите удалить эту подписку?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-danger btn-sm" title="Удалить">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center">Нет подписок</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="card-footer clearfix">
                    {{ $subscriptions->links() }}
                </div>
            </div>
        </div>
    </div>
@endsection


