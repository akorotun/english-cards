@extends('layouts.app')

@section('title', 'Навчальний профіль — English Cards')

@section('content')
    <section class="panel" aria-labelledby="page-title">
        <h1 id="page-title">{{ $profile->name }}</h1>
        <div class="action-row profile-actions profile-actions-bottom">
            <a class="button button-primary" href="{{ route('learning.index') }}">Навчання</a>
            <a class="button button-primary" href="{{ route('check.index') }}">Перевірка</a>
        </div>
    </section>
@endsection
