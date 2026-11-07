<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('partials.pwa-head')
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('ui.auth.set_password_title') }} — DTC EMS</title>
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
    @include('partials.auth-appearance')
</head>
<body class="auth-activation-page">

<div class="auth-card">

    {{-- ── Header ────────────────────────────────────────── --}}
    <div class="auth-card-header">
        <div class="auth-brand">
            <img src="{{ asset('images/DTC-LOGO.webp') }}" alt="DTC Logo">
            <div>
                <p class="auth-brand-name">DTC EMS</p>
                <p class="auth-brand-sub">Danao Technological College</p>
            </div>
        </div>
        <p class="auth-card-tagline">
            @if($linkExpired)
                {{ __('ui.auth.activate_tagline') }} <span>{{ __('ui.auth.account_word') }}</span>
            @else
                {{ __('ui.auth.welcome_to') }} <span>DTC EMS.</span>
            @endif
        </p>
    </div>

    {{-- ── Body ─────────────────────────────────────────── --}}
    <div class="auth-card-body">

        @if (session('success'))
            <div class="auth-alert auth-alert--success" role="status">
                <div>{{ session('success') }}<p class="auth-message-help">{{ __('ui.auth.email_help') }}</p></div>
            </div>
        @endif

        @if ($errors->any())
            <div class="auth-alert auth-alert--error">
                <i data-lucide="alert-circle"></i>
                {{ $errors->first() }}
            </div>
        @endif

        @if ($linkExpired)

            {{-- ── Expired link state ───────────────────── --}}

            <h1 class="auth-card-title">{{ __('ui.auth.activation_unavailable') }}</h1>
            <p class="auth-card-subtitle">
                {{ __('ui.auth.request_new_link') }}
            </p>

            <div class="auth-field">
                <label class="auth-label" for="setup_email">Email</label>
                <div class="auth-input-wrap">
                    <i data-lucide="mail" class="auth-input-icon" aria-hidden="true"></i>
                    <input
                        type="text"
                        id="setup_email"
                        class="auth-input"
                        value="{{ $email }}"
                        placeholder=" "
                        disabled
                    >
                </div>
            </div>

            <form method="POST" action="{{ route('password.activation.resend') }}" data-link-request>
                @csrf
                <input type="hidden" name="email" value="{{ $email }}">
                <button type="submit" class="auth-btn">
                    {{ __('ui.auth.send_new_link') }}
                </button>
            </form>

        @else

            {{-- ── Valid link — password setup ─────────── --}}

            <h1 class="auth-card-title">{{ __('ui.auth.set_password_title') }}</h1>
            <p class="auth-card-subtitle">
                {{ __('ui.auth.create_password_activate') }}
            </p>
            <p class="auth-account-email">{{ $email }}</p>

            <form method="POST" action="{{ route('password.setup.store') }}">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">
                <input type="hidden" name="email" value="{{ $email }}">

                <div class="auth-field">
                    <label for="password" class="auth-label">{{ __('ui.auth.new_password') }}</label>
                    <div class="auth-input-wrap">
                        <i data-lucide="lock" class="auth-input-icon" aria-hidden="true"></i>
                        <input
                            type="password"
                            name="password"
                            id="password"
                            class="auth-input @error('password') auth-input--error @enderror"
                            placeholder="{{ __('ui.auth.at_least_8') }}"
                            autocomplete="new-password"
                            minlength="8"
                            required
                            autofocus
                        >
                        <button type="button" class="auth-eye" onclick="togglePw('password', this)" aria-label="{{ __('ui.auth.show_password') }}">
                            <i data-lucide="eye" aria-hidden="true"></i>
                        </button>
                    </div>
                </div>

                <div class="auth-field">
                    <label for="password_confirmation" class="auth-label">{{ __('ui.auth.confirm_password') }}</label>
                    <div class="auth-input-wrap">
                        <i data-lucide="lock" class="auth-input-icon" aria-hidden="true"></i>
                        <input
                            type="password"
                            name="password_confirmation"
                            id="password_confirmation"
                            class="auth-input @error('password_confirmation') auth-input--error @enderror"
                            placeholder="{{ __('ui.auth.reenter_password') }}"
                            autocomplete="new-password"
                            minlength="8"
                            required
                        >
                        <button type="button" class="auth-eye" onclick="togglePw('password_confirmation', this)" aria-label="{{ __('ui.auth.show_password') }}">
                            <i data-lucide="eye" aria-hidden="true"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" class="auth-btn">
                    {{ __('ui.auth.set_password') }}
                </button>
            </form>

        @endif

    </div>

    {{-- ── Footer ───────────────────────────────────────── --}}
    <div class="auth-card-footer">
        <p class="auth-card-copyright">{{ __('ui.auth.copyright', ['year' => date('Y')]) }}</p>
    </div>

</div>

<script>
function togglePw(id, btn) {
    const input = document.getElementById(id);
    if (!input || !btn) return;
    const hidden = input.type === 'password';
    input.type = hidden ? 'text' : 'password';
    const icon = document.createElement('i');
    icon.setAttribute('data-lucide', hidden ? 'eye-off' : 'eye');
    icon.setAttribute('aria-hidden', 'true');
    btn.replaceChildren(icon);
    if (window.lucide) lucide.createIcons();
    btn.setAttribute('aria-pressed', String(hidden));
    btn.setAttribute('aria-label', hidden ? @json(__('ui.auth.hide_password')) : @json(__('ui.auth.show_password')));
}
</script>

    <script>
        document.addEventListener("DOMContentLoaded", function () {
            if (window.lucide) lucide.createIcons();
        });
    </script>
@include('components.dtc.password-guidance')
<script src="{{ asset('js/auth-link-request.js') }}" defer></script>
</body>
</html>
