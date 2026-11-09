@php
    $layoutActiveProfile = auth()->check() && auth()->user()->hasVerifiedEmail()
        ? app(\App\ActiveLearningProfile::class)->current(request())
        : null;
    $layoutTheme = $layoutActiveProfile?->theme_preference?->value ?? 'system';
    $layoutProfileName = $layoutActiveProfile?->name ?? '';
    $layoutProfileInitial = mb_strtoupper(mb_substr($layoutProfileName, 0, 1));
@endphp
<!doctype html>
<html lang="uk" data-theme="{{ $layoutTheme }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#edf6ff">
    <title>@yield('title', 'English Cards')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <a class="skip-link" href="#main">Перейти до вмісту</a>
    <div class="site-wrap @yield('layout-class')">
        <header class="site-header">
            <a class="brand" href="/" aria-label="English Cards — головна">English <span>Cards</span></a>
            <div class="header-actions">
                @auth
                    @if (auth()->user()->hasVerifiedEmail())
                        <details class="profile-menu" data-header-menu data-dropdown @if (! $layoutActiveProfile) hidden @endif>
                            <summary class="profile-trigger" data-active-profile-trigger aria-label="Активний профіль: {{ $layoutProfileName }}" title="Активний профіль: {{ $layoutProfileName }}">
                                <span class="profile-avatar" data-active-profile-initial>{{ $layoutProfileInitial }}</span>
                                <span class="profile-trigger-name" data-active-profile-name>{{ $layoutProfileName }}</span>
                            </summary>
                            <div class="header-dropdown profile-options">
                                <p class="header-dropdown-title">Активний профіль</p>
                                <strong class="profile-full-name" data-active-profile-name>{{ $layoutProfileName }}</strong>
                                <a href="{{ route('profiles.index') }}">Перемкнути профіль</a>
                            </div>
                        </details>
                    @endif
                @endauth
                @if ($layoutActiveProfile && ! request()->routeIs('profiles.show'))
                    @include('profiles.partials.theme-menu', ['themeProfile' => $layoutActiveProfile])
                @endif
                @auth
                    @if (auth()->user()->hasVerifiedEmail())
                        <details class="navigation-menu" data-header-menu data-dropdown>
                            <summary class="navigation-trigger" aria-label="Відкрити меню" title="Меню">
                                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round">
                                    <path d="M4 6h16M4 12h16M4 18h16" />
                                </svg>
                            </summary>
                            <nav aria-label="Основна навігація" class="header-dropdown site-nav">
                                <a href="{{ route('home') }}" @if (request()->routeIs('home')) aria-current="page" @endif><x-icons.layout-dashboard /> Мій кабінет</a>
                                <a href="{{ route('profiles.index') }}" @if (request()->routeIs('profiles.*')) aria-current="page" @endif><x-icons.users /> Профілі</a>
                                <a href="{{ route('topics.index') }}" @if (request()->routeIs('topics.*')) aria-current="page" @endif><x-icons.books /> Теми</a>
                                <a href="{{ route('learning.index') }}" @if (request()->routeIs('learning.*')) aria-current="page" @endif><x-icons.book-open /> Навчання</a>
                                <a href="{{ route('check.index') }}" @if (request()->routeIs('check.*')) aria-current="page" @endif><x-icons.check-circle /> Перевірка</a>
                                <a href="{{ route('vocabulary.import') }}" @if (request()->routeIs('vocabulary.import*')) aria-current="page" @endif><x-icons.file-import /> Імпорт слів</a>
                                <form method="post" action="{{ route('logout') }}" class="site-nav-logout">
                                    @csrf
                                    <button type="submit"><x-icons.log-out /> Вийти</button>
                                </form>
                            </nav>
                        </details>
                    @endif
                @endauth
            </div>
        </header>

        <main id="main" class="auth-shell @if (request()->routeIs('home', 'learning.*', 'check.*', 'demo.*', 'topics.index', 'profiles.index', 'vocabulary.import')) learning-shell @endif">
            <div class="notice" role="status" data-ui-feedback hidden></div>
            @if (auth()->check() && auth()->user()->hasVerifiedEmail())
                <div class="notice" data-check-sync data-user-id="{{ auth()->id() }}" data-url="{{ route('check.sync') }}" data-csrf="{{ csrf_token() }}" hidden>
                    <p role="status" aria-live="polite" data-check-sync-message></p>
                    <button class="button button-secondary" type="button" data-check-sync-retry hidden>Повторити синхронізацію</button>
                </div>
            @endif
            @if (session('status'))
                <div class="notice" role="status">{{ session('status') }}</div>
            @endif
            @yield('content')
        </main>
    </div>
</body>
</html>
