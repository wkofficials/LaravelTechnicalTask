<x-guest-layout>
    <div class="mb-7 text-center">
        <span class="inline-flex items-center gap-1.5 rounded-full bg-indigo-50 px-3 py-1 text-xs font-semibold text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-300">
            <i class="bi bi-stars"></i> Free plan &middot; no card needed
        </span>
        <h1 class="mt-3 text-2xl font-extrabold tracking-tight">Create your company workspace</h1>
        <p class="mt-1.5 text-sm text-slate-500 dark:text-slate-400">You'll be its owner and can invite your team next.</p>
    </div>

    <form method="POST" action="{{ route('register') }}" class="space-y-5"
          x-data="{
              show: false,
              busy: false,
              password: '',
              get score() {
                  let s = 0;
                  if (this.password.length >= 8) s++;
                  if (this.password.length >= 12) s++;
                  if (/[A-Z]/.test(this.password) && /[a-z]/.test(this.password)) s++;
                  if (/\d/.test(this.password)) s++;
                  if (/[^A-Za-z0-9]/.test(this.password)) s++;
                  return Math.min(s, 4);
              },
              get label() { return ['Too short', 'Weak', 'Fair', 'Good', 'Strong'][this.score]; },
          }"
          @submit="busy = true">
        @csrf

        {{-- Company --}}
        <div>
            <x-input-label for="company_name" :value="__('Company name')" />
            <div class="relative mt-1.5">
                <i class="bi bi-building pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400" aria-hidden="true"></i>
                <x-text-input id="company_name" class="block w-full pl-10" type="text" name="company_name" :value="old('company_name')"
                              required autofocus autocomplete="organization" placeholder="Acme Traders" />
            </div>
            <x-input-error :messages="$errors->get('company_name')" class="mt-2" />
        </div>

        <div class="grid gap-5 sm:grid-cols-2">
            <div>
                <x-input-label for="name" :value="__('Your name')" />
                <div class="relative mt-1.5">
                    <i class="bi bi-person pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400" aria-hidden="true"></i>
                    <x-text-input id="name" class="block w-full pl-10" type="text" name="name" :value="old('name')"
                                  required autocomplete="name" placeholder="Ayesha Khan" />
                </div>
                <x-input-error :messages="$errors->get('name')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="email" :value="__('Work email')" />
                <div class="relative mt-1.5">
                    <i class="bi bi-envelope pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400" aria-hidden="true"></i>
                    <x-text-input id="email" class="block w-full pl-10" type="email" name="email" :value="old('email')"
                                  required autocomplete="username" placeholder="you@company.com" />
                </div>
                <x-input-error :messages="$errors->get('email')" class="mt-2" />
            </div>
        </div>

        {{-- Password --}}
        <div>
            <x-input-label for="password" :value="__('Password')" />
            <div class="relative mt-1.5">
                <i class="bi bi-lock pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400" aria-hidden="true"></i>
                <x-text-input id="password" class="block w-full pl-10 pr-11" type="password" x-bind:type="show ? 'text' : 'password'"
                              name="password" x-model="password" required autocomplete="new-password" placeholder="At least 8 characters"
                              aria-describedby="password-strength" />
                <button type="button" @click="show = !show"
                        class="absolute right-2 top-1/2 -translate-y-1/2 rounded-lg p-1.5 text-slate-400 hover:text-slate-600 focus:outline-none focus:ring-2 focus:ring-indigo-500/40 dark:hover:text-slate-200"
                        x-bind:aria-label="show ? 'Hide password' : 'Show password'" aria-label="Show password">
                    <i class="bi" x-bind:class="show ? 'bi-eye-slash' : 'bi-eye'"></i>
                </button>
            </div>

            <div class="mt-2" x-show="password.length > 0" x-cloak id="password-strength">
                <div class="flex gap-1.5" aria-hidden="true">
                    <template x-for="i in 4" :key="i">
                        <span class="h-1.5 flex-1 rounded-full transition-colors duration-300"
                              :class="i <= score
                                  ? ['bg-rose-500', 'bg-amber-500', 'bg-lime-500', 'bg-emerald-500'][score - 1]
                                  : 'bg-slate-200 dark:bg-slate-700'"></span>
                    </template>
                </div>
                <p class="mt-1 text-xs font-medium text-slate-500 dark:text-slate-400">Password strength: <span x-text="label"></span></p>
            </div>
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="password_confirmation" :value="__('Confirm password')" />
            <div class="relative mt-1.5">
                <i class="bi bi-shield-lock pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400" aria-hidden="true"></i>
                <x-text-input id="password_confirmation" class="block w-full pl-10" type="password" x-bind:type="show ? 'text' : 'password'"
                              name="password_confirmation" required autocomplete="new-password" placeholder="Repeat your password" />
            </div>
            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>

        <x-primary-button class="w-full py-3 text-base" x-bind:disabled="busy">
            <span x-show="!busy">{{ __('Create workspace') }} <i class="bi bi-arrow-right"></i></span>
            <span x-show="busy" x-cloak><i class="bi bi-arrow-repeat inline-block animate-spin"></i> {{ __('Creating…') }}</span>
        </x-primary-button>

        <p class="text-center text-xs text-slate-500 dark:text-slate-400">
            By creating an account you agree to the
            <a href="{{ route('terms') }}" class="font-semibold text-slate-700 underline-offset-2 hover:underline dark:text-slate-300">Terms</a>
            and <a href="{{ route('privacy') }}" class="font-semibold text-slate-700 underline-offset-2 hover:underline dark:text-slate-300">Privacy Policy</a>.
        </p>
    </form>

    <p class="mt-7 text-center text-sm text-slate-600 dark:text-slate-400">
        Already have an account?
        <a href="{{ route('login') }}" class="font-semibold text-indigo-600 hover:text-indigo-500 dark:text-indigo-400">Sign in</a>
    </p>
</x-guest-layout>
