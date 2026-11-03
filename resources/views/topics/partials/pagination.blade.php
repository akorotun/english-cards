@if ($paginator->hasPages())
    <nav class="topic-pagination" aria-label="Сторінки списку">
        <p class="hint">Сторінка {{ $paginator->currentPage() }} з {{ $paginator->lastPage() }}</p>
        <div class="action-row">
            @if ($paginator->onFirstPage())
                <span class="button button-secondary pagination-disabled" aria-disabled="true">Попередня</span>
            @else
                <a class="button button-secondary" href="{{ $paginator->previousPageUrl() }}" rel="prev">Попередня</a>
            @endif
            @if ($paginator->hasMorePages())
                <a class="button button-secondary" href="{{ $paginator->nextPageUrl() }}" rel="next">Наступна</a>
            @else
                <span class="button button-secondary pagination-disabled" aria-disabled="true">Наступна</span>
            @endif
        </div>
    </nav>
@endif
