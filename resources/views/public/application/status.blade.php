@extends('layouts.public')

@section('title', 'Check Application Status')

@section('styles')
<link rel="stylesheet" href="{{ asset('css/application-wizard.css') }}?v={{ filemtime(public_path('css/application-wizard.css')) }}">
@stop

@section('content')
<div class="app-wizard-page status-lookup-page {{ isset($application) ? '' : 'admission-wizard' }}">
    @if(isset($application))
        <header class="app-wizard-intro">
            <div class="status-page-heading">
                <h1>Application status</h1>
                <span class="status-result-badge is-{{ $application->status }}" role="status">
                    {{ ucwords(str_replace('_', ' ', $application->status)) }}
                </span>
            </div>
        </header>
    @endif

    @if(isset($application))
        <section class="wiz-panel status-result-card" aria-labelledby="status-result-title">
            <div class="wiz-panel-header">
                <div class="wiz-panel-icon"><i data-lucide="clipboard-check" aria-hidden="true"></i></div>
                <div>
                    <h2 class="wiz-panel-title" id="status-result-title">Application details</h2>
                    <p class="wiz-panel-sub">Reference {{ $application->reference_no }}</p>
                </div>
            </div>
            <div class="wiz-panel-body">
                <dl class="status-facts">
                    <div><dt>Applicant</dt><dd>{{ $application->full_name }}</dd></div>
                    <div><dt>Applicant type</dt><dd>{{ ucwords(str_replace('_', ' ', $application->academic_status)) }}</dd></div>
                    <div><dt>Program</dt><dd>{{ $application->program->name ?? '—' }}</dd></div>
                    <div><dt>Email</dt><dd>{{ $application->email }}</dd></div>
                </dl>

                <div class="status-update" role="status">
                    <p class="status-description">{{ $application->getStatusDescription() }}</p>
                    <div class="status-next-step"><strong>Next step</strong><span>{{ $nextStep }}</span></div>
                    @if($application->status === 'approved')
                        @if($application->user)
                            <a href="{{ route('login') }}" class="wiz-btn wiz-btn--ghost status-action-link">Sign in to your portal</a>
                        @else
                            <a href="{{ route('password.activation.request') }}" class="wiz-btn wiz-btn--ghost status-action-link">Request an activation link</a>
                        @endif
                    @endif
                </div>

                @if(in_array($application->status, ['revision_required', 'rejected'], true) && filled($application->remarks))
                    <section class="status-remarks" aria-labelledby="status-remarks-title">
                        <h3 id="status-remarks-title">Registrar remarks</h3>
                        <div class="status-remarks-copy">{{ $application->remarks }}</div>
                    </section>
                @endif

                @if($application->status === 'revision_required' && $canCorrectEmail)
                    <section class="status-remarks status-email-correction" aria-labelledby="email-correction-title">
                        <h3 id="email-correction-title">Update your application email</h3>
                        <p class="status-remarks-copy">Your current address is {{ $application->email }}. Enter the correct address and verify it to resubmit your application. Other requested corrections must be arranged with the Registrar.</p>
                        @if(session('success'))<div class="alert alert-success" role="status">{{ session('success') }}</div>@endif
                        @if(session('warning'))<div class="alert alert-warning" role="alert">{{ session('warning') }}</div>@endif
                        <form method="POST" action="{{ $emailCorrectionUrl }}" class="status-email-correction-form">
                            @csrf
                            <div class="wiz-field">
                                <label for="new_email">New email address <span class="req">*</span></label>
                                <input type="email" name="new_email" id="new_email" class="wiz-input @error('new_email') wiz-invalid @enderror" value="{{ old('new_email') }}" placeholder="you@example.com" autocomplete="email" maxlength="255" required>
                                @error('new_email')<span class="wiz-err-msg">{{ $message }}</span>@enderror
                            </div>
                            <button type="submit" class="wiz-btn wiz-btn--primary status-submit-btn">Send verification link <i data-lucide="arrow-right" aria-hidden="true"></i></button>
                        </form>
                    </section>
                @endif

                <section class="status-progress" aria-labelledby="status-progress-title">
                    <h3 id="status-progress-title">Progress</h3>
                    <ol>
                        @foreach($progressTimeline as $step)
                            <li class="is-{{ $step['status'] }}">
                                <span class="status-progress-marker" aria-hidden="true"></span>
                                <div>
                                    <strong>{{ $step['label'] }}</strong>
                                    <p>{{ $step['description'] }}</p>
                                    @if($step['timestamp'])
                                        <time datetime="{{ $step['timestamp']->toIso8601String() }}">{{ $step['timestamp']->format('M d, Y g:i A') }}</time>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ol>
                </section>

                @if($application->status === 'approved' && !empty($physicalRequirements))
                    <div class="status-requirements">
                        <h3>Documents to prepare</h3>
                        <p>Please bring these physical items to the Registrar's Office when instructed.</p>
                        <ul>
                            @foreach($physicalRequirements as $requirement)
                                <li>{{ $requirement }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <a href="{{ route('public.application.status.form') }}" class="wiz-btn wiz-btn--ghost status-another-link">
                    <i data-lucide="search" aria-hidden="true"></i> Check another application
                </a>
            </div>
        </section>
    @else
        <section class="admission-card status-lookup-card" aria-labelledby="status-form-title">
            <div class="admission-progress-heading">
                <span>Applicant services</span>
                <span>Check status</span>
            </div>
            <header class="app-wizard-intro status-lookup-intro">
                <h1 id="status-form-title">Check your application status</h1>
                <p>Enter the reference number and email address from your application.</p>
            </header>
            <div class="status-lookup-body">
                @if(session('error'))
                    <div class="alert alert-danger status-error" role="alert">{{ session('error') }}</div>
                @endif

                @if($errors->any())
                    <div class="alert alert-danger status-error" role="alert" aria-live="polite">
                        <p class="mb-1 fw-semibold">We couldn't find your application.</p>
                        <ul class="mb-0 ps-3">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form method="POST" action="{{ route('public.application.status') }}" class="status-lookup-form">
                    @csrf
                    <div class="wiz-row wiz-row--2 status-lookup-fields">
                        <div class="wiz-field">
                            <label for="reference_no">Application reference number <span class="req">*</span></label>
                            <input type="text" name="reference_no" id="reference_no"
                                class="wiz-input @error('reference_no') wiz-invalid @enderror"
                                value="{{ $referenceNo ?? old('reference_no') }}" placeholder="e.g. APP-2026-ABC123"
                                autocomplete="off" maxlength="50" required aria-describedby="reference-hint" autofocus>
                            <span class="wiz-hint" id="reference-hint">Found on your confirmation page and email. It starts with APP-.</span>
                            @error('reference_no')<span class="wiz-err-msg">{{ $message }}</span>@enderror
                        </div>

                        <div class="wiz-field">
                            <label for="email">Email address <span class="req">*</span></label>
                            <input type="email" name="email" id="email"
                                class="wiz-input @error('email') wiz-invalid @enderror"
                                value="{{ $email ?? old('email') }}" placeholder="you@example.com"
                                autocomplete="email" maxlength="255" required>
                            @error('email')<span class="wiz-err-msg">{{ $message }}</span>@enderror
                        </div>
                    </div>

                    <button type="submit" class="wiz-btn wiz-btn--primary status-submit-btn">
                        Check application status <i data-lucide="arrow-right" aria-hidden="true"></i>
                    </button>
                </form>
            </div>
        </section>

        <p class="status-apply-prompt">Haven't submitted an application yet? <a href="{{ route('public.application.create') }}">Start an application</a></p>
    @endif
</div>
@endsection
