@extends('cockpit.layout')

@section('title', 'Редактировать XUI Сервер')
@section('page-title', 'Редактировать XUI Сервер')

@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Редактирование XUI сервера #{{ $xui->id }}</h3>
                </div>
                <!-- /.card-header -->
                <form action="{{ route('cockpit.xui.update', $xui) }}" method="POST">
                    @csrf
                    @method('PUT')
                    <div class="card-body">
                        <div class="form-group">
                            <label for="tag">Тег <span class="text-danger">*</span></label>
                            <select name="tag" id="tag" class="form-control @error('tag') is-invalid @enderror" required>
                                <option value="">Выберите тег</option>
                                @foreach($tags as $value => $label)
                                    <option value="{{ $value }}" {{ old('tag', $xui->tag->value) == $value ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('tag')
                                <span class="invalid-feedback" role="alert">
                                    <strong>{{ $message }}</strong>
                                </span>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label for="host">Хост <span class="text-danger">*</span></label>
                            <input type="text" name="host" id="host" class="form-control @error('host') is-invalid @enderror" value="{{ old('host', $xui->host) }}" required>
                            @error('host')
                                <span class="invalid-feedback" role="alert">
                                    <strong>{{ $message }}</strong>
                                </span>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label for="port">Порт <span class="text-danger">*</span></label>
                            <input type="number" name="port" id="port" class="form-control @error('port') is-invalid @enderror" value="{{ old('port', $xui->port) }}" min="1" max="65535" required>
                            @error('port')
                                <span class="invalid-feedback" role="alert">
                                    <strong>{{ $message }}</strong>
                                </span>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label for="path">Путь <span class="text-danger">*</span></label>
                            <input type="text" name="path" id="path" class="form-control @error('path') is-invalid @enderror" value="{{ old('path', $xui->path) }}" required>
                            @error('path')
                                <span class="invalid-feedback" role="alert">
                                    <strong>{{ $message }}</strong>
                                </span>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label for="username">Имя пользователя <span class="text-danger">*</span></label>
                            <input type="text" name="username" id="username" class="form-control @error('username') is-invalid @enderror" value="{{ old('username', $xui->username) }}" required>
                            @error('username')
                                <span class="invalid-feedback" role="alert">
                                    <strong>{{ $message }}</strong>
                                </span>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label for="password">Пароль</label>
                            <input type="password" name="password" id="password" class="form-control @error('password') is-invalid @enderror" placeholder="Оставьте пустым, чтобы не изменять">
                            <small class="form-text text-muted">Оставьте пустым, если не хотите изменять пароль</small>
                            @error('password')
                                <span class="invalid-feedback" role="alert">
                                    <strong>{{ $message }}</strong>
                                </span>
                            @enderror
                        </div>

                        <div class="form-group">
                            <div class="custom-control custom-switch">
                                <input type="checkbox" name="ssl" id="ssl" class="custom-control-input" value="1" {{ old('ssl', $xui->ssl) ? 'checked' : '' }}>
                                <label class="custom-control-label" for="ssl">Использовать SSL</label>
                            </div>
                        </div>

                        <div class="form-group">
                            <div class="custom-control custom-switch">
                                <input type="checkbox" name="is_active" id="is_active" class="custom-control-input" value="1" {{ old('is_active', $xui->is_active) ? 'checked' : '' }}>
                                <label class="custom-control-label" for="is_active">Активен</label>
                            </div>
                        </div>
                    </div>
                    <!-- /.card-body -->
                    <div class="card-footer">
                        <button type="submit" class="btn btn-primary">Сохранить</button>
                        <a href="{{ route('cockpit.xui.index') }}" class="btn btn-default">Отмена</a>
                    </div>
                </form>
            </div>
            <!-- /.card -->
        </div>
    </div>
@endsection

