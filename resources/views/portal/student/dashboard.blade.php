@extends('adminlte::page')
@include('partials.navbar')

@section('title', 'My Dashboard')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h4 class="mb-0 font-weight-bold">Student Dashboard</h4>
            <p class="mb-0 u-text-secondary-sm" >Welcome to Danao Technological College!</p>
        </div>
    </div>
@endsection

@section('content')

@php
    if (!$latestEnrollment) {
        $appStatusLabel = 'Not Started';
    } elseif ($latestEnrollment->status === 'approved' && $latestEnrollment->is_paid) {
        $appStatusLabel = 'Enrolled ✓';
    } elseif ($latestEnrollment->status === 'approved') {
        $appStatusLabel = 'Approved';
    } elseif ($latestEnrollment->status === 'pending') {
        $appStatusLabel = 'In Progress';
    } elseif ($latestEnrollment->status === 'rejected') {
        $appStatusLabel = 'Rejected';
    } else {
        $appStatusLabel = 'Status Unavailable';
    }
@endphp

<!-- TOP STAT CARDS -->
<div class="row mb-3">
    <div class="col-lg-3 col-sm-6 col-12 mb-3">
        <x-dtc.stat-card
            icon="file-text" color="primary"
            label="Enrollment Status" value="{{ $appStatusLabel }}"
            href="{{ route('portal.enrollment.index') }}" link-text="View Details" />
    </div>

    <div class="col-lg-3 col-sm-6 col-12 mb-3">
        <x-dtc.stat-card
            icon="list-ordered" color="warning"
            label="Enrollment Steps" value="{{ $completedSteps }} of {{ count($timeline) ?: 5 }}"
            href="{{ route('portal.enrollment.index') }}" link-text="View Steps" />
    </div>

    <div class="col-lg-3 col-sm-6 col-12 mb-3">
        <x-dtc.stat-card
            icon="calendar" color="success"
            label="Last Updated"
            value="{{ $latestEnrollment ? $latestEnrollment->updated_at->format('M d, Y') : 'N/A' }}"
            note="{{ $latestEnrollment ? $latestEnrollment->updated_at->format('h:i A') : '' }}" />
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
        <div class="dtc-welcome-layout">
            <div class="dtc-welcome-avatar-wrap">
            <a href="{{ route('portal.profile.show') }}"
               class="dtc-welcome-avatar-link"
               aria-label="Open profile settings"
               title="Profile settings">
            @if(auth()->user()->avatar)
            <img src="{{ Storage::url(auth()->user()->avatar) }}"
                 alt=""
                 aria-hidden="true"
                 class="dtc-welcome-avatar-image">
            @else
            <div aria-hidden="true" class="dtc-welcome-avatar-initials">
                {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
            </div>
            @endif
            </a>
            <button type="button"
               class="dtc-avatar-camera-badge"
               data-avatar-picker-trigger
               aria-label="Choose a new profile photo"
               title="Change profile photo">
                <i data-lucide="camera" style="width:15px;height:15px;"></i>
            </button>
            </div>
            <form method="POST"
                  action="{{ route('portal.profile.update') }}"
                  enctype="multipart/form-data"
                  id="dashboardAvatarForm"
                  class="d-none"
                  data-dashboard-avatar-form>
                @csrf
                <input type="hidden" name="return_to_dashboard" value="1">
                <input type="hidden" name="name" value="{{ auth()->user()->name }}">
                <input type="hidden" name="email" value="{{ auth()->user()->email }}">
                <input type="file"
                       id="dashboardAvatarInput"
                       name="avatar"
                       accept="image/jpeg,image/png,image/webp"
                       data-dashboard-avatar-input>
            </form>
            <div class="dtc-welcome-copy">
                <h4 class="dtc-welcome-title" style="font-weight:800; margin:0 0 4px;">
                    Hello, {{ explode(' ', auth()->user()->name)[0] }}! 
                </h4>
                @if($studentProfile && $studentProfile->enrolled_ay)
                <p style="font-size:12px; margin:0 0 4px; color:var(--dtc-muted, #6c757d);">
                    🎓
                    @if($studentProfile->program){{ $studentProfile->program->name }} · @endif
                    Enrolled AY {{ $studentProfile->enrolled_ay }}
                </p>
                @endif
                <p class="dtc-welcome-text" style="font-size:13px; margin:0;">
                    Welcome to Danao Technological College!
                    @if(!$latestEnrollment)
                        Please complete your application and follow the enrollment steps below.
                    @elseif($latestEnrollment->status === 'approved' && $latestEnrollment->is_paid)
                        Your payment is confirmed and your enrollment is complete.
                    @elseif($latestEnrollment->status === 'approved' && $latestEnrollment->payment && $latestEnrollment->payment->status === 'pending')
                        Your payment proof is awaiting Cashier verification.
                    @elseif($latestEnrollment->status === 'approved' && $latestEnrollment->payment && $latestEnrollment->payment->status === 'rejected')
                        Your payment proof was rejected. Review the payment details and submit a corrected proof.
                    @elseif($latestEnrollment->status === 'approved')
                        Your enrollment is approved! Please proceed to the Cashier to pay ₱{{ number_format($fee, 2) }}.
                    @elseif($latestEnrollment->status === 'pending')
                        Your enrollment is under review. Please wait for the Registrar's approval.
                    @elseif($latestEnrollment->status === 'rejected')
                        Your enrollment was rejected. Review the Registrar's feedback in the progress section and use the resubmission option below.
                    @else
                        Your enrollment was not approved. Please contact the Registrar.
                    @endif
                </p>
            </div>
        </div>
    </div>
</div>

{{-- ══ MAIN CONTENT ROW ════════════════════════════════════════════════ --}}
<div class="row">

    {{-- LEFT: Enrollment Progress + Subjects --}}
    <div class="col-lg-8">

        {{-- Enrollment Progress Stepper --}}
        @if($timeline)
        <div class="card mb-3">
            <div class="card-header font-weight-bold">
                Enrollment Progress
            </div>
            <div class="card-body">
                {{-- Stepper --}}
                <div style="display:flex; align-items:flex-start; justify-content:space-between;
                            position:relative; padding:0 10px;">

                    {{-- Progress line --}}
                    <div style="position:absolute; top:18px; left:10%; right:10%;
                                height:3px; background:var(--dtc-border); z-index:0;"></div>
                    <div style="position:absolute; top:18px; left:10%;
                                height:3px; background:var(--dtc-primary); z-index:1;
                                width:{{ $completedSteps> 0 ? (($completedSteps - 1) / (count($timeline) - 1)) * 80 : 0 }}%;"></div>

                    @foreach ($timeline as $i => $step)
                    <div style="display:flex; flex-direction:column; align-items:center;
                                z-index:2; flex:1; text-align:center;">
                        <div data-step-state="{{ ($step['rejected'] ?? false) ? 'rejected' : ($step['done'] ? 'done' : ($step['active'] ? 'active' : 'pending')) }}"
                             style="width:36px; height:36px; border-radius:50%;
                                    border:3px solid {{ ($step['rejected'] ?? false) ? 'var(--dtc-danger, #dc3545)' : ($step['done'] ? 'var(--dtc-primary)' : ($step['active'] ? 'var(--dtc-accent)' : 'var(--dtc-border)')) }};
                                    background:{{ ($step['rejected'] ?? false) ? 'var(--dtc-danger, #dc3545)' : ($step['done'] ? 'var(--dtc-primary)' : ($step['active'] ? 'var(--dtc-primary-soft)' : 'var(--dtc-surface)')) }};
                                    display:flex; align-items:center; justify-content:center;
                                    font-size:12px; font-weight:700;
                                    color:{{ (($step['rejected'] ?? false) || $step['done']) ? '#fff' : ($step['active'] ? 'var(--dtc-warning)' : 'var(--dtc-text-muted)') }};
                                    margin-bottom:8px;">
                            @if($step['rejected'] ?? false)
                                <i data-lucide="x" class="u-text-xxs"></i>
                            @elseif($step['done'])
                                <i data-lucide="check" class="u-text-xxs"></i>
                            @else
                                {{ $i + 1 }}
                            @endif
                        </div>
                        <div style="font-size:11px; font-weight:{{ $step['done'] || $step['active'] ? '700' : '500' }};
                                    color:{{ ($step['rejected'] ?? false) ? 'var(--dtc-danger, #dc3545)' : ($step['done'] ? 'var(--dtc-primary)' : ($step['active'] ? 'var(--dtc-warning)' : 'var(--dtc-text-muted)')) }};">
                            {{ $step['label'] }}
                        </div>
                        <div style="font-size:10px; color:var(--dtc-text-muted); margin-top:2px;">
                            {{ $step['sublabel'] }}
                        </div>
                    </div>
                    @endforeach
                </div>

                {{-- Current step info --}}
                @php $activeStep = collect($timeline)->firstWhere('active', true); @endphp
                @if($activeStep)
                <div class="alert alert-warning" style="margin-top:20px; border-radius:12px;
                            padding:12px 16px; display:flex; align-items:center; gap:12px;">
                    <i data-lucide="arrow-right"></i>
                    <div>
                        <div style="font-size:13px; font-weight:600;">
                            Current Step: {{ $activeStep['label'] }}
                        </div>
                        <div style="font-size:12px; opacity:0.85;">
                            {{ $activeStep['sublabel'] }}
                        </div>
                    </div>
                </div>
                @elseif($latestEnrollment && $latestEnrollment->status === 'approved' && $latestEnrollment->is_paid)
                <div class="alert alert-success" style="margin-top:20px; border-radius:12px;
                            padding:12px 16px; display:flex; align-items:center; gap:12px;">
                    <i data-lucide="check-circle"></i>
                    <div style="font-size:13px; font-weight:600;">
                        All steps completed! You are officially enrolled.
                    </div>
                </div>
                @elseif($latestEnrollment && $latestEnrollment->status === 'rejected')
                <div class="alert alert-danger" style="margin-top:20px; border-radius:12px; padding:12px 16px;">
                    <div style="font-size:13px; font-weight:700;">Enrollment rejected</div>
                    <div style="font-size:12px; margin-top:4px;">
                        {{ $latestEnrollment->remarks ?: 'No additional feedback was provided. Review the available next steps or contact the Registrar for guidance.' }}
                    </div>
                </div>
                @endif
            </div>
        </div>
        @endif

        {{-- Enrolled Subjects — COR-style table --}}
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span class="font-weight-bold">Enrolled Subjects</span>
                <div class="d-flex align-items-center" style="gap:10px;">
                    @if($subjects->isNotEmpty())
                        <span style="font-size:12px; color:var(--dtc-primary); font-weight:600;">
                            {{ $subjects->count() }} Subject{{ $subjects->count() > 1 ? 's' : '' }}
                        </span>
                    @endif
                    @if($latestEnrollment && $latestEnrollment->status === 'approved' && $latestEnrollment->is_paid)
                        <a href="{{ route('portal.cor.show') }}"
                           class="btn btn-outline-primary btn-sm"
                           style="font-size:11px; padding:3px 10px;">
                            <i data-lucide="scroll-text" style="width:12px;height:12px;margin-right:4px;"></i>
                            View Full COR
                        </a>
                    @endif
                </div>
            </div>
            <div class="card-body p-0">
                @if($subjects->isNotEmpty())
                <div class="table-responsive">
                    <table class="table table-sm mb-0" style="font-size:12.5px;">
                        <thead>
                            <tr style="background:var(--dtc-surface-soft);">
                                <th style="padding:10px 16px; font-size:10.5px; font-weight:700; text-transform:uppercase; letter-spacing:0.5px; color:var(--dtc-text-secondary); border-bottom:1px solid var(--dtc-border);">Course Code</th>
                                <th style="padding:10px 16px; font-size:10.5px; font-weight:700; text-transform:uppercase; letter-spacing:0.5px; color:var(--dtc-text-secondary); border-bottom:1px solid var(--dtc-border);">Description</th>
                                <th style="padding:10px 16px; font-size:10.5px; font-weight:700; text-transform:uppercase; letter-spacing:0.5px; color:var(--dtc-text-secondary); border-bottom:1px solid var(--dtc-border); text-align:center;">Units</th>
                                <th style="padding:10px 16px; font-size:10.5px; font-weight:700; text-transform:uppercase; letter-spacing:0.5px; color:var(--dtc-text-secondary); border-bottom:1px solid var(--dtc-border); text-align:center;">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($subjects as $subject)
                            <tr style="border-bottom:1px solid var(--dtc-border-soft);">
                                <td style="padding:10px 16px; vertical-align:middle;">
                                    <span class="dtc-status-badge is-neutral" style="font-weight:700; font-size:11px;">
                                        {{ $subject->subject_code }}
                                    </span>
                                </td>
                                <td style="padding:10px 16px; vertical-align:middle; color:var(--dtc-text); font-weight:500;">
                                    {{ $subject->subject_name }}
                                </td>
                                <td style="padding:10px 16px; vertical-align:middle; text-align:center; color:var(--dtc-text); font-weight:600;">
                                    {{ rtrim(rtrim(number_format($subject->units ?? 3, 1), '0'), '.') }}
                                </td>
                                <td style="padding:10px 16px; vertical-align:middle; text-align:center;">
                                    @if($latestEnrollment && $latestEnrollment->status === 'approved' && $latestEnrollment->is_paid)
                                        <span class="dtc-status-badge is-success" style="font-size:10.5px;">
                                            <i data-lucide="check" style="width:10px;height:10px;"></i> Enrolled
                                        </span>
                                    @elseif($latestEnrollment && $latestEnrollment->status === 'pending')
                                        <span class="dtc-status-badge is-warning" style="font-size:10.5px;">
                                            <i data-lucide="clock" style="width:10px;height:10px;"></i> Pending
                                        </span>
                                    @elseif($latestEnrollment && $latestEnrollment->status === 'approved' && !$latestEnrollment->is_paid && $latestEnrollment->payment && $latestEnrollment->payment->status === 'pending')
                                        <span class="dtc-status-badge is-warning" style="font-size:10.5px;">Payment Verification</span>
                                    @elseif($latestEnrollment && $latestEnrollment->status === 'approved' && !$latestEnrollment->is_paid && $latestEnrollment->payment && $latestEnrollment->payment->status === 'rejected')
                                        <span class="dtc-status-badge is-danger" style="font-size:10.5px;">Payment Rejected</span>
                                    @elseif($latestEnrollment && $latestEnrollment->status === 'approved' && !$latestEnrollment->is_paid)
                                        <span class="dtc-status-badge is-warning" style="font-size:10.5px;">Awaiting Payment</span>
                                    @elseif($latestEnrollment && $latestEnrollment->status === 'rejected')
                                        <span class="dtc-status-badge is-danger" style="font-size:10.5px;">Rejected</span>
                                    @else
                                        <span class="dtc-status-badge is-neutral" style="font-size:10.5px;">—</span>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                        @php $totalUnits = $subjects->sum(fn($s) => $s->units ?? 3); @endphp
                        <tfoot>
                            <tr style="background:var(--dtc-surface-soft);">
                                <td colspan="2" style="padding:10px 16px; font-weight:700; font-size:12px; color:var(--dtc-text); text-align:right;">
                                    Total Units
                                </td>
                                <td style="padding:10px 16px; font-weight:800; font-size:13px; color:var(--dtc-primary); text-align:center;">
                                    {{ rtrim(rtrim(number_format($totalUnits, 1), '0'), '.') }}
                                </td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                @else
                <div class="text-center py-4 u-muted">
                    <i data-lucide="book-open" class="mb-2" style="width:2em;height:2em"></i>
                    <p class="u-text-sm">No subjects enrolled yet.</p>
                    @if(!$latestEnrollment)
                        <a href="{{ route('portal.enrollment.create') }}"
                           class="btn btn-primary btn-sm">Enroll Now</a>
                    @endif
                </div>
                @endif
            </div>
        </div>

        {{-- Next Steps --}}
        @if($nextSteps)
        <div class="card mb-3">
            <div class="card-header font-weight-bold">Next Steps</div>
            <div class="card-body p-0">
                @foreach ($nextSteps as $step)
                @if($step['isPayment'] ?? false)
                    <button type="button"
                       class="dtc-payment-btn dtc-next-step-row {{ $step['active'] ? 'is-active' : '' }}"
                       data-url="{{ $step['url'] }}" style="width:100%; border:0; text-align:left;">
                        <div style="width:44px; height:44px; border-radius:12px; flex-shrink:0;
                                    background:{{ $step['active'] ? 'var(--dtc-primary)' : 'var(--dtc-surface-soft)' }};
                                    display:flex; align-items:center; justify-content:center;">
                            <i data-lucide="{{ $step['icon'] }}" style="font-size:18px;
                                      color:{{ $step['active'] ? '#fff' : 'var(--dtc-text-muted)' }};"></i>
                        </div>
                        <div  class="u-flex-1">
                            <div style="font-size:13px; font-weight:600;
                                        color:{{ $step['active'] ? 'var(--dtc-on-primary-soft)' : 'var(--dtc-text)' }};">
                                {{ $step['label'] }}
                            </div>
                            <div  class="u-text-xxs-secondary">
                                {{ $step['desc'] }}
                            </div>
                        </div>
                        <i data-lucide="chevron-right" style="color:{{ $step['active'] ? 'var(--dtc-on-primary-soft)' : 'var(--dtc-text-muted)' }};
                                  font-size:12px;"></i>
                    </button>
                @else
                    <a href="{{ $step['url'] }}" class="dtc-next-step-row {{ $step['active'] ? 'is-active' : '' }}">
                        <div style="width:44px; height:44px; border-radius:12px; flex-shrink:0;
                                    background:{{ $step['active'] ? 'var(--dtc-primary)' : 'var(--dtc-surface-soft)' }};
                                    display:flex; align-items:center; justify-content:center;">
                            <i data-lucide="{{ $step['icon'] }}" style="font-size:18px;
                                      color:{{ $step['active'] ? '#fff' : 'var(--dtc-text-muted)' }};"></i>
                        </div>
                        <div  class="u-flex-1">
                            <div style="font-size:13px; font-weight:600;
                                        color:{{ $step['active'] ? 'var(--dtc-on-primary-soft)' : 'var(--dtc-text)' }};">
                                {{ $step['label'] }}
                            </div>
                            <div  class="u-text-xxs-secondary">
                                {{ $step['desc'] }}
                            </div>
                        </div>
                        <i data-lucide="chevron-right" style="color:{{ $step['active'] ? 'var(--dtc-on-primary-soft)' : 'var(--dtc-text-muted)' }};
                                  font-size:12px;"></i>
                    </a>
                @endif
                @endforeach
            </div>
        </div>
        @endif

    </div>

    {{-- RIGHT: Announcements + Quick Actions --}}
    <div class="col-lg-4">

        {{-- Important Reminder --}}
        @if($latestEnrollment && $latestEnrollment->status === 'approved' && !$latestEnrollment->is_paid)
        <div class="card mb-3 alert alert-warning"
             style="border-radius:16px;">
            <div class="card-body p-3">
                <div style="display:flex; align-items:center; gap:10px; margin-bottom:10px;">
                    <div class="dtc-icon-swatch is-warning" style="width:32px; height:32px;">
                        <i data-lucide="bell" style="font-size:14px;"></i>
                    </div>
                    <span style="font-size:13px; font-weight:700;">
                        Important Reminder
                    </span>
                </div>
                <p style="font-size:12px; opacity:0.85; margin:0 0 12px; line-height:1.6;">
                    Please complete your enrollment process by paying the ₱{{ number_format($fee, 2) }} fee at the Cashier's Office.
                </p>
                <button type="button" class="btn btn-warning btn-sm btn-block dtc-payment-btn u-text-xxs"
                        data-url="{{ route('portal.enrollment.payment-info', $latestEnrollment) }}"
                        >
                    Proceed to Payment →
                </button>
            </div>
        </div>
        @endif

        {{-- Announcements (Recent Notifications) --}}
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
                <a href="{{ route('portal.enrollment.index') }}" class="quick-action-btn">
                    <i data-lucide="file-text"></i> My Application
                </a>
                <a href="{{ route('portal.appointments.create') }}" class="quick-action-btn">
                    <i data-lucide="calendar-check"></i> Book Appointment
                </a>
                @if($latestEnrollment && $latestEnrollment->status === 'approved' && $latestEnrollment->is_paid)
                <a href="{{ route('portal.cor.show') }}" class="quick-action-btn">
                    <i data-lucide="scroll-text"></i> Certificate of Registration
                </a>
                @endif
                <a href="{{ route('notifications.index') }}" class="quick-action-btn">
                    <i data-lucide="bell"></i> Notifications
                    @if($unreadCount > 0)
                        <span class="badge badge-danger ml-auto" style="font-size:10px;">{{ $unreadCount }}</span>
                    @endif
                </a>
            </div>
        </div>

        {{-- Next Appointment --}}
        @if($nextAppointment)
        <div class="card u-primary-gradient-btn"
             >
            <div class="card-body p-3">
                <div style="font-size:11px; font-weight:600; opacity:0.75; margin-bottom:8px;
                            text-transform:uppercase; letter-spacing:0.5px;">
                    Upcoming Appointment
                </div>
                <div style="display:flex; align-items:center; gap:14px;">
                    <div style="background:rgba(255,255,255,0.15); border-radius:12px;
                                padding:10px 14px; text-align:center; min-width:52px;">
                        <div style="font-size:22px; font-weight:800; line-height:1; color:#FFC72C;">
                            {{ \Carbon\Carbon::parse($nextAppointment->slot->date)->format('d') }}
                        </div>
                        <div style="font-size:10px; font-weight:600; opacity:0.8;">
                            {{ \Carbon\Carbon::parse($nextAppointment->slot->date)->format('M') }}
                        </div>
                    </div>
                    <div>
                        <div  class="u-text-sm-bold">
                            {{ ucfirst($nextAppointment->document_type) }}
                        </div>
                        <div style="font-size:11px; opacity:0.75; margin-top:2px;">
                            <i data-lucide="clock" class="mr-1"></i>
                            {{ \Carbon\Carbon::parse($nextAppointment->slot->start_time)->format('h:i A') }}
                        </div>
                        <div style="font-size:11px; opacity:0.75; margin-top:2px;">
                            <i data-lucide="map-pin" class="mr-1"></i> Registrar Office
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @endif

    </div>
</div>

@include('student.enrollment._payment-modal')

<div class="modal fade" id="dashboardAvatarCropModal" tabindex="-1" role="dialog" aria-modal="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width:520px;">
        <div class="modal-content ps-crop-modal">
            <div class="modal-header ps-crop-header">
                <div class="d-flex align-items-center gap-2">
                    <div class="ps-crop-header-icon"><i data-lucide="image" style="width:16px;height:16px;"></i></div>
                    <h6 class="modal-title mb-0">Crop Profile Photo</h6>
                </div>
                <button type="button" class="close ps-crop-close" data-dismiss="modal" aria-label="Close">
                    <i data-lucide="x" style="width:18px;height:18px;"></i>
                </button>
            </div>
            <div class="modal-body ps-crop-body">
                <div class="ps-crop-hint"><i data-lucide="move" style="width:13px;height:13px;"></i> Drag to reposition, scroll to zoom, or rotate the image.</div>
                <div class="ps-crop-container">
                    <img id="dashboardCropImage" src="" alt="Crop profile photo" style="max-width:100%;display:block;">
                </div>
            </div>
            <div class="modal-footer ps-crop-footer">
                <button type="button" class="btn ps-crop-btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn ps-crop-btn-rotate" id="dashboardCropRotateLeft" title="Rotate left"><i data-lucide="rotate-ccw" style="width:14px;height:14px;"></i></button>
                <button type="button" class="btn ps-crop-btn-rotate" id="dashboardCropRotateRight" title="Rotate right"><i data-lucide="rotate-cw" style="width:14px;height:14px;"></i></button>
                <button type="button" class="btn ps-crop-btn-primary" id="dashboardCropApply">Crop Photo</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="dashboardAvatarConfirmModal" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="dashboardAvatarConfirmTitle">
    <div class="modal-dialog modal-dialog-centered dtc-avatar-confirm-dialog" role="document">
        <div class="modal-content dtc-avatar-confirm-card">
            <div class="modal-body text-center">
                <img id="dashboardAvatarConfirmPreview" class="dtc-avatar-confirm-preview" src="" alt="Cropped profile photo preview">
                <h5 id="dashboardAvatarConfirmTitle" class="dtc-avatar-confirm-title">Use this profile photo?</h5>
                <p class="dtc-avatar-confirm-copy">This will replace your current profile photo.</p>
                <div class="dtc-avatar-confirm-actions">
                    <button type="button" class="btn dtc-avatar-confirm-cancel" data-dismiss="modal">Cancel</button>
                    <button type="button" class="btn dtc-avatar-confirm-apply" id="dashboardAvatarConfirmApply">Save photo</button>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection

@section('css')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.css">
<link rel="stylesheet" href="{{ asset('css/profile.css') }}">
<style>
/* The "active" step marker uses a pale yellow tint that reads fine on a
   white card but looks like a stray light patch on a dark card. */
body.dtc-dark [data-step-state="active"] {
    background: rgba(255, 199, 44, 0.15) !important;
}
.dtc-welcome-layout {
    display: flex;
    align-items: center;
    gap: 20px;
}
.dtc-welcome-avatar-wrap {
    position: relative;
    flex: 0 0 64px;
    width: 64px;
    height: 64px;
}
.dtc-welcome-avatar-link {
    display: block;
    width: 100%;
    height: 100%;
    border-radius: 50%;
    line-height: 0;
}
.dtc-welcome-avatar-image,
.dtc-welcome-avatar-initials {
    width: 100%;
    height: 100%;
    border-radius: 50%;
    object-fit: cover;
}
.dtc-welcome-avatar-initials {
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    background: linear-gradient(135deg, #0F4CDB, #1a5feb);
    font-size: 26px;
    font-weight: 800;
}
.dtc-welcome-copy {
    flex: 1 1 0;
    min-width: 0;
}
.dtc-avatar-camera-badge {
    position: absolute;
    right: 0;
    bottom: 0;
    width: 22px;
    height: 22px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: var(--dtc-text-secondary);
    background: var(--dtc-surface-soft);
    border: 2px solid var(--dtc-surface);
    border-radius: 50%;
    box-shadow: 0 1px 4px rgba(15, 23, 42, .25);
    padding: 0;
    cursor: pointer;
    line-height: 1;
}
.dtc-avatar-camera-badge:hover {
    color: var(--dtc-text);
    filter: brightness(.96);
}
@media (max-width: 576px) {
    .dtc-welcome-card .card-body {
        padding: 16px !important;
    }
    .dtc-welcome-layout {
        display: flex;
        align-items: flex-start;
        gap: 12px;
    }
    .dtc-welcome-avatar-wrap {
        flex-basis: 52px;
        width: 52px;
        height: 52px;
    }
    .dtc-avatar-camera-badge {
        width: 20px;
        height: 20px;
    }
    .dtc-welcome-title {
        font-size: clamp(1rem, 5vw, 1.25rem);
    }
    .dtc-welcome-text {
        font-size: 12px !important;
        line-height: 1.5;
        overflow-wrap: anywhere;
    }
    .dtc-avatar-confirm-dialog { margin: .5rem; }
    .dtc-avatar-confirm-actions { flex-direction: column-reverse; }
    .dtc-avatar-confirm-actions .btn { width: 100%; }
}
</style>
@endsection

@section('js')
<script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const trigger = document.querySelector('[data-avatar-picker-trigger]');
    const input = document.getElementById('dashboardAvatarInput');
    const form = document.getElementById('dashboardAvatarForm');
    const cropModal = $('#dashboardAvatarCropModal');
    const confirmModal = $('#dashboardAvatarConfirmModal');
    const cropImage = document.getElementById('dashboardCropImage');
    const confirmPreview = document.getElementById('dashboardAvatarConfirmPreview');
    let cropper = null;
    let croppedFile = null;
    let previewUrl = null;

    if (!trigger || !input || !form || typeof Cropper === 'undefined') return;

    trigger.addEventListener('click', function () {
        input.click();
    });

    input.addEventListener('change', function () {
        if (!input.files || !input.files.length) return;
        const selectedFile = input.files[0];
        const reader = new FileReader();
        reader.onload = function (event) {
            cropImage.src = event.target.result;
            cropModal.modal('show');
        };
        reader.readAsDataURL(selectedFile);
    });

    cropModal.on('shown.bs.modal', function () {
        if (cropper) cropper.destroy();
        cropper = new Cropper(cropImage, {
            aspectRatio: 1,
            viewMode: 1,
            dragMode: 'move',
            autoCropArea: .85,
            guides: false,
            center: true,
            highlight: false,
            cropBoxMovable: true,
            cropBoxResizable: true,
            toggleDragModeOnDblclick: false,
        });
        if (window.lucide) window.lucide.createIcons();
    });

    cropModal.on('hidden.bs.modal', function () {
        if (cropper) {
            cropper.destroy();
            cropper = null;
        }
        if (!croppedFile) {
            input.value = '';
        } else {
            previewUrl = URL.createObjectURL(croppedFile);
            confirmPreview.src = previewUrl;
            confirmModal.modal('show');
        }
    });

    document.getElementById('dashboardCropRotateLeft').addEventListener('click', function () {
        if (cropper) cropper.rotate(-90);
    });
    document.getElementById('dashboardCropRotateRight').addEventListener('click', function () {
        if (cropper) cropper.rotate(90);
    });

    document.getElementById('dashboardCropApply').addEventListener('click', function () {
        if (!cropper) return;
        const canvas = cropper.getCroppedCanvas({
            width: 400,
            height: 400,
            imageSmoothingEnabled: true,
            imageSmoothingQuality: 'high',
        });
        canvas.toBlob(function (blob) {
            if (!blob) return;
            croppedFile = new File([blob], 'avatar.jpg', { type: 'image/jpeg' });
            cropModal.modal('hide');
        }, 'image/jpeg', .92);
    });

    confirmModal.on('hidden.bs.modal', function () {
        if (previewUrl) URL.revokeObjectURL(previewUrl);
        previewUrl = null;
        confirmPreview.removeAttribute('src');
        croppedFile = null;
        input.value = '';
    });

    document.getElementById('dashboardAvatarConfirmApply').addEventListener('click', function () {
        if (!croppedFile) return;
        const transfer = new DataTransfer();
        transfer.items.add(croppedFile);
        input.files = transfer.files;

        if (form.requestSubmit) {
            form.requestSubmit();
        } else {
            form.submit();
        }
    });
});
</script>
@endsection
