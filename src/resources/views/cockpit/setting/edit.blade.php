@extends('cockpit.layout')

@section('title', 'Редактировать настройку')
@section('page-title', 'Редактировать настройку')

@section('content')
    <div class="row">
        <div class="col-md-8">
            <div class="card card-primary">
                <div class="card-header">
                    <h3 class="card-title">Настройка #{{ $setting->id }}</h3>
                </div>
                <form action="{{ route('cockpit.setting.update', $setting) }}" method="POST">
                    @csrf
                    @method('PUT')
                    <div class="card-body">
                        <div class="form-group">
                            <label for="key">Ключ</label>
                            <input type="text" name="key" id="key" class="form-control @error('key') is-invalid @enderror" value="{{ old('key', $setting->key) }}" required>
                            @error('key')
                                <span class="invalid-feedback">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label for="type">Тип</label>
                            <select name="type" id="type" class="form-control @error('type') is-invalid @enderror" required>
                                @php
                                    $types = ['string', 'int', 'bool', 'float', 'json'];
                                @endphp
                                @foreach($types as $type)
                                    <option value="{{ $type }}" @selected(old('type', $setting->type) === $type)>{{ $type }}</option>
                                @endforeach
                            </select>
                            @error('type')
                                <span class="invalid-feedback">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label for="value">Значение</label>
                            <textarea name="value" id="value" class="form-control @error('value') is-invalid @enderror" rows="3">{{ old('value', $setting->value) }}</textarea>
                            @error('value')
                                <span class="invalid-feedback">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label for="group">Группа</label>
                            <input type="text" name="group" id="group" class="form-control @error('group') is-invalid @enderror" value="{{ old('group', $setting->group) }}">
                            @error('group')
                                <span class="invalid-feedback">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label for="description">Описание</label>
                            <input type="text" name="description" id="description" class="form-control @error('description') is-invalid @enderror" value="{{ old('description', $setting->description) }}">
                            @error('description')
                                <span class="invalid-feedback">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-group form-check">
                            <input type="checkbox" name="is_system" id="is_system" class="form-check-input" value="1" {{ old('is_system', $setting->is_system) ? 'checked' : '' }}>
                            <label class="form-check-label" for="is_system">Системная настройка (нельзя удалить)</label>
                        </div>
                    </div>
                    <div class="card-footer">
                        <button type="submit" class="btn btn-primary">Сохранить</button>
                        <a href="{{ route('cockpit.setting.show', $setting) }}" class="btn btn-secondary">Отмена</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection


