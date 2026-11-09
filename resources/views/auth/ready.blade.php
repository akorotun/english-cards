@extends('layouts.app')

@section('title', 'Навчальний профіль — English Cards')

@section('content')
    <h1 class="learning-header-title" id="page-title">
        <x-icons.layout-dashboard />
        Мій кабінет
    </h1>
    <section class="panel" aria-labelledby="profile-name">
        <h2 class="home-profile-name" id="profile-name">{{ $profile->name }}</h2>
        <div class="action-row profile-actions profile-actions-bottom">
            <a class="button button-primary" href="{{ route('learning.index') }}">Навчання</a>
            <a class="button button-primary" href="{{ route('check.index') }}">Перевірка</a>
        </div>
        <dl class="home-statistics" aria-label="Статистика навчального профілю">
            <div><dt>Доступно слів</dt><dd>{{ $statistics->available }}</dd></div>
            <div><dt>Засвоєно</dt><dd>{{ $statistics->learned }}</dd></div>
            <div><dt>У процесі</dt><dd>{{ $statistics->in_progress }}</dd></div>
        </dl>
    </section>
@endsection
