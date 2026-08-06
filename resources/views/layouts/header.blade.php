<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'Laravel Technical Task')</title>

    <meta name="description" content="Laravel Technical Assessment">
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


    {{-- Google Font --}}
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/@fontsource/source-sans-3@5.0.12/index.css">


    {{-- OverlayScrollbars CSS --}}
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/overlayscrollbars@2.11.0/styles/overlayscrollbars.min.css">


    {{-- Bootstrap Icons --}}
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">


    {{-- AdminLTE CSS --}}
    <link rel="stylesheet"
          href="{{ asset('adminlte/css/adminlte.css') }}">


    {{-- Custom Page CSS --}}
    @stack('styles')

</head>