@extends('cockpit.layout')

@section('title', 'Настройки')
@section('page-title', 'Настройки')

@section('content')
<div class="page-header d-print-none">
  <div class="row align-items-center">
    <div class="col">
      <h2 class="page-title">Настройки</h2>
      <div class="text-secondary">Управление системными настройками</div>
    </div>
    <div class="col-auto ms-auto d-print-none">
      <a href="{{ route('cockpit.setting.create') }}" class="btn btn-primary">
        <i class="ti ti-plus"></i> Добавить настройку
      </a>
    </div>
  </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Список настроек</h3>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-vcenter">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Ключ</th>
                                <th>Тип</th>
                                <th>Группа</th>
                                <th>Системная</th>
                                <th>Обновлена</th>
                                <th class="w-1">Действия</th>
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
                                            <span class="badge bg-red">Да</span>
                                        @else
                                            <span class="badge bg-secondary">Нет</span>
                                        @endif
                                    </td>
                                    <td>{{ $setting->updated_at?->format('d.m.Y H:i') }}</td>
                                    <td>
                                        <div class="btn-list">
                                            <a href="{{ route('cockpit.setting.show', $setting) }}" class="btn btn-info btn-sm btn-glass" title="Просмотр">
                                                <i class="ti ti-eye"></i>
                                            </a>
                                            <a href="{{ route('cockpit.setting.edit', $setting) }}" class="btn btn-warning btn-sm btn-glass" title="Редактировать">
                                                <i class="ti ti-edit"></i>
                                            </a>
                                            @unless($setting->is_system)
                                                <form action="{{ route('cockpit.setting.destroy', $setting) }}" method="POST" class="d-inline" data-confirm="Вы уверены, что хотите удалить эту настройку?" data-ajax="delete">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-danger btn-sm btn-glass" title="Удалить">
                                                        <i class="ti ti-trash"></i>
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
            </div>
            <div class="card-footer">
                {{ $settings->links() }}
            </div>
        </div>
    </div>
</div>
@endsection
