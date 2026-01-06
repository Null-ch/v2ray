@extends('cockpit.layout')

@section('title', 'Настройки')
@section('page-title', 'Настройки')

@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Список настроек</h3>
                    <div class="card-tools">
                        <a href="{{ route('cockpit.setting.create') }}" class="btn btn-primary btn-sm">
                            <i class="fas fa-plus"></i> Добавить настройку
                        </a>
                    </div>
                </div>
                <div class="card-body table-responsive p-0">
                    <table class="table table-hover text-nowrap">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Ключ</th>
                                <th>Тип</th>
                                <th>Группа</th>
                                <th>Системная</th>
                                <th>Обновлена</th>
                                <th>Действия</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($settings as $setting)
                                <tr>
                                    <td>{{ $setting->id }}</td>
                                    <td>{{ $setting->key }}</td>
                                    <td>{{ $setting->type }}</td>
                                    <td>{{ $setting->group ?? '-' }}</td>
                                    <td>
                                        @if($setting->is_system)
                                            <span class="badge badge-danger">Да</span>
                                        @else
                                            <span class="badge badge-secondary">Нет</span>
                                        @endif
                                    </td>
                                    <td>{{ $setting->updated_at?->format('d.m.Y H:i') }}</td>
                                    <td>
                                        <div class="btn-group">
                                            <a href="{{ route('cockpit.setting.show', $setting) }}" class="btn btn-info btn-sm" title="Просмотр">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="{{ route('cockpit.setting.edit', $setting) }}" class="btn btn-warning btn-sm" title="Редактировать">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            @unless($setting->is_system)
                                                <form action="{{ route('cockpit.setting.destroy', $setting) }}" method="POST" class="d-inline" onsubmit="return confirm('Вы уверены, что хотите удалить эту настройку?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-danger btn-sm" title="Удалить">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            @endunless
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center">Нет настроек</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="card-footer clearfix">
                    {{ $settings->links() }}
                </div>
            </div>
        </div>
    </div>
@endsection


