@extends('cockpit.layout')

@section('title', 'Просмотр настройки')
@section('page-title', 'Просмотр настройки')

@section('content')
    <div class="row">
        <div class="col-md-8">
            <div class="card card-primary">
                <div class="card-header">
                    <h3 class="card-title">Настройка #{{ $setting->id }}</h3>
                </div>
                <div class="card-body">
                    <dl class="row">
                        <dt class="col-sm-3">Ключ</dt>
                        <dd class="col-sm-9"><code>{{ $setting->key }}</code></dd>

                        <dt class="col-sm-3">Тип</dt>
                        <dd class="col-sm-9">{{ $setting->type }}</dd>

                        <dt class="col-sm-3">Группа</dt>
                        <dd class="col-sm-9">{{ $setting->group ?? '-' }}</dd>

                        <dt class="col-sm-3">Описание</dt>
                        <dd class="col-sm-9">{{ $setting->description ?? '-' }}</dd>

                        <dt class="col-sm-3">Системная</dt>
                        <dd class="col-sm-9">
                            @if($setting->is_system)
                                <span class="badge badge-danger">Да</span>
                            @else
                                <span class="badge badge-secondary">Нет</span>
                            @endif
                        </dd>

                        <dt class="col-sm-3">Значение (сырое)</dt>
                        <dd class="col-sm-9">
                            <pre class="mb-0" style="white-space: pre-wrap; word-break: break-all;">{{ $setting->value }}</pre>
                        </dd>
                    </dl>
                </div>
                <div class="card-footer">
                    <a href="{{ route('cockpit.setting.edit', $setting) }}" class="btn btn-warning">
                        <i class="fas fa-edit"></i> Редактировать
                    </a>
                    <a href="{{ route('cockpit.setting.index') }}" class="btn btn-secondary">К списку</a>
                </div>
            </div>
        </div>
    </div>
@endsection


