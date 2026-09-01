@extends('adminlte::page')
@include('partials.navbar')

@section('title', 'My Dashboard')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h4 class="mb-0 font-weight-bold">New Applicant Dashboard</h4>
            <p class="mb-0 u-text-secondary-sm" >Welcome to Danao Technological College!</p>
        </div>
    </div>
@endsection

@section('content')

@php
    $applicationBadges = [
        'submitted'         => ['tone' => 'neutral', 'label' => 'Submitted'],
        'under_review'      => ['tone' => 'info',    'label' => 'Under Review'],
        'revision_required' => ['tone' => 'warning', 'label' => 'Revision Required'],
        'approved'          => ['tone' => 'success', 'label' => 'Approved'],
        'rejected'          => ['tone' => 'danger',  'label' => 'Rejected'],
    ];

    $enrollmentLabel = 'Not Started';
    if ($enrollment) {
        if ($enrollment->is_paid) {
            $enrollmentLabel = 'Paid';
        } elseif ($enrollment->status === 'approved') {
            $enrollmentLabel = 'Approved';
        } elseif ($enrollment->status === 'pending') {
            $enrollmentLabel = 'Pending';
        } else {
            $enrollmentLabel = ucfirst($enrollment->status);
        }
    }

    $appStatusLabel = $application
        ? ($applicationBadges[$application->status]['label'] ?? ucwords(str_replace('_',' ',$application->status)))
        : 'Not Found';
@endphp

<!-- TOP STAT CARDS -->
<div class="row mb-3">
    <div class="col-lg-3 col-sm-6 col-12 mb-3">
        <x-dtc.stat-card
            icon="file-text" color="primary"
            label="Pre-Enrollment Application" value="{{ $appStatusLabel }}"
            href="{{ route('portal.application.show') }}" link-text="View Application" />
    </div>

    <div class="col-lg-3 col-sm-6 col-12 mb-3">
        <x-dtc.stat-card
            icon="graduation-cap" color="warning"
            label="Official Enrollment" value="{{ $enrollmentLabel }}"
            href="{{ route('portal.enrollment.index') }}" link-text="View Details" />
    </div>

    <div class="col-lg-3 col-sm-6 col-12 mb-3">
        <x-dtc.stat-card
            icon="book" color="success"
            label="Approved Program" value="{{ $application->program->name ?? 'Not provided' }}" />
    </div>

    <div class="col-lg-3 col-sm-6 col-12 mb-3">
        <x-dtc.stat-card
            icon="bell" color="info" :badge="$unreadCount"
            label="Notifications" value="{{ $unreadCount }} New"
            href="{{ route('notifications.index') }}" />
    </div>
</div>

{{-- ══ WELCOME CARD ════════════════════════════════════════════════════ --}}
<div class="card mb-3 dtc-welcome-card">
    <div class="card-body" style="padding:24px;">
        <div style="display:flex; align-items:center; gap:20px;">
            <div style="width:64px; height:64px; border-radius:50%;
                        background:linear-gradient(135deg,#0F4CDB,#1a5feb);
                        display:flex; align-items:center; justify-content:center;
                        color:#fff; font-weight:800; font-size:26px; flex-shrink:0;">
                {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
            </div>
            <div>
                <h4 class="dtc-welcome-title" style="font-weight:800; margin:0 0 4px;">
                    Hello, {{ explode(' ', auth()->user()->name)[0] }}! 👋
                </h4>
                <p class="dtc-welcome-text" style="font-size:13px; margin:0;">
                    @if($application && $application->status === 'approved')
                        Your pre-enrollment application has been approved.
                        @if(!$enrollment)
                            Complete your official enrollment to continue.
                        @endif
                    @else
                        Welcome to Danao Technological College!
                    @endif
                </p>
            </div>
        </div>
    </div>
</div>

{{-- ══ MAIN CONTENT ROW ════════════════════════════════════════════════ --}}
<div class="row">

    {{-- LEFT: Application Summary + Next Step --}}
    <div class="col-lg-8">

        {{-- Application Summary --}}
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span class="font-weight-bold">Pre-Enrollment Application</span>
                @if($application)
                    <span class="dtc-status-badge is-{{ $applicationBadges[$application->status]['tone'] ?? 'neutral' }}" style="font-weight:700;">
                        {{ $applicationBadges[$application->status]['label'] ?? ucwords(str_replace('_',' ',$application->status)) }}
                    </span>
                @endif
            </div>
            <div class="card-body">
                @if($application)
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <div  class="u-eyebrow">
                                Reference Number
                            </div>
                            <div style="font-size:14px; font-weight:700;">
                                {{ $application->reference_no }}
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div  class="u-eyebrow">
                                Program
                            </div>
                            <div style="font-size:14px; font-weight:700;">
                                {{ $application->program->name ?? 'Not provided' }}
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div  class="u-eyebrow">
                                Submitted
                            </div>
                            <div  class="u-text-sm-secondary-primary">
                                {{ $application->created_at ? $application->created_at->format('M d, Y') : 'Not provided' }}
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div  class="u-eyebrow">
                                Approved
                            </div>
                            <div  class="u-text-sm-secondary-primary">
                                {{ $application->reviewed_at ? $application->reviewed_at->format('M d, Y') : 'Not yet reviewed' }}
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div  class="u-eyebrow">
                                Documents Submitted
                            </div>
                            <div  class="u-text-sm-secondary-primary">
                                {{ $application->documents_count }} document{{ $application->documents_count == 1 ? '' : 's' }}
                            </div>
                        </div>
                        @if($application->remarks)
                        <div class="col-md-6 mb-3">
                            <div  class="u-eyebrow">
                                Registrar Remarks
                            </div>
                            <div  class="u-text-sm-secondary-primary">
                                {{ $application->remarks }}
                            </div>
                        </div>
                        @endif
                    </div>

                    <a href="{{ route('portal.application.show') }}"
                       class="btn btn-outline-primary btn-sm mt-2">
                        View Full Application →
                    </a>
                @else
                    <div class="text-center py-4 u-muted" >
                        <i data-lucide="alert-triangle" class="mb-2" style="width:2em;height:2em"></i>
                        <p  class="u-text-sm">
                            No approved application found for your account.
                            Please contact the Registrar's Office.
                        </p>
                    </div>
                @endif
            </div>
        </div>

        {{-- Next Step --}}
        <div class="card mb-3">
            <div class="card-header font-weight-bold">Next Step</div>
            <div class="card-body p-0">
                @if($nextStep['url'])
                    @if($nextStep['isPayment'] ?? false)
                        <button type="button" class="dtc-payment-btn dtc-next-step-btn" data-url="{{ $nextStep['url'] }}">
                            <div class="dtc-next-step-icon" style="background:var(--dtc-primary);">
                                <i data-lucide="{{ $nextStep['icon'] }}" style="font-size:20px; color:#fff;"></i>
                            </div>
                            <div style="flex:1; text-align:left;">
                                <div style="font-size:14px; font-weight:700; color:var(--dtc-primary);">
                                    {{ $nextStep['label'] }}
                                </div>
                                <div  class="u-text-xxs-secondary">
                                    {{ $nextStep['desc'] }}
                                </div>
                            </div>
                            <i data-lucide="chevron-right" style="color:var(--dtc-primary); font-size:14px;"></i>
                        </button>
                    @else
                        <a href="{{ $nextStep['url'] }}" class="dtc-next-step-btn">
                            <div class="dtc-next-step-icon" style="background:var(--dtc-primary);">
                                <i data-lucide="{{ $nextStep['icon'] }}" style="font-size:20px; color:#fff;"></i>
                            </div>
                            <div  class="u-flex-1">
                                <div style="font-size:14px; font-weight:700; color:var(--dtc-primary);">
                                    {{ $nextStep['label'] }}
                                </div>
                                <div  class="u-text-xxs-secondary">
                                    {{ $nextStep['desc'] }}
                                </div>
                            </div>
                            <i data-lucide="chevron-right" style="color:var(--dtc-primary); font-size:14px;"></i>
                        </a>
                    @endif
                @else
                    <div class="dtc-next-step-btn" style="cursor:default;">
                        <div class="dtc-next-step-icon" style="background:var(--dtc-surface-soft);">
                            <i data-lucide="{{ $nextStep['icon'] }}" style="font-size:20px; color:var(--dtc-text-muted);"></i>
                        </div>
                        <div>
                            <div style="font-size:14px; font-weight:700; color:var(--dtc-text);">
                                {{ $nextStep['label'] }}
                            </div>
                            <div  class="u-text-xxs-secondary">
                                {{ $nextStep['desc'] }}
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>

    </div>

    {{-- RIGHT: Announcements + Quick Actions --}}
    <div class="col-lg-4">

        {{-- Announcements --}}
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span class="font-weight-bold">Announcements</span>
                <a href="{{ route('notifications.index') }}"
                   style="font-size:12px; color:var(--dtc-primary); text-decoration:none; font-weight:600;">
                    View All
                </a>
            </div>
            <div class="card-body p-0">
                @forelse ($announcements as $notif)
                <div style="padding:14px 20px; border-bottom:1px solid var(--dtc-border-soft);">
                    <div style="display:flex; gap:10px; align-items:flex-start;">
                        <div class="dtc-icon-swatch is-{{ $notif->type === 'success' ? 'success' : ($notif->type === 'danger' ? 'danger' : 'info') }}" style="width:32px; height:32px; flex-shrink:0;">
                            <i data-lucide="megaphone" style="font-size:12px;"></i>
                        </div>
                        <div>
                            <div  class="u-text-sm-bold">
                                {{ $notif->title }}
                            </div>
                            <div style="font-size:11px; color:var(--dtc-text-muted); margin-top:2px;">
                                {{ $notif->created_at->format('M d, Y') }}
                            </div>
                        </div>
                    </div>
                </div>
                @empty
                <div class="text-center py-4" style="color:var(--dtc-text-muted); font-size:13px;">
                    No announcements yet.
                </div>
                @endforelse
            </div>
        </div>

        {{-- Quick Actions --}}
        <div class="card mb-3">
            <div class="card-header font-weight-bold">Quick Actions</div>
            <div class="card-body p-3">
                <a href="{{ route('portal.application.show') }}" class="quick-action-btn">
                    <i data-lucide="file-text"></i> My Application
                </a>
                <a href="{{ route('portal.enrollment.create') }}" class="quick-action-btn">
                    <i data-lucide="plus-circle"></i> Enroll Now
                </a>
                <a href="{{ route('portal.enrollment.index') }}" class="quick-action-btn">
                    <i data-lucide="list"></i> My Enrollment Status
                </a>
                <a href="{{ route('notifications.index') }}" class="quick-action-btn">
                    <i data-lucide="bell"></i> Notifications
                    @if($unreadCount > 0)
                        <span class="badge badge-danger ml-auto" style="font-size:10px;">{{ $unreadCount }}</span>
                    @endif
                </a>
            </div>
        </div>

    </div>
</div>

@include('student.enrollment._payment-modal')

@endsection