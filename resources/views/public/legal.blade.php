@extends('layouts.public')

@section('title', $title.' — '.config('app.name'))

@section('content')
    <section class="max-w-3xl mx-auto px-6 py-16">
        <h1 class="text-3xl font-extrabold tracking-tight">{{ $title }}</h1>

        @if (! view()->exists("legal.{$page}"))
            {{-- Shown until the real text is added as resources/views/legal/{terms,privacy}.blade.php --}}
            <div class="mt-6 p-4 rounded-lg bg-amber-50 border border-amber-200 text-amber-900 dark:bg-amber-500/10 dark:border-amber-500/30 dark:text-amber-200">
                <strong>Placeholder.</strong> The {{ strtolower($title) }} for this service have not been published yet.
                Please contact {{ config('billing.seller.email') ?: 'us' }} with any questions.
            </div>
        @else
            <div class="mt-6 space-y-4 leading-relaxed text-slate-700 dark:text-slate-300">
                @include("legal.{$page}")
            </div>
        @endif
    </section>
@endsection
