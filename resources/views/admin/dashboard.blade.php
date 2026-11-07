@extends('adminlte::page')
@section('plugins.ChartJs', true)
@include('partials.navbar')


@section('title', __('dashboard.admin_title'))

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h4 class="mb-0 font-weight-bold">{{ __('dashboard.admin_title') }}</h4>
            <p class="mb-0 u-text-secondary-sm" >{{ __('dashboard.system_overview') }}</p>
        </div>
        <div>
            <a href="{{ route('admin.users.index') }}" class="dtc-btn dtc-btn-primary dtc-header-btn">
                <i data-lucide="plus"></i>
                <span class="dtc-header-btn-label">{{ __('dashboard.add_user') }}</span>
            </a>
        </div>
    </div>
@endsection

@push('css')
<link rel="stylesheet" href="{{ asset('css/dashboard-cards.css') }}">
@endpush
@section('content')

{{-- KPI ROW --}}
<div class="dashboard-kpis"><div class="dtc-kpi-grid admin-kpis">
<div class="kpi-revenue">
        <x-dtc.stat-card
            icon="circle-dollar-sign" color="primary" :featured="true"
            :label="__('dashboard.total_revenue')" value="₱{{ number_format($totalRevenue, 2) }}"
            :metrics="[__('dashboard.transactions') => number_format($totalPaid), __('dashboard.average_transaction') => '₱'.number_format($totalPaid > 0 ? $totalRevenue / $totalPaid : 0, 2)]" />
    </div>
<div class="kpi-students">
        <x-dtc.stat-card
            icon="graduation-cap" color="primary"
            :label="__('dashboard.students')" value="{{ number_format($totalStudents) }}"
            href="{{ route('admin.users.index') }}" :link-text="__('dashboard.view_students')" />
    </div>
<div class="kpi-enrollments">
        <x-dtc.stat-card
            icon="file-text" color="warning"
            :label="__('dashboard.pending_enrollments')" value="{{ number_format($pendingEnrollments) }}"
            :note="__('dashboard.awaiting_approval')" />
    </div>
<div class="kpi-visits">
        <x-dtc.stat-card
            icon="calendar-check" color="success"
            :label="__('dashboard.appointments_today')" value="{{ number_format($appointmentsToday) }}"
            :note="__('dashboard.scheduled_visits')" />
    </div>
<div class="kpi-alumni">
        <x-dtc.stat-card
            icon="award" color="info"
            :label="__('dashboard.alumni')" value="{{ number_format($totalAlumni) }}"
            :note="__('dashboard.registered_alumni')" />
    </div>
</div></div>

@include('partials.staff-dashboard-welcome', ['staffRoleLabel' => __('dashboard.administrator'), 'staffWelcomeMessage' => __('dashboard.administrator_welcome')])

{{-- CHARTS ROW --}}
<div class="row">

    {{-- Enrollment Overview Chart --}}
    <div class="col-lg-7 mb-3">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span class="font-weight-bold">{{ __('dashboard.enrollment_overview') }}</span>
                <span class="badge badge-primary" style="font-size:11px;">{{ now()->year }}</span>
            </div>
            <div class="card-body">
                <canvas id="enrollmentChart" height="110"></canvas>
            </div>
        </div>
    </div>

    {{-- Payment Statistics Donut --}}
    <div class="col-lg-5 mb-3">
        <div class="card h-100">
            <div class="card-header font-weight-bold">{{ __('dashboard.payment_statistics') }}</div>
            <div class="card-body">
                <div class="d-flex justify-content-center mb-3">
                    <div style="position:relative; width:180px; height:180px;">
                        <canvas id="paymentChart"></canvas>
                        <div style="position:absolute; top:50%; left:50%; transform:translate(-50%,-50%); text-align:center;">
                            <div style="font-size:20px; font-weight:700;">₱{{ number_format($totalRevenue, 0) }}</div>
                            <div style="font-size:10px; color:var(--dtc-text-secondary); font-weight:600;">{{ __('dashboard.total') }}</div>
                        </div>
                    </div>
                </div>
                <div class="d-flex flex-column gap-2 u-gap-8" >
                    <div class="d-flex justify-content-between align-items-center u-text-sm" >
                        <span><span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:#0F4CDB;margin-right:6px;"></span>{{ __('dashboard.paid') }}</span>
                        <span class="font-weight-bold">{{ $totalPaid }}</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center u-text-sm" >
                        <span><span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:#FFC72C;margin-right:6px;"></span>{{ __('dashboard.unpaid_approved') }}</span>
                        <span class="font-weight-bold">{{ $totalUnpaid }}</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center u-text-sm" >
                        <span><span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:#E2E8F0;margin-right:6px;"></span>{{ __('dashboard.pending') }}</span>
                        <span class="font-weight-bold">{{ $totalPending }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

{{-- ══ BOTTOM ROW ═══════════════════════════════════════════════════════ --}}
<div class="row">

    {{-- Recent Activities --}}
    <div class="col-lg-5 mb-3">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span class="font-weight-bold">{{ __('dashboard.recent_activities') }}</span>
                <a href="{{ route('notifications.index') }}" style="font-size:12px; color:var(--dtc-primary);">{{ __('dashboard.view_all') }}</a>
            </div>
            <div class="card-body p-3">
                @forelse ($recentActivities as $activity)
                    @php
                        $__activityTone = match ($activity->type) {
                            'success' => 'success',
                            'danger'  => 'danger',
                            'warning' => 'warning',
                            default   => 'info',
                        };
                        $__activityIcon = match ($activity->type) {
                            'success' => 'check',
                            'danger'  => 'x',
                            'warning' => 'alert-triangle',
                            default   => 'info',
                        };
                    @endphp
                <div class="activity-item">
                    <div class="activity-icon dtc-icon-swatch is-{{ $__activityTone }}">
                        <i data-lucide="{{ $__activityIcon }}"></i>
                    </div>
                    <div class="flex-grow-1">
                        <div class="activity-text">{{ $activity->title }}</div>
                        <div class="activity-time">{{ $activity->created_at->diffForHumans() }}</div>
                    </div>
                </div>
                @empty
                <div class="text-center py-4 u-muted" >
                    <i data-lucide="bell-off" class="mb-2" style="width:2em;height:2em"></i>
                    <p  class="u-text-sm">{{ __('dashboard.no_recent_activities') }}</p>
                </div>
                @endforelse
            </div>
        </div>
    </div>

    {{-- Upcoming Appointments --}}
    <div class="col-lg-4 mb-3">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span class="font-weight-bold">{{ __('dashboard.upcoming_appointments') }}</span>
                <a href="{{ route('registrar.appointments.index') }}" style="font-size:12px; color:var(--dtc-primary);">{{ __('dashboard.view_all') }}</a>
            </div>
            <div class="card-body p-0">
                @forelse ($upcomingAppointments as $appt)
                <div style="padding:14px 20px; border-bottom:1px solid var(--dtc-border-soft); display:flex; align-items:center; gap:12px;">
                    <div class="dtc-date-chip" style="min-width:50px; padding:8px 10px;">
                        {{ \Carbon\Carbon::parse($appt->slot->date)->format('M') }}<br>
                        <span class="dtc-date-chip-day" style="font-size:18px;">{{ \Carbon\Carbon::parse($appt->slot->date)->format('d') }}</span>
                    </div>
                    <div>
                        <div  class="u-text-sm-bold">{{ $appt->user->name }}</div>
                        <div  class="u-text-xs-secondary">
                            {{ ucfirst($appt->document_type) }} •
                            {{ \Carbon\Carbon::parse($appt->slot->start_time)->format('h:i A') }}
                        </div>
                    </div>
                </div>
                @empty
                <div class="text-center py-4 u-muted" >
                    <i data-lucide="calendar" class="mb-2" style="width:2em;height:2em"></i>
                    <p  class="u-text-sm">{{ __('dashboard.no_upcoming_appointments') }}</p>
                </div>
                @endforelse
            </div>
        </div>
    </div>

    {{-- Quick Actions --}}
    <div class="col-lg-3 mb-3">
        <div class="card h-100">
            <div class="card-header font-weight-bold">{{ __('dashboard.quick_actions') }}</div>
            <div class="card-body p-3">
                <a href="{{ route('admin.users.index') }}" class="quick-action-btn">
                    <i data-lucide="user-plus"></i> {{ __('dashboard.add_user') }}
                </a>
                <a href="{{ route('registrar.enrollments.index') }}" class="quick-action-btn">
                    <i data-lucide="file-text"></i> {{ __('dashboard.manage_enrollments') }}
                </a>
                <a href="{{ route('registrar.appointments.slots') }}" class="quick-action-btn">
                    <i data-lucide="calendar-plus"></i> {{ __('dashboard.manage_slots') }}
                </a>
                <a href="{{ route('cashier.payments.index') }}" class="quick-action-btn">
                    <i data-lucide="banknote"></i> {{ __('dashboard.process_payments') }}
                </a>
                <a href="{{ route('admin.users.index') }}" class="quick-action-btn">
                    <i data-lucide="users"></i> {{ __('dashboard.all_users') }}
                </a>
                <a href="{{ route('admin.reports.enrollment') }}" class="quick-action-btn" target="_blank">
                <i data-lucide="file-type"></i> {{ __('dashboard.download_enrollment_report') }}
                </a>
                <a href="{{ route('admin.reports.payment') }}" class="quick-action-btn" target="_blank">
                    <i data-lucide="file-type"></i> {{ __('dashboard.download_payment_report') }}
                </a>
            </div>
        </div>
    </div>

</div>

@endsection

@section('js')
<script>
document.addEventListener('DOMContentLoaded', function () {

    const chartTheme = getDtcChartTheme();

    // -- Enrollment Line Chart ------------------------------------------
    const enrollmentCtx = document.getElementById('enrollmentChart');
    if (enrollmentCtx) {
        registerDtcChart(new Chart(enrollmentCtx.getContext('2d'), {
            type: 'line',
            data: {
                labels: ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'],
                datasets: [{
                label: @json(__('dashboard.dashboard_enrollments')),
                    data: @json($enrollmentData),
                    borderColor: '#0F4CDB',
                    backgroundColor: 'rgba(15, 76, 219, 0.08)',
                    borderWidth: 2.5,
                    pointBackgroundColor: '#0F4CDB',
                    pointBorderColor: chartTheme.pointBorder,
                    pointBorderWidth: 2,
                    pointRadius: 5,
                    fill: true,
                    tension: 0.4,
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false } },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { font: { family: 'Poppins', size: 11 }, color: chartTheme.text }
                    },
                    y: {
                        beginAtZero: true,
                        grid: { color: chartTheme.grid, drawBorder: false },
                        ticks: { font: { family: 'Poppins', size: 11 }, color: chartTheme.text, stepSize: 1 }
                    }
                }
            }
        }));
    }

    // -- Payment Donut Chart --------------------------------------------
    const paymentCtx = document.getElementById('paymentChart');
    if (paymentCtx) {
        registerDtcChart(new Chart(paymentCtx.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: [@json(__('dashboard.paid')), @json(__('dashboard.dashboard_unpaid')), @json(__('dashboard.pending'))],
                datasets: [{
                    data: [{{ $totalPaid }}, {{ $totalUnpaid }}, {{ $totalPending }}],
                    backgroundColor: ['#0F4CDB', '#FFC72C', '#E2E8F0'],
                    borderWidth: 0,
                    hoverOffset: 6,
                }]
            },
            options: {
                responsive: true,
                cutout: '72%',
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: chartTheme.tooltipBg,
                        titleColor: chartTheme.tooltipText,
                        bodyColor: chartTheme.tooltipText,
                        callbacks: {
                            label: ctx => ` ${ctx.label}: ${ctx.raw}`
                        }
                    }
                }
            }
        }));
    }

});
</script>
@endsection
