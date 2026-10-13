<x-guest-layout>
    <div class="mb-7 text-center">
        <h1 class="text-2xl font-extrabold tracking-tight">Welcome back <span aria-hidden="true">👋</span></h1>
        <p class="mt-1.5 text-sm text-slate-500 dark:text-slate-400">Sign in to your company workspace</p>
    </div>

    <x-auth-session-status class="mb-5" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}" class="space-y-5" x-data="{ show: false, busy: false }" @submit="busy = true">
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

        <div>
            <div class="flex items-center justify-between">
                <x-input-label for="password" :value="__('Password')" />
                @if (Route::has('password.request'))
                    <a class="text-sm font-semibold text-indigo-600 hover:text-indigo-500 dark:text-indigo-400" href="{{ route('password.request') }}">
                        {{ __('Forgot password?') }}
                    </a>
                @endif
            </div>
            <div class="relative mt-1.5">
                <i class="bi bi-lock pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400" aria-hidden="true"></i>
                <x-text-input id="password" class="block w-full pl-10 pr-11" type="password" x-bind:type="show ? 'text' : 'password'"
                              name="password" required autocomplete="current-password" placeholder="••••••••" />
                <button type="button" @click="show = !show"
                        class="absolute right-2 top-1/2 -translate-y-1/2 rounded-lg p-1.5 text-slate-400 hover:text-slate-600 focus:outline-none focus:ring-2 focus:ring-indigo-500/40 dark:hover:text-slate-200"
                        x-bind:aria-label="show ? 'Hide password' : 'Show password'" aria-label="Show password">
                    <i class="bi" x-bind:class="show ? 'bi-eye-slash' : 'bi-eye'"></i>
                </button>
            </div>
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <label for="remember_me" class="inline-flex cursor-pointer items-center gap-2">
            <input id="remember_me" type="checkbox" name="remember"
                   class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-800">
            <span class="text-sm text-slate-600 dark:text-slate-400">{{ __('Keep me signed in') }}</span>
        </label>

        <x-primary-button class="w-full py-3 text-base" x-bind:disabled="busy">
            <span x-show="!busy">{{ __('Sign in') }} <i class="bi bi-arrow-right"></i></span>
            <span x-show="busy" x-cloak><i class="bi bi-arrow-repeat inline-block animate-spin"></i> {{ __('Signing in…') }}</span>
        </x-primary-button>
    </form>

    <div class="my-7 flex items-center gap-3 text-xs font-semibold uppercase tracking-wider text-slate-400">
        <span class="h-px flex-1 bg-slate-200 dark:bg-slate-800"></span> New here? <span class="h-px flex-1 bg-slate-200 dark:bg-slate-800"></span>
    </div>

    <a href="{{ route('register') }}"
       class="flex w-full items-center justify-center gap-2 rounded-xl border border-slate-300 px-5 py-3 font-semibold text-slate-700 transition hover:border-indigo-400 hover:bg-indigo-50 hover:text-indigo-700 dark:border-slate-700 dark:text-slate-200 dark:hover:border-indigo-500 dark:hover:bg-indigo-500/10">
        <i class="bi bi-building-add"></i> Create a company account
    </a>

    @if (app()->environment('local'))
        {{-- Local development only: the seeded demo accounts. --}}
        <details class="mt-6 rounded-xl border border-dashed border-amber-300 bg-amber-50/70 p-3 text-xs text-amber-900 dark:border-amber-500/30 dark:bg-amber-500/5 dark:text-amber-200">
            <summary class="cursor-pointer font-semibold"><i class="bi bi-cone-striped"></i> Demo accounts (local only)</summary>
            <div class="mt-2 grid gap-1" x-data>
                @foreach (['admin@example.com' => 'Owner', 'manager@example.com' => 'Manager', 'operator@example.com' => 'Operator', 'viewer@example.com' => 'Viewer', 'other@example.com' => 'Other company', 'platform@example.com' => 'Platform admin'] as $demoEmail => $demoRole)
                    <button type="button" class="flex justify-between rounded-lg px-2 py-1 text-left hover:bg-amber-100 dark:hover:bg-amber-500/10"
                            @click="document.getElementById('email').value = '{{ $demoEmail }}'; document.getElementById('password').value = 'password'; document.getElementById('password').focus()">
                        <span class="font-mono">{{ $demoEmail }}</span><span class="opacity-75">{{ $demoRole }}</span>
                    </button>
                @endforeach
                <span class="px-2 opacity-75">Password for all: <code>password</code></span>
            </div>
        </details>
    @endif
</x-guest-layout>
