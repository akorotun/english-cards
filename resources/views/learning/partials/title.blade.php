<h1 class="learning-header-title" id="learning-title">
            @if ($isCheck ?? false)
                <x-icons.check-circle />
            @else
                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 6.5C9.5 4.5 6.5 4 3 5v13c3.5-1 6.5-.5 9 1.5m0-13C14.5 4.5 17.5 4 21 5v13c-3.5-1-6.5-.5-9 1.5m0-13v13" />
                </svg>
            @endif
            {{ ($isCheck ?? false) ? 'Перевірка' : 'Навчання' }}
        </h1>
