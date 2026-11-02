@extends('layouts.app')

@section('title', 'English Cards — вчіть слова у своєму темпі')

@section('content')
    <section class="panel welcome-panel" aria-labelledby="page-title">
        <p class="eyebrow">English Cards</p>
        <h1 id="page-title">Англійські слова у вашому темпі</h1>
        <p class="intro">Створіть акаунт і навчальний профіль, щоб розпочати.</p>
        <div class="action-row">
            @auth
                <a class="button button-primary" href="{{ route('home') }}">Перейти до акаунта</a>
            @else
                <a class="button button-primary" href="{{ route('register') }}">Створити акаунт</a>
                <a class="button button-secondary" href="{{ route('login') }}">Увійти</a>
            @endauth
        </div>
    </section>
@endsection
