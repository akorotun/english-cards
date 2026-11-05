<h1 class="learning-header-title" id="learning-title">
            @if ($isCheck ?? false)
                <x-icons.check-circle />
            @else
                <x-icons.book-open />
            @endif
            {{ ($isCheck ?? false) ? 'Перевірка' : 'Навчання' }}
        </h1>
