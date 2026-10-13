@extends('layouts.public')

@section('content')
    {{-- Hero --}}
    <section class="relative overflow-hidden bg-gradient-to-b from-indigo-50 via-white to-white dark:from-slate-900 dark:via-slate-950 dark:to-slate-950">
        <div class="max-w-6xl mx-auto px-6 pt-20 pb-24 text-center">
            <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-indigo-100 text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-300 text-xs font-semibold tracking-wide uppercase">
                <i class="bi bi-file-earmark-spreadsheet"></i> Spreadsheet imports for teams
            </span>

            <h1 class="mt-6 text-4xl sm:text-5xl font-extrabold tracking-tight">
                Stop copy-pasting spreadsheets.<br class="hidden sm:block">
                <span class="text-indigo-600 dark:text-indigo-400">Import them, checked and clean.</span>
            </h1>

            <p class="mt-5 max-w-2xl mx-auto text-lg text-slate-600 dark:text-slate-400">
                Upload Excel or CSV files with tens of thousands of rows, match the columns once, and see exactly
                which rows were added, updated or rejected &mdash; and why. Your company's data stays your company's.
            </p>

            <div class="mt-8 flex flex-wrap items-center justify-center gap-3">
                @auth
                    <a href="{{ route('dashboard') }}" class="inline-flex items-center gap-2 px-6 py-3 rounded-lg bg-indigo-600 text-white font-semibold hover:bg-indigo-500 transition shadow-lg shadow-indigo-600/20">
                        Go to Dashboard <i class="bi bi-arrow-right"></i>
                    </a>
                @else
                    <a href="{{ route('register') }}" class="inline-flex items-center gap-2 px-6 py-3 rounded-lg bg-indigo-600 text-white font-semibold hover:bg-indigo-500 transition shadow-lg shadow-indigo-600/20">
                        Create your company account <i class="bi bi-arrow-right"></i>
                    </a>
                    <a href="{{ route('pricing') }}" class="px-6 py-3 rounded-lg border border-slate-300 dark:border-slate-700 font-semibold hover:bg-slate-50 dark:hover:bg-slate-800 transition">
                        See plans
                    </a>
                @endauth
            </div>
        </div>
    </section>

    {{-- Features --}}
    <section class="max-w-6xl mx-auto px-6 py-20">
        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach ([
                ['bi-diagram-3', 'Match columns once', 'Columns are suggested from your headings. Save the mapping as a template and the next file — even with columns moved — is ready in a click.'],
                ['bi-check2-square', 'Preview before importing', 'See your data and confirm the mapping first. Nothing is written until you say so.'],
                ['bi-files', 'You decide on duplicates', 'Reject, skip or update rows that already exist. Repeats inside a file are caught too. Nothing is overwritten silently.'],
                ['bi-exclamation-triangle', 'Clear error reports', 'Every rejected row with its spreadsheet row number and the reason — download them, fix them, upload again.'],
                ['bi-people', 'Built for teams', 'Invite colleagues as owner, manager, operator or viewer. Each role sees and does only what it should.'],
                ['bi-shield-lock', 'Your data stays yours', 'Every company is separated from every other. Activity is logged, and exports are private and expire after a day.'],
            ] as [$icon, $title, $description])
                <div class="p-6 rounded-2xl border border-slate-200 dark:border-slate-800 hover:border-indigo-300 dark:hover:border-indigo-700 hover:shadow-lg transition">
                    <div class="w-11 h-11 rounded-xl bg-indigo-600/10 text-indigo-600 dark:text-indigo-400 flex items-center justify-center text-xl mb-4">
                        <i class="bi {{ $icon }}"></i>
                    </div>
                    <h3 class="font-semibold text-lg mb-1">{{ $title }}</h3>
                    <p class="text-sm text-slate-600 dark:text-slate-400 leading-relaxed">{{ $description }}</p>
                </div>
            @endforeach
        </div>
    </section>
@endsection
