@extends('cockpit.layout')

@section('title', 'Пользователи')
@section('page-title', 'Пользователи')

@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Список пользователей</h3>
                    <div class="card-tools">
                        <a href="{{ route('cockpit.user.create') }}" class="btn btn-primary btn-sm">
                            <i class="fas fa-plus"></i> Добавить пользователя
                        </a>
                    </div>
                </div>
                <div class="card-body table-responsive p-0">
                    <table class="table table-hover text-nowrap">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Имя</th>
                                <th>TG Tag</th>
                                <th>Телефон</th>
                                <th>TG ID</th>
                                <th>UUID</th>
                                <th>Реферер</th>
                                <th>Реферальный код</th>
                                <th>Баланс</th>
                                <th>Создан</th>
                                <th>Действия</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($users as $user)
                                <tr>
                                    <td>{{ $user->id }}</td>
                                    <td>{{ $user->name }}</td>
                                    <td>{{ $user->tg_tag ?? '-' }}</td>
                                    <td>{{ $user->phone_number }}</td>
                                    <td>{{ $user->tg_id }}</td>
                                    <td><code>{{ $user->uuid }}</code></td>
                                    <td>
                                        @if($user->referrer)
                                            <a href="{{ route('cockpit.user.show', $user->referrer->id) }}">
                                                {{ $user->referrer->name }} (#{{ $user->referrer->id }})
                                            </a>
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($user->referral_code)
                                            <code>{{ $user->referral_code }}</code>
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($user->balance)
                                            <span class="badge badge-success">{{ number_format($user->balance->balance, 2) }}</span>
                                        @else
                                            <span class="badge badge-secondary">0.00</span>
                                        @endif
                                    </td>
                                    <td>{{ $user->created_at->format('d.m.Y H:i') }}</td>
                                    <td>
                                        <div class="btn-group">
                                            <a href="{{ route('cockpit.user.show', $user) }}" class="btn btn-info btn-sm" title="Просмотр">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="{{ route('cockpit.user.edit', $user) }}" class="btn btn-warning btn-sm" title="Редактировать">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <form action="{{ route('cockpit.user.destroy', $user) }}" method="POST" class="d-inline" onsubmit="return confirm('Вы уверены, что хотите удалить этого пользователя?');">
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
                                    <td colspan="11" class="text-center">Нет пользователей</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="card-footer clearfix">
                    {{ $users->links() }}
                </div>
            </div>
        </div>
    </div>
@endsection

