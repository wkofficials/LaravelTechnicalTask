<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'Dashboard') · {{ config('app.name') }}</title>

    <meta name="description" content="{{ config('app.name') }} — spreadsheet imports for teams">
    <meta name="author" content="Waseem Khan">


    {{-- AdminLTE Theme Initialization --}}
    <script>
        (() => {
            const html = document.documentElement;
            const key = 'lte-theme';

            let theme = 'light';

            try {
                theme = localStorage.getItem(key) ?? theme;
            } catch (e) {}

            if (!['light', 'dark'].includes(theme)) {
                theme = window.matchMedia('(prefers-color-scheme: dark)').matches
                    ? 'dark'
                    : 'light';
            }

            html.setAttribute('data-bs-theme', theme);
            html.style.colorScheme = theme;

        })();
    </script>


    {{-- Font (same as the public and sign-in pages) --}}
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link rel="stylesheet" href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800&display=swap">


    {{-- OverlayScrollbars CSS --}}
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/overlayscrollbars@2.11.0/styles/overlayscrollbars.min.css">


    {{-- Bootstrap Icons --}}
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">


    {{-- AdminLTE CSS --}}
    <link rel="stylesheet"
          href="{{ asset('adminlte/css/adminlte.css') }}">

    {{-- ImportFlow theme on top of AdminLTE (cache-busted by file time) --}}
    <link rel="stylesheet"
          href="{{ asset('css/admin-theme.css') }}?v={{ filemtime(public_path('css/admin-theme.css')) }}">


    {{-- Custom Page CSS --}}
    @stack('styles')

</head>