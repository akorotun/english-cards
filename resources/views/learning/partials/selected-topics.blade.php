<details class="learning-context">
            <summary @isset($wordCount) class="learning-context-with-count" @endisset><span aria-hidden="true">📚</span> Обрані теми ({{ $selectedTopics->count() }}) @isset($wordCount)<span class="learning-word-count">{{ $wordCount }} {{ $wordLabel }}</span>@endisset</summary>
            <ul>
                @foreach ($selectedTopics as $topic)
                    <li>{{ $topic->name }}</li>
                @endforeach
            </ul>
            @if ($needsReview)<p>Не показувати вивчені слова</p>@endif
            <a href="{{ $changeTopicsUrl }}">Змінити теми</a>
        </details>
