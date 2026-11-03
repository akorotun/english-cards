@extends('layouts.app')

@section('title', 'Теми — English Cards')

@section('content')
    <section class="panel" aria-labelledby="page-title">
        <h1 id="page-title">Теми</h1>
        <p class="intro">Системні теми та ваші приватні добірки слів. Оберіть тип і рівень, щоб знайти потрібну тему.</p>

        <form method="get" action="{{ route('topics.index') }}" class="learning-filters topic-filters" data-topic-browse-filters>
            <div class="learning-filter-heading">
                <span>Фільтри</span>
                <button type="button" class="learning-icon-button" data-browse-reset title="Скинути фільтри" aria-label="Скинути фільтри">
                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 1 0 3-6.7L3 8"/><path d="M3 3v5h5"/></svg>
                </button>
            </div>
            <div class="learning-search">
                <label class="filter-visually-hidden" for="topic-browse-search">Пошук тем</label>
                <svg class="learning-search-icon" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg>
                <input id="topic-browse-search" type="search" name="search" value="{{ $search }}" maxlength="200" placeholder="Пошук тем..." data-browse-search>
                <button class="learning-search-clear" type="button" data-browse-clear aria-label="Очистити пошук" title="Очистити пошук" @if ($search === '') hidden @endif>×</button>
            </div>
            @include('topics.partials.filter', [
                'name' => 'scope', 'label' => 'Тип теми', 'compactPrefix' => 'Тип',
                'options' => ['all' => 'Усі', 'system' => 'Системні', 'private' => 'Власні'], 'selectedValue' => $filters['scope'],
            ])
            @include('topics.partials.filter', [
                'name' => 'level', 'label' => 'Рівень', 'compactPrefix' => 'Рівень',
                'options' => ['' => 'Усі', 'A1-A2' => 'A1-A2'] + $levels->mapWithKeys(fn (string $level): array => [$level => $level])->all() + ($filters['level'] !== '' ? [$filters['level'] => $filters['level']] : []), 'selectedValue' => $filters['level'],
            ])
            <noscript><button class="button button-primary" type="submit">Застосувати фільтри</button></noscript>
            <div class="notice import-error" role="alert" data-browse-error hidden>Не вдалося оновити список тем. <button type="button" data-browse-retry>Спробувати ще раз</button></div>
        </form>

        <div data-topic-browse-results aria-live="polite">
            @error('page') <p class="field-error" role="alert">{{ $message }}</p> @enderror

            @if ($topics->isEmpty())
                <p class="notice">
                    @if ($topics->currentPage() > 1)
                        На цій сторінці немає тем. Поверніться на попередню сторінку або скиньте фільтри.
                    @elseif ($levels->isEmpty() && $search === '')
                        Доступних тем поки немає. Ви можете додати власні теми через імпорт слів.
                    @else
                        За вибраними фільтрами тем не знайдено. Спробуйте інший тип, рівень або пошуковий запит.
                    @endif
                </p>
            @else
                <ul class="topic-list">
                    @foreach ($topics as $topic)
                        <li class="topic-item">
                            @include('topics.partials.details')
                            <a class="button button-secondary" href="{{ route('topics.show', ['topic' => $topic->id, ...$listFilters, 'topics_page' => $topics->currentPage()]) }}" aria-label="Переглянути тему {{ $topic->name }}">Переглянути</a>
                        </li>
                    @endforeach
                </ul>
            @endif

            {{ $topics->links('topics.partials.pagination') }}
        </div>

        <p class="topic-import"><a href="{{ route('vocabulary.import') }}">Імпорт слів</a></p>
    </section>
@endsection
