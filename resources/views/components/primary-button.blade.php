<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-indigo-600 to-violet-600 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-indigo-600/25 transition hover:from-indigo-500 hover:to-violet-500 hover:shadow-indigo-600/40 focus:outline-none focus:ring-4 focus:ring-indigo-500/30 active:scale-[.98] disabled:cursor-not-allowed disabled:opacity-60']) }}>
    {{ $slot }}
</button>
