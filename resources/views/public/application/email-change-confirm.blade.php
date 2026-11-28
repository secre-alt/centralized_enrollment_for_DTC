@extends('layouts.public')

@section('title', 'Verify Application Email')

@section('styles')
<link rel="stylesheet" href="{{ asset('css/application-wizard.css') }}">
@stop

@section('content')
<div class="app-wizard-page status-lookup-page admission-wizard">
    <section class="admission-card status-lookup-card" aria-labelledby="email-change-title">
        <div class="admission-progress-heading">
            <span>Applicant services</span>
            <span>Email verification</span>
        </div>
        <header class="app-wizard-intro status-lookup-intro">
            <h1 id="email-change-title">Verify your new email</h1>
            <p>Confirm the address below to update your application and send it back for Registrar review.</p>
        </header>
        <div class="status-lookup-body">
            <p><strong>{{ $emailChange->new_email }}</strong></p>
            <form method="POST" action="{{ route('public.application.status.email-change.complete', [$emailChange, $token]) }}">
                @csrf
                <button type="submit" class="wiz-btn wiz-btn--primary status-submit-btn">Verify email and resubmit <i data-lucide="arrow-right" aria-hidden="true"></i></button>
            </form>
            <p class="wiz-hint mt-3">If you didn’t request this change, close this page. Your current application email and status will stay unchanged.</p>
        </div>
    </section>
</div>
@endsection
