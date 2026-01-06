@extends('cockpit.layout')

@section('title', 'Просмотр подписки')
@section('page-title', 'Просмотр подписки')

@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Подписка #{{ $subscription->id }}</h3>
                    <div class="card-tools">
                        <a href="{{ route('cockpit.subscription.edit', $subscription) }}" class="btn btn-warning btn-sm">
                            <i class="fas fa-edit"></i> Редактировать
                        </a>
                        <a href="{{ route('cockpit.subscription.index') }}" class="btn btn-default btn-sm">
                            <i class="fas fa-arrow-left"></i> Назад
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <dl class="row">
                        <dt class="col-sm-3">ID:</dt>
                        <dd class="col-sm-9">{{ $subscription->id }}</dd>

                        <dt class="col-sm-3">Пользователь:</dt>
                        <dd class="col-sm-9">
                            {{ $subscription->user?->name }}
                        </dd>

                        <dt class="col-sm-3">UUID:</dt>
                        <dd class="col-sm-9">{{ $subscription->uuid }}</dd>

                        <dt class="col-sm-3">XUI сервер:</dt>
                        <dd class="col-sm-9">
                            @if($subscription->xui)
                                {{ $subscription->xui->tag->label() }} — {{ $subscription->xui->host }} (ID: {{ $subscription->xui->id }})
                            @else
                                <span class="text-muted">-</span>
                            @endif
                        </dd>

                        <dt class="col-sm-3">Истекает:</dt>
                        <dd class="col-sm-9">
                            @if($subscription->expires_at)
                                {{ $subscription->expires_at->format('d.m.Y H:i:s') }}
                            @else
                                <span class="text-muted">Без срока</span>
                            @endif
                        </dd>

                        <dt class="col-sm-3">Создана:</dt>
                        <dd class="col-sm-9">{{ $subscription->created_at?->format('d.m.Y H:i:s') }}</dd>

                        <dt class="col-sm-3">Обновлена:</dt>
                        <dd class="col-sm-9">{{ $subscription->updated_at?->format('d.m.Y H:i:s') }}</dd>
                    </dl>
                </div>
                <div class="card-footer">
                    <form action="{{ route('cockpit.subscription.destroy', $subscription) }}" method="POST" class="d-inline" onsubmit="return confirm('Вы уверены, что хотите удалить эту подписку?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-trash"></i> Удалить
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection


