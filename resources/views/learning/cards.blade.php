@extends('layouts.app')

@section('title', ($isCheck ? 'Перевірка' : 'Навчання').' — English Cards')

@section('content')
    @php
        $selectionUrl = route($isCheck ? 'check.selection' : 'learning.selection', ['topic_ids' => $selectedTopics->modelKeys(), 'needs_review' => (int) $needsReview]);
    @endphp
    <section class="learning-session" @if ($isCheck) data-check-session data-user-id="{{ auth()->id() }}" data-profile-id="{{ $profile->id }}" @else data-learning-session @endif data-cards="{{ json_encode($words->map->only(['id', 'word_en', 'word_uk'])->values(), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) }}" aria-labelledby="learning-title">
        @include('learning.partials.title')
        @include('learning.partials.selected-topics', ['changeTopicsUrl' => $isCheck ? null : $selectionUrl])
        <noscript><p class="notice">Для перегляду карток увімкніть JavaScript.</p></noscript>
        @if ($isCheck)
            <p class="notice import-error" role="alert" data-check-error hidden></p>
            <div data-check-content hidden>
                <div data-check-card-screen>
                    <p class="check-round" data-check-round aria-live="polite"></p>
                    <div class="learning-position">
                        <progress class="learning-position-bar" data-check-progress value="1" aria-label="Позиція у поточному колі"></progress>
                        <p class="learning-counter" aria-live="polite" data-check-counter></p>
                    </div>
                    <div class="learning-card">
                        <p class="learning-word" lang="en" data-check-en></p>
                        <p class="learning-translation" lang="uk" data-check-uk hidden></p>
                        <button class="button button-secondary learning-speak" type="button" data-check-speak aria-label="Послухати англійське слово">🔊 Послухати</button>
                        <p class="learning-audio-feedback" role="status" data-check-audio-feedback hidden></p>
                    </div>
                    <div class="learning-controls">
                        <button class="button button-primary" type="button" data-check-reveal>Показати переклад</button>
                    </div>
                    <div class="learning-controls" data-check-answers hidden>
                        <button class="button button-secondary" type="button" data-check-answer="dont_know">Не знаю</button>
                        <button class="button button-primary" type="button" data-check-answer="know">Знаю</button>
                    </div>
                </div>
                <div class="panel learning-complete" data-check-complete hidden tabindex="-1">
                    <h2>Перевірку завершено</h2>
                    <p>Відповіді збережено в цьому браузері. Прогрес ще не синхронізовано.</p>
                    <a class="button button-secondary" href="{{ route('check.index') }}">До вибору тем</a>
                </div>
            </div>
        @else
        <div data-learning-content hidden>
            <div data-learning-card-screen>
                <div class="learning-position">
                    <progress class="learning-position-bar" data-learning-progress max="{{ $words->count() }}" value="1" aria-label="Позиція в підбірці"></progress>
                    <p class="learning-counter" aria-live="polite" data-learning-counter></p>
                </div>
                <div class="learning-card" data-learning-card>
                    <p class="learning-word" lang="en" data-learning-en></p>
                    <p class="learning-translation" lang="uk" data-learning-uk></p>
                    <button class="button button-secondary learning-speak" type="button" data-learning-speak aria-label="Послухати англійське слово">🔊 Послухати</button>
                    <p class="learning-audio-feedback" role="status" data-learning-audio-feedback hidden></p>
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
        @endif
    </section>
@endsection
