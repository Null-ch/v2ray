@extends('cockpit.layout')

@section('title', 'Создать подписку')
@section('page-title', 'Создать подписку')

@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Новая подписка</h3>
                </div>
                <form action="{{ route('cockpit.subscription.store') }}" method="POST">
                    @csrf
                    <div class="card-body">
                        <div class="form-group">
                            <label for="user_id">Пользователь <span class="text-danger">*</span></label>
                            <select name="user_id" id="user_id" class="form-control @error('user_id') is-invalid @enderror" required>
                                <option value="">Выберите пользователя</option>
                                @foreach($users as $user)
                                    <option value="{{ $user->id }}" {{ old('user_id') == $user->id ? 'selected' : '' }}>
                                        {{ $user->name ?? $user->tg_tag ?? 'ID: '.$user->id }}
                                    </option>
                                @endforeach
                            </select>
                            @error('user_id')
                                <span class="invalid-feedback" role="alert">
                                    <strong>{{ $message }}</strong>
                                </span>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label for="uuid">UUID <span class="text-danger">*</span></label>
                            <input type="text" name="uuid" id="uuid" class="form-control @error('uuid') is-invalid @enderror" value="{{ old('uuid') }}" required>
                            @error('uuid')
                                <span class="invalid-feedback" role="alert">
                                    <strong>{{ $message }}</strong>
                                </span>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label for="xui_id">XUI сервер <span class="text-danger">*</span></label>
                            <select name="xui_id" id="xui_id" class="form-control @error('xui_id') is-invalid @enderror" required>
                                <option value="">Выберите XUI сервер</option>
                                @foreach($xuis as $xui)
                                    <option value="{{ $xui->id }}" {{ old('xui_id') == $xui->id ? 'selected' : '' }}>
                                        {{ $xui->tag->label() }} — {{ $xui->host }}
                                    </option>
                                @endforeach
                            </select>
                            @error('xui_id')
                                <span class="invalid-feedback" role="alert">
                                    <strong>{{ $message }}</strong>
                                </span>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label for="expires_at">Дата окончания</label>
                            <input
                                type="datetime-local"
                                name="expires_at"
                                id="expires_at"
                                class="form-control @error('expires_at') is-invalid @enderror"
                                value="{{ old('expires_at') }}"
                            >
                            @error('expires_at')
                                <span class="invalid-feedback" role="alert">
                                    <strong>{{ $message }}</strong>
                                </span>
                            @enderror
                        </div>
                    </div>
                    <div class="card-footer">
                        <button type="submit" class="btn btn-primary">Создать</button>
                        <a href="{{ route('cockpit.subscription.index') }}" class="btn btn-default">Отмена</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection


