@extends('layouts.app')

@section('title', 'Навчальні профілі — English Cards')

@section('content')
    <section class="panel" aria-labelledby="page-title">
        <h1 id="page-title">Навчальні профілі</h1>
        <p class="intro">Виберіть профіль, з яким хочете продовжити навчання.</p>

        @if ($profiles->isEmpty())
            <p class="notice">У вас поки немає навчальних профілів. Створіть перший профіль нижче.</p>
        @else
            <ul class="profile-list">
                @foreach ($profiles as $profile)
                    <li class="profile-item" data-profile-item="{{ $profile->id }}">
                        <div>
                            <strong>{{ $profile->name }}</strong>
                            <span class="profile-current" data-profile-current @if (! $activeProfile?->is($profile)) hidden @endif>Активний профіль</span>
                        </div>
                        <div class="profile-actions">
                            <form method="post" action="{{ route('profiles.activate', $profile) }}" data-profile-activate>
                                @csrf
                                <button class="button button-primary" type="submit">Обрати</button>
                            </form>
                            <a class="button button-secondary" href="{{ route('profiles.show', $profile) }}">Налаштування</a>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    <section class="panel profile-create" aria-labelledby="create-title">
        <h2 id="create-title">Додати профіль</h2>
        <p class="intro">Назва допоможе розрізняти профілі в одному акаунті.</p>
        <form method="post" action="{{ route('profiles.store') }}" class="form-stack">
            @csrf
            <div class="field">
                <label for="name">Назва профілю</label>
                <input id="name" name="name" type="text" value="{{ old('name') }}" maxlength="100" required aria-invalid="{{ $errors->has('name') ? 'true' : 'false' }}" @error('name') aria-describedby="name-error" @enderror>
                @error('name') <p id="name-error" class="field-error">{{ $message }}</p> @enderror
            </div>
            <button class="button button-primary" type="submit">Створити профіль</button>
        </form>
    </section>

    <form method="post" action="{{ route('logout') }}" class="logout-form">
        @csrf
        <button class="button button-secondary" type="submit">Вийти з акаунта</button>
    </form>
@endsection
