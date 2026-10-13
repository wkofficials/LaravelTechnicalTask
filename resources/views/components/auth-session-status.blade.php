@props(['status'])

@if ($status)
    <div {{ $attributes->merge(['class' => 'flex items-start gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-300']) }} role="status">
        <i class="bi bi-check-circle-fill mt-0.5" aria-hidden="true"></i>
        <span>{{ $status }}</span>
    </div>
@endif
