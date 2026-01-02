@extends('cockpit.layout')

@section('title', 'Создать пользователя')
@section('page-title', 'Создать пользователя')

@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Новый пользователь</h3>
                </div>
                <form action="{{ route('cockpit.user.store') }}" method="POST">
                    @csrf
                    <div class="card-body">
                        <div class="form-group">
                            <label for="name">Имя <span class="text-danger">*</span></label>
                            <input type="text" name="name" id="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}" required>
                            @error('name')
                                <span class="invalid-feedback"><strong>{{ $message }}</strong></span>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label for="tg_tag">TG Tag</label>
                            <input type="text" name="tg_tag" id="tg_tag" class="form-control @error('tg_tag') is-invalid @enderror" value="{{ old('tg_tag') }}">
                            @error('tg_tag')
                                <span class="invalid-feedback"><strong>{{ $message }}</strong></span>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label for="phone_number">Телефон <span class="text-danger">*</span></label>
                            <input type="text" name="phone_number" id="phone_number" class="form-control @error('phone_number') is-invalid @enderror" value="{{ old('phone_number') }}" required>
                            @error('phone_number')
                                <span class="invalid-feedback"><strong>{{ $message }}</strong></span>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label for="tg_id">TG ID <span class="text-danger">*</span></label>
                            <input type="number" name="tg_id" id="tg_id" class="form-control @error('tg_id') is-invalid @enderror" value="{{ old('tg_id') }}" required>
                            @error('tg_id')
                                <span class="invalid-feedback"><strong>{{ $message }}</strong></span>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label for="uuid">UUID <span class="text-danger">*</span></label>
                            <input type="text" name="uuid" id="uuid" class="form-control @error('uuid') is-invalid @enderror" value="{{ old('uuid') }}" required>
                            @error('uuid')
                                <span class="invalid-feedback"><strong>{{ $message }}</strong></span>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label for="referrer_id">Реферер</label>
                            <select name="referrer_id" id="referrer_id" class="form-control @error('referrer_id') is-invalid @enderror">
                                <option value="">Нет реферера</option>
                                @foreach($users as $u)
                                    <option value="{{ $u->id }}" {{ old('referrer_id') == $u->id ? 'selected' : '' }}>{{ $u->name }} (#{{ $u->id }})</option>
                                @endforeach
                            </select>
                            @error('referrer_id')
                                <span class="invalid-feedback"><strong>{{ $message }}</strong></span>
                            @enderror
                        </div>
                    </div>
                    <div class="card-footer">
                        <button type="submit" class="btn btn-primary">Создать</button>
                        <a href="{{ route('cockpit.user.index') }}" class="btn btn-default">Отмена</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

