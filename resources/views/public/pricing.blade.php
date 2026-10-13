@extends('layouts.public')

@section('title', 'Pricing — '.config('app.name'))

@section('content')
    <section class="max-w-6xl mx-auto px-6 py-16">
        <div class="text-center max-w-2xl mx-auto">
            <h1 class="text-4xl font-extrabold tracking-tight">Plans</h1>
            <p class="mt-4 text-slate-600 dark:text-slate-400">
                Start on the free plan. When you need more, pick a plan and we'll send an invoice &mdash;
                the plan starts as soon as payment arrives.
            </p>
        </div>

        <div class="mt-12 grid gap-6 md:grid-cols-3">
            @foreach ($plans as $plan)
                <div class="p-8 rounded-2xl border {{ $loop->iteration === 2 ? 'border-indigo-500 shadow-lg shadow-indigo-600/10' : 'border-slate-200 dark:border-slate-800' }} flex flex-col">
                    <h2 class="text-xl font-bold">{{ $plan->name }}</h2>

                    <div class="mt-4">
                        @if ($plan->is_default)
                            <span class="text-4xl font-extrabold">Free</span>
                        @elseif ($plan->price_monthly_cents !== null)
                            <span class="text-4xl font-extrabold">{{ $plan->formattedPrice('monthly') }}</span>
                            <span class="text-slate-500">/ month</span>
                            @if ($plan->price_yearly_cents !== null)
                                <div class="text-sm text-slate-500 mt-1">or {{ $plan->formattedPrice('yearly') }} / year</div>
                            @endif
                        @else
                            <span class="text-2xl font-bold">Contact us</span>
                        @endif
                    </div>

                    <ul class="mt-6 space-y-2 text-sm text-slate-700 dark:text-slate-300 flex-1">
                        <li><i class="bi bi-check2 text-indigo-600"></i> {{ number_format($plan->max_users) }} users</li>
                        <li><i class="bi bi-check2 text-indigo-600"></i> {{ number_format($plan->monthly_import_rows) }} imported rows / month</li>
                        <li><i class="bi bi-check2 text-indigo-600"></i> Up to {{ number_format($plan->max_rows_per_import) }} rows per file</li>
                        <li><i class="bi bi-check2 text-indigo-600"></i> Files up to {{ min($plan->max_file_size_mb, \App\Models\Plan::HARD_MAX_FILE_SIZE_MB) }} MB</li>
                        <li><i class="bi bi-check2 text-indigo-600"></i> {{ number_format($plan->max_templates) }} saved column templates</li>
                    </ul>

                    <a href="{{ auth()->check() ? route('billing.index') : route('register') }}"
                       class="mt-8 inline-flex justify-center px-5 py-3 rounded-lg font-semibold transition {{ $loop->iteration === 2 ? 'bg-indigo-600 text-white hover:bg-indigo-500' : 'border border-slate-300 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800' }}">
                        {{ $plan->is_default ? 'Start free' : 'Choose '.$plan->name }}
                    </a>
                </div>
            @endforeach
        </div>

        <p class="mt-10 text-center text-sm text-slate-500">
            Imported rows count rows added or updated; rejected and skipped rows are free. Usage resets monthly.
        </p>
    </section>
@endsection
