@extends('cockpit.layout')

@section('title', 'Просмотр тарифа')
@section('page-title', 'Просмотр тарифа')

@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Тариф #{{ $pricing->id }}</h3>
                    <div class="card-tools">
                        <a href="{{ route('cockpit.pricing.edit', $pricing) }}" class="btn btn-warning btn-sm">
                            <i class="fas fa-edit"></i> Редактировать
                        </a>
                        <a href="{{ route('cockpit.pricing.index') }}" class="btn btn-default btn-sm">
                            <i class="fas fa-arrow-left"></i> Назад
                        </a>
                    </div>
                </div>
                <!-- /.card-header -->
                <div class="card-body">
                    <dl class="row">
                        <dt class="col-sm-3">ID:</dt>
                        <dd class="col-sm-9">{{ $pricing->id }}</dd>

                        <dt class="col-sm-3">Название:</dt>
                        <dd class="col-sm-9">{{ $pricing->title }}</dd>

                        <dt class="col-sm-3">Длительность:</dt>
                        <dd class="col-sm-9">
                            @php
                                $days = round($pricing->duration / (24 * 60 * 60 * 1000));
                            @endphp
                            {{ $days }} {{ $days == 1 ? 'день' : ($days < 5 ? 'дня' : 'дней') }}
                            <small class="text-muted">({{ number_format($pricing->duration) }} мс)</small>
                        </dd>

                        <dt class="col-sm-3">Стоимость:</dt>
                        <dd class="col-sm-9">{{ number_format($pricing->price, 2) }} ₽</dd>

                        <dt class="col-sm-3">Создан:</dt>
                        <dd class="col-sm-9">{{ $pricing->created_at->format('d.m.Y H:i:s') }}</dd>

                        <dt class="col-sm-3">Обновлен:</dt>
                        <dd class="col-sm-9">{{ $pricing->updated_at->format('d.m.Y H:i:s') }}</dd>
                    </dl>
                </div>
                <!-- /.card-body -->
                <div class="card-footer">
                    <form action="{{ route('cockpit.pricing.destroy', $pricing) }}" method="POST" class="d-inline" onsubmit="return confirm('Вы уверены, что хотите удалить этот тариф?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-trash"></i> Удалить
                        </button>
                    </form>
                </div>
            </div>
            <!-- /.card -->
        </div>
    </div>
@endsection

