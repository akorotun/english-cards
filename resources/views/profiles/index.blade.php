@extends('layouts.app')

@section('title', 'Навчальні профілі — English Cards')

@section('content')
    <h1 class="learning-header-title" id="page-title">
        <x-icons.users />
        Профілі
    </h1>
    <section class="panel" aria-labelledby="page-title">
        @if ($profiles->isEmpty())
            <p class="notice">У вас поки немає навчальних профілів. Створіть перший профіль нижче.</p>
        @else
            <ul class="profile-list">
                @foreach ($profiles as $profile)
                    <li class="profile-item" data-profile-item="{{ $profile->id }}">
                        <div>
                            <strong>{{ $profile->name }}</strong>
                        </div>
                        <div class="profile-actions">
                            <form method="post" action="{{ route('profiles.activate', $profile) }}" data-profile-activate>
                                @csrf
                                <span class="profile-current" data-profile-current @if (! $activeProfile?->is($profile)) hidden @endif>Активний</span>
                                <button class="button button-primary" type="submit" data-profile-select @if ($activeProfile?->is($profile)) hidden @endif>Обрати</button>
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

@endsection
