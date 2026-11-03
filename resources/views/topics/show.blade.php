@extends('layouts.app')

@section('title', 'Словник теми — English Cards')

@section('content')
    <section class="panel topic-detail" aria-labelledby="page-title">
        <a class="topic-back" href="{{ $backUrl }}">Назад до тем</a>
        <h1 id="page-title">{{ $topic->name }}</h1>
        <dl class="topic-meta">
            <div><dt>Рівень</dt><dd>{{ $topic->level }}</dd></div>
            <div><dt>Тип</dt><dd>{{ $topic->user_id === null ? 'Системна' : 'Приватна' }}</dd></div>
            <div><dt>Кількість слів</dt><dd>{{ $topic->words_count }}</dd></div>
        </dl>

        @if ($topic->words_count === 0)
            <p class="notice">У цій темі поки немає слів.</p>
        @elseif ($words->isEmpty())
            <p class="notice">На цій сторінці немає слів. <a href="{{ $words->url(1) }}">На початок словника</a></p>
        @else
            <table class="word-table">
                <thead>
                    <tr><th scope="col" lang="en">English</th><th scope="col">Українська</th></tr>
                </thead>
                <tbody>
                    @foreach ($words as $word)
                        <tr><td lang="en">{{ $word->word_en }}</td><td lang="uk">{{ $word->word_uk }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        {{ $words->links('topics.partials.pagination') }}
    </section>
@endsection
