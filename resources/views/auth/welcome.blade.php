<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('partials.pwa-head')
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DTC EMS — Danao Technological College</title>

    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/welcome.css') }}">
    <link rel="stylesheet" href="{{ asset('css/public-navbar.css') }}">
    <link rel="stylesheet" href="{{ asset('css/signin-modal.css') }}">
    {{-- DTC Theme (dark mode support) --}}
    <link rel="stylesheet" href="{{ asset('css/dtc-theme.css') }}">
    <script src="{{ asset('js/signin-modal.js') }}" defer></script>

    {{-- DTC EMS theme preload: prevents light-mode flash on saved dark theme --}}
    <script>
        (function () {
            try {
                var saved;
                try { saved = localStorage.getItem('dtc-ems-theme'); } catch (e) {}
                if (saved === 'dark' || (saved !== 'light' && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                    document.documentElement.classList.add('dtc-dark-preload');
                    document.documentElement.classList.add('dtc-dark');
                }
            } catch (e) {}
        })();
    </script>
</head>
<body>


  <div class="page-view active" id="view-landing">

    <nav class="public-navbar">

        <div class="public-navbar-container">

            <a href="{{ route('landing') }}" class="public-brand">

                <img
                    src="{{ asset('images/DTC-LOGO.webp') }}"
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
                class="nav-link {{ request()->routeIs('landing') ? 'active' : '' }}">
                    {{ __('ui.navigation.home') }}
                </a>

                <a href="{{ route('public.application.create') }}"
                class="nav-link {{ request()->routeIs('public.application.create') ? 'active' : '' }}">
                    {{ __('ui.navigation.admissions') }}
                </a>

                <a href="#services" class="nav-link">
                    {{ __('ui.navigation.services') }}
                </a>

                <a href="#process" class="nav-link">
                    {{ __('ui.navigation.how_it_works') }}
                </a>

                <a href="#faqs" class="nav-link">
                    {{ __('ui.navigation.faqs') }}
                </a>

            </div>

            {{-- Dark Mode Toggle --}}
            <div class="public-navbar-theme">
                <button
                    type="button"
                    id="darkModeToggle"
                    class="theme-toggle-btn"
                    aria-label="{{ __('ui.navigation.enable_dark_mode') }}"
                    aria-pressed="false"
                    title="{{ __('ui.navigation.enable_dark_mode') }}">
                    <i data-lucide="moon" id="darkModeIcon" aria-hidden="true"></i>
                </button>
            </div>

            <button type="button" class="public-signin" data-signin-modal="trigger">
                <i data-lucide="arrow-right"></i> {{ __('ui.navigation.sign_in') }}
            </button>


        <button
            type="button"
            class="public-navbar-toggle"
            id="welcomeNavbarToggle"
            aria-label="{{ __('ui.navigation.toggle_navigation') }}"
            aria-expanded="false"
            aria-controls="welcomeMobileNav"
        >
            <i data-lucide="menu"></i>
        </button>

        </div>


    <div class="public-mobile-nav" id="welcomeMobileNav">

        <a href="{{ route('landing') }}"
           class="nav-link {{ request()->routeIs('landing') ? 'active' : '' }}">
            {{ __('ui.navigation.home') }}
        </a>

        <a href="{{ route('public.application.create') }}"
           class="nav-link {{ request()->routeIs('public.application.create') ? 'active' : '' }}">
            {{ __('ui.navigation.admissions') }}
        </a>

        <a href="#services" class="nav-link">
            {{ __('ui.navigation.services') }}
        </a>

        <a href="#process" class="nav-link">
            {{ __('ui.navigation.how_it_works') }}
        </a>

        <a href="#faqs" class="nav-link">
            {{ __('ui.navigation.faqs') }}
        </a>

        <button type="button" class="public-signin" data-signin-modal="trigger">
            <i data-lucide="arrow-right"></i> {{ __('ui.navigation.sign_in') }}
        </button>

    </div>

    </nav>

        <section class="hero">
            <div class="container">
                <div class="hero-left">
                    <div class="hero-eyebrow">DTC EMS</div>
                    <h1>{{ __('home.hero_title') }}<br><span>{{ __('home.hero_subtitle') }}</span></h1>
                    <p class="hero-desc">{{ __('home.hero_description') }}</p>
                    <p class="hero-support">
                        {{ __('home.hero_support') }}
                    </p>

                    <div class="hero-cta-group">
                        <a href="{{ route('public.application.create') }}" class="btn-primary-cta">
                            {{ __('home.apply') }} <i data-lucide="arrow-right"></i>
                        </a>

                        <button type="button" class="btn-secondary-cta" data-signin-modal="trigger">
                            {{ __('home.sign_in_account') }}
                        </button>
                    </div>

                    <p class="hero-explore-link">
                        {{ __('home.new_here') }} <a href="#services">{{ __('home.explore_services') }}</a>
                    </p>

                    <!-- <div class="hero-features">
                        <div class="feature-pill"><i data-lucide="file-text"></i> Online Enrollment</div>
                        <div class="feature-pill"><i data-lucide="calendar-check"></i> Certificate Appointments</div>
                        <div class="feature-pill"><i data-lucide="banknote"></i> Payment Processing</div>
                        <div class="feature-pill"><i data-lucide="shield"></i> Role-Based Access</div>
                    </div> -->
                </div>

                <!-- <div class="hero-right">
                    <div class="hero-visual">
                        <div class="circle c1"></div>
                        <div class="circle c2"></div>
                        <div class="circle c3"></div>

                        <div class="mockup-card">
                            <div class="mockup-header">
                                <div class="dot"><img src="{{ asset('images/DTC-LOGO.webp') }}" alt="DTC Logo"></div>
                                <span>DTC EMS &middot; Student Portal</span>
                            </div>
                            <div class="mockup-body">
                                <h4>Good morning, Student</h4>
                                <p>Here's your current activity at a glance.</p>

                                <div class="mockup-stats">
                                    <div class="mockup-stat">
                                        <div class="label">Enrollment</div>
                                        <div class="value accent">Active</div>
                                    </div>
                                    <div class="mockup-stat">
                                        <div class="label">Balance</div>
                                        <div class="value">₱0.00</div>
                                    </div>
                                </div>

                                <div class="mockup-list">
                                    <div class="mockup-list-item">
                                        <i data-lucide="file-text"></i> Certificate Request
                                        <span class="badge">Pending</span>
                                    </div>
                                    <div class="mockup-list-item">
                                        <i data-lucide="calendar-check"></i> Appointment
                                        <span class="badge">Scheduled</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div> -->
            </div>
        </section>

        <section class="services" id="services">
            <div class="container">
                <div class="section-heading reveal">
                    <div class="eyebrow">{{ __('home.services') }}</div>
                    <h2>{{ __('home.services_title') }}</h2>
                    <p>{{ __('home.services_description') }}</p>
                </div>

                <div class="services-carousel reveal reveal-scale">

                    <button
                        type="button"
                        class="carousel-arrow carousel-arrow-prev"
                        id="servicesPrev"
                        aria-label="{{ __('home.previous_service') }}"
                    >
                        <i data-lucide="chevron-left"></i>
                    </button>

                    <div class="carousel-viewport" id="servicesViewport" tabindex="0" aria-roledescription="carousel" aria-label="{{ __('home.services_carousel') }}">
                        <div class="carousel-track" id="servicesTrack">

                            <div class="service-card" data-service-id="enrollment">
                                <div class="icon-box"><i data-lucide="graduation-cap"></i></div>
                                <h3>{{ __('home.online_enrollment') }}</h3>
                                <p>{{ __('home.online_enrollment_description') }}</p>
                                <span class="card-hint"><i data-lucide="mouse-pointer-click"></i> {{ __('home.service_hint') }}</span>
                            </div>

                            <div class="service-card" data-service-id="certificates">
                                <div class="icon-box"><i data-lucide="file-text"></i></div>
                                <h3>{{ __('home.certificate_requests') }}</h3>
                                <p>{{ __('home.certificate_requests_description') }}</p>
                                <span class="card-hint"><i data-lucide="mouse-pointer-click"></i> {{ __('home.service_hint') }}</span>
                            </div>

                            <div class="service-card" data-service-id="appointments">
                                <div class="icon-box"><i data-lucide="calendar-check"></i></div>
                                <h3>{{ __('home.appointment_scheduling') }}</h3>
                                <p>{{ __('home.appointment_scheduling_description') }}</p>
                                <span class="card-hint"><i data-lucide="mouse-pointer-click"></i> {{ __('home.service_hint') }}</span>
                            </div>

                            <div class="service-card" data-service-id="payments">
                                <div class="icon-box"><i data-lucide="banknote"></i></div>
                                <h3>{{ __('home.payment_processing') }}</h3>
                                <p>{{ __('home.payment_processing_description') }}</p>
                                <span class="card-hint"><i data-lucide="mouse-pointer-click"></i> {{ __('home.service_hint') }}</span>
                            </div>

                            <div class="service-card" data-service-id="access">
                                <div class="icon-box"><i data-lucide="shield"></i></div>
                                <h3>{{ __('home.role_based_access') }}</h3>
                                <p>{{ __('home.role_based_access_description') }}</p>
                                <span class="card-hint"><i data-lucide="mouse-pointer-click"></i> {{ __('home.service_hint') }}</span>
                            </div>
                        </div>
                    </div>

                    <button
                        type="button"
                        class="carousel-arrow carousel-arrow-next"
                        id="servicesNext"
                        aria-label="{{ __('home.next_service') }}"
                    >
                        <i data-lucide="chevron-right"></i>
                    </button>

                </div>

                <div class="carousel-dots" id="servicesDots" role="tablist" aria-label="{{ __('home.services') }}"></div>
            </div>
        </section>

        <section class="process" id="process">
            <div class="container">
                <div class="section-heading reveal">
                    <div class="eyebrow">{{ __('home.how_it_works') }}</div>
                    <h2>{{ __('home.four_steps') }}</h2>
                </div>

                <div class="process-steps reveal">
                    <div class="process-step">
                        <div class="num">1</div>
                        <h4>{{ __('home.enroll') }}</h4>
                        <p>{{ __('home.enroll_description') }}</p>
                    </div>
                    <div class="process-step">
                        <div class="num">2</div>
                        <h4>{{ __('home.request') }}</h4>
                        <p>{{ __('home.request_description') }}</p>
                    </div>
                    <div class="process-step">
                        <div class="num">3</div>
                        <h4>{{ __('home.schedule') }}</h4>
                        <p>{{ __('home.schedule_description') }}</p>
                    </div>
                    <div class="process-step">
                        <div class="num">4</div>
                        <h4>{{ __('home.pay') }}</h4>
                        <p>{{ __('home.pay_description') }}</p>
                    </div>
                </div>
            </div>
        </section>

        <section class="faqs" id="faqs">
            <div class="container">
                <div class="section-heading reveal">
                    <div class="eyebrow">{{ __('home.faqs') }}</div>
                    <h2>{{ __('home.faq_title') }}</h2>
                    <p>{{ __('home.faq_description') }}</p>
                </div>

                <div class="faq-list reveal">
                    <div class="faq-item">
                        <button type="button" class="faq-question" onclick="toggleFaq(this)">
                            <span>{{ __('home.faq_enroll_question') }}</span>
                            <i data-lucide="chevron-down"></i>
                        </button>
                        <div class="faq-answer">
                            <p>{{ __('home.faq_enroll_answer') }}</p>
                        </div>
                    </div>

                    <div class="faq-item">
                        <button type="button" class="faq-question" onclick="toggleFaq(this)">
                            <span>{{ __('home.faq_certificate_question') }}</span>
                            <i data-lucide="chevron-down"></i>
                        </button>
                        <div class="faq-answer">
                            <p>{{ __('home.faq_certificate_answer') }}</p>
                        </div>
                    </div>

                    <div class="faq-item">
                        <button type="button" class="faq-question" onclick="toggleFaq(this)">
                            <span>{{ __('home.faq_appointment_question') }}</span>
                            <i data-lucide="chevron-down"></i>
                        </button>
                        <div class="faq-answer">
                            <p>{{ __('home.faq_appointment_answer') }}</p>
                        </div>
                    </div>

                    <div class="faq-item">
                        <button type="button" class="faq-question" onclick="toggleFaq(this)">
                            <span>{{ __('home.faq_payment_question') }}</span>
                            <i data-lucide="chevron-down"></i>
                        </button>
                        <div class="faq-answer">
                            <p>{{ __('home.faq_payment_answer') }}</p>
                        </div>
                    </div>

                    <div class="faq-item">
                        <button type="button" class="faq-question" onclick="toggleFaq(this)">
                            <span>{{ __('home.faq_password_question') }}</span>
                            <i data-lucide="chevron-down"></i>
                        </button>
                        <div class="faq-answer">
                            <p>{{ __('home.faq_password_answer') }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <footer class="site-footer">
            {{ __('ui.public.footer', ['year' => date('Y')]) }}
        </footer>

        <button
            type="button"
            class="back-to-top"
            id="backToTop"
            aria-label="{{ __('home.back_to_top') }}"
        >
            <i data-lucide="arrow-up"></i>
        </button>
    </div>

    <script>
        // If we've just landed on /login (e.g. session-timeout redirect) or
        // a sign-in attempt failed validation, open the sign-in modal
        // automatically so the user isn't left staring at the landing page.
        @if (request()->routeIs('login') && ! $errors->any())
        document.addEventListener('DOMContentLoaded', function () {
            if (window.DtcSignInModal) window.DtcSignInModal.open();
        });
        @endif

        // -- Active navigation while scrolling -----------------------------
        (function () {
            const navLinks = document.querySelectorAll(
                '.public-nav-links .nav-link, #welcomeMobileNav .nav-link'
            );
            const sections = document.querySelectorAll('#services, #process, #faqs');

            if (!navLinks.length || !sections.length) return;

            function updateActiveNav() {
                const scrollPosition = window.scrollY + 180;

                let currentSection = '';

                sections.forEach(section => {
                    if (scrollPosition >= section.offsetTop) {
                        currentSection = section.id;
                    }
                });

                navLinks.forEach(link => {
                    link.classList.remove('active');

                    const href = link.getAttribute('href');

                    if (currentSection && href === '#' + currentSection) {
                        link.classList.add('active');
                    }
                });

                // If we're above all sections, Home is active
                if (!currentSection && window.scrollY < 300) {
                    const homeLinks = document.querySelectorAll(
                        '.public-nav-links .nav-link[href="{{ route("landing") }}"], ' +
                        '#welcomeMobileNav .nav-link[href="{{ route("landing") }}"]'
                    );

                    homeLinks.forEach(link => link.classList.add('active'));
                }
            }

            window.addEventListener('scroll', updateActiveNav, { passive: true });

            updateActiveNav();
        })();
        
        // -- Mobile nav (burger menu) -------------------------------------
        (function () {
            const toggle = document.getElementById('welcomeNavbarToggle');
            const mobileNav = document.getElementById('welcomeMobileNav');

            if (!toggle || !mobileNav) return;

            function toggleMobileNav() {
                mobileNav.classList.toggle('open');
                toggle.classList.toggle('open');

                const isOpen = mobileNav.classList.contains('open');
                toggle.setAttribute('aria-expanded', isOpen);
            }

            function closeMobileNav() {
                mobileNav.classList.remove('open');
                toggle.classList.remove('open');
                toggle.setAttribute('aria-expanded', 'false');
            }

            toggle.addEventListener('click', toggleMobileNav);

            // Close menu when clicking a mobile navigation link
            mobileNav.querySelectorAll('a, button').forEach(link => {
                link.addEventListener('click', closeMobileNav);
            });

            function dismissOutside(event) {
                if (!toggle.contains(event.target) && !mobileNav.contains(event.target)) closeMobileNav();
            }
            document.addEventListener('pointerdown', dismissOutside, { passive: true });
            document.addEventListener('touchstart', dismissOutside, { passive: true });
            document.addEventListener('click', dismissOutside);
            document.addEventListener('scroll', closeMobileNav, { passive: true, capture: true });

            // Close menu when pressing Escape
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') {
                    closeMobileNav();
                }
            });

            // Close menu if resized back to desktop
            window.addEventListener('resize', function () {
                if (window.innerWidth >= 992) {
                    closeMobileNav();
                }
            });

            toggle.setAttribute('aria-expanded', 'false');
        })();
        // -- FAQs accordion -----------------------------------------------
        function toggleFaq(button) {
            const item = button.closest('.faq-item');
            const wasActive = item.classList.contains('active');

            // Close all FAQ items first (one open at a time)
            document.querySelectorAll('.faq-item').forEach(faq => faq.classList.remove('active'));

            if (!wasActive) {
                item.classList.add('active');
            }
        }

        // -- Services 3D carousel -----------------------------------------
        (function () {
            const viewport = document.getElementById('servicesViewport');
            const track = document.getElementById('servicesTrack');
            const prevBtn = document.getElementById('servicesPrev');
            const nextBtn = document.getElementById('servicesNext');
            const dotsWrap = document.getElementById('servicesDots');

            if (!viewport || !track || !prevBtn || !nextBtn || !dotsWrap) return;

            const slides = Array.from(track.children);
            const total = slides.length;
            if (total === 0) return;

            let index = 0;

            function wrap(i) {
                return (i + total) % total;
            }

            function render() {
                slides.forEach((slide, i) => {
                    slide.classList.remove('is-center', 'is-prev', 'is-next', 'is-far', 'is-far-prev', 'is-far-next');

                    if (i === index) {
                        slide.classList.add('is-center');
                    } else if (i === wrap(index - 1)) {
                        slide.classList.add('is-prev');
                    } else if (i === wrap(index + 1)) {
                        slide.classList.add('is-next');
                    } else if (i === wrap(index - 2)) {
                        slide.classList.add('is-far', 'is-far-prev');
                    } else {
                        slide.classList.add('is-far', 'is-far-next');
                    }
                });

                Array.from(dotsWrap.children).forEach((dot, i) => {
                    dot.classList.toggle('active', i === index);
                });
            }

            function goTo(i) {
                index = wrap(i);
                render();
            }

            function buildDots() {
                dotsWrap.innerHTML = '';
                for (let i = 0; i < total; i++) {
                    const dot = document.createElement('button');
                    dot.type = 'button';
                    dot.className = 'carousel-dot';
                    dot.setAttribute('role', 'tab');
                    dot.setAttribute('aria-label', 'Go to ' + slides[i].querySelector('h3').textContent);
                    dot.addEventListener('click', () => goTo(i));
                    dotsWrap.appendChild(dot);
                }
            }

            prevBtn.addEventListener('click', () => goTo(index - 1));
            nextBtn.addEventListener('click', () => goTo(index + 1));

            viewport.addEventListener('keydown', (e) => {
                if (e.key === 'ArrowLeft') {
                    e.preventDefault();
                    goTo(index - 1);
                } else if (e.key === 'ArrowRight') {
                    e.preventDefault();
                    goTo(index + 1);
                }
            });

            // Drag / swipe support (mouse + touch)
            let dragging = false;
            let startX = 0;

            function dragStart(x) {
                dragging = true;
                startX = x;
            }

            function dragEnd(x) {
                if (!dragging) return;
                dragging = false;

                const delta = x - startX;
                if (Math.abs(delta) < 40) return;

                if (delta < 0) {
                    goTo(index + 1);
                } else {
                    goTo(index - 1);
                }
            }

            track.addEventListener('mousedown', (e) => dragStart(e.clientX));
            window.addEventListener('mouseup', (e) => dragEnd(e.clientX));

            track.addEventListener('touchstart', (e) => dragStart(e.touches[0].clientX), { passive: true });
            track.addEventListener('touchend', (e) => dragEnd(e.changedTouches[0].clientX), { passive: true });

            buildDots();
            render();
        })();

        // -- Scroll reveal ------------------------------------------------
        (function () {
            const items = document.querySelectorAll('.reveal');
            if (items.length === 0) return;

            const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            if (prefersReducedMotion) {
                items.forEach(el => el.classList.add('revealed'));
                return;
            }

            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('revealed');
                        observer.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.15, rootMargin: '0px 0px -40px 0px' });

            items.forEach(el => observer.observe(el));
        })();

        // -- Back to top --------------------------------------------------
        (function () {
            const btn = document.getElementById('backToTop');
            if (!btn) return;

            let ticking = false;

            function onScroll() {
                if (ticking) return;
                ticking = true;

                requestAnimationFrame(() => {
                    btn.classList.toggle('visible', window.scrollY > 480);
                    ticking = false;
                });
            }

            window.addEventListener('scroll', onScroll, { passive: true });

            btn.addEventListener('click', () => {
                const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
                window.scrollTo({ top: 0, behavior: prefersReducedMotion ? 'auto' : 'smooth' });
            });
        })();
    </script>

@include('partials.signin-modal')

<script src="{{ asset('js/dtc-app.js') }}" defer></script>
<script src="{{ asset('js/welcome-interactive.js') }}" defer></script>

    <script>
        document.addEventListener("DOMContentLoaded", function () {
            if (window.lucide) lucide.createIcons();
        });
    </script>
</body>
</html>
