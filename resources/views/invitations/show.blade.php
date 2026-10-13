<x-guest-layout>
    <h2 class="text-xl font-extrabold tracking-tight text-slate-900 dark:text-slate-100">
        Join {{ $invitation->company->name }}
    </h2>

    <p class="mt-2 text-sm text-slate-600 dark:text-slate-400">
        You were invited as <strong>{{ $invitation->role }}</strong>
        using <strong>{{ $invitation->email }}</strong>.
    </p>

    @auth
        @if ($emailMatches)
            <form method="POST" action="{{ route('invitations.accept', $token) }}" class="mt-6 flex justify-end">
                @csrf
                <x-primary-button>{{ __('Accept invitation') }}</x-primary-button>
            </form>
        @else
            <p class="mt-4 text-sm text-red-600">
                You are signed in as {{ auth()->user()->email }}. This invitation is for a different email address —
                sign out and open the link again.
            </p>

            <form method="POST" action="{{ route('logout') }}" class="mt-6 flex justify-end">
                @csrf
                <x-primary-button>{{ __('Sign out') }}</x-primary-button>
            </form>
        @endif
    @else
        <form method="POST" action="{{ route('invitations.register', $token) }}" class="mt-6">
            @csrf

            <div>
                <x-input-label for="name" :value="__('Your Name')" />
                <x-text-input id="name" class="block mt-1 w-full" type="text" name="name" :value="old('name')" required autofocus autocomplete="name" />
                <x-input-error :messages="$errors->get('name')" class="mt-2" />
            </div>

            <div class="mt-4">
                <x-input-label for="password" :value="__('Password')" />
                <x-text-input id="password" class="block mt-1 w-full" type="password" name="password" required autocomplete="new-password" />
                <x-input-error :messages="$errors->get('password')" class="mt-2" />
            </div>

            <div class="mt-4">
                <x-input-label for="password_confirmation" :value="__('Confirm Password')" />
                <x-text-input id="password_confirmation" class="block mt-1 w-full" type="password" name="password_confirmation" required autocomplete="new-password" />
            </div>

            <div class="flex justify-end mt-6">
                <x-primary-button>{{ __('Create account & join') }}</x-primary-button>
            </div>
        </form>
    @endauth
</x-guest-layout>
