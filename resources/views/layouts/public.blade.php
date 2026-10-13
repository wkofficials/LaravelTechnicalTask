<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>@yield('title', config('app.name'))</title>
        <meta name="description" content="@yield('description', 'Import, validate and manage spreadsheet data for your whole team — securely separated per company.')">

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800&display=swap" rel="stylesheet" />
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased text-slate-900 bg-white dark:bg-slate-950 dark:text-slate-100">

        <header class="border-b border-slate-100 dark:border-slate-800">
            <div class="max-w-6xl mx-auto px-6 py-4 flex items-center justify-between gap-4">
                <a href="{{ url('/') }}" class="flex items-center gap-2 font-bold text-lg">
                    <i class="bi bi-boxes text-indigo-600 dark:text-indigo-400"></i>
                    {{ config('app.name') }}
                </a>
                <nav class="flex items-center gap-1 sm:gap-3">
                    <a href="{{ route('pricing') }}" class="px-3 py-2 rounded-lg text-sm font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 transition">Pricing</a>
                    @auth
                        <a href="{{ route('dashboard') }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-indigo-600 text-white text-sm font-semibold hover:bg-indigo-500 transition">
                            Dashboard <i class="bi bi-arrow-right"></i>
                        </a>
                    @else
                        <a href="{{ route('login') }}" class="px-3 py-2 rounded-lg text-sm font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 transition">Log in</a>
                        <a href="{{ route('register') }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-indigo-600 text-white text-sm font-semibold hover:bg-indigo-500 transition">Start free</a>
                    @endauth
                </nav>
            </div>
        </header>

        <main>
            @yield('content')
        </main>

        <footer class="border-t border-slate-100 dark:border-slate-800 py-8">
            <div class="max-w-6xl mx-auto px-6 text-sm text-slate-500 dark:text-slate-400 flex flex-wrap items-center justify-between gap-3">
                <span>&copy; {{ now()->year }} {{ config('billing.seller.name') }}</span>
                <nav class="flex gap-4">
                    <a href="{{ route('pricing') }}" class="hover:text-slate-900 dark:hover:text-white">Pricing</a>
                    <a href="{{ route('terms') }}" class="hover:text-slate-900 dark:hover:text-white">Terms</a>
                    <a href="{{ route('privacy') }}" class="hover:text-slate-900 dark:hover:text-white">Privacy</a>
                    @if (config('billing.seller.email'))
                        <a href="mailto:{{ config('billing.seller.email') }}" class="hover:text-slate-900 dark:hover:text-white">Contact</a>
                    @endif
                </nav>
            </div>
        </footer>
    </body>
</html>
