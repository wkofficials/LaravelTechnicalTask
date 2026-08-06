<!DOCTYPE html>

<html>

@include('layouts.header')

<body>

<div class="app-wrapper">

@include('layouts.navbar')

@include('layouts.sidebar')

<main class="app-main">

@yield('content')

</main>

@include('layouts.footer')

</div>

@include('layouts.scripts')

</body>

</html>