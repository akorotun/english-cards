@php
    $layoutActiveProfile = auth()->check() && auth()->user()->hasVerifiedEmail()
        ? app(\App\ActiveLearningProfile::class)->current(request())
        : null;
    $layoutTheme = $layoutActiveProfile?->theme_preference?->value ?? 'system';
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
    <div class="site-wrap">
        <header class="site-header">
            <a class="brand" href="/" aria-label="English Cards — головна">English <span>Cards</span></a>
            @auth
                @if (auth()->user()->hasVerifiedEmail())
                    <nav aria-label="Основна навігація" class="site-nav">
                        <a href="{{ route('profiles.index') }}" @if (request()->routeIs('profiles.*')) aria-current="page" @endif>Профілі</a>
                        <a href="{{ route('vocabulary.import') }}" @if (request()->routeIs('vocabulary.import*')) aria-current="page" @endif>Імпорт слів</a>
                    </nav>
                @endif
            @endauth
            @if ($layoutActiveProfile && ! request()->routeIs('profiles.show'))
                @include('profiles.partials.theme-menu', ['themeProfile' => $layoutActiveProfile])
            @endif
        </header>

        <main id="main" class="auth-shell">
            <div class="notice" role="status" data-ui-feedback hidden></div>
            @if (session('status'))
                <div class="notice" role="status">{{ session('status') }}</div>
            @endif
            @yield('content')
        </main>
    </div>
</body>
</html>
