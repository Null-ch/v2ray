@extends('cockpit.layout')

@section('title', 'Создать XUI Сервер')
@section('page-title', 'Создать XUI Сервер')

@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Новый XUI сервер</h3>
                </div>
                <!-- /.card-header -->
                <form action="{{ route('cockpit.xui.store') }}" method="POST">
                    @csrf
                    <div class="card-body">
                        <div class="form-group">
                            <label for="tag">Тег <span class="text-danger">*</span></label>
                            <select name="tag" id="tag" class="form-control @error('tag') is-invalid @enderror" required>
                                <option value="">Выберите тег</option>
                                @foreach($tags as $value => $label)
                                    <option value="{{ $value }}" {{ old('tag') == $value ? 'selected' : '' }}>{{ $label }}</option>
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
                            <input type="text" name="host" id="host" class="form-control @error('host') is-invalid @enderror" value="{{ old('host') }}" required>
                            @error('host')
                                <span class="invalid-feedback" role="alert">
                                    <strong>{{ $message }}</strong>
                                </span>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label for="port">Порт <span class="text-danger">*</span></label>
                            <input type="number" name="port" id="port" class="form-control @error('port') is-invalid @enderror" value="{{ old('port', 2053) }}" min="1" max="65535" required>
                            @error('port')
                                <span class="invalid-feedback" role="alert">
                                    <strong>{{ $message }}</strong>
                                </span>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label for="path">Путь <span class="text-danger">*</span></label>
                            <input type="text" name="path" id="path" class="form-control @error('path') is-invalid @enderror" value="{{ old('path', '/') }}" required>
                            @error('path')
                                <span class="invalid-feedback" role="alert">
                                    <strong>{{ $message }}</strong>
                                </span>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label for="username">Имя пользователя <span class="text-danger">*</span></label>
                            <input type="text" name="username" id="username" class="form-control @error('username') is-invalid @enderror" value="{{ old('username') }}" required>
                            @error('username')
                                <span class="invalid-feedback" role="alert">
                                    <strong>{{ $message }}</strong>
                                </span>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label for="password">Пароль <span class="text-danger">*</span></label>
                            <input type="password" name="password" id="password" class="form-control @error('password') is-invalid @enderror" required>
                            @error('password')
                                <span class="invalid-feedback" role="alert">
                                    <strong>{{ $message }}</strong>
                                </span>
                            @enderror
                        </div>

                        <div class="form-group">
                            <div class="custom-control custom-switch">
                                <input type="checkbox" name="ssl" id="ssl" class="custom-control-input" value="1" {{ old('ssl', true) ? 'checked' : '' }}>
                                <label class="custom-control-label" for="ssl">Использовать SSL</label>
                            </div>
                        </div>

                        <div class="form-group">
                            <div class="custom-control custom-switch">
                                <input type="checkbox" name="is_active" id="is_active" class="custom-control-input" value="1" {{ old('is_active', true) ? 'checked' : '' }}>
                                <label class="custom-control-label" for="is_active">Активен</label>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="inbound_id">Inbound ID</label>
                            <input type="number" name="inbound_id" id="inbound_id" class="form-control @error('inbound_id') is-invalid @enderror" value="{{ old('inbound_id') }}" min="1" placeholder="Оставьте пустым для автоматического выбора">
                            <small class="form-text text-muted">ID inbound для использования по умолчанию. Если не указан, будет использован первый доступный inbound.</small>
                            @error('inbound_id')
                                <span class="invalid-feedback" role="alert">
                                    <strong>{{ $message }}</strong>
                                </span>
                            @enderror
                        </div>
                    </div>
                    <!-- /.card-body -->
                    <div class="card-footer">
                        <button type="submit" class="btn btn-primary">Создать</button>
                        <a href="{{ route('cockpit.xui.index') }}" class="btn btn-default">Отмена</a>
                    </div>
                </form>
            </div>
            <!-- /.card -->
        </div>
    </div>
@endsection

