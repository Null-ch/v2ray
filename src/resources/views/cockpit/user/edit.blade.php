@extends('cockpit.layout')

@section('title', 'Редактировать пользователя')
@section('page-title', 'Редактировать пользователя')

@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Редактирование пользователя #{{ $user->id }}</h3>
                </div>
                <form action="{{ route('cockpit.user.update', $user) }}" method="POST">
                    @csrf
                    @method('PUT')
                    <div class="card-body">
                        <div class="form-group">
                            <label for="name">Имя <span class="text-danger">*</span></label>
                            <input type="text" name="name" id="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $user->name) }}" required>
                            @error('name')
                                <span class="invalid-feedback"><strong>{{ $message }}</strong></span>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label for="tg_tag">TG Tag</label>
                            <input type="text" name="tg_tag" id="tg_tag" class="form-control @error('tg_tag') is-invalid @enderror" value="{{ old('tg_tag', $user->tg_tag) }}">
                            @error('tg_tag')
                                <span class="invalid-feedback"><strong>{{ $message }}</strong></span>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label for="tg_id">TG ID <span class="text-danger">*</span></label>
                            <input type="number" name="tg_id" id="tg_id" class="form-control @error('tg_id') is-invalid @enderror" value="{{ old('tg_id', $user->tg_id) }}" required>
                            @error('tg_id')
                                <span class="invalid-feedback"><strong>{{ $message }}</strong></span>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label for="uuid">UUID <span class="text-danger">*</span></label>
                            <input type="text" name="uuid" id="uuid" class="form-control @error('uuid') is-invalid @enderror" value="{{ old('uuid', $user->uuid) }}" required>
                            @error('uuid')
                                <span class="invalid-feedback"><strong>{{ $message }}</strong></span>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label for="referrer_id">Реферер</label>
                            <select name="referrer_id" id="referrer_id" class="form-control @error('referrer_id') is-invalid @enderror">
                                <option value="">Нет реферера</option>
                                @foreach($users as $u)
                                    <option value="{{ $u->id }}" {{ old('referrer_id', $user->referrer_id) == $u->id ? 'selected' : '' }}>{{ $u->name }} (#{{ $u->id }})</option>
                                @endforeach
                            </select>
                            @error('referrer_id')
                                <span class="invalid-feedback"><strong>{{ $message }}</strong></span>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label for="referral_code">Реферальный код</label>
                            <input type="text" name="referral_code" id="referral_code" class="form-control @error('referral_code') is-invalid @enderror" value="{{ old('referral_code', $user->referral_code) }}">
                            <small class="form-text text-muted">Уникальный код для реферальной программы</small>
                            @error('referral_code')
                                <span class="invalid-feedback"><strong>{{ $message }}</strong></span>
                            @enderror
                        </div>
                    </div>
                    <div class="card-footer">
                        <button type="submit" class="btn btn-primary">Сохранить</button>
                        <a href="{{ route('cockpit.user.index') }}" class="btn btn-default">Отмена</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

