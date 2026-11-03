@extends('layouts.app')

@section('title', 'Теми — English Cards')

@section('content')
    <section class="panel" aria-labelledby="page-title">
        <h1 id="page-title">Теми</h1>
        <p class="intro">Системні теми та ваші приватні добірки слів. Оберіть тип і рівень, щоб знайти потрібну тему.</p>

        <form method="get" action="{{ route('topics.index') }}" class="form-stack topic-filters">
            @include('topics.partials.filter', [
                'name' => 'scope',
                'label' => 'Тип тем',
                'options' => ['all' => 'Усі', 'system' => 'Системні', 'private' => 'Приватні'],
                'selectedValue' => $filters['scope'],
            ])
            @include('topics.partials.filter', [
                'name' => 'level',
                'label' => 'Рівень',
                'options' => ['' => 'Усі рівні'] + $levels->mapWithKeys(fn (string $level): array => [$level => $level])->all(),
                'selectedValue' => $filters['level'],
            ])
            <div class="action-row">
                <button class="button button-primary" type="submit">Застосувати</button>
                <a class="button button-secondary" href="{{ route('topics.index') }}">Скинути фільтри</a>
            </div>
        </form>

        @error('page') <p class="field-error" role="alert">{{ $message }}</p> @enderror

        @if ($topics->isEmpty())
            <p class="notice">
                @if ($topics->currentPage() > 1)
                    На цій сторінці немає тем. Поверніться на попередню сторінку або скиньте фільтри.
                @elseif ($filters['scope'] !== 'all' || $filters['level'] !== '')
                    За вибраними фільтрами тем не знайдено. Спробуйте інший тип або рівень.
                @else
                    Доступних тем поки немає. Ви можете додати власні теми через імпорт слів.
                @endif
            </p>
        @else
            <ul class="topic-list">
                @foreach ($topics as $topic)
                    @php
                        $wordCount = $topic->words_count;
                        $wordLabel = match (true) {
                            $wordCount % 100 >= 11 && $wordCount % 100 <= 14 => 'слів',
                            $wordCount % 10 === 1 => 'слово',
                            $wordCount % 10 >= 2 && $wordCount % 10 <= 4 => 'слова',
                            default => 'слів',
                        };
                    @endphp
                    <li class="topic-item">
                        <h2>{{ $topic->name }}</h2>
                        <dl class="topic-meta">
                            <div><dt>Рівень</dt><dd>{{ $topic->level }}</dd></div>
                            <div><dt>Тип</dt><dd>{{ $topic->user_id === null ? 'Системна' : 'Приватна' }}</dd></div>
                        </dl>
                        <p class="topic-count">{{ $wordCount }} {{ $wordLabel }}</p>
                        <a class="button button-secondary" href="{{ url()->query('/topics/'.$topic->id, [...$filters, 'topics_page' => $topics->currentPage()]) }}" aria-label="Переглянути тему {{ $topic->name }}">Переглянути</a>
                    </li>
                @endforeach
            </ul>
        @endif

        {{ $topics->links('topics.partials.pagination') }}

        <p class="topic-import"><a href="{{ route('vocabulary.import') }}">Імпорт слів</a></p>
    </section>
@endsection
