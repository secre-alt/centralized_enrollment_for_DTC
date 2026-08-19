@extends('adminlte::page')
@include('partials.navbar')

@section('title', 'My Dashboard')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h4 class="mb-0 font-weight-bold">New Applicant Dashboard</h4>
            <p class="mb-0" style="color:var(--dtc-text-secondary); font-size:13px;">Welcome to Danao Technological College!</p>
        </div>
    </div>
@endsection

@section('content')

@php
    $applicationBadges = [
        'submitted'         => ['bg' => '#F1F5F9', 'color' => '#475569', 'label' => 'Submitted'],
        'under_review'      => ['bg' => '#DBEAFE', 'color' => '#1D4ED8', 'label' => 'Under Review'],
        'revision_required' => ['bg' => '#FEF9C3', 'color' => '#A16207', 'label' => 'Revision Required'],
        'approved'          => ['bg' => '#DCFCE7', 'color' => '#15803D', 'label' => 'Approved'],
        'rejected'          => ['bg' => '#FEE2E2', 'color' => '#DC2626', 'label' => 'Rejected'],
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
            icon="fa-file-alt" color="primary"
            label="Pre-Enrollment Application" value="{{ $appStatusLabel }}"
            href="{{ route('portal.application.show') }}" link-text="View Application" />
    </div>

    <div class="col-lg-3 col-sm-6 col-12 mb-3">
        <x-dtc.stat-card
            icon="fa-graduation-cap" color="warning"
            label="Official Enrollment" value="{{ $enrollmentLabel }}"
            href="{{ route('portal.enrollment.index') }}" link-text="View Details" />
    </div>

    <div class="col-lg-3 col-sm-6 col-12 mb-3">
        <x-dtc.stat-card
            icon="fa-book" color="success"
            label="Approved Program" value="{{ $application->program->name ?? 'Not provided' }}" />
    </div>

    <div class="col-lg-3 col-sm-6 col-12 mb-3">
        <x-dtc.stat-card
            icon="fa-bell" color="info" :badge="$unreadCount"
            label="Notifications" value="{{ $unreadCount }} New"
            href="{{ route('notifications.index') }}" />
    </div>
</div>

{{-- ══ WELCOME CARD ════════════════════════════════════════════════════ --}}
<div class="card mb-3"
     style="background:linear-gradient(135deg,#EEF2FF,#E0E7FF);
            border:1.5px solid #C7D2FE; border-radius:16px;">
    <div class="card-body" style="padding:24px;">
        <div style="display:flex; align-items:center; gap:20px;">
            <div style="width:64px; height:64px; border-radius:50%;
                        background:linear-gradient(135deg,#0F4CDB,#1a5feb);
                        display:flex; align-items:center; justify-content:center;
                        color:#fff; font-weight:800; font-size:26px; flex-shrink:0;">
                {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
            </div>
            <div>
                <h4 style="font-weight:800; margin:0 0 4px;">
                    Hello, {{ explode(' ', auth()->user()->name)[0] }}! 👋
                </h4>
                <p style="font-size:13px; color:var(--dtc-text); margin:0;">
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
                    <span style="background:{{ $applicationBadges[$application->status]['bg'] ?? '#F1F5F9' }};
                                 color:{{ $applicationBadges[$application->status]['color'] ?? '#475569' }};
                                 font-size:11px; font-weight:700; padding:4px 12px; border-radius:20px;">
                        {{ $applicationBadges[$application->status]['label'] ?? ucwords(str_replace('_',' ',$application->status)) }}
                    </span>
                @endif
            </div>
            <div class="card-body">
                @if($application)
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <div style="font-size:11px; color:#94A3B8; font-weight:600; text-transform:uppercase;">
                                Reference Number
                            </div>
                            <div style="font-size:14px; font-weight:700;">
                                {{ $application->reference_no }}
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div style="font-size:11px; color:#94A3B8; font-weight:600; text-transform:uppercase;">
                                Program
                            </div>
                            <div style="font-size:14px; font-weight:700;">
                                {{ $application->program->name ?? 'Not provided' }}
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div style="font-size:11px; color:#94A3B8; font-weight:600; text-transform:uppercase;">
                                Submitted
                            </div>
                            <div style="font-size:13px; color:var(--dtc-text);">
                                {{ $application->created_at ? $application->created_at->format('M d, Y') : 'Not provided' }}
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div style="font-size:11px; color:#94A3B8; font-weight:600; text-transform:uppercase;">
                                Approved
                            </div>
                            <div style="font-size:13px; color:var(--dtc-text);">
                                {{ $application->reviewed_at ? $application->reviewed_at->format('M d, Y') : 'Not yet reviewed' }}
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div style="font-size:11px; color:#94A3B8; font-weight:600; text-transform:uppercase;">
                                Documents Submitted
                            </div>
                            <div style="font-size:13px; color:var(--dtc-text);">
                                {{ $application->documents_count }} document{{ $application->documents_count == 1 ? '' : 's' }}
                            </div>
                        </div>
                        @if($application->remarks)
                        <div class="col-md-6 mb-3">
                            <div style="font-size:11px; color:#94A3B8; font-weight:600; text-transform:uppercase;">
                                Registrar Remarks
                            </div>
                            <div style="font-size:13px; color:var(--dtc-text);">
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
                    <div class="text-center py-4" style="color:#94A3B8;">
                        <i class="fas fa-exclamation-triangle fa-2x mb-2"></i>
                        <p style="font-size:13px;">
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
                            <div class="dtc-next-step-icon" style="background:#0F4CDB;">
                                <i class="fas {{ $nextStep['icon'] }}" style="font-size:20px; color:#fff;"></i>
                            </div>
                            <div style="flex:1; text-align:left;">
                                <div style="font-size:14px; font-weight:700; color:#0F4CDB;">
                                    {{ $nextStep['label'] }}
                                </div>
                                <div style="font-size:12px; color:var(--dtc-text-secondary); margin-top:2px;">
                                    {{ $nextStep['desc'] }}
                                </div>
                            </div>
                            <i class="fas fa-chevron-right" style="color:#0F4CDB; font-size:14px;"></i>
                        </button>
                    @else
                        <a href="{{ $nextStep['url'] }}" class="dtc-next-step-btn">
                            <div class="dtc-next-step-icon" style="background:#0F4CDB;">
                                <i class="fas {{ $nextStep['icon'] }}" style="font-size:20px; color:#fff;"></i>
                            </div>
                            <div style="flex:1;">
                                <div style="font-size:14px; font-weight:700; color:#0F4CDB;">
                                    {{ $nextStep['label'] }}
                                </div>
                                <div style="font-size:12px; color:var(--dtc-text-secondary); margin-top:2px;">
                                    {{ $nextStep['desc'] }}
                                </div>
                            </div>
                            <i class="fas fa-chevron-right" style="color:#0F4CDB; font-size:14px;"></i>
                        </a>
                    @endif
                @else
                    <div class="dtc-next-step-btn" style="cursor:default;">
                        <div class="dtc-next-step-icon" style="background:var(--dtc-surface-soft);">
                            <i class="fas {{ $nextStep['icon'] }}" style="font-size:20px; color:var(--dtc-text-muted);"></i>
                        </div>
                        <div>
                            <div style="font-size:14px; font-weight:700; color:var(--dtc-text);">
                                {{ $nextStep['label'] }}
                            </div>
                            <div style="font-size:12px; color:var(--dtc-text-secondary); margin-top:2px;">
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
                   style="font-size:12px; color:#0F4CDB; text-decoration:none; font-weight:600;">
                    View All
                </a>
            </div>
            <div class="card-body p-0">
                @forelse ($announcements as $notif)
                <div style="padding:14px 20px; border-bottom:1px solid #F1F5F9;">
                    <div style="display:flex; gap:10px; align-items:flex-start;">
                        <div style="width:32px; height:32px; border-radius:10px; flex-shrink:0;
                                    background:{{ $notif->type === 'success' ? '#DCFCE7' : ($notif->type === 'danger' ? '#FEE2E2' : '#DBEAFE') }};
                                    display:flex; align-items:center; justify-content:center;">
                            <i class="fas fa-bullhorn"
                               style="font-size:12px;
                                      color:{{ $notif->type === 'success' ? '#15803D' : ($notif->type === 'danger' ? '#DC2626' : '#1D4ED8') }};"></i>
                        </div>
                        <div>
                            <div style="font-size:13px; font-weight:600;">
                                {{ $notif->title }}
                            </div>
                            <div style="font-size:11px; color:#94A3B8; margin-top:2px;">
                                {{ $notif->created_at->format('M d, Y') }}
                            </div>
                        </div>
                    </div>
                </div>
                @empty
                <div class="text-center py-4" style="color:#94A3B8; font-size:13px;">
                    No announcements yet.
                </div>
                @endforelse
            </div>
        </div>

        {{-- Quick Actions --}}
        <div class="card mb-3">
            <div class="card-header font-weight-bold">Quick Actions</div>
            <div class="card-body p-0">
                @php
                    $actions = [
                        ['icon' => 'fa-file-alt',   'label' => 'My Application',        'sub' => 'View your pre-enrollment application', 'url' => route('portal.application.show')],
                        ['icon' => 'fa-plus-circle', 'label' => 'Enroll Now',            'sub' => 'Complete your official enrollment',    'url' => route('portal.enrollment.create')],
                        ['icon' => 'fa-list',        'label' => 'My Enrollment Status',  'sub' => 'Track your official enrollment',        'url' => route('portal.enrollment.index')],
                        ['icon' => 'fa-bell',        'label' => 'Notifications',         'sub' => $unreadCount . ' unread',                'url' => route('notifications.index')],
                    ];
                @endphp
                @foreach($actions as $action)
                <a href="{{ $action['url'] }}"
                   style="display:flex; align-items:center; justify-content:space-between;
                          padding:14px 20px; border-bottom:1px solid #F1F5F9;
                          text-decoration:none; transition:background 0.2s;"
                   onmouseover="this.style.background='#F8FAFC'"
                   onmouseout="this.style.background='transparent'">
                    <div style="display:flex; align-items:center; gap:12px;">
                        <div style="width:36px; height:36px; border-radius:10px;
                                    background:#EEF2FF; display:flex; align-items:center;
                                    justify-content:center;">
                            <i class="fas {{ $action['icon'] }}"
                               style="font-size:14px; color:#0F4CDB;"></i>
                        </div>
                        <div>
                            <div style="font-size:13px; font-weight:600;">
                                {{ $action['label'] }}
                            </div>
                            <div style="font-size:11px; color:var(--dtc-text-secondary);">
                                {{ $action['sub'] }}
                            </div>
                        </div>
                    </div>
                    <i class="fas fa-chevron-right" style="color:#CBD5E1; font-size:12px;"></i>
                </a>
                @endforeach
            </div>
        </div>

    </div>
</div>

@include('student.enrollment._payment-modal')

@endsection