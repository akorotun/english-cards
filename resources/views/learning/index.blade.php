@extends('layouts.app')

@section('title', 'Вивчення — English Cards')

@section('content')
    @php
        $checkedTopicIds = old('topic_ids', $selectedTopics?->modelKeys() ?? []);
        $checkedTopicIds = is_array($checkedTopicIds) ? $checkedTopicIds : [];
        $reviewChecked = in_array(old('needs_review', $needsReview), [true, 1, '1'], true);
    @endphp
    <section class="panel" aria-labelledby="page-title">
        <h1 id="page-title">Вивчення</h1>
        <p class="intro">Оберіть одну або кілька тем для профілю {{ $profile->name }}. Спільні картки потраплять до підбірки лише один раз.</p>

        @if ($errors->any())
            <div class="notice import-error" role="alert">
                <ul>
                    @foreach ($errors->all() as $message)
                        <li>{{ $message }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($words !== null)
            <div class="notice" role="status">
                <h2>Підбірку сформовано</h2>
                <p>Унікальних карток: <strong>{{ $words->count() }}</strong></p>
                <p>Вибрані теми:</p>
                <ul>
                    @foreach ($selectedTopics as $selectedTopic)
                        <li>{{ $selectedTopic->name }} · {{ $selectedTopic->level }} · {{ $selectedTopic->user_id === null ? 'Системна' : 'Приватна' }}</li>
                    @endforeach
                </ul>
                @if ($words->isEmpty())
                    <p>{{ $needsReview ? 'За фільтром немає карток. Вимкніть «Потрібно повторити» або змініть теми.' : 'У вибраних темах поки немає слів. Оберіть інші теми.' }}</p>
                @else
                    <p>{{ $needsReview ? 'Фільтр: Потрібно повторити (рівні 0–2).' : 'Усі картки вибраних тем, незалежно від рівня знання.' }}</p>
                    <a class="button button-primary" href="{{ route('learning.cards', ['topic_ids' => $selectedTopics->modelKeys(), 'needs_review' => (int) $needsReview]) }}">Почати вивчення</a>
                @endif
            </div>
        @endif

        @if ($topics->isEmpty())
            <p class="notice">Доступних тем поки немає. Ви можете додати власні теми через імпорт слів.</p>
            <a class="button button-secondary" href="{{ route('vocabulary.import') }}">Імпорт слів</a>
        @else
            <form method="get" action="{{ route('learning.selection') }}" class="form-stack">
                <fieldset class="field filter-field" aria-describedby="topic-selection-hint">
                    <legend>Теми для вивчення</legend>
                    <p id="topic-selection-hint" class="hint">Позначте потрібні теми. Лічильник кожної теми показує всі її картки до застосування фільтра.</p>
                    <ul class="topic-list">
                        @foreach ($topics as $topic)
                            <li class="topic-item">
                                @include('topics.partials.details')
                                <label class="learning-option">
                                    <input type="checkbox" name="topic_ids[]" value="{{ $topic->id }}" @checked(in_array($topic->id, $checkedTopicIds)) aria-labelledby="choose-topic-{{ $topic->id }} topic-{{ $topic->id }}-name">
                                    <span id="choose-topic-{{ $topic->id }}">Обрати тему</span>
                                </label>
                            </li>
                        @endforeach
                    </ul>
                </fieldset>
                <div>
                    <input type="hidden" name="needs_review" value="0">
                    <label class="learning-option">
                        <input type="checkbox" name="needs_review" value="1" @checked($reviewChecked) aria-describedby="needs-review-hint">
                        <span>Потрібно повторити</span>
                    </label>
                    <p id="needs-review-hint" class="hint">Лише картки з рівнем знання 0–2, включно з новими. Прогрес не змінюється.</p>
                </div>
                <button class="button button-primary" type="submit">Сформувати підбірку</button>
            </form>
        @endif
    </section>
@endsection
