<fieldset class="field filter-field">
    <legend id="{{ $name }}-label">{{ $label }}</legend>
    <details class="filter-menu" data-filter-menu data-dropdown>
        <summary class="filter-trigger" aria-labelledby="{{ $name }}-label {{ $name }}-value" aria-invalid="{{ $errors->has($name) ? 'true' : 'false' }}" @error($name) aria-describedby="{{ $name }}-error" @enderror>
            <span id="{{ $name }}-value" data-filter-value>{{ $options[$selectedValue] ?? reset($options) }}</span>
            <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                <path d="m6 9 6 6 6-6" />
            </svg>
        </summary>
        <div class="header-dropdown filter-options">
            @foreach ($options as $value => $optionLabel)
                <label class="filter-option">
                    <input type="radio" name="{{ $name }}" value="{{ $value }}" @checked((string) $value === $selectedValue)>
                    <span>{{ $optionLabel }}</span>
                </label>
            @endforeach
        </div>
    </details>
    @error($name) <p id="{{ $name }}-error" class="field-error">{{ $message }}</p> @enderror
</fieldset>
