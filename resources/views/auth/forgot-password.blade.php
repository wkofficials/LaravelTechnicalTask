<x-guest-layout>
    <div class="mb-7 text-center">
        <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-indigo-50 text-xl text-indigo-600 dark:bg-indigo-500/10 dark:text-indigo-300">
            <i class="bi bi-key"></i>
        </span>
        <h1 class="mt-4 text-2xl font-extrabold tracking-tight">Forgot your password?</h1>
        <p class="mt-1.5 text-sm text-slate-500 dark:text-slate-400">
            Enter your email and we'll send you a link to choose a new one.
        </p>
    </div>

    <x-auth-session-status class="mb-5" :status="session('status')" />

    <form method="POST" action="{{ route('password.email') }}" class="space-y-5" x-data="{ busy: false }" @submit="busy = true">
        @csrf

        <div>
            <x-input-label for="email" :value="__('Email')" />
            <div class="relative mt-1.5">
                <i class="bi bi-envelope pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400" aria-hidden="true"></i>
                <x-text-input id="email" class="block w-full pl-10" type="email" name="email" :value="old('email')"
                              required autofocus autocomplete="username" placeholder="you@company.com" />
            </div>
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <x-primary-button class="w-full py-3 text-base" x-bind:disabled="busy">
            <span x-show="!busy">{{ __('Send reset link') }} <i class="bi bi-send"></i></span>
            <span x-show="busy" x-cloak><i class="bi bi-arrow-repeat inline-block animate-spin"></i> {{ __('Sending…') }}</span>
        </x-primary-button>
    </form>

    <p class="mt-7 text-center text-sm">
        <a href="{{ route('login') }}" class="font-semibold text-indigo-600 hover:text-indigo-500 dark:text-indigo-400">
            <i class="bi bi-arrow-left"></i> Back to sign in
        </a>
    </p>
</x-guest-layout>
