@extends('layouts.app')

@section('title', 'Новий пароль — English Cards')

@section('content')
    <section class="panel" aria-labelledby="page-title">
        <p class="eyebrow">Доступ до акаунта</p>
        <h1 id="page-title">Задайте новий пароль</h1>
        <form method="post" action="{{ route('password.store') }}" class="form-stack">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <div class="field">
                <label for="email">Електронна пошта</label>
                <input id="email" name="email" type="email" value="{{ old('email', $email) }}" autocomplete="email" required aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}" @error('email') aria-describedby="email-error" @enderror>
                @error('email') <p id="email-error" class="field-error">{{ $message }}</p> @enderror
            </div>
            <div class="field">
                <label for="password">Новий пароль</label>
                <input id="password" name="password" type="password" autocomplete="new-password" required aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}" @error('password') aria-describedby="password-error" @enderror>
                @error('password') <p id="password-error" class="field-error">{{ $message }}</p> @enderror
            </div>
            <div class="field">
                <label for="password_confirmation">Підтвердження нового пароля</label>
                <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required>
            </div>
            <button class="button button-primary" type="submit">Змінити пароль</button>
        </form>
        <p class="form-footer"><a href="{{ route('login') }}">Повернутися до входу</a></p>
    </section>
@endsection
