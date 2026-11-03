@extends('layouts.app')

@section('title', 'Навчання — English Cards')

@section('content')
    @php
        $checkedTopicIds = old('topic_ids', $selectedTopics?->modelKeys() ?? []);
        $checkedTopicIds = is_array($checkedTopicIds) ? $checkedTopicIds : [];
        $reviewChecked = in_array(old('needs_review', $needsReview), [true, 1, '1'], true);
    @endphp
    <section class="panel" aria-labelledby="page-title">
        <h1 id="page-title">Навчання</h1>

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
                    <p>{{ $needsReview ? 'За фільтром немає карток. Вимкніть «Не показувати вивчені слова» або змініть теми.' : 'У вибраних темах поки немає слів. Оберіть інші теми.' }}</p>
                @else
                    <p>{{ $needsReview ? 'Фільтр: Не показувати вивчені слова.' : 'Усі картки вибраних тем, незалежно від рівня знання.' }}</p>
                    <a class="button button-primary" href="{{ route('learning.cards', ['topic_ids' => $selectedTopics->modelKeys(), 'needs_review' => (int) $needsReview]) }}">Почати навчання</a>
                @endif
            </div>
        @endif

        @if ($topics->isEmpty())
            <p class="notice">Доступних тем поки немає. Ви можете додати власні теми через імпорт слів.</p>
            <a class="button button-secondary" href="{{ route('vocabulary.import') }}">Імпорт слів</a>
        @else
            <form method="get" action="{{ route('learning.selection') }}" class="form-stack" data-learning-selection>
                <fieldset class="field filter-field">
                    <legend>Оберіть теми</legend>
                    <ul class="topic-list">
                        @foreach ($topics as $topic)
                            <li class="topic-item">
                                @include('topics.partials.details')
                                <label class="learning-option">
                                    <input type="checkbox" name="topic_ids[]" value="{{ $topic->id }}" data-has-learned-words="{{ $topic->has_learned_words ? '1' : '0' }}" @checked(in_array($topic->id, $checkedTopicIds)) aria-labelledby="choose-topic-{{ $topic->id }} topic-{{ $topic->id }}-name">
                                    <span id="choose-topic-{{ $topic->id }}">Обрати тему</span>
                                </label>
                            </li>
                        @endforeach
                    </ul>
                </fieldset>
                <div data-learned-filter @if (! $topics->contains(fn ($topic) => $topic->has_learned_words && in_array($topic->id, $checkedTopicIds))) hidden @endif>
                    <input type="hidden" name="needs_review" value="0">
                    <label class="learning-option">
                        <input type="checkbox" name="needs_review" value="1" @checked($reviewChecked) aria-describedby="needs-review-hint">
                        <span>Не показувати вивчені слова</span>
                    </label>
                    <p id="needs-review-hint" class="hint">Приховати слова з рівнем знання 3 для цього профілю.</p>
                </div>
                <noscript><p class="hint">Щоб змінити фільтр, спочатку сформуйте підбірку з вибраних тем.</p></noscript>
                <button class="button button-primary" type="submit">Сформувати підбірку</button>
            </form>
        @endif
    </section>
@endsection
