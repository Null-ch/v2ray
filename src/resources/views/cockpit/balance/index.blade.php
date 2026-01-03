@extends('cockpit.layout')

@section('title', 'Балансы')
@section('page-title', 'Балансы')

@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Список балансов</h3>
                    <div class="card-tools">
                        <a href="{{ route('cockpit.balance.create') }}" class="btn btn-primary btn-sm">
                            <i class="fas fa-plus"></i> Добавить баланс
                        </a>
                    </div>
                </div>
                <div class="card-body table-responsive p-0">
                    <table class="table table-hover text-nowrap">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Пользователь</th>
                                <th>Баланс</th>
                                <th>Создан</th>
                                <th>Действия</th>
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
                                        <span class="badge badge-success">{{ number_format($balance->balance, 2) }}</span>
                                    </td>
                                    <td>{{ $balance->created_at->format('d.m.Y H:i') }}</td>
                                    <td>
                                        <div class="btn-group">
                                            <a href="{{ route('cockpit.balance.show', $balance) }}" class="btn btn-info btn-sm">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="{{ route('cockpit.balance.edit', $balance) }}" class="btn btn-warning btn-sm">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <form action="{{ route('cockpit.balance.destroy', $balance) }}" method="POST" class="d-inline" onsubmit="return confirm('Вы уверены?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-danger btn-sm">
                                                    <i class="fas fa-trash"></i>
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
                <div class="card-footer clearfix">
                    {{ $balances->links() }}
                </div>
            </div>
        </div>
    </div>
@endsection

