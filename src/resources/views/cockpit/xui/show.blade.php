@extends('cockpit.layout')

@section('title', 'Просмотр XUI Сервера')
@section('page-title', 'Просмотр XUI Сервера')

@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">XUI Сервер #{{ $xui->id }}</h3>
                    <div class="card-tools">
                        <a href="{{ route('cockpit.xui.edit', $xui) }}" class="btn btn-warning btn-sm">
                            <i class="fas fa-edit"></i> Редактировать
                        </a>
                        <a href="{{ route('cockpit.xui.index') }}" class="btn btn-default btn-sm">
                            <i class="fas fa-arrow-left"></i> Назад
                        </a>
                    </div>
                </div>
                <!-- /.card-header -->
                <div class="card-body">
                    <dl class="row">
                        <dt class="col-sm-3">ID:</dt>
                        <dd class="col-sm-9">{{ $xui->id }}</dd>

                        <dt class="col-sm-3">Тег:</dt>
                        <dd class="col-sm-9">
                            <span class="badge badge-info">{{ $xui->tag_label }}</span>
                            <small class="text-muted">({{ $xui->tag->value }})</small>
                        </dd>

                        <dt class="col-sm-3">Хост:</dt>
                        <dd class="col-sm-9">{{ $xui->host }}</dd>

                        <dt class="col-sm-3">Порт:</dt>
                        <dd class="col-sm-9">{{ $xui->port }}</dd>

                        <dt class="col-sm-3">Путь:</dt>
                        <dd class="col-sm-9">{{ $xui->path }}</dd>

                        <dt class="col-sm-3">Имя пользователя:</dt>
                        <dd class="col-sm-9">{{ $xui->username }}</dd>

                        <dt class="col-sm-3">Пароль:</dt>
                        <dd class="col-sm-9">
                            <code>••••••••</code>
                        </dd>

                        <dt class="col-sm-3">SSL:</dt>
                        <dd class="col-sm-9">
                            @if($xui->ssl)
                                <span class="badge badge-success">Да</span>
                            @else
                                <span class="badge badge-secondary">Нет</span>
                            @endif
                        </dd>

                        <dt class="col-sm-3">Активен:</dt>
                        <dd class="col-sm-9">
                            @if($xui->is_active)
                                <span class="badge badge-success">Активен</span>
                            @else
                                <span class="badge badge-danger">Неактивен</span>
                            @endif
                        </dd>

                        <dt class="col-sm-3">Создан:</dt>
                        <dd class="col-sm-9">{{ $xui->created_at->format('d.m.Y H:i:s') }}</dd>

                        <dt class="col-sm-3">Обновлен:</dt>
                        <dd class="col-sm-9">{{ $xui->updated_at->format('d.m.Y H:i:s') }}</dd>
                    </dl>
                </div>
                <!-- /.card-body -->
                <div class="card-footer">
                    <form action="{{ route('cockpit.xui.destroy', $xui) }}" method="POST" class="d-inline" onsubmit="return confirm('Вы уверены, что хотите удалить этот сервер?');">
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

