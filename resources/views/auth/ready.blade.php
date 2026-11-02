@extends('layouts.app')

@section('title', 'Акаунт готовий — English Cards')

@section('content')
    <section class="panel" aria-labelledby="page-title">
        <p class="eyebrow">Акаунт підтверджено</p>
        <h1 id="page-title">Вітаємо в English Cards</h1>
        <p class="intro">Ваш акаунт готовий. Тут незабаром з’явиться вибір навчального профілю.</p>
        <form method="post" action="{{ route('logout') }}" class="logout-form">
            @csrf
            <button class="button button-secondary" type="submit">Вийти</button>
        </form>
    </section>
@endsection
