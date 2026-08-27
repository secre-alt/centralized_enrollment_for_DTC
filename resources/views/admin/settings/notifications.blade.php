@extends('adminlte::page')
@include('partials.navbar')

@section('title', 'Notification Settings')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center flex-wrap" style="gap:12px;">
        <div>
            <h4 class="mb-0 font-weight-bold u-text">Notification Settings</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 p-0">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}" class="u-text-secondary">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('admin.settings.index') }}" class="u-text-secondary">Settings</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Notifications</li>
                </ol>
            </nav>
        </div>

        <button type="submit" form="notifications-settings-form" class="btn btn-primary">
            <i class="fas fa-save mr-1"></i> Save Changes
        </button>
    </div>
@endsection

@section('content')
<div class="row">

    <div class="col-12">

        @if (session('status'))
            <div class="alert alert-success">
                <i class="fas fa-check-circle mr-1"></i> {{ session('status') }}
            </div>
        @endif

        <form id="notifications-settings-form" method="POST" action="{{ route('admin.settings.notifications.update') }}">
            @csrf
            @method('PUT')

            <div class="card">
                <div class="card-body">
                    <div class="settings-section-title">
                        <div class="settings-section-icon" style="background:#EEF2FF; color:#0F4CDB;">
                            <i class="fas fa-bell"></i>
                        </div>
                        <h3>Notification Channels</h3>
                    </div>
                    <p style="font-size:12px; color:var(--dtc-text-secondary); margin:-8px 0 20px;">
                        Choose how the system notifies students, applicants, and staff about
                        enrollment updates, payments, and announcements.
                    </p>

                    {{-- Email --}}
                    <div class="settings-toggle-row d-flex align-items-center justify-content-between flex-wrap py-3" style="gap:16px; border-bottom:1px solid var(--dtc-border);">
                        <div class="d-flex align-items-center" style="gap:14px;">
                            <div class="settings-section-icon" style="background:#EEF2FF; color:#0F4CDB;">
                                <i class="fas fa-envelope"></i>
                            </div>
                            <div>
                                <h3 style="font-size:15px; font-weight:700; color:var(--dtc-text); margin:0;">Email Notifications</h3>
                                <p style="font-size:12px; color:var(--dtc-text-secondary); margin:2px 0 0; max-width:480px;">
                                    Send updates to email addresses on file &mdash; application status,
                                    payment receipts, and document requests.
                                </p>
                            </div>
                        </div>

                        <label class="dtc-toggle">
                            <input type="checkbox" name="email_notifications" value="1"
                                   {{ old('email_notifications', $settings->email_notifications ?? '1') === '1' ? 'checked' : '' }}>
                            <span class="slider"></span>
                        </label>
                    </div>

                    {{-- SMS --}}
                    <div class="settings-toggle-row d-flex align-items-center justify-content-between flex-wrap py-3" style="gap:16px; border-bottom:1px solid var(--dtc-border);">
                        <div class="d-flex align-items-center" style="gap:14px;">
                            <div class="settings-section-icon" style="background:#FFF7E0; color:#D97706;">
                                <i class="fas fa-sms"></i>
                            </div>
                            <div>
                                <h3 style="font-size:15px; font-weight:700; color:var(--dtc-text); margin:0;">SMS Notifications</h3>
                                <p style="font-size:12px; color:var(--dtc-text-secondary); margin:2px 0 0; max-width:480px;">
                                    Send text messages for time-sensitive alerts such as appointment
                                    reminders and enrollment deadlines.
                                </p>
                            </div>
                        </div>

                        <label class="dtc-toggle">
                            <input type="checkbox" name="sms_notifications" value="1"
                                   {{ old('sms_notifications', $settings->sms_notifications ?? '0') === '1' ? 'checked' : '' }}>
                            <span class="slider"></span>
                        </label>
                    </div>

                    {{-- In-app --}}
                    <div class="settings-toggle-row d-flex align-items-center justify-content-between flex-wrap py-3" style="gap:16px;">
                        <div class="d-flex align-items-center" style="gap:14px;">
                            <div class="settings-section-icon" style="background:#E7F8EF; color:#0F9D58;">
                                <i class="fas fa-desktop"></i>
                            </div>
                            <div>
                                <h3 style="font-size:15px; font-weight:700; color:var(--dtc-text); margin:0;">In-App Alerts</h3>
                                <p style="font-size:12px; color:var(--dtc-text-secondary); margin:2px 0 0; max-width:480px;">
                                    Show the notification bell and in-app alerts for logged-in
                                    students, registrars, and cashiers.
                                </p>
                            </div>
                        </div>

                        <label class="dtc-toggle">
                            <input type="checkbox" name="app_notifications" value="1"
                                   {{ old('app_notifications', $settings->app_notifications ?? '1') === '1' ? 'checked' : '' }}>
                            <span class="slider"></span>
                        </label>
                    </div>

                </div>
            </div>
        </form>
    </div>
</div>
@endsection
