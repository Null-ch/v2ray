@extends('cockpit.layout')

@section('title', 'Тарифы')
@section('page-title', 'Тарифы')

@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Список тарифов</h3>
                    <div class="card-tools">
                        <a href="{{ route('cockpit.pricing.create') }}" class="btn btn-primary btn-sm">
                            <i class="fas fa-plus"></i> Добавить тариф
                        </a>
                    </div>
                </div>
                <!-- /.card-header -->
                <div class="card-body table-responsive p-0">
                    <table class="table table-hover text-nowrap">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Название</th>
                                <th>Длительность</th>
                                <th>Стоимость</th>
                                <th>Создан</th>
                                <th>Действия</th>
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
                                        <div class="btn-group">
                                            <a href="{{ route('cockpit.pricing.show', $pricing) }}" class="btn btn-info btn-sm" title="Просмотр">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="{{ route('cockpit.pricing.edit', $pricing) }}" class="btn btn-warning btn-sm" title="Редактировать">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <form action="{{ route('cockpit.pricing.destroy', $pricing) }}" method="POST" class="d-inline" onsubmit="return confirm('Вы уверены, что хотите удалить этот тариф?');">
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
                                    <td colspan="6" class="text-center">Нет тарифов</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <!-- /.card-body -->
                <div class="card-footer clearfix">
                    {{ $pricings->links() }}
                </div>
            </div>
            <!-- /.card -->
        </div>
    </div>
@endsection

