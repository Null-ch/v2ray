@extends('cockpit.layout')

@section('title', 'Просмотр баланса')
@section('page-title', 'Просмотр баланса')

@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Баланс #{{ $balance->id }}</h3>
                    <div class="card-tools">
                        <a href="{{ route('cockpit.balance.edit', $balance) }}" class="btn btn-warning btn-sm">
                            <i class="fas fa-edit"></i> Редактировать
                        </a>
                        <a href="{{ route('cockpit.balance.index') }}" class="btn btn-default btn-sm">
                            <i class="fas fa-arrow-left"></i> Назад
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <dl class="row">
                        <dt class="col-sm-3">ID:</dt>
                        <dd class="col-sm-9">{{ $balance->id }}</dd>

                        <dt class="col-sm-3">Пользователь:</dt>
                        <dd class="col-sm-9">
                            @if($balance->user)
                                <a href="{{ route('cockpit.user.show', $balance->user->id) }}">
                                    {{ $balance->user->name }} (#{{ $balance->user->id }})
                                </a>
                            @else
                                <span class="text-muted">Удален</span>
                            @endif
                        </dd>

                        <dt class="col-sm-3">Баланс:</dt>
                        <dd class="col-sm-9">
                            <span class="badge badge-success">{{ number_format($balance->balance, 2) }}</span>
                        </dd>

                        <dt class="col-sm-3">Создан:</dt>
                        <dd class="col-sm-9">{{ $balance->created_at->format('d.m.Y H:i:s') }}</dd>

                        <dt class="col-sm-3">Обновлен:</dt>
                        <dd class="col-sm-9">{{ $balance->updated_at->format('d.m.Y H:i:s') }}</dd>
                    </dl>
                </div>
                <div class="card-footer">
                    <form action="{{ route('cockpit.balance.destroy', $balance) }}" method="POST" class="d-inline" onsubmit="return confirm('Вы уверены?');">
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

