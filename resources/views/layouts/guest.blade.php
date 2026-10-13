<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800&display=swap" rel="stylesheet" />
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="h-full font-sans antialiased text-slate-900 bg-slate-50 dark:bg-slate-950 dark:text-slate-100">
        <div class="flex min-h-full">

            {{-- Brand panel (large screens) --}}
            <aside class="relative hidden lg:flex lg:w-[46%] xl:w-1/2 flex-col justify-between overflow-hidden bg-gradient-to-br from-indigo-700 via-violet-700 to-fuchsia-700 p-12 text-white">
                <div class="pointer-events-none absolute inset-0 bg-grid [mask-image:radial-gradient(ellipse_at_center,black_40%,transparent_75%)]"></div>
                <div class="pointer-events-none absolute -top-24 -left-24 h-96 w-96 rounded-full bg-fuchsia-400/40 blur-3xl animate-blob"></div>
                <div class="pointer-events-none absolute bottom-0 right-0 h-[28rem] w-[28rem] rounded-full bg-indigo-400/40 blur-3xl animate-blob-slow"></div>

                <a href="{{ url('/') }}" class="relative flex items-center gap-3 text-xl font-extrabold tracking-tight">
                    <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-white/15 ring-1 ring-white/25 backdrop-blur">
                        <i class="bi bi-boxes"></i>
                    </span>
                    {{ config('app.name') }}
                </a>

                <div class="relative max-w-md">
                    <h2 class="text-4xl font-extrabold leading-tight tracking-tight">
                        Your spreadsheets,<br>
                        <span class="bg-gradient-to-r from-amber-200 to-pink-200 bg-clip-text text-transparent">imported clean.</span>
                    </h2>
                    <p class="mt-4 text-lg text-indigo-100/90">
                        Upload, match columns, preview — and know exactly which rows made it in and why the rest didn't.
                    </p>

                    {{-- Illustration of the import flow (decorative) --}}
                    <div class="mt-10 rounded-2xl bg-white/10 p-5 ring-1 ring-white/20 backdrop-blur-md shadow-2xl shadow-indigo-950/30" aria-hidden="true">
                        <div class="flex items-center justify-between text-sm">
                            <span class="flex items-center gap-2 font-semibold">
                                <i class="bi bi-file-earmark-spreadsheet-fill text-emerald-300"></i> customers-october.xlsx
                            </span>
                            <span class="rounded-full bg-emerald-400/20 px-2.5 py-0.5 text-xs font-semibold text-emerald-200">Processing</span>
                        </div>
                        <div class="mt-4 h-2.5 overflow-hidden rounded-full bg-white/15">
                            <div class="h-full rounded-full bg-gradient-to-r from-emerald-300 via-teal-200 to-cyan-200 animate-progress"></div>
                        </div>
                        <div class="mt-4 grid grid-cols-3 gap-3 text-center text-xs">
                            <div class="rounded-lg bg-white/10 py-2"><div class="font-bold text-emerald-200"><i class="bi bi-plus-circle"></i> Added</div></div>
                            <div class="rounded-lg bg-white/10 py-2"><div class="font-bold text-sky-200"><i class="bi bi-arrow-repeat"></i> Updated</div></div>
                            <div class="rounded-lg bg-white/10 py-2"><div class="font-bold text-rose-200"><i class="bi bi-x-circle"></i> Rejected</div></div>
                        </div>
                    </div>
                </div>

                <ul class="relative grid gap-3 text-sm text-indigo-50">
                    <li class="flex items-center gap-3"><i class="bi bi-shield-check text-lg text-emerald-300"></i> Every company's data is kept separate</li>
                    <li class="flex items-center gap-3"><i class="bi bi-people text-lg text-amber-200"></i> Roles for owners, managers, operators and viewers</li>
                    <li class="flex items-center gap-3"><i class="bi bi-arrow-counterclockwise text-lg text-pink-200"></i> Safe retries — nothing is ever imported twice</li>
                </ul>
            </aside>

            {{-- Form side --}}
            <main class="relative flex flex-1 flex-col justify-center px-5 py-10 sm:px-10">
                <div class="pointer-events-none absolute inset-x-0 top-0 h-72 bg-gradient-to-b from-indigo-100/70 to-transparent dark:from-indigo-950/40 lg:hidden"></div>

                <div class="relative mx-auto w-full max-w-md animate-fade-up">
                    <a href="{{ url('/') }}" class="mb-8 flex items-center justify-center gap-2 text-xl font-extrabold lg:hidden">
                        <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-indigo-600 to-fuchsia-600 text-white shadow-lg shadow-indigo-600/30">
                            <i class="bi bi-boxes"></i>
                        </span>
                        {{ config('app.name') }}
                    </a>

                    <div class="rounded-3xl border border-slate-200/80 bg-white/90 p-7 shadow-xl shadow-slate-900/5 backdrop-blur sm:p-9 dark:border-slate-800 dark:bg-slate-900/80 dark:shadow-black/20">
                        {{ $slot }}
                    </div>

                    <p class="mt-8 text-center text-xs text-slate-500 dark:text-slate-500">
                        &copy; {{ now()->year }} {{ config('billing.seller.name') }}
                        &middot; <a href="{{ route('terms') }}" class="hover:text-slate-700 dark:hover:text-slate-300">Terms</a>
                        &middot; <a href="{{ route('privacy') }}" class="hover:text-slate-700 dark:hover:text-slate-300">Privacy</a>
                    </p>
                </div>
            </main>
        </div>
    </body>
</html>
