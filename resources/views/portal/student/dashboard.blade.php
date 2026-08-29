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
    } elseif ($latestEnrollment->is_paid) {
        $appStatusLabel = 'Enrolled ✓';
    } elseif ($latestEnrollment->status === 'approved') {
        $appStatusLabel = 'Approved';
    } elseif ($latestEnrollment->status === 'pending') {
        $appStatusLabel = 'In Progress';
    } else {
        $appStatusLabel = 'Rejected';
    }
@endphp

<!-- TOP STAT CARDS -->
<div class="row mb-3">
    <div class="col-lg-3 col-sm-6 col-12 mb-3">
        <x-dtc.stat-card
            icon="file-text" color="primary"
            label="Application Status" value="{{ $appStatusLabel }}"
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
                    Welcome to Danao Technological College!
                    @if(!$latestEnrollment)
                        Please complete your application and follow the enrollment steps below.
                    @elseif($latestEnrollment->is_paid)
                        Congratulations! You are officially enrolled.
                    @elseif($latestEnrollment->status === 'approved')
                        Your enrollment is approved! Please proceed to the Cashier to pay ₱{{ number_format($fee, 2) }}.
                    @elseif($latestEnrollment->status === 'pending')
                        Your enrollment is under review. Please wait for the Registrar's approval.
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
                        <div data-step-state="{{ $step['done'] ? 'done' : ($step['active'] ? 'active' : 'pending') }}"
                             style="width:36px; height:36px; border-radius:50%;
                                    border:3px solid {{ $step['done'] ? 'var(--dtc-primary)' : ($step['active'] ? 'var(--dtc-accent)' : 'var(--dtc-border)') }};
                                    background:{{ $step['done'] ? 'var(--dtc-primary)' : ($step['active'] ? 'var(--dtc-primary-soft)' : 'var(--dtc-surface)') }};
                                    display:flex; align-items:center; justify-content:center;
                                    font-size:12px; font-weight:700;
                                    color:{{ $step['done'] ? '#fff' : ($step['active'] ? 'var(--dtc-warning)' : 'var(--dtc-text-muted)') }};
                                    margin-bottom:8px;">
                            @if($step['done'])
                                <i data-lucide="check" class="u-text-xxs"></i>
                            @else
                                {{ $i + 1 }}
                            @endif
                        </div>
                        <div style="font-size:11px; font-weight:{{ $step['done'] || $step['active'] ? '700' : '500' }};
                                    color:{{ $step['done'] ? 'var(--dtc-primary)' : ($step['active'] ? 'var(--dtc-warning)' : 'var(--dtc-text-muted)') }};">
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
                @elseif($latestEnrollment && $latestEnrollment->is_paid)
                <div class="alert alert-success" style="margin-top:20px; border-radius:12px;
                            padding:12px 16px; display:flex; align-items:center; gap:12px;">
                    <i data-lucide="check-circle"></i>
                    <div style="font-size:13px; font-weight:600;">
                        All steps completed! You are officially enrolled.
                    </div>
                </div>
                @endif
            </div>
        </div>
        @endif

        {{-- Subject List (as Requirements Checklist) --}}
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span class="font-weight-bold">Enrolled Subjects</span>
                @if($subjects->isNotEmpty())
                    <span style="font-size:12px; color:var(--dtc-primary); font-weight:600;">
                        {{ $subjects->count() }} Subject{{ $subjects->count()> 1 ? 's' : '' }}
                    </span>
                @endif
            </div>
            <div class="card-body p-0">
                @forelse ($subjects as $subject)
                <div style="padding:12px 20px; border-bottom:1px solid var(--dtc-border-soft);
                            display:flex; align-items:center; justify-content:space-between;">
                    <div  class="u-flex-center-gap-12">
                        <div class="dtc-icon-swatch is-primary" style="flex-shrink:0;">
                            <i data-lucide="book" class="u-link-md"></i>
                        </div>
                        <div>
                            <div  class="u-text-sm-bold">
                                {{ $subject->subject_code }}
                            </div>
                            <div  class="u-text-xs-secondary">
                                {{ $subject->subject_name }}
                            </div>
                        </div>
                    </div>
                    @if($latestEnrollment && $latestEnrollment->is_paid)
                        <span class="dtc-status-badge is-success" style="font-weight:600;">
                            <i data-lucide="check" class="mr-1"></i> Enrolled
                        </span>
                    @elseif($latestEnrollment && $latestEnrollment->status === 'pending')
                        <span class="dtc-status-badge is-warning" style="font-weight:600;">
                            <i data-lucide="clock" class="mr-1"></i> Pending
                        </span>
                    @endif
                </div>
                @empty
                <div class="text-center py-4 u-muted" >
                    <i data-lucide="book" class="mb-2" style="width:2em;height:2em"></i>
                    <p  class="u-text-sm">No subjects enrolled yet.</p>
                    @if(!$latestEnrollment)
                        <a href="{{ route('portal.enrollment.create') }}"
                           class="btn btn-primary btn-sm">Enroll Now</a>
                    @endif
                </div>
                @endforelse
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
            <div class="card-body p-0">
                @php
                    $actions = [
                        ['icon' => 'file-text',      'label' => 'My Application',     'sub' => 'View enrollment status',    'url' => route('portal.enrollment.index')],
                        ['icon' => 'calendar-check', 'label' => 'Book Appointment',   'sub' => 'Schedule document pickup',  'url' => route('portal.appointments.create')],
                        ['icon' => 'bell',           'label' => 'Notifications',      'sub' => $unreadCount . ' unread',    'url' => route('notifications.index')],
                    ];
                    if(auth()->user()->hasRole('alumni')) {
                        $actions[] = ['icon' => 'folder-open', 'label' => 'Document Requests', 'sub' => 'Request TOR, Diploma etc.', 'url' => route('portal.documents.index')];
                    }
                @endphp
                @foreach($actions as $action)
                <a href="{{ $action['url'] }}"
                   class="dtc-quick-action-row"
                   style="display:flex; align-items:center; justify-content:space-between;
                          padding:14px 20px; border-bottom:1px solid var(--dtc-border-soft);
                          text-decoration:none; transition:background 0.2s;">
                    <div  class="u-flex-center-gap-12">
                        <div class="dtc-icon-swatch is-primary">
                            <i data-lucide="{{ $action['icon'] }}" class="u-link-md"
                               ></i>
                        </div>
                        <div>
                            <div  class="u-text-sm-bold">
                                {{ $action['label'] }}
                            </div>
                            <div  class="u-text-xs-secondary">
                                {{ $action['sub'] }}
                            </div>
                        </div>
                    </div>
                    <i data-lucide="chevron-right" style="color:var(--dtc-text-muted); font-size:12px;"></i>
                </a>
                @endforeach
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

@endsection

@section('css')
<style>
/* The "active" step marker uses a pale yellow tint that reads fine on a
   white card but looks like a stray light patch on a dark card. */
body.dtc-dark [data-step-state="active"] {
    background: rgba(255, 199, 44, 0.15) !important;
}
</style>
@endsection