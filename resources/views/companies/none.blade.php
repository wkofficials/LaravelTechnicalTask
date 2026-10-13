<x-guest-layout>
    <h2 class="text-xl font-extrabold tracking-tight text-slate-900 dark:text-slate-100">No active company</h2>

    <p class="mt-2 text-sm text-slate-600 dark:text-slate-400">
        Your account ({{ auth()->user()->email }}) is not a member of any active company.
        Ask a company owner to invite you, or open the invitation link from your email.
    </p>

    @if (auth()->user()->is_super_admin)
        <p class="mt-4 text-sm">
            <a class="underline" href="{{ route('platform.dashboard') }}">Go to the platform admin area</a>
        </p>
    @endif

    <form method="POST" action="{{ route('logout') }}" class="mt-6 flex justify-end">
        @csrf
        <x-primary-button>{{ __('Sign out') }}</x-primary-button>
    </form>
</x-guest-layout>
