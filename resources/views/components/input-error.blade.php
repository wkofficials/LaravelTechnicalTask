@props(['messages'])

@if ($messages)
    <ul {{ $attributes->merge(['class' => 'space-y-1 text-sm font-medium text-rose-600 dark:text-rose-400']) }} role="alert">
        @foreach ((array) $messages as $message)
            <li class="flex items-start gap-1.5"><i class="bi bi-exclamation-circle mt-0.5" aria-hidden="true"></i>{{ $message }}</li>
        @endforeach
    </ul>
@endif
