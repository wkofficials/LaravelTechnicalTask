<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ config('app.name', 'Laravel') }}</title>
        <meta name="description" content="A role-based admin system for bulk Excel imports, background job processing, and searchable record management at scale.">

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800&display=swap" rel="stylesheet" />
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased text-slate-900 bg-white dark:bg-slate-950 dark:text-slate-100">

        {{-- Nav --}}
        <header class="border-b border-slate-100 dark:border-slate-800">
            <div class="max-w-6xl mx-auto px-6 py-4 flex items-center justify-between">
                <div class="flex items-center gap-2 font-bold text-lg">
                    <i class="bi bi-boxes text-indigo-600 dark:text-indigo-400"></i>
                    {{ config('app.name') }}
                </div>
                <nav class="flex items-center gap-3">
                    @auth
                        <a
                            href="{{ route('dashboard') }}"
                            class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-indigo-600 text-white text-sm font-semibold hover:bg-indigo-500 transition"
                        >
                            Go to Dashboard <i class="bi bi-arrow-right"></i>
                        </a>
                    @else
                        <a href="{{ route('login') }}" class="px-4 py-2 rounded-lg text-sm font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 transition">
                            Log in
                        </a>
                        @if (Route::has('register'))
                            <a href="{{ route('register') }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-indigo-600 text-white text-sm font-semibold hover:bg-indigo-500 transition">
                                Register
                            </a>
                        @endif
                    @endauth
                </nav>
            </div>
        </header>

        {{-- Hero --}}
        <section class="relative overflow-hidden bg-gradient-to-b from-indigo-50 via-white to-white dark:from-slate-900 dark:via-slate-950 dark:to-slate-950">
            <div class="max-w-6xl mx-auto px-6 pt-20 pb-24 text-center">
                <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-indigo-100 text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-300 text-xs font-semibold tracking-wide uppercase">
                    <i class="bi bi-stars"></i> Laravel Technical Assessment
                </span>

                <h1 class="mt-6 text-4xl sm:text-5xl font-extrabold tracking-tight">
                    Import 100,000 rows.<br class="hidden sm:block">
                    Search them in <span class="text-indigo-600 dark:text-indigo-400">under a second.</span>
                </h1>

                <p class="mt-5 max-w-2xl mx-auto text-lg text-slate-600 dark:text-slate-400">
                    A role-based admin system built to demonstrate queued Excel imports, live progress tracking,
                    permission-scoped CRUD, and fast indexed search &mdash; proven at real scale, not just in theory.
                </p>

                <div class="mt-8 flex items-center justify-center gap-3">
                    @auth
                        <a href="{{ route('dashboard') }}" class="inline-flex items-center gap-2 px-6 py-3 rounded-lg bg-indigo-600 text-white font-semibold hover:bg-indigo-500 transition shadow-lg shadow-indigo-600/20">
                            Go to Dashboard <i class="bi bi-arrow-right"></i>
                        </a>
                    @else
                        <a href="{{ route('login') }}" class="inline-flex items-center gap-2 px-6 py-3 rounded-lg bg-indigo-600 text-white font-semibold hover:bg-indigo-500 transition shadow-lg shadow-indigo-600/20">
                            Log in <i class="bi bi-arrow-right"></i>
                        </a>
                        @if (Route::has('register'))
                            <a href="{{ route('register') }}" class="px-6 py-3 rounded-lg border border-slate-300 dark:border-slate-700 font-semibold hover:bg-slate-50 dark:hover:bg-slate-800 transition">
                                Create an account
                            </a>
                        @endif
                    @endauth
                </div>
            </div>
        </section>

        {{-- Feature grid --}}
        <section class="max-w-6xl mx-auto px-6 py-20">
            <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-6">
                @foreach ([
                    ['bi-shield-lock', 'Role-Based Access Control', 'Three distinct roles &mdash; Admin, Manager, Viewer &mdash; via Spatie Permission, each with its own enforced set of permissions, not just labels.'],
                    ['bi-file-earmark-spreadsheet', 'Bulk Excel Import', 'Upload files with ~100,000 rows. Processed entirely off the request via chunked, batch-inserted queue jobs.'],
                    ['bi-graph-up-arrow', 'Live Progress Tracking', 'A polling status endpoint reports real row-by-row progress while the import job runs in the background.'],
                    ['bi-table', 'Full CRUD', 'Create, edit, and delete imported records &mdash; each action individually gated by its own permission.'],
                    ['bi-search', 'Fast Search & Sort', 'Indexed FULLTEXT search and sortable columns, load-tested end-to-end against a genuine 100,000-row dataset.'],
                    ['bi-stack', 'Modern Laravel Stack', 'Laravel 12, MySQL, database-driven queues, and an AdminLTE-powered admin panel.'],
                ] as [$icon, $title, $description])
                    <div class="p-6 rounded-2xl border border-slate-200 dark:border-slate-800 hover:border-indigo-300 dark:hover:border-indigo-700 hover:shadow-lg transition">
                        <div class="w-11 h-11 rounded-xl bg-indigo-600/10 text-indigo-600 dark:text-indigo-400 flex items-center justify-center text-xl mb-4">
                            <i class="bi {{ $icon }}"></i>
                        </div>
                        <h3 class="font-semibold text-lg mb-1">{{ $title }}</h3>
                        <p class="text-sm text-slate-600 dark:text-slate-400 leading-relaxed">{!! $description !!}</p>
                    </div>
                @endforeach
            </div>
        </section>

        <footer class="border-t border-slate-100 dark:border-slate-800 py-8">
            <div class="max-w-6xl mx-auto px-6 text-sm text-slate-500 dark:text-slate-400 flex items-center justify-between">
                <span>&copy; {{ now()->year }} {{ config('app.name') }}</span>
                <span>Built with Laravel {{ app()->version() }}</span>
            </div>
        </footer>
    </body>
</html>
