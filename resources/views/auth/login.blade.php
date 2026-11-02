@extends('layouts.app')

@section('title', 'Вхід — English Cards')

@section('content')
    <section class="panel" aria-labelledby="page-title">
        <p class="eyebrow">Повернення до навчання</p>
        <h1 id="page-title">Увійдіть в акаунт</h1>
        <p class="intro">Ваші навчальні профілі будуть доступні після входу.</p>

        <form method="post" action="{{ route('login') }}" class="form-stack">
            @csrf
            <div class="field">
                <label for="email">Електронна пошта</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}" @error('email') aria-describedby="email-error" @enderror>
                @error('email') <p id="email-error" class="field-error">{{ $message }}</p> @enderror
            </div>
            <div class="field">
                <label for="password">Пароль</label>
                <input id="password" name="password" type="password" autocomplete="current-password" required aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}" @error('password') aria-describedby="password-error" @enderror>
                @error('password') <p id="password-error" class="field-error">{{ $message }}</p> @enderror
            </div>
            <a class="text-link align-right" href="{{ route('password.request') }}">Забули пароль?</a>
            <button class="button button-primary" type="submit">Увійти</button>
        </form>
        <p class="form-footer">Ще немає акаунта? <a href="{{ route('register') }}">Зареєструватися</a></p>
    </section>
@endsection
