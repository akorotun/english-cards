@extends('layouts.app')

@section('title', 'Відновлення пароля — English Cards')

@section('content')
    <section class="panel" aria-labelledby="page-title">
        <p class="eyebrow">Доступ до акаунта</p>
        <h1 id="page-title">Відновіть пароль</h1>
        <p class="intro">Вкажіть електронну пошту. Якщо акаунт існує, ми надішлемо посилання для зміни пароля.</p>
        <form method="post" action="{{ route('password.email') }}" class="form-stack">
            @csrf
            <div class="field">
                <label for="email">Електронна пошта</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}" @error('email') aria-describedby="email-error" @enderror>
                @error('email') <p id="email-error" class="field-error">{{ $message }}</p> @enderror
            </div>
            <button class="button button-primary" type="submit">Надіслати посилання</button>
        </form>
        <p class="form-footer"><a href="{{ route('login') }}">Повернутися до входу</a></p>
    </section>
@endsection
