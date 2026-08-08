<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $errors->any() ? 'Sign In' : 'DTC EMS' }} — Danao Technological College</title>

    <link rel="stylesheet" href="{{ asset('vendor/fontawesome-free/css/all.min.css') }}">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/welcome.css') }}">
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
</head>
<body>


    <div class="page-view {{ $errors->any() ? '' : 'active' }}" id="view-landing">

     <nav class="navbar">
            <div class="container">
                <div class="navbar-brand" onclick="showLanding()">
                    <img src="{{ asset('images/DTC-LOGO.png') }}" alt="DTC Logo">
                    <div class="brand-text">
                        <h1>DTC EMS</h1>
                        <p>Danao Technological College</p>
                    </div>
                </div>
 
                <div class="nav-links">
                    <a href="#" class="active">Home</a>
                    <a href="#process">How It Works</a>
                    <a href="#services">Services</a>
                    <a href="#faqs">FAQs</a>
                </div>
 
                <button type="button" class="btn-nav-signin btn-nav-signin-desktop" onclick="showLogin()">
                    Sign In <i class="fas fa-arrow-right"></i>
                </button>
 
                <button type="button" class="navbar-burger" id="navbarBurger" onclick="toggleMobileNav()" aria-label="Toggle menu">
                    <i class="fas fa-bars"></i>
                </button>
            </div>
 
            <div class="mobile-nav" id="mobileNav">
                <a href="#" class="active" onclick="closeMobileNav()">Home</a>
                <a href="#process" onclick="closeMobileNav()">How It Works</a>
                <a href="#services" onclick="closeMobileNav()">Services</a>
                <a href="#faqs" onclick="closeMobileNav()">FAQs</a>
                <button type="button" class="btn-nav-signin" onclick="closeMobileNav(); showLogin()">
                    Sign In <i class="fas fa-arrow-right"></i>
                </button>
            </div>
        </nav>

        <section class="hero">
            <div class="container">
                <div class="hero-left">
                    <div class="hero-eyebrow">DTC EMS</div>
                    <h1>Your DTC Services.<br><span>One Convenient Portal.</span></h1>
                    <p class="hero-desc">
                        Manage your enrollment, certificate requests, appointments, and
                        payments through one secure and centralized online platform.
                    </p>
                    <p class="hero-support">
                        Enroll. Request. Schedule. Pay. Everything you need, connected in one system.
                    </p>

                    <div class="hero-cta-group">
                        <button type="button" class="btn-primary-cta" onclick="showLogin()">
                            Sign In to Portal <i class="fas fa-arrow-right"></i>
                        </button>
                        <a href="#services" class="btn-secondary-cta">
                            Explore Services
                        </a>
                    </div>

                    <div class="hero-features">
                        <div class="feature-pill"><i class="fas fa-file-alt"></i> Online Enrollment</div>
                        <div class="feature-pill"><i class="fas fa-calendar-check"></i> Certificate Appointments</div>
                        <div class="feature-pill"><i class="fas fa-money-bill-wave"></i> Payment Processing</div>
                        <div class="feature-pill"><i class="fas fa-shield-alt"></i> Role-Based Access</div>
                    </div>
                </div>

                <div class="hero-right">
                    <div class="hero-visual">
                        <div class="circle c1"></div>
                        <div class="circle c2"></div>
                        <div class="circle c3"></div>

                        <div class="mockup-card">
                            <div class="mockup-header">
                                <div class="dot"><img src="{{ asset('images/DTC-LOGO.png') }}" alt="DTC Logo"></div>
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
                                        <i class="fas fa-file-alt"></i> Certificate Request
                                        <span class="badge">Pending</span>
                                    </div>
                                    <div class="mockup-list-item">
                                        <i class="fas fa-calendar-check"></i> Appointment
                                        <span class="badge">Scheduled</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="services" id="services">
            <div class="container">
                <div class="section-heading">
                    <div class="eyebrow">Services</div>
                    <h2>Everything, connected in one system</h2>
                    <p>One portal for every DTC service — from enrollment to certificates, appointments, and payments.</p>
                </div>

                <div class="services-grid">
                    <div class="service-card">
                        <div class="icon-box"><i class="fas fa-user-graduate"></i></div>
                        <h3>Online Enrollment</h3>
                        <p>Enroll and manage your student records from anywhere, anytime.</p>
                    </div>
                    <div class="service-card">
                        <div class="icon-box"><i class="fas fa-file-alt"></i></div>
                        <h3>Certificate Requests</h3>
                        <p>Request official certificates and track their status in real time.</p>
                    </div>
                    <div class="service-card">
                        <div class="icon-box"><i class="fas fa-calendar-check"></i></div>
                        <h3>Appointment Scheduling</h3>
                        <p>Book a pickup or claiming schedule that works for you.</p>
                    </div>
                    <div class="service-card">
                        <div class="icon-box"><i class="fas fa-money-bill-wave"></i></div>
                        <h3>Payment Processing</h3>
                        <p>Pay online via GCash, or walk in and pay directly at the Cashier.</p>
                    </div>
                    <div class="service-card">
                        <div class="icon-box"><i class="fas fa-shield-alt"></i></div>
                        <h3>Role-Based Access</h3>
                        <p>Students, alumni, registrars, cashiers, and admins each get their own dashboard.</p>
                    </div>
                </div>
            </div>
        </section>

        <section class="process" id="process">
            <div class="container">
                <div class="section-heading">
                    <div class="eyebrow">How It Works</div>
                    <h2>Four simple steps</h2>
                </div>

                <div class="process-steps">
                    <div class="process-step">
                        <div class="num">1</div>
                        <h4>Enroll</h4>
                        <p>Complete your enrollment online.</p>
                    </div>
                    <div class="process-step">
                        <div class="num">2</div>
                        <h4>Request</h4>
                        <p>Submit a certificate request.</p>
                    </div>
                    <div class="process-step">
                        <div class="num">3</div>
                        <h4>Schedule</h4>
                        <p>Pick an appointment slot.</p>
                    </div>
                    <div class="process-step">
                        <div class="num">4</div>
                        <h4>Pay</h4>
                        <p>Pay via GCash QR code or walk-in.</p>
                    </div>
                </div>
            </div>
        </section>

        <section class="faqs" id="faqs">
            <div class="container">
                <div class="section-heading">
                    <div class="eyebrow">FAQs</div>
                    <h2>Frequently asked questions</h2>
                    <p>Quick answers about enrollment, certificates, appointments, and payments.</p>
                </div>

                <div class="faq-list">
                    <div class="faq-item">
                        <button type="button" class="faq-question" onclick="toggleFaq(this)">
                            <span>How do I enroll through DTC EMS?</span>
                            <i class="fas fa-chevron-down"></i>
                        </button>
                        <div class="faq-answer">
                            <p>Sign in to the portal with your student account, go to the Enrollment section, and follow the on-screen steps to submit your enrollment for the current term.</p>
                        </div>
                    </div>

                    <div class="faq-item">
                        <button type="button" class="faq-question" onclick="toggleFaq(this)">
                            <span>How do I request a certificate?</span>
                            <i class="fas fa-chevron-down"></i>
                        </button>
                        <div class="faq-answer">
                            <p>From your dashboard, open Certificate Requests, choose the document you need, and submit the request. You can track its status in real time until it's ready for pickup.</p>
                        </div>
                    </div>

                    <div class="faq-item">
                        <button type="button" class="faq-question" onclick="toggleFaq(this)">
                            <span>How does appointment scheduling work?</span>
                            <i class="fas fa-chevron-down"></i>
                        </button>
                        <div class="faq-answer">
                            <p>After your request is approved, you'll be able to pick an available date and time slot for claiming your certificate or completing enrollment steps in person.</p>
                        </div>
                    </div>

                    <div class="faq-item">
                        <button type="button" class="faq-question" onclick="toggleFaq(this)">
                            <span>What payment options are available?</span>
                            <i class="fas fa-chevron-down"></i>
                        </button>
                        <div class="faq-answer">
                            <p>You can pay online through GCash via QR code, or pay in person as a walk-in, with the Cashier recording your payment directly in the system.</p>
                        </div>
                    </div>

                    <div class="faq-item">
                        <button type="button" class="faq-question" onclick="toggleFaq(this)">
                            <span>I forgot my password. What do I do?</span>
                            <i class="fas fa-chevron-down"></i>
                        </button>
                        <div class="faq-answer">
                            <p>Click "Sign In," then use the Forgot Password link on the sign-in form to reset it through your registered email address.</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <footer class="site-footer">
            © {{ date('Y') }} Danao Technological College — Enrollment Management System. All rights reserved.
        </footer>
    </div>

   
    <div class="page-view {{ $errors->any() ? 'active' : '' }}" id="view-login">
        <div class="login-screen">

            <div class="login-branding">
                <div class="circle c1"></div>
                <div class="circle c2"></div>

                <div class="brand-row" onclick="showLanding()">
                    <img src="{{ asset('images/DTC-LOGO.png') }}" alt="DTC Logo">
                    <div>
                        <h1>DTC EMS</h1>
                        <p>Danao Technological College</p>
                    </div>
                </div>

                <h2>Your DTC services, connected.</h2>
                <p class="tagline">
                    Sign in to manage your enrollment, certificate requests,
                    appointments, and payments in one place.
                </p>

                <!-- <div class="role-row">
                    <div class="role-pill"><i class="fas fa-shield-alt"></i> Admin</div>
                    <div class="role-pill"><i class="fas fa-id-card"></i> Registrar</div>
                    <div class="role-pill"><i class="fas fa-cash-register"></i> Cashier</div>
                    <div class="role-pill"><i class="fas fa-user-graduate"></i> Student</div>
                    <div class="role-pill"><i class="fas fa-user-tie"></i> Alumni</div>
                </div> -->
            </div>

            <div class="login-panel">
                <div class="login-card">

                    <div class="card-header-logo">
                        <h2>Sign In</h2>
                        <p>Use your DTC EMS account</p>
                    </div>

                    @if ($errors->has('captcha'))
                        <div class="error-box">
                            <i class="fas fa-exclamation-circle"></i>
                            {{ $errors->first('captcha') }}
                        </div>
                    @endif

                    <form method="POST" action="{{ route('login') }}">
                        @csrf

                        <div class="form-group">
                            <label for="email-input">Email address</label>
                            <div class="input-wrap">
                                <i class="fas fa-envelope icon"></i>
                                <input type="email" name="email" id="email-input" placeholder="Enter your email"
                                       value="{{ old('email') }}" autocomplete="email" required autofocus>
                            </div>
                            @error('email')
                                <div class="error-box" style="margin-top: 8px;">
                                    <i class="fas fa-exclamation-circle"></i>
                                    {{ $message }}
                                </div>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label for="password">Password</label>
                            <div class="input-wrap">
                                <i class="fas fa-lock icon"></i>
                                <input type="password" name="password" id="password"
                                       placeholder="Enter your password"
                                       autocomplete="current-password" required>
                                <i class="fas fa-eye toggle-pwd" id="togglePwd"></i>
                            </div>
                        </div>

                        <label class="remember">
                            <span class="remember-left">
                                <input type="checkbox" name="remember"> Remember me
                            </span>
                        </label>

                        @if(config('services.recaptcha.enabled', true))
                            <div class="recaptcha-wrap">
                                <div class="g-recaptcha"
                                    data-sitekey="{{ config('services.recaptcha.site_key') }}">
                                </div>
                            </div>
                        @endif

                        <button type="submit" class="btn-signin">
                            <i class="fas fa-sign-in-alt"></i> Sign In
                        </button>
                    </form>

                    <div class="card-footer-text">
                        © {{ date('Y') }} Danao Technological College
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // ── Mobile nav (burger menu) ─────────────────────────────────────
        function toggleMobileNav() {
            document.getElementById('mobileNav').classList.toggle('open');
            document.getElementById('navbarBurger').classList.toggle('open');
        }
 
        function closeMobileNav() {
            document.getElementById('mobileNav').classList.remove('open');
            document.getElementById('navbarBurger').classList.remove('open');
        }
        // ── FAQs accordion ───────────────────────────────────────────────
        function toggleFaq(button) {
            const item = button.closest('.faq-item');
            const wasActive = item.classList.contains('active');

            // Close all FAQ items first (one open at a time)
            document.querySelectorAll('.faq-item').forEach(faq => faq.classList.remove('active'));

            if (!wasActive) {
                item.classList.add('active');
            }
        }

        // ── View switching: landing <-> sign in (no page reload) ───────────
        function showLogin() {
            document.getElementById('view-landing').classList.remove('active');
            document.getElementById('view-login').classList.add('active');
        }

        function showLanding() {
            document.getElementById('view-login').classList.remove('active');
            document.getElementById('view-landing').classList.add('active');
        }

        // Toggle password visibility
        document.getElementById('togglePwd').addEventListener('click', function () {
            const pwd = document.getElementById('password');
            const type = pwd.getAttribute('type') === 'password' ? 'text' : 'password';
            pwd.setAttribute('type', type);
            this.classList.toggle('fa-eye');
            this.classList.toggle('fa-eye-slash');
        });
    </script>

</body>
</html>