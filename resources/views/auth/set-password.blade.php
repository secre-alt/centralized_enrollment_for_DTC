<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Set Your Password — DTC EMS</title>

    <link rel="stylesheet" href="{{ asset('vendor/fontawesome-free/css/all.min.css') }}">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/dtc-tokens.css') }}">
    <link rel="stylesheet" href="{{ asset('css/welcome.css') }}">
</head>
<body>

    <div class="page-view active" id="view-set-password">
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

                <h2>Welcome to DTC EMS.</h2>
                <p class="tagline">
                    @if($linkExpired)
                        For your security, activation links only stay valid
                        for a limited time.
                    @else
                        Your application has been approved. Set a password below
                        to activate your account and sign in.
                    @endif
                </p>
            </div>

            <div class="login-panel">
                <div class="login-card">

                    @if(session('success'))
                        <div class="error-box" style="background:#F0FDF4; border-color:#BBF7D0; color:#166534;">
                            <i class="fas fa-check-circle"></i>
                            {{ session('success') }}
                        </div>
                    @endif

                    @if ($errors->any())
                        <div class="error-box">
                            <i class="fas fa-exclamation-circle"></i>
                            {{ $errors->first() }}
                        </div>
                    @endif

                    @if($linkExpired)

                        {{-- ============================================ --}}
                        {{-- EXPIRED / INVALID ACTIVATION LINK STATE       --}}
                        {{-- ============================================ --}}

                        <div class="card-header-logo" style="text-align:center;">
                            <div style="width:56px; height:56px; border-radius:50%; background:#FEF3C7; display:flex; align-items:center; justify-content:center; margin:0 auto 16px;">
                                <i class="fas fa-clock" style="color:#B8860B; font-size:22px;"></i>
                            </div>
                            <h2>Activation Link Expired</h2>
                            <p>
                                This activation link is no longer valid or has
                                already been used.
                            </p>
                        </div>

                        <p style="font-size:13px; color:#64748B; line-height:1.6; margin-bottom:20px;">
                            You can request a new activation link below and
                            we'll send it to your email address.
                        </p>

                        <div class="form-group">
                            <label>Email</label>
                            <div class="input-wrap">
                                <i class="fas fa-envelope icon"></i>
                                <input type="text" value="{{ $email }}" disabled style="background:#F1F5F9; color:#334155;">
                            </div>
                        </div>

                        <form method="POST" action="{{ route('password.activation.resend') }}">
                            @csrf

                            <input type="hidden" name="email" value="{{ $email }}">

                            <button type="submit" class="btn-signin">
                                <i class="fas fa-paper-plane"></i> Request New Activation Link
                            </button>
                        </form>

                    @else

                        {{-- ============================================ --}}
                        {{-- VALID ACTIVATION LINK — PASSWORD SETUP FORM   --}}
                        {{-- ============================================ --}}

                        <div class="card-header-logo">
                            <h2>Set Your Password</h2>
                            <p>Create a password for {{ $email }}</p>
                        </div>

                        <form method="POST" action="{{ route('password.setup.store') }}">
                            @csrf

                            <input type="hidden" name="token" value="{{ $token }}">
                            <input type="hidden" name="email" value="{{ $email }}">

                            <div class="form-group">
                                <label for="password">New Password</label>
                                <div class="input-wrap password-wrap">
                                    <i class="fas fa-lock icon"></i>

                                    <input
                                        type="password"
                                        name="password"
                                        id="password"
                                        placeholder="At least 8 characters"
                                        autocomplete="new-password"
                                        minlength="8"
                                        required
                                        autofocus
                                    >

                                    <button
                                        type="button"
                                        class="password-toggle"
                                        onclick="togglePassword('password', this)"
                                        aria-label="Show password"
                                    >
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="password_confirmation">Confirm Password</label>
                                <div class="input-wrap password-wrap">
                                    <i class="fas fa-lock icon"></i>

                                    <input
                                        type="password"
                                        name="password_confirmation"
                                        id="password_confirmation"
                                        placeholder="Re-enter your password"
                                        autocomplete="new-password"
                                        minlength="8"
                                        required
                                    >

                                    <button
                                        type="button"
                                        class="password-toggle"
                                        onclick="togglePassword('password_confirmation', this)"
                                        aria-label="Show password"
                                    >
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>

                            <button type="submit" class="btn-signin">
                                <i class="fas fa-check"></i> Set Password &amp; Continue
                            </button>
                        </form>

                    @endif

                    <div class="card-footer-text">
                        © {{ date('Y') }} Danao Technological College
                    </div>
                </div>
            </div>
        </div>
    </div>

</body>
</html>
<style>
    .password-wrap {
    position: relative;
}

.password-wrap input {
    padding-right: 45px;
}

.password-toggle {
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    border: none;
    background: transparent;
    color: #777;
    cursor: pointer;
    padding: 5px;
    font-size: 15px;
    z-index: 2;
}

.password-toggle:hover {
    color: #333;
}

.password-toggle:focus {
    outline: none;
}
</style>
<script>
    function togglePassword(inputId, button) {
        const input = document.getElementById(inputId);
        const icon = button.querySelector('i');

        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
            button.setAttribute('aria-label', 'Hide password');
        } else {
            input.type = 'password';
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
            button.setAttribute('aria-label', 'Show password');
        }
    }
</script>