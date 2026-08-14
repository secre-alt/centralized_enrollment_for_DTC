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

                <a href="{{ route('landing') }}"
                class="{{ request()->routeIs('landing') ? 'active' : '' }}">
                    Home
                </a>

                <a href="{{ route('public.application.create') }}"
                class="{{ request()->routeIs('public.application.create') ? 'active' : '' }}">
                    Admissions
                </a>

                <a href="{{ route('public.application.status.form') }}"
                class="{{ request()->routeIs('public.application.status.form') ? 'active' : '' }}">
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

            <a href="{{ route('landing') }}"
            class="{{ request()->routeIs('landing') ? 'active' : '' }}">
                Home
            </a>

            <a href="{{ route('public.application.create') }}"
            class="{{ request()->routeIs('public.application.create') ? 'active' : '' }}">
                Admissions
            </a>

            <a href="{{ route('public.application.status.form') }}"
            class="{{ request()->routeIs('public.application.status.form') ? 'active' : '' }}">
                Check Status
            </a>

            <a href="{{ route('login') }}" class="public-signin">
                Sign In
                <i class="fas fa-arrow-right"></i>
            </a>

        </div>

    </nav>

<script>
    const applicationNavbarToggle =
        document.getElementById('applicationNavbarToggle');

    const applicationMobileNav =
        document.getElementById('applicationMobileNav');

    applicationNavbarToggle.addEventListener('click', function () {
        applicationMobileNav.classList.toggle('open');

        const isOpen = applicationMobileNav.classList.contains('open');

        this.setAttribute('aria-expanded', isOpen);
    });

    // Close mobile menu after clicking a link
    applicationMobileNav.querySelectorAll('a').forEach(link => {
        link.addEventListener('click', function () {
            applicationMobileNav.classList.remove('open');
            applicationNavbarToggle.setAttribute('aria-expanded', 'false');
        });
    });
</script>