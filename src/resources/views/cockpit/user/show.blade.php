@extends('cockpit.layout')

@section('title', 'Просмотр пользователя')
@section('page-title', 'Просмотр пользователя')

@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Пользователь #{{ $user->id }}</h3>
                    <div class="card-tools">
                        <a href="{{ route('cockpit.user.edit', $user) }}" class="btn btn-warning btn-sm">
                            <i class="fas fa-edit"></i> Редактировать
                        </a>
                        <a href="{{ route('cockpit.user.index') }}" class="btn btn-default btn-sm">
                            <i class="fas fa-arrow-left"></i> Назад
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <dl class="row">
                        <dt class="col-sm-3">ID:</dt>
                        <dd class="col-sm-9">{{ $user->id }}</dd>

                        <dt class="col-sm-3">Имя:</dt>
                        <dd class="col-sm-9">{{ $user->name }}</dd>

                        <dt class="col-sm-3">TG Tag:</dt>
                        <dd class="col-sm-9">{{ $user->tg_tag ?? '-' }}</dd>

                        <dt class="col-sm-3">Телефон:</dt>
                        <dd class="col-sm-9">{{ $user->phone_number }}</dd>

                        <dt class="col-sm-3">TG ID:</dt>
                        <dd class="col-sm-9">{{ $user->tg_id }}</dd>

                        <dt class="col-sm-3">UUID:</dt>
                        <dd class="col-sm-9"><code>{{ $user->uuid }}</code></dd>

                        <dt class="col-sm-3">Реферер:</dt>
                        <dd class="col-sm-9">
                            @if($user->referrer)
                                <a href="{{ route('cockpit.user.show', $user->referrer->id) }}">
                                    {{ $user->referrer->name }} (#{{ $user->referrer->id }})
                                </a>
                            @else
                                <span class="text-muted">Нет</span>
                            @endif
                        </dd>

                        <dt class="col-sm-3">Реферальный код:</dt>
                        <dd class="col-sm-9">
                            @if($user->referral_code)
                                <code>{{ $user->referral_code }}</code>
                            @else
                                <span class="text-muted">-</span>
                            @endif
                        </dd>

                        <dt class="col-sm-3">Баланс:</dt>
                        <dd class="col-sm-9">
                            @if($user->balance)
                                <span class="badge badge-success">{{ number_format($user->balance->balance, 2) }}</span>
                            @else
                                <span class="badge badge-secondary">0.00</span>
                            @endif
                        </dd>

                        <dt class="col-sm-3">Создан:</dt>
                        <dd class="col-sm-9">{{ $user->created_at->format('d.m.Y H:i:s') }}</dd>

                        <dt class="col-sm-3">Обновлен:</dt>
                        <dd class="col-sm-9">{{ $user->updated_at->format('d.m.Y H:i:s') }}</dd>
                    </dl>
                </div>
                <div class="card-footer">
                    <form action="{{ route('cockpit.user.destroy', $user) }}" method="POST" class="d-inline" onsubmit="return confirm('Вы уверены, что хотите удалить этого пользователя?');">
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

