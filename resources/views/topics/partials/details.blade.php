@php
    $wordCount = $topic->words_count;
    $wordLabel = match (true) {
        $wordCount % 100 >= 11 && $wordCount % 100 <= 14 => 'слів',
        $wordCount % 10 === 1 => 'слово',
        $wordCount % 10 >= 2 && $wordCount % 10 <= 4 => 'слова',
        default => 'слів',
    };
@endphp
<h2 id="topic-{{ $topic->id }}-name">{{ $topic->name }}</h2>
<dl class="topic-meta">
    <div><dt>Рівень</dt><dd>{{ $topic->level }}</dd></div>
    <div><dt>Тип</dt><dd>{{ $topic->user_id === null ? 'Системна' : 'Приватна' }}</dd></div>
</dl>
<p class="topic-count">{{ $wordCount }} {{ $wordLabel }}</p>
