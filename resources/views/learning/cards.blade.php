@extends('layouts.app')

@section('title', 'Вивчення — English Cards')

@section('content')
    @php
        $selectionUrl = route('learning.selection', ['topic_ids' => $selectedTopics->modelKeys(), 'needs_review' => (int) $needsReview]);
    @endphp
    <section class="learning-session" data-learning-session data-cards="{{ json_encode($words->map->only(['word_en', 'word_uk'])->values(), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) }}" aria-labelledby="learning-title">
        <h1 id="learning-title">Вивчення</h1>
        <div class="learning-context">
            <strong>Вибрані теми:</strong>
            @foreach ($selectedTopics as $topic)
                <span>{{ $topic->name }} · {{ $topic->level }} · {{ $topic->user_id === null ? 'Системна' : 'Приватна' }}{{ ! $loop->last ? ';' : '' }}</span>
            @endforeach
            @if ($needsReview)<span> · Потрібно повторити</span>@endif
        </div>
        <noscript><p class="notice">Для перегляду карток увімкніть JavaScript.</p></noscript>
        <div data-learning-content hidden>
            <div data-learning-card-screen>
                <p class="learning-counter" aria-live="polite" data-learning-counter></p>
                <div class="learning-card" data-learning-card>
                    <p class="learning-word" lang="en" data-learning-en></p>
                    <p class="learning-translation" lang="uk" data-learning-uk></p>
                </div>
                <div class="learning-controls">
                    <button class="button button-secondary" type="button" data-learning-previous>← Назад</button>
                    <button class="button button-primary" type="button" data-learning-next>Далі →</button>
                </div>
            </div>
            <div class="panel learning-complete" data-learning-complete hidden tabindex="-1">
                <h2>Перегляд завершено</h2>
                <p>Переглянуто карток: <strong>{{ $words->count() }}</strong></p>
                <div class="learning-controls">
                    <button class="button button-primary" type="button" data-learning-restart>Пройти ще раз</button>
                    <a class="button button-secondary" href="{{ $selectionUrl }}">Змінити теми</a>
                </div>
            </div>
        </div>
        <p><a href="{{ $selectionUrl }}">Змінити теми</a></p>
    </section>
@endsection
