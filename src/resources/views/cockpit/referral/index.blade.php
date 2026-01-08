@extends('cockpit.layout')

@section('title', 'Рефералы')
@section('page-title', 'Рефералы')

@section('content')
<div class="page-header d-print-none">
  <div class="row align-items-center">
    <div class="col">
      <h2 class="page-title">Рефералы</h2>
      <div class="text-secondary">Управление реферальными связями</div>
    </div>
    <div class="col-auto ms-auto d-print-none">
      <a href="{{ route('cockpit.referral.create') }}" class="btn btn-primary">
        <i class="ti ti-plus"></i> Добавить связь
      </a>
    </div>
  </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Список реферальных связей</h3>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-vcenter">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Кто пригласил</th>
                                <th>Кого пригласили</th>
                                <th>Создано</th>
                                <th class="w-1">Действия</th>
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
                                        <div class="btn-list">
                                            <a href="{{ route('cockpit.referral.show', $referral) }}" class="btn btn-info btn-sm btn-glass" title="Просмотр">
                                                <i class="ti ti-eye"></i>
                                            </a>
                                            <a href="{{ route('cockpit.referral.edit', $referral) }}" class="btn btn-warning btn-sm btn-glass" title="Редактировать">
                                                <i class="ti ti-edit"></i>
                                            </a>
                                            <form action="{{ route('cockpit.referral.destroy', $referral) }}" method="POST" class="d-inline" data-confirm="Вы уверены?" data-ajax="delete">
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
                                    <td colspan="5" class="text-center">Нет реферальных связей</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer">
                {{ $referrals->links() }}
            </div>
        </div>
    </div>
</div>
@endsection
