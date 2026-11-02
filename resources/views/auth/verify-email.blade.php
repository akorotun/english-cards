@extends('layouts.app')

@section('title', 'Підтвердження пошти — English Cards')

@section('content')
    <section class="panel" aria-labelledby="page-title">
        <p class="eyebrow">Майже готово</p>
        <h1 id="page-title">Підтвердіть електронну пошту</h1>
        <p class="intro">Ми надіслали посилання на <strong>{{ auth()->user()->email }}</strong>. Перейдіть за ним, щоб відкрити свій акаунт.</p>
        <form method="post" action="{{ route('verification.send') }}" class="form-stack">
            @csrf
            <button class="button button-secondary" type="submit">Надіслати лист повторно</button>
        </form>
        <form method="post" action="{{ route('logout') }}" class="logout-form">
            @csrf
            <button class="button button-secondary" type="submit">Вийти</button>
        </form>
    </section>
@endsection
