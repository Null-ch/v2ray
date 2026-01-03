@extends('cockpit.layout')

@section('title', 'Создать баланс')
@section('page-title', 'Создать баланс')

@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Новый баланс</h3>
                </div>
                <form action="{{ route('cockpit.balance.store') }}" method="POST">
                    @csrf
                    <div class="card-body">
                        <div class="form-group">
                            <label for="user_id">Пользователь <span class="text-danger">*</span></label>
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
                            <label for="balance">Баланс <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" min="0" name="balance" id="balance" class="form-control @error('balance') is-invalid @enderror" value="{{ old('balance', 0) }}" required>
                            @error('balance')
                                <span class="invalid-feedback"><strong>{{ $message }}</strong></span>
                            @enderror
                        </div>
                    </div>
                    <div class="card-footer">
                        <button type="submit" class="btn btn-primary">Создать</button>
                        <a href="{{ route('cockpit.balance.index') }}" class="btn btn-default">Отмена</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

