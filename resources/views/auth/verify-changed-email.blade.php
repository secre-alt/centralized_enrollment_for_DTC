<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('ui.auth.verify_email_title') }} | DTC EMS</title>
    @include('partials.pwa-head')
    @include('partials.auth-appearance')
</head>
<body class="auth-activation-page auth-verification-page">
<main class="auth-card" aria-labelledby="verification-title">
    <header class="auth-card-header">
        <div class="auth-brand">
            <img src="{{ asset('images/DTC-LOGO.webp') }}" alt="DTC Logo">
            <div><p class="auth-brand-name">DTC EMS</p><p class="auth-brand-sub">Danao Technological College</p></div>
        </div>
        <p class="auth-card-tagline">{{ __('ui.auth.secure_account') }} <span>{{ __('ui.auth.account_secure') }}</span></p>
    </header>
    <section class="auth-card-body">
        <h1 class="auth-card-title" id="verification-title">{{ __('ui.auth.verify_new_email') }}</h1>
        <p class="auth-card-subtitle">{{ __('ui.auth.confirm_email_before_continue') }}</p>
        <p class="auth-account-email">{{ auth()->user()->email }}</p>
        @if(session('status'))
            <div class="auth-alert auth-alert--success" role="status">{{ session('status') }}</div>
        @endif
        <form method="POST" action="{{ route('verification.send') }}" data-link-request>
            @csrf
            <button type="submit" class="auth-btn">{{ __('ui.auth.send_verification_link') }}</button>
        </form>
        <p class="auth-message-help auth-verification-help">{{ __('ui.auth.verification_help') }}</p>
        <div class="auth-secondary-actions">
            <a class="auth-secondary-btn" href="{{ route(auth()->user()->hasAnyRole(['student', 'alumni', 'new_applicant']) ? 'portal.profile.show' : 'profile.show') }}">{{ __('ui.auth.review_email') }}</a>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="auth-secondary-btn">{{ __('ui.auth.logout') }}</button>
            </form>
        </div>
    </section>
    <footer class="auth-card-footer"><p class="auth-card-copyright">{{ __('ui.auth.copyright', ['year' => date('Y')]) }}</p></footer>
</main>
<script src="{{ asset('js/auth-link-request.js') }}" defer></script>
</body>
</html>
