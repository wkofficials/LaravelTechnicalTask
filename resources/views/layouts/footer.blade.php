<footer class="app-footer d-flex flex-wrap justify-content-between gap-2">
    <span>&copy; {{ now()->year }} <strong>{{ config('app.name') }}</strong></span>
    <span class="d-flex gap-3">
        <a href="{{ route('pricing') }}" class="link-secondary text-decoration-none">Pricing</a>
        <a href="{{ route('terms') }}" class="link-secondary text-decoration-none">Terms</a>
        <a href="{{ route('privacy') }}" class="link-secondary text-decoration-none">Privacy</a>
    </span>
</footer>
