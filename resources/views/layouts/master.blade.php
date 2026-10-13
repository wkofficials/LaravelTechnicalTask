<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    @include('layouts.header')
</head>

<body class="layout-fixed sidebar-expand-lg bg-body-tertiary">

    <div class="app-wrapper">

        {{-- Navbar --}}
        @include('layouts.navbar')

        {{-- Sidebar --}}
        @include('layouts.sidebar')

        {{-- Main Content --}}
        <main class="app-main">
            {{-- Page Header --}}
            @unless (View::hasSection('hide-page-header'))
                <div class="app-content-header">
                    <div class="container-fluid">
                        <div class="d-flex flex-wrap align-items-end justify-content-between gap-3">
                            <div>
                                @php
                                    $homeUrl = app(\App\Support\Tenancy\CurrentCompany::class)->has()
                                        ? route('dashboard')
                                        : (auth()->user()->is_super_admin ? route('platform.dashboard') : url('/'));
                                @endphp
                                <nav aria-label="breadcrumb">
                                    <ol class="breadcrumb if-breadcrumb">
                                        <li class="breadcrumb-item"><a href="{{ $homeUrl }}"><i class="bi bi-house-door"></i> Home</a></li>
                                        @if (request()->routeIs('platform.*'))
                                            <li class="breadcrumb-item"><a href="{{ route('platform.dashboard') }}">Platform</a></li>
                                        @endif
                                        <li class="breadcrumb-item active" aria-current="page">@yield('title', 'Dashboard')</li>
                                    </ol>
                                </nav>
                                <h1 class="if-page-title">@yield('page-title', 'Dashboard')</h1>
                                @hasSection('page-subtitle')
                                    <p class="if-page-subtitle">@yield('page-subtitle')</p>
                                @endif
                            </div>
                            @yield('page-actions')
                        </div>
                    </div>
                </div>
            @endunless

            {{-- Page Content --}}
            <div class="app-content">
                <div class="container-fluid">
                    @yield('content')
                </div>
            </div>
        </main>

        {{-- Footer --}}
        @include('layouts.footer')

    </div>

    {{-- Scripts --}}
    @include('layouts.scripts')

</body>

</html>