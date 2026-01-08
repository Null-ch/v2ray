@extends('cockpit.layout')

@section('title', 'Балансы')
@section('page-title', 'Балансы')

@section('content')
<div class="page-header d-print-none">
  <div class="row align-items-center">
    <div class="col">
      <h2 class="page-title">Балансы</h2>
      <div class="text-secondary">Управление балансами пользователей</div>
    </div>
    <div class="col-auto ms-auto d-print-none">
      <a href="{{ route('cockpit.balance.create') }}" class="btn btn-primary">
        <i class="ti ti-plus"></i> Добавить баланс
      </a>
    </div>
  </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Список балансов</h3>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-vcenter">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Пользователь</th>
                                <th>Баланс</th>
                                <th>Создан</th>
                                <th class="w-1">Действия</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($balances as $balance)
                                <tr>
                                    <td>{{ $balance->id }}</td>
                                    <td>
                                        @if($balance->user)
                                            <a href="{{ route('cockpit.user.show', $balance->user->id) }}">
                                                {{ $balance->user->name }} (#{{ $balance->user->id }})
                                            </a>
                                        @else
                                            <span class="text-muted">Удален</span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="badge bg-green">{{ number_format($balance->balance, 2) }}</span>
                                    </td>
                                    <td>{{ $balance->created_at->format('d.m.Y H:i') }}</td>
                                    <td>
                                        <div class="btn-list">
                                            <a href="{{ route('cockpit.balance.show', $balance) }}" class="btn btn-info btn-sm btn-glass" title="Просмотр">
                                                <i class="ti ti-eye"></i>
                                            </a>
                                            <a href="{{ route('cockpit.balance.edit', $balance) }}" class="btn btn-warning btn-sm btn-glass" title="Редактировать">
                                                <i class="ti ti-edit"></i>
                                            </a>
                                            <form action="{{ route('cockpit.balance.destroy', $balance) }}" method="POST" class="d-inline" data-confirm="Вы уверены?" data-ajax="delete">
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
                                    <td colspan="5" class="text-center">Нет балансов</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer">
                {{ $balances->links() }}
            </div>
        </div>
    </div>
</div>
@endsection
