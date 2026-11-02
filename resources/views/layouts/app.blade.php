<!doctype html>
<html lang="uk">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#eaf4ff">
    <title>@yield('title', 'English Cards')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <a class="skip-link" href="#main">Перейти до вмісту</a>
    <div class="site-wrap">
        <header class="site-header">
            <a class="brand" href="/" aria-label="English Cards — головна">English <span>Cards</span></a>
            <nav class="site-nav" aria-label="Основна навігація">
                @auth
                    <form method="post" action="{{ route('logout') }}">
                        @csrf
                        <button class="nav-link" type="submit">Вийти</button>
                    </form>
                @else
                    <a class="nav-link" href="{{ route('login') }}">Увійти</a>
                    <a class="nav-link" href="{{ route('register') }}">Реєстрація</a>
                @endauth
            </nav>
        </header>

        <main id="main" class="auth-shell">
            @if (session('status'))
                <div class="notice" role="status">{{ session('status') }}</div>
            @endif
            @yield('content')
        </main>
    </div>
</body>
</html>
