@extends('cockpit.layout')

@section('title', 'Просмотр реферальной связи')
@section('page-title', 'Просмотр реферальной связи')

@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Реферальная связь #{{ $referral->id }}</h3>
                    <div class="card-tools">
                        <a href="{{ route('cockpit.referral.edit', $referral) }}" class="btn btn-warning btn-sm">
                            <i class="fas fa-edit"></i> Редактировать
                        </a>
                        <a href="{{ route('cockpit.referral.index') }}" class="btn btn-default btn-sm">
                            <i class="fas fa-arrow-left"></i> Назад
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <dl class="row">
                        <dt class="col-sm-3">ID:</dt>
                        <dd class="col-sm-9">{{ $referral->id }}</dd>

                        <dt class="col-sm-3">Кто пригласил:</dt>
                        <dd class="col-sm-9">
                            @if($referral->user)
                                <a href="{{ route('cockpit.user.show', $referral->user->id) }}">
                                    {{ $referral->user->name }} (#{{ $referral->user->id }})
                                </a>
                            @else
                                <span class="text-muted">Удален</span>
                            @endif
                        </dd>

                        <dt class="col-sm-3">Кого пригласили:</dt>
                        <dd class="col-sm-9">
                            @if($referral->referredUser)
                                <a href="{{ route('cockpit.user.show', $referral->referredUser->id) }}">
                                    {{ $referral->referredUser->name }} (#{{ $referral->referredUser->id }})
                                </a>
                            @else
                                <span class="text-muted">Удален</span>
                            @endif
                        </dd>

                        <dt class="col-sm-3">Создано:</dt>
                        <dd class="col-sm-9">{{ $referral->created_at->format('d.m.Y H:i:s') }}</dd>

                        <dt class="col-sm-3">Обновлено:</dt>
                        <dd class="col-sm-9">{{ $referral->updated_at->format('d.m.Y H:i:s') }}</dd>
                    </dl>
                </div>
                <div class="card-footer">
                    <form action="{{ route('cockpit.referral.destroy', $referral) }}" method="POST" class="d-inline" onsubmit="return confirm('Вы уверены?');">
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

