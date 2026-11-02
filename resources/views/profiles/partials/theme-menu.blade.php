<details class="theme-menu" data-theme-menu data-dropdown>
    <summary class="theme-trigger" aria-label="Вибрати тему оформлення" title="Тема оформлення">
        <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="4" />
            <path d="M12 2v2m0 16v2M4.93 4.93l1.42 1.42m11.3 11.3 1.42 1.42M2 12h2m16 0h2M4.93 19.07l1.42-1.42m11.3-11.3 1.42-1.42" />
        </svg>
    </summary>
    <div class="theme-options" aria-label="Тема оформлення">
        <p class="theme-options-title">Тема оформлення</p>
        @foreach (\App\ThemePreference::cases() as $themeOption)
            <form method="post" action="{{ route('profiles.theme.update', $themeProfile) }}" data-theme-form>
                @csrf
                @method('PATCH')
                <input type="hidden" name="theme_preference" value="{{ $themeOption->value }}">
                <button type="submit" data-theme-option="{{ $themeOption->value }}" aria-pressed="{{ $themeProfile->theme_preference === $themeOption ? 'true' : 'false' }}">
                    {{ match ($themeOption) {
                        \App\ThemePreference::System => 'Системна',
                        \App\ThemePreference::Light => 'Світла',
                        \App\ThemePreference::Dark => 'Темна',
                    } }}
                </button>
            </form>
        @endforeach
    </div>
</details>
