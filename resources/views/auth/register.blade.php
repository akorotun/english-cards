@extends('layouts.app')

@section('title', 'Реєстрація — English Cards')

@section('content')
    <section class="panel" aria-labelledby="page-title">
        <p class="eyebrow">Початок навчання</p>
        <h1 id="page-title">Створіть акаунт</h1>
        <p class="intro">Вкажіть ім’я першого навчального профілю. Інші профілі можна буде додати пізніше.</p>

        <form method="post" action="{{ route('register') }}" class="form-stack">
            @csrf
            <div class="field">
                <label for="email">Електронна пошта</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}" @error('email') aria-describedby="email-error" @enderror>
                @error('email') <p id="email-error" class="field-error">{{ $message }}</p> @enderror
            </div>
            <div class="field">
                <label for="profile_name">Ім’я першого профілю</label>
                <input id="profile_name" name="profile_name" type="text" value="{{ old('profile_name') }}" maxlength="100" autocomplete="nickname" required aria-invalid="{{ $errors->has('profile_name') ? 'true' : 'false' }}" @error('profile_name') aria-describedby="profile-name-error" @enderror>
                @error('profile_name') <p id="profile-name-error" class="field-error">{{ $message }}</p> @enderror
            </div>
            <div class="field">
                <label for="password">Пароль</label>
                <input id="password" name="password" type="password" autocomplete="new-password" required aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}" @error('password') aria-describedby="password-error" @enderror>
                @error('password') <p id="password-error" class="field-error">{{ $message }}</p> @enderror
            </div>
            <div class="field">
                <label for="password_confirmation">Підтвердження пароля</label>
                <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required>
            </div>
            <button class="button button-primary" type="submit">Зареєструватися</button>
        </form>
        <p class="form-footer">Уже маєте акаунт? <a href="{{ route('login') }}">Увійти</a></p>
    </section>
@endsection
