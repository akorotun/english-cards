@extends('layouts.app')

@section('title', 'English Cards — вчіть слова у своєму темпі')
@section('layout-class', 'welcome-layout')

@section('content')
    <section class="welcome-hero" aria-labelledby="page-title">
        <div class="welcome-introduction">
            <h1 id="page-title">Вивчайте англійські слова легко</h1>
            <p class="intro">Обирайте теми, вивчайте слова за картками та перевіряйте, що вже запам’ятали.</p>
            <div class="action-row welcome-actions">
                @auth
                    <a class="button button-primary" href="{{ route('home') }}">Мій кабінет</a>
                @else
                    <a class="button button-primary" href="{{ route('register') }}">Зареєструватися</a>
                    <a class="button button-secondary" href="{{ route('login') }}">Увійти</a>
                    <a class="button button-secondary" href="{{ route('demo.index') }}">Спробувати без реєстрації</a>
                @endauth
            </div>
        </div>
        <section class="welcome-modes" aria-labelledby="welcome-modes-title">
            <h2 id="welcome-modes-title">Два режими для практики</h2>
            <div class="welcome-mode-list">
                <article class="welcome-mode">
                    <span class="welcome-mode-icon"><x-icons.book-open /></span>
                    <div>
                        <h3>Навчання</h3>
                        <p>Знайомтеся зі словами, перекладами та вимовою</p>
                    </div>
                </article>
                <article class="welcome-mode">
                    <span class="welcome-mode-icon"><x-icons.check-circle /></span>
                    <div>
                        <h3>Перевірка</h3>
                        <p>Пригадуйте переклад, перевіряйте себе та відстежуйте прогрес</p>
                    </div>
                </article>
            </div>
        </section>
    </section>
@endsection
