@extends('cockpit.layout')

@section('title', 'Рефералы')
@section('page-title', 'Рефералы')

@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Список реферальных связей</h3>
                    <div class="card-tools">
                        <a href="{{ route('cockpit.referral.create') }}" class="btn btn-primary btn-sm">
                            <i class="fas fa-plus"></i> Добавить связь
                        </a>
                    </div>
                </div>
                <div class="card-body table-responsive p-0">
                    <table class="table table-hover text-nowrap">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Кто пригласил</th>
                                <th>Кого пригласили</th>
                                <th>Создано</th>
                                <th>Действия</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($referrals as $referral)
                                <tr>
                                    <td>{{ $referral->id }}</td>
                                    <td>
                                        @if($referral->user)
                                            <a href="{{ route('cockpit.user.show', $referral->user->id) }}">
                                                {{ $referral->user->name }} (#{{ $referral->user->id }})
                                            </a>
                                        @else
                                            <span class="text-muted">Удален</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($referral->referredUser)
                                            <a href="{{ route('cockpit.user.show', $referral->referredUser->id) }}">
                                                {{ $referral->referredUser->name }} (#{{ $referral->referredUser->id }})
                                            </a>
                                        @else
                                            <span class="text-muted">Удален</span>
                                        @endif
                                    </td>
                                    <td>{{ $referral->created_at->format('d.m.Y H:i') }}</td>
                                    <td>
                                        <div class="btn-group">
                                            <a href="{{ route('cockpit.referral.show', $referral) }}" class="btn btn-info btn-sm">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="{{ route('cockpit.referral.edit', $referral) }}" class="btn btn-warning btn-sm">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <form action="{{ route('cockpit.referral.destroy', $referral) }}" method="POST" class="d-inline" onsubmit="return confirm('Вы уверены?');">
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
                                    <td colspan="5" class="text-center">Нет реферальных связей</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="card-footer clearfix">
                    {{ $referrals->links() }}
                </div>
            </div>
        </div>
    </div>
@endsection

