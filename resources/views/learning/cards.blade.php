@extends('layouts.app')

@section('title', ($isCheck ? 'Перевірка' : 'Навчання').' — English Cards')

@section('content')
    @php
        $selectionUrl = route($isCheck ? 'check.selection' : 'learning.selection', ['topic_ids' => $selectedTopics->modelKeys(), 'needs_review' => (int) $needsReview]);
    @endphp
    <section class="learning-session" @if ($isCheck) data-check-session data-user-id="{{ auth()->id() }}" data-profile-id="{{ $profile->id }}" data-restart-url="{{ route('check.start', ['topic_ids' => $selectedTopics->modelKeys(), 'needs_review' => (int) $needsReview]) }}" @else data-learning-session @endif data-cards="{{ json_encode($words->map->only(['id', 'word_en', 'word_uk'])->values(), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) }}" aria-labelledby="learning-title">
        @include('learning.partials.title')
        @include('learning.partials.selected-topics', ['changeTopicsUrl' => $isCheck ? null : $selectionUrl])
        <noscript><p class="notice">Для перегляду карток увімкніть JavaScript.</p></noscript>
        @if ($isCheck)
            <p class="notice import-error" role="alert" data-check-error hidden></p>
            <div data-check-content hidden>
                <div data-check-card-screen>
                    <div class="check-round-header">
                        <p class="check-round" data-check-round aria-live="polite"></p>
                        <button class="button button-secondary check-stop" type="button" data-check-exit><svg aria-hidden="true" viewBox="0 0 16 16"><rect x="2" y="2" width="12" height="12" rx="1.5" fill="currentColor"/></svg> Завершити</button>
                    </div>
                    <div class="learning-position">
                        <progress class="learning-position-bar" data-check-progress value="1" aria-label="Позиція у поточному колі"></progress>
                        <p class="learning-counter" aria-live="polite" data-check-counter></p>
                    </div>
                    <div class="learning-card check-card">
                        <p class="learning-word" lang="en" data-check-en></p>
                        <p class="learning-translation check-translation" lang="uk"><span id="check-translation" data-check-uk aria-hidden="true"></span></p>
                    </div>
                    <div class="learning-controls check-assists">
                        <button class="button button-secondary check-reveal" type="button" data-check-reveal aria-expanded="false" aria-controls="check-translation"><span>Показати переклад</span><span>Приховати переклад</span></button>
                        <button class="button button-secondary" type="button" data-check-speak aria-label="Послухати англійське слово"><svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 5 6 9H3v6h3l5 4V5Z"/><path d="M15.5 8.5a5 5 0 0 1 0 7"/><path d="M19 5a10 10 0 0 1 0 14"/></svg>Послухати</button>
                    </div>
                    <p class="learning-audio-feedback" role="status" data-check-audio-feedback hidden></p>
                    <div class="learning-controls" data-check-answers>
                        <button class="button button-secondary" type="button" data-check-answer="dont_know">Не знаю</button>
                        <button class="button button-secondary" type="button" data-check-answer="know">Знаю</button>
                    </div>
                </div>
                <div class="panel learning-complete" data-check-complete hidden tabindex="-1">
                    <h2 data-check-complete-title>Перевірку завершено</h2>
                    <dl class="check-results">
                        <div><dt>Перевірено</dt><dd data-check-complete-count></dd></div>
                        <div data-check-stat="firstKnown"><dt>З першої спроби знав</dt><dd></dd></div>
                        <div data-check-stat="neededRepeat"><dt>Потребували повторення</dt><dd></dd></div>
                        <div data-check-stat="remembered"><dt>Згадав під час повторення</dt><dd></dd></div>
                        <div data-check-stat="stillUnknown"><dt>Залишилися незнайомими</dt><dd></dd></div>
                    </dl>
                    <div class="notice check-sync-status">
                        <p role="status" data-check-complete-sync></p>
                        <button class="button button-secondary" type="button" data-check-complete-retry hidden>Повторити синхронізацію</button>
                    </div>
                    <div class="learning-controls">
                        <button class="button button-primary" type="button" data-check-restart hidden disabled>Повторити незнайомі</button>
                        <a class="button button-secondary" href="{{ route('check.index') }}">Завершити</a>
                    </div>
                </div>
                <dialog class="check-dialog" data-check-dialog aria-labelledby="check-dialog-title" aria-describedby="check-dialog-description">
                    <h2 id="check-dialog-title">Завершити перевірку?</h2>
                    <p id="check-dialog-description">Зроблені відповіді буде збережено та надіслано на сервер. Неперевірені слова залишаться без змін.</p>
                    <p class="notice import-error" role="alert" data-check-dialog-error hidden></p>
                    <div class="learning-controls">
                        <button class="button button-secondary" type="button" data-check-continue autofocus>Продовжити</button>
                        <button class="button button-primary" type="button" data-check-confirm>Завершити</button>
                    </div>
                </dialog>
            </div>
        @else
        <div data-learning-content hidden>
            <div data-learning-card-screen>
                <div class="learning-position">
                    <progress class="learning-position-bar" data-learning-progress max="{{ $words->count() }}" value="1" aria-label="Позиція в підбірці"></progress>
                    <p class="learning-counter" aria-live="polite" data-learning-counter></p>
                </div>
                <div class="learning-card learning-reading-card" data-learning-card>
                    <p class="learning-word" lang="en" data-learning-en></p>
                    <p class="learning-translation" lang="uk" data-learning-uk></p>
                </div>
                <div class="learning-controls">
                    <button class="button button-secondary" type="button" data-learning-speak aria-label="Послухати англійське слово"><svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 5 6 9H3v6h3l5 4V5Z"/><path d="M15.5 8.5a5 5 0 0 1 0 7"/><path d="M19 5a10 10 0 0 1 0 14"/></svg>Послухати</button>
                </div>
                <p class="learning-audio-feedback" role="status" data-learning-audio-feedback hidden></p>
                <div class="learning-controls learning-navigation">
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
