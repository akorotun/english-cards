@extends('layouts.app')

@section('title', ($isCheck ? 'Перевірка' : 'Навчання').' — English Cards')

@section('content')
    @php
        $isDemo = $isDemo ?? false;
        $checkedTopicIds = old('topic_ids', $selectedTopics?->modelKeys() ?? []);
        $checkedTopicIds = is_array($checkedTopicIds) ? $checkedTopicIds : [];
        $reviewChecked = in_array(old('needs_review', $needsReview), [true, 1, '1'], true);
    @endphp
    @if ($isDemo)
        <h1 class="learning-header-title" id="learning-title">Деморежим</h1>
        <p class="notice">У деморежимі доступні лише системні теми. Прогрес не зберігається. <a href="{{ route('register') }}">Зареєструватися</a> або <a href="{{ route('login') }}">увійти</a>.</p>
    @else
        @include('learning.partials.title')
    @endif
    <section class="panel" aria-labelledby="learning-title">

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
            <div class="learning-selection-result" role="status" data-selection-result>
                @php
                    $wordCount = $words->count();
                    $wordLabel = match (true) {
                        $wordCount % 100 >= 11 && $wordCount % 100 <= 14 => 'слів',
                        $wordCount % 10 === 1 => 'слово',
                        $wordCount % 10 >= 2 && $wordCount % 10 <= 4 => 'слова',
                        default => 'слів',
                    };
                @endphp
                @include('learning.partials.selected-topics', ['changeTopicsUrl' => '#topic-selection'])
                @if ($words->isEmpty())
                    <p>{{ $needsReview ? 'За фільтром немає карток. Вимкніть «Не показувати вивчені слова» або змініть теми.' : 'У вибраних темах поки немає слів. Оберіть інші теми.' }}</p>
                @elseif ($isDemo)
                    @if ($demoMode)
                        <p>Цей режим демо поки недоступний. Оберіть теми або поверніться пізніше.</p>
                    @else
                        <div class="learning-controls">
                            <a class="button button-primary" href="{{ route('demo.start', ['mode' => 'learning', 'topic_ids' => $selectedTopics->modelKeys()]) }}">Почати навчання</a>
                            <a class="button button-secondary" href="{{ route('demo.start', ['mode' => 'check', 'topic_ids' => $selectedTopics->modelKeys()]) }}">Почати перевірку</a>
                        </div>
                    @endif
                @elseif ($checkStarted)
                    <p>Підбірку сформовано. Перевірка карток поки недоступна.</p>
                @else
                    <a class="button button-primary learning-selection-start" href="{{ route($isCheck ? 'check.start' : 'learning.cards', ['topic_ids' => $selectedTopics->modelKeys(), 'needs_review' => (int) $needsReview]) }}">{{ $isCheck ? 'Почати перевірку' : 'Почати навчання' }}</a>
                @endif
            </div>
        @endif

        @if ($topics->isEmpty())
            @if ($isDemo)
                <p class="notice">Системних тем поки немає.</p>
            @else
                <p class="notice">Доступних тем поки немає. Ви можете додати власні теми через імпорт слів.</p>
                <a class="button button-secondary" href="{{ route('vocabulary.import') }}">Імпорт слів</a>
            @endif
        @else
            <form method="get" action="{{ route($isDemo ? 'demo.selection' : ($isCheck ? 'check.selection' : 'learning.selection')) }}" class="form-stack" data-learning-selection data-has-result="{{ $words !== null ? '1' : '0' }}">
                <div class="learning-filters" data-topic-filters hidden>
                    <div class="learning-filter-heading">
                        <span>Фільтри</span>
                        <button type="button" class="learning-icon-button" data-reset-filters title="Скинути фільтри" aria-label="Скинути фільтри">
                            <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 1 0 3-6.7L3 8"/><path d="M3 3v5h5"/></svg>
                        </button>
                    </div>
                    <div class="learning-search">
                        <label class="filter-visually-hidden" for="learning-topic-search">Пошук тем</label>
                        <svg class="learning-search-icon" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg>
                        <input id="learning-topic-search" type="search" placeholder="Пошук тем..." data-topic-search>
                        <button class="learning-search-clear" type="button" data-clear-search aria-label="Очистити пошук" title="Очистити пошук" hidden>×</button>
                    </div>
                    @unless ($isDemo)<div data-topic-scope>
                        @include('topics.partials.filter', [
                            'name' => 'topic_scope', 'label' => 'Тип теми',
                            'options' => ['' => 'Усі', 'system' => 'Системні', 'own' => 'Власні'], 'selectedValue' => '', 'compactPrefix' => 'Тип',
                        ])
                    </div>@endunless
                    <div data-topic-level>
                        @include('topics.partials.filter', [
                            'name' => 'topic_level', 'label' => 'Рівень',
                            'options' => ['' => 'Усі'] + $topics->pluck('level')->unique()->sort()->mapWithKeys(fn ($level) => [$level => $level])->all(), 'selectedValue' => '', 'compactPrefix' => 'Рівень',
                        ])
                    </div>
                    <p class="hint learning-filter-empty" data-filter-empty hidden>За фільтрами тем не знайдено.</p>
                </div>
                <fieldset class="field filter-field learning-topic-selection" id="topic-selection">
                    <legend><span class="learning-topic-heading">Оберіть теми
                        <button type="button" class="learning-icon-button" data-clear-selection title="Очистити вибір" aria-label="Очистити вибір">
                            <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7h16M10 11v6m4-6v6M5 7l1 13h12l1-13M9 7V4h6v3"/></svg>
                        </button>
                    </span></legend>
                    <ul class="learning-topic-list">
                        @foreach ($topics as $topic)
                            @php
                                $topicWordCount = $topic->words_count;
                                $topicWordLabel = match (true) {
                                    $topicWordCount % 100 >= 11 && $topicWordCount % 100 <= 14 => 'слів',
                                    $topicWordCount % 10 === 1 => 'слово',
                                    $topicWordCount % 10 >= 2 && $topicWordCount % 10 <= 4 => 'слова',
                                    default => 'слів',
                                };
                            @endphp
                            <li data-topic-row data-name="{{ $topic->name }}" data-level="{{ $topic->level }}" data-scope="{{ $topic->user_id === null ? 'system' : 'own' }}">
                                <label class="learning-topic-row">
                                    <input type="checkbox" name="topic_ids[]" value="{{ $topic->id }}" data-has-learned-words="{{ $topic->has_learned_words ? '1' : '0' }}" @checked(in_array($topic->id, $checkedTopicIds)) aria-labelledby="topic-{{ $topic->id }}-name" aria-describedby="topic-{{ $topic->id }}-meta">
                                    <span class="learning-topic-content">
                                        <span class="learning-topic-name" id="topic-{{ $topic->id }}-name">{{ $topic->name }}</span>
                                        <span class="learning-topic-meta" id="topic-{{ $topic->id }}-meta">{{ $topic->level }} · {{ $topic->user_id === null ? 'Системна' : 'Приватна' }} · {{ $topicWordCount }} {{ $topicWordLabel }}</span>
                                    </span>
                                </label>
                            </li>
                        @endforeach
                    </ul>
                </fieldset>
                @unless ($isDemo)<div data-learned-filter @if (! $topics->contains(fn ($topic) => $topic->has_learned_words && in_array($topic->id, $checkedTopicIds))) hidden @endif>
                    <input type="hidden" name="needs_review" value="0">
                    <label class="learning-option">
                        <input type="checkbox" name="needs_review" value="1" @checked($reviewChecked) aria-describedby="needs-review-hint">
                        <span>Не показувати вивчені слова</span>
                    </label>
                    <p id="needs-review-hint" class="hint">Приховати слова з рівнем знання 3 для цього профілю.</p>
                </div>@endunless
                <noscript><p class="hint">Щоб змінити фільтр, спочатку сформуйте підбірку з вибраних тем.</p></noscript>
                <button class="button button-primary" type="submit">Сформувати підбірку</button>
            </form>
        @endif
    </section>
@endsection
