@extends('layouts.app')

@section('title', 'Налаштування профілю — English Cards')

@section('content')
    <section class="panel" aria-labelledby="page-title">
        <h1 id="page-title">Налаштування профілю</h1>
        <p class="intro">{{ $profile->name }}</p>

        <form method="post" action="{{ route('profiles.update', $profile) }}" class="form-stack">
            @csrf
            @method('PATCH')
            <div class="field">
                <label for="name">Назва профілю</label>
                <input id="name" name="name" type="text" value="{{ old('name', $profile->name) }}" maxlength="100" required aria-invalid="{{ $errors->has('name') ? 'true' : 'false' }}" @error('name') aria-describedby="name-error" @enderror>
                @error('name') <p id="name-error" class="field-error">{{ $message }}</p> @enderror
            </div>
            <button class="button button-primary" type="submit">Зберегти назву</button>
        </form>

        <div class="profile-setting">
            <h2>Тема оформлення</h2>
            <p class="hint"><span data-theme-current-label>{{ $themeLabel }}</span>. Системна тема повторює налаштування вашого пристрою.</p>
            @if ($isActive)
                @include('profiles.partials.theme-menu', ['themeProfile' => $profile])
            @else
                <p class="hint">Щоб змінити тему, спочатку оберіть цей профіль.</p>
            @endif
        </div>

        <div class="profile-actions profile-actions-bottom">
            <form method="post" action="{{ route('profiles.activate', $profile) }}">
                @csrf
                <button class="button button-secondary" type="submit">Обрати цей профіль</button>
            </form>
            <a class="button button-secondary" href="{{ route('profiles.index') }}">Усі профілі</a>
        </div>
    </section>
@endsection
