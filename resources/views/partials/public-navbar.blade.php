    <nav class="public-navbar">

        <div class="public-navbar-container">

            <a href="{{ route('landing') }}" class="public-brand">

                <img
                    src="{{ asset('images/DTC-LOGO.png') }}"
                    alt="DTC Logo"
                    class="public-logo"
                >

                <div class="public-brand-text">
                    <h1>DTC EMS</h1>
                    <p>Danao Technological College</p>
                </div>

            </a>


            <div class="public-nav-links">

                <a href="{{ route('landing') }}">
                    Home
                </a>

                <a href="{{ route('public.application.create') }}"
                class="active">
                    Admissions
                </a>

                <a href="{{ route('public.application.status.form') }}">
                    Check Status
                </a>

            </div>


            <a href="{{ route('login') }}" class="public-signin">
                Sign In
                <i class="fas fa-arrow-right"></i>
            </a>


            <button
                type="button"
                class="public-navbar-toggle"
                id="applicationNavbarToggle"
            >
                <i class="fas fa-bars"></i>
            </button>

        </div>


        <div class="public-mobile-nav" id="applicationMobileNav">

            <a href="{{ route('landing') }}">
                Home
            </a>

            <a href="{{ route('public.application.create') }}"
            class="active">
                Admissions
            </a>

            <a href="{{ route('public.application.status.form') }}">
                Check Status
            </a>

            <a href="{{ route('login') }}" class="public-signin">
                Sign In
                <i class="fas fa-arrow-right"></i>
            </a>

        </div>

    </nav>
<script>
    function togglePublicNavbar() {
        const mobileNav = document.getElementById('publicMobileNav');
        const navbarBurger = document.getElementById('publicNavbarBurger');

        if (mobileNav) {
            mobileNav.classList.toggle('open');
        }

        if (navbarBurger) {
            navbarBurger.classList.toggle('open');
        }
    }

    function closePublicNavbar() {
        const mobileNav = document.getElementById('publicMobileNav');
        const navbarBurger = document.getElementById('publicNavbarBurger');

        if (mobileNav) {
            mobileNav.classList.remove('open');
        }

        if (navbarBurger) {
            navbarBurger.classList.remove('open');
        }
    }
</script>