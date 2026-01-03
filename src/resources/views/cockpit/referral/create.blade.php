@extends('cockpit.layout')

@section('title', 'Создать реферальную связь')
@section('page-title', 'Создать реферальную связь')

@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Новая реферальная связь</h3>
                </div>
                <form action="{{ route('cockpit.referral.store') }}" method="POST">
                    @csrf
                    <div class="card-body">
                        <div class="form-group">
                            <label for="user_id">Кто пригласил <span class="text-danger">*</span></label>
                            <select name="user_id" id="user_id" class="form-control @error('user_id') is-invalid @enderror" required>
                                <option value="">Выберите пользователя</option>
                                @foreach($users as $user)
                                    <option value="{{ $user->id }}" {{ old('user_id') == $user->id ? 'selected' : '' }}>{{ $user->name }} (#{{ $user->id }})</option>
                                @endforeach
                            </select>
                            @error('user_id')
                                <span class="invalid-feedback"><strong>{{ $message }}</strong></span>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label for="referred_user_id">Кого пригласили <span class="text-danger">*</span></label>
                            <select name="referred_user_id" id="referred_user_id" class="form-control @error('referred_user_id') is-invalid @enderror" required>
                                <option value="">Выберите пользователя</option>
                                @foreach($users as $user)
                                    <option value="{{ $user->id }}" {{ old('referred_user_id') == $user->id ? 'selected' : '' }}>{{ $user->name }} (#{{ $user->id }})</option>
                                @endforeach
                            </select>
                            @error('referred_user_id')
                                <span class="invalid-feedback"><strong>{{ $message }}</strong></span>
                            @enderror
                        </div>
                    </div>
                    <div class="card-footer">
                        <button type="submit" class="btn btn-primary">Создать</button>
                        <a href="{{ route('cockpit.referral.index') }}" class="btn btn-default">Отмена</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

