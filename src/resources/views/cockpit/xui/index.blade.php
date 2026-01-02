@extends('cockpit.layout')

@section('title', 'XUI Серверы')
@section('page-title', 'XUI Серверы')

@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Список XUI серверов</h3>
                    <div class="card-tools">
                        <a href="{{ route('cockpit.xui.create') }}" class="btn btn-primary btn-sm">
                            <i class="fas fa-plus"></i> Добавить сервер
                        </a>
                    </div>
                </div>
                <!-- /.card-header -->
                <div class="card-body table-responsive p-0">
                    <table class="table table-hover text-nowrap">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Тег</th>
                                <th>Хост</th>
                                <th>Порт</th>
                                <th>Путь</th>
                                <th>Пользователь</th>
                                <th>SSL</th>
                                <th>Активен</th>
                                <th>Создан</th>
                                <th>Действия</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($xuis as $xui)
                                <tr>
                                    <td>{{ $xui->id }}</td>
                                    <td>
                                        <span class="badge badge-info">{{ $xui->tag_label }}</span>
                                    </td>
                                    <td>{{ $xui->host }}</td>
                                    <td>{{ $xui->port }}</td>
                                    <td>{{ $xui->path }}</td>
                                    <td>{{ $xui->username }}</td>
                                    <td>
                                        @if($xui->ssl)
                                            <span class="badge badge-success">Да</span>
                                        @else
                                            <span class="badge badge-secondary">Нет</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($xui->is_active)
                                            <span class="badge badge-success">Активен</span>
                                        @else
                                            <span class="badge badge-danger">Неактивен</span>
                                        @endif
                                    </td>
                                    <td>{{ $xui->created_at->format('d.m.Y H:i') }}</td>
                                    <td>
                                        <div class="btn-group">
                                            <a href="{{ route('cockpit.xui.show', $xui) }}" class="btn btn-info btn-sm" title="Просмотр">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="{{ route('cockpit.xui.edit', $xui) }}" class="btn btn-warning btn-sm" title="Редактировать">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <form action="{{ route('cockpit.xui.destroy', $xui) }}" method="POST" class="d-inline" onsubmit="return confirm('Вы уверены, что хотите удалить этот сервер?');">
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
                                    <td colspan="10" class="text-center">Нет серверов</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <!-- /.card-body -->
                <div class="card-footer clearfix">
                    {{ $xuis->links() }}
                </div>
            </div>
            <!-- /.card -->
        </div>
    </div>
@endsection

