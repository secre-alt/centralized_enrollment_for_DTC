<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>
        @yield('title', 'DTC EMS')
    </title>

    {{-- Font Awesome --}}
    <link rel="stylesheet" href="{{ asset('vendor/fontawesome-free/css/all.min.css') }}">

    {{-- Poppins --}}
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    {{-- Bootstrap 5 --}}
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet">

    <link rel="stylesheet" href="{{ asset('css/dtc-tokens.css') }}">
    <link rel="stylesheet" href="{{ asset('css/application.css') }}">
    <link rel="stylesheet" href="{{ asset('css/public-navbar.css') }}">
    {{-- DTC Public Theme --}}
    <link rel="stylesheet" href="{{ asset('css/welcome.css') }}">

    {{-- Page-specific styles --}}
    @yield('styles')
</head>

<body>

    {{-- =========================================================
        PUBLIC NAVIGATION
    ========================================================== --}}
    @include('partials.public-navbar')


    {{-- =========================================================
        PAGE CONTENT
    ========================================================== --}}
    <main>
        @yield('content')
    </main>


    {{-- =========================================================
        FOOTER
    ========================================================== --}}
    <footer class="site-footer">
        © {{ date('Y') }} Danao Technological College —
        Enrollment Management System. All rights reserved.
    </footer>


    {{-- Page-specific scripts --}}
    @yield('scripts')

</body>

</html>