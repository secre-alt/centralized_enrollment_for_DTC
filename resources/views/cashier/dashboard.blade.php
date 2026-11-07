@extends('adminlte::page')
@section('plugins.ChartJs', true)
@include('partials.navbar')

@section('title', __('dashboard.cashier_title'))

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h4 class="mb-0 font-weight-bold">{{ __('dashboard.cashier_title') }}</h4>
            <p class="mb-0 u-text-secondary-sm" >{{ __('dashboard.manage_payments_receipts') }}</p>
        </div>
        <a href="{{ route('cashier.payments.index') }}" class="dtc-btn dtc-btn-primary dtc-header-btn">
            <i data-lucide="banknote"></i>
            <span class="dtc-header-btn-label">{{ __('dashboard.process_payments') }}</span>
        </a>
    </div>
@endsection

@push('css')
<link rel="stylesheet" href="{{ asset('css/dashboard-summaries.css') }}">
@endpush
@push('css')
<link rel="stylesheet" href="{{ asset('css/dashboard-cards.css') }}">
@endpush
@section('content')

{{-- KPI ROW --}}
<div class="dashboard-kpis"><div class="dtc-kpi-grid cashier-kpis">
<div class="kpi-collection">
        <x-dtc.stat-card
            icon="circle-dollar-sign" color="primary" :featured="true"
            :label="__('dashboard.today_collection')" value="₱{{ number_format($todayCollection, 2) }}"
            />
    </div>
<div class="kpi-payments">
        <x-dtc.stat-card
            icon="clock" color="warning"
            :label="__('dashboard.pending_payments')" value="{{ $pendingPayments }}"
            href="{{ route('cashier.payments.index') }}" :link-text="__('dashboard.review_payments')" />
    </div>
<div class="kpi-paid">
        <x-dtc.stat-card
            icon="check-circle" color="success"
            :label="__('dashboard.paid_this_month')" value="{{ $paidThisMonth }}"
            :note="__('dashboard.transactions')" />
    </div>
<div class="kpi-walkin">
        <x-dtc.stat-card
            icon="banknote" color="info"
            :label="__('dashboard.walkin_payments')" value="{{ $walkinPayments }}"
            :note="__('dashboard.this_month')" />
    </div>
</div></div>

@include('partials.staff-dashboard-welcome', ['staffRoleLabel' => __('ui.navigation.labels.Cashier Dashboard'), 'staffWelcomeMessage' => __('dashboard.cashier_welcome')])

{{-- MAIN ROW --}}
<div class="row">

    {{-- Recent Payments Table --}}
    <div class="col-lg-8 mb-3">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span class="font-weight-bold">{{ __('dashboard.recent_payments') }}</span>
                <a href="{{ route('cashier.payments.index') }}"
                   style="font-size:12px; color:var(--dtc-primary); text-decoration:none;">{{ __('dashboard.view_all') }}</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
<table class="dashboard-summary-table table mb-0">
                    <thead>
                        <tr>
                            <th>{{ __('dashboard.receipt_number') }}</th>
                            <th>{{ __('dashboard.payer') }}</th>
                            <th>{{ __('dashboard.amount') }}</th>
                            <th>{{ __('dashboard.date') }}</th>
                            <th>{{ __('dashboard.status') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($recentPayments as $payment)
                        <tr>
                            <td data-summary-label="{{ __('dashboard.receipt_number') }}" style="font-size:12px; font-weight:600; color:var(--dtc-primary);">
                                {{ $payment->receipt_no }}
                            </td>
                            <td data-summary-label="{{ __('dashboard.payer') }}">
                                <div  class="u-flex-center-gap-10">
                                    <x-dtc.user-avatar :user="$payment->enrollment->user" style="width:32px; height:32px; border-radius:50%;
                                                background:linear-gradient(135deg,#22C55E,#059669);
                                                display:flex; align-items:center; justify-content:center;
                                                color:#fff; font-weight:700; font-size:12px; flex-shrink:0;" />
                                    <span  class="u-text-sm">{{ $payment->enrollment->user->name }}</span>
                                </div>
                            </td>
                            <td data-summary-label="{{ __('dashboard.amount') }}" style="font-size:13px; font-weight:600; color:var(--dtc-success);">
                                ₱{{ number_format($payment->amount, 2) }}
                            </td>
                            <td data-summary-label="{{ __('dashboard.date') }}" class="u-text-xxs-secondary">
                                {{ $payment->paid_at->format('M d, Y') }}
                            </td>
                            <td data-summary-label="{{ __('dashboard.status') }}">
                                @if ($payment->refundIsPending())
                                    <x-dtc.status-badge status="Refund Follow-up" />
                                @elseif ($payment->refundIsComplete())
                                    <x-dtc.status-badge status="Refunded" />
                                @else
                                    <x-dtc.status-badge status="Paid" />
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="text-center py-4" style="color:var(--dtc-text-muted); font-size:13px;">
                                {{ __('dashboard.no_payments') }}
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
                </div>
            </div>
            @if ($recentPayments->isNotEmpty())
                <div class="card-footer u-panel-footer">
                    <a href="{{ route('cashier.payments.index') }}" class="u-text-sm" style="color:var(--dtc-primary); text-decoration:none;">{{ __('dashboard.view_all_payments') }}</a>
                </div>
            @endif
        </div>
    </div>

    {{-- Right Column --}}
    <div class="col-lg-4 mb-3">

        {{-- Collection Overview Donut --}}
        <div class="card mb-3">
            <div class="card-header font-weight-bold">{{ __('dashboard.collection_overview') }}</div>
            <div class="card-body">
                <div style="position:relative; width:160px; height:160px; margin:0 auto 16px;">
                    <canvas id="collectionChart"></canvas>
                    <div style="position:absolute; top:50%; left:50%;
                                transform:translate(-50%,-50%); text-align:center;">
                        <div style="font-size:16px; font-weight:700;">
                            ₱{{ number_format($totalRevenue, 0) }}
                        </div>
                        <div style="font-size:10px; color:var(--dtc-text-secondary); font-weight:600;">{{ __('dashboard.total') }}</div>
                    </div>
                </div>
                <div  class="u-text-sm">
                    <div class="d-flex justify-content-between mb-2">
                        <span><span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:#0F4CDB;margin-right:6px;"></span>{{ __('dashboard.paid') }}</span>
                        <span class="font-weight-bold">{{ $totalPaid }}</span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span><span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:#FFC72C;margin-right:6px;"></span>{{ __('dashboard.pending') }}</span>
                        <span class="font-weight-bold">{{ $pendingPayments }}</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Quick Actions --}}
        <div class="card">
            <div class="card-header font-weight-bold">{{ __('dashboard.quick_actions') }}</div>
            <div class="card-body p-3">
                <a href="{{ route('cashier.payments.index') }}" class="quick-action-btn">
                    <i data-lucide="banknote"></i> {{ __('dashboard.process_payments') }}
                </a>
                <a href="{{ route('notifications.index') }}" class="quick-action-btn">
                    <i data-lucide="bell"></i> {{ __('dashboard.notifications') }}
                </a>
            </div>
        </div>

    </div>
</div>

{{-- Today's Summary --}}
<div class="row">
    <div class="col-12 mb-3">
        <div class="card">
            <div class="card-header font-weight-bold">{{ __('dashboard.today_summary') }}</div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-6 col-md-3 mb-3 mb-md-0">
                        <div style="font-size:22px; font-weight:700; color:var(--dtc-primary);">{{ $totalTransactions }}</div>
                        <div class="u-text-xxs-secondary-bold">{{ __('dashboard.total_transactions') }}</div>
                    </div>
                    <div class="col-6 col-md-3 mb-3 mb-md-0">
                        <div style="font-size:22px; font-weight:700; color:var(--dtc-success);">{{ $totalPaid }}</div>
                        <div class="u-text-xxs-secondary-bold">{{ __('dashboard.paid') }}</div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div style="font-size:22px; font-weight:700; color:var(--dtc-warning);">{{ $pendingPayments }}</div>
                        <div class="u-text-xxs-secondary-bold">{{ __('dashboard.pending') }}</div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div style="font-size:22px; font-weight:700;">₱{{ number_format($totalRevenue, 2) }}</div>
                        <div class="u-text-xxs-secondary-bold">{{ __('dashboard.total_amount') }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection

@section('js')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const ctx = document.getElementById('collectionChart');
    if (ctx) {
        const chartTheme = getDtcChartTheme();
        registerDtcChart(new Chart(ctx.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: [@json(__('dashboard.paid')), @json(__('dashboard.pending'))],
                datasets: [{
                    data: [{{ $totalPaid }}, {{ $pendingPayments }}],
                    backgroundColor: ['#0F4CDB', '#FFC72C'],
                    borderWidth: 0,
                    hoverOffset: 4,
                }]
            },
            options: {
                responsive: true,
                cutout: '70%',
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: chartTheme.tooltipBg,
                        titleColor: chartTheme.tooltipText,
                        bodyColor: chartTheme.tooltipText,
                    }
                }
            }
        }));
    }
});
</script>
@endsection
