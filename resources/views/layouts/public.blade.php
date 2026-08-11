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

    <link rel="stylesheet" href="{{ asset('css/application.css') }}">

    {{-- DTC Public Theme --}}
    <link rel="stylesheet" href="{{ asset('css/welcome.css') }}">

    {{-- Page-specific styles --}}
    @yield('styles')
</head>

<body>

    {{-- =========================================================
        PUBLIC NAVIGATION
    ========================================================== --}}
    <nav class="navbar">
        <div class="container">

            <a href="{{ route('landing') }}"
               class="navbar-brand"
               style="text-decoration: none;">

                <img src="{{ asset('images/DTC-LOGO.png') }}"
                     alt="DTC Logo">

                <div class="brand-text">
                    <h1>DTC EMS</h1>
                    <p>Danao Technological College</p>
                </div>

            </a>

            <div class="nav-links">

                <a href="{{ route('landing') }}">
                    Home
                </a>

                <a href="{{ route('public.application.create') }}">
                    Apply
                </a>

                <a href="{{ route('public.application.status.form') }}">
                    Check Status
                </a>

                <!-- <button type="button" class="btn-nav-signin btn-nav-signin-desktop" onclick="showLogin()">
                    Sign In <i class="fas fa-arrow-right"></i>
                </button -->

            </div>

            {{-- Mobile menu --}}
            <button type="button"
                    class="navbar-burger"
                    id="navbarBurger"
                    onclick="togglePublicNav()"
                    aria-label="Toggle menu">

                <i class="fas fa-bars"></i>

            </button>

        </div>

        {{-- Mobile navigation --}}
        <div class="mobile-nav" id="mobileNav">

            <a href="{{ route('landing') }}"
               onclick="closePublicNav()">
                Home
            </a>

            <a href="{{ route('public.application.create') }}"
               onclick="closePublicNav()">
                Apply
            </a>

            <a href="{{ route('public.application.status.form') }}"
               onclick="closePublicNav()">
                Check Status
            </a>

            <a href="{{ route('login') }}"
               class="btn-nav-signin"
               onclick="closePublicNav()">
                Sign In
                <i class="fas fa-arrow-right"></i>
            </a>

        </div>
    </nav>


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


    {{-- =========================================================
        PUBLIC LAYOUT SCRIPTS
    ========================================================== --}}
    <script>
        function togglePublicNav() {
            const mobileNav = document.getElementById('mobileNav');
            const navbarBurger = document.getElementById('navbarBurger');

            if (mobileNav) {
                mobileNav.classList.toggle('open');
            }

            if (navbarBurger) {
                navbarBurger.classList.toggle('open');
            }
        }

        function closePublicNav() {
            const mobileNav = document.getElementById('mobileNav');
            const navbarBurger = document.getElementById('navbarBurger');

            if (mobileNav) {
                mobileNav.classList.remove('open');
            }

            if (navbarBurger) {
                navbarBurger.classList.remove('open');
            }
        }
    </script>

    {{-- Page-specific scripts --}}
    @yield('scripts')

</body>

</html>