@extends('layouts.app')

@section('title', 'Навчальний профіль — English Cards')

@section('content')
    <section class="panel" aria-labelledby="page-title">
        <p class="eyebrow">Активний профіль</p>
        <h1 id="page-title">{{ $profile->name }}</h1>
        <p class="intro">Оберіть теми й сформуйте підбірку для навчання.</p>
        <div class="action-row">
            <a class="button button-primary" href="{{ route('learning.index') }}">Вибрати теми</a>
            <a class="button button-primary" href="{{ route('check.index') }}">Перевірка</a>
            <a class="button button-secondary" href="{{ route('profiles.index') }}">Перемкнути профіль</a>
            <a class="button button-secondary" href="{{ route('profiles.show', $profile) }}">Налаштування</a>
        </div>
        <form method="post" action="{{ route('logout') }}" class="logout-form">
            @csrf
            <button class="button button-secondary" type="submit">Вийти</button>
        </form>
    </section>
@endsection
