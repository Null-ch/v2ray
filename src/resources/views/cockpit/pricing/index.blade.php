@extends('cockpit.layout')

@section('title', 'Тарифы')
@section('page-title', 'Тарифы')

@section('content')
<div class="page-header d-print-none">
  <div class="row align-items-center">
    <div class="col">
      <h2 class="page-title">Тарифы</h2>
      <div class="text-secondary">Управление тарифными планами</div>
    </div>
    <div class="col-auto ms-auto d-print-none">
      <a href="{{ route('cockpit.pricing.create') }}" class="btn btn-primary">
        <i class="ti ti-plus"></i> Добавить тариф
      </a>
    </div>
  </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Список тарифов</h3>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-vcenter">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Название</th>
                                <th>Длительность</th>
                                <th>Стоимость</th>
                                <th>Создан</th>
                                <th class="w-1">Действия</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($pricings as $pricing)
                                <tr>
                                    <td>{{ $pricing->id }}</td>
                                    <td>{{ $pricing->title }}</td>
                                    <td>
                                        @php
                                            $days = round($pricing->duration / (24 * 60 * 60 * 1000));
                                        @endphp
                                        {{ $days }} {{ $days == 1 ? 'день' : ($days < 5 ? 'дня' : 'дней') }}
                                        <small class="text-muted">({{ number_format($pricing->duration) }} мс)</small>
                                    </td>
                                    <td>{{ number_format($pricing->price, 2) }} ₽</td>
                                    <td>{{ $pricing->created_at->format('d.m.Y H:i') }}</td>
                                    <td>
                                        <div class="btn-list">
                                            <a href="{{ route('cockpit.pricing.show', $pricing) }}" class="btn btn-info btn-sm btn-glass" title="Просмотр">
                                                <i class="ti ti-eye"></i>
                                            </a>
                                            <a href="{{ route('cockpit.pricing.edit', $pricing) }}" class="btn btn-warning btn-sm btn-glass" title="Редактировать">
                                                <i class="ti ti-edit"></i>
                                            </a>
                                            <form action="{{ route('cockpit.pricing.destroy', $pricing) }}" method="POST" class="d-inline" data-confirm="Вы уверены, что хотите удалить этот тариф?" data-ajax="delete">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-danger btn-sm btn-glass" title="Удалить">
                                                    <i class="ti ti-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center">Нет тарифов</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer">
                {{ $pricings->links() }}
            </div>
        </div>
    </div>
</div>
@endsection
