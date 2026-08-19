@extends('adminlte::page')
@include('partials.navbar')

@section('title', 'Official Receipt')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h4 class="mb-0 font-weight-bold" style="color:var(--dtc-text);">Official Receipt</h4>
            <p class="mb-0" style="color:var(--dtc-text-secondary); font-size:13px;">Enrollment payment confirmation</p>
        </div>
        <button onclick="window.print()" class="btn btn-primary btn-sm">
            <i class="fas fa-print mr-1"></i> Print Receipt
        </button>
    </div>
@endsection

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-7">

        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        <div class="card" id="printable-receipt">
            {{-- Receipt Header --}}
            <div class="card-body text-center"
                 style="background:linear-gradient(135deg,#0F4CDB,#1a5feb);
                        border-radius:16px 16px 0 0; padding:30px;">
                <div style="width:60px; height:60px; background:rgba(255,255,255,0.2);
                            border-radius:16px; display:flex; align-items:center;
                            justify-content:center; margin:0 auto 12px;">
                    <i class="fas fa-receipt" style="font-size:24px; color:#FFC72C;"></i>
                </div>
                <h4 style="color:#fff; font-weight:800; margin:0;">
                    Danao Technological College
                </h4>
                <p style="color:rgba(255,255,255,0.8); font-size:13px; margin:4px 0 0;">
                    Official Receipt — Enrollment Fee
                </p>
            </div>

            {{-- Receipt Details --}}
            <div class="card-body" style="padding:28px;">

                {{-- Receipt No + Date --}}
                <div style="display:flex; justify-content:space-between;
                            align-items:center; margin-bottom:24px;
                            padding-bottom:16px; border-bottom:2px dashed var(--dtc-border);">
                    <div>
                        <div style="font-size:11px; color:var(--dtc-text-muted); font-weight:600;
                                    text-transform:uppercase; letter-spacing:0.5px;">
                            Receipt No.
                        </div>
                        <div style="font-size:18px; font-weight:800; color:#0F4CDB;">
                            {{ $payment->receipt_no }}
                        </div>
                    </div>
                    <div style="text-align:right;">
                        <div style="font-size:11px; color:var(--dtc-text-muted); font-weight:600;
                                    text-transform:uppercase; letter-spacing:0.5px;">
                            Date Issued
                        </div>
                        <div style="font-size:14px; font-weight:600; color:var(--dtc-text);">
                            {{ $payment->paid_at->format('M d, Y h:i A') }}
                        </div>
                    </div>
                </div>

                {{-- Student Info --}}
                <div style="margin-bottom:20px;">
                    <div style="font-size:11px; color:var(--dtc-text-muted); font-weight:600;
                                text-transform:uppercase; letter-spacing:0.5px; margin-bottom:8px;">
                        Student Information
                    </div>
                    <div style="background:var(--dtc-surface-soft); border-radius:12px; padding:16px;">
                        <div style="display:flex; justify-content:space-between; margin-bottom:8px;">
                            <span style="font-size:13px; color:var(--dtc-text-secondary);">Name</span>
                            <span style="font-size:13px; font-weight:600; color:var(--dtc-text);">
                                {{ $enrollment->user->name }}
                            </span>
                        </div>
                        <div style="display:flex; justify-content:space-between; margin-bottom:8px;">
                            <span style="font-size:13px; color:var(--dtc-text-secondary);">Email</span>
                            <span style="font-size:13px; font-weight:600; color:var(--dtc-text);">
                                {{ $enrollment->user->email }}
                            </span>
                        </div>
                        <div style="display:flex; justify-content:space-between; margin-bottom:8px;">
                            <span style="font-size:13px; color:var(--dtc-text-secondary);">Program</span>
                            <span style="font-size:13px; font-weight:600; color:var(--dtc-text);">
                                {{ $enrollment->program->name }}
                            </span>
                        </div>
                        <div style="display:flex; justify-content:space-between;">
                            <span style="font-size:13px; color:var(--dtc-text-secondary);">Year / Semester</span>
                            <span style="font-size:13px; font-weight:600; color:var(--dtc-text);">
                                Year {{ $enrollment->year_level }} — Semester {{ $enrollment->semester }}
                            </span>
                        </div>
                    </div>
                </div>

                {{-- Payment Details --}}
                <div style="margin-bottom:20px;">
                    <div style="font-size:11px; color:var(--dtc-text-muted); font-weight:600;
                                text-transform:uppercase; letter-spacing:0.5px; margin-bottom:8px;">
                        Payment Details
                    </div>
                    <div style="background:var(--dtc-surface-soft); border-radius:12px; padding:16px;">
                        <div style="display:flex; justify-content:space-between; margin-bottom:8px;">
                            <span style="font-size:13px; color:var(--dtc-text-secondary);">Description</span>
                            <span style="font-size:13px; font-weight:600; color:var(--dtc-text);">
                                Enrollment Fee
                            </span>
                        </div>
                        <div style="display:flex; justify-content:space-between; margin-bottom:8px;">
                            <span style="font-size:13px; color:var(--dtc-text-secondary);">Payment Method</span>
                            <span style="font-size:13px; font-weight:600; color:var(--dtc-text);">
                                @if ($payment->isWalkIn())
                                    Walk-in / Cash
                                @else
                                    GCash
                                    @if ($payment->reference_number)
                                        <span style="color:var(--dtc-text-secondary); font-size:11px;">/ Ref: {{ $payment->reference_number }}</span>
                                    @endif
                                @endif
                            </span>
                        </div>
                        <div style="display:flex; justify-content:space-between; margin-bottom:8px;">
                            <span style="font-size:13px; color:var(--dtc-text-secondary);">Processed by</span>
                            <span style="font-size:13px; font-weight:600; color:var(--dtc-text);">
                                {{ $payment->cashier->name ?? '—' }}
                            </span>
                        </div>
                    </div>
                </div>

                {{-- Total --}}
                <div style="background:linear-gradient(135deg,#0F4CDB,#1a5feb);
                            border-radius:12px; padding:18px 20px;
                            display:flex; justify-content:space-between;
                            align-items:center; margin-bottom:20px;">
                    <span style="font-size:14px; font-weight:600; color:rgba(255,255,255,0.85);">
                        Total Amount Paid
                    </span>
                    <span style="font-size:24px; font-weight:800; color:#FFC72C;">
                        ₱{{ number_format($payment->amount, 2) }}
                    </span>
                </div>

                {{-- Status --}}
                <div style="text-align:center; padding:16px; background:#F0FDF4;
                            border-radius:12px; margin-bottom:20px;">
                    <i class="fas fa-check-circle" style="font-size:24px; color:#22C55E; margin-bottom:8px;"></i>
                    <div style="font-size:15px; font-weight:700; color:#15803D;">
                        Payment Confirmed — Officially Enrolled
                    </div>
                    <div style="font-size:12px; color:var(--dtc-text-secondary); margin-top:4px;">
                        This serves as your official receipt. Please keep this for your records.
                    </div>
                </div>

                {{-- Actions --}}
                <div class="d-flex" style="gap:10px;">
                    <a href="{{ route('cashier.payments.index') }}" class="btn btn-secondary flex-fill">
                        ← Back to Payments
                    </a>
                    <button onclick="window.print()" class="btn btn-primary flex-fill">
                        <i class="fas fa-print mr-1"></i> Print Receipt
                    </button>
                </div>

            </div>
        </div>
    </div>
</div>

@section('css')
<style>
@media print {
    .main-sidebar, .main-header, .content-header,
    .btn, .main-footer, .breadcrumb { display: none !important; }
    .content-wrapper { margin: 0 !important; }
    #printable-receipt { box-shadow: none !important; }

    /* Force light colors on paper regardless of the on-screen theme —
       a dark background wastes ink and looks wrong on a printed receipt. */
    #printable-receipt, #printable-receipt .card-body {
        background: #fff !important;
    }
    #printable-receipt [style*="--dtc-surface-soft"],
    #printable-receipt [style*="background:var(--dtc-surface-soft)"] {
        background: #F8FAFC !important;
    }
    #printable-receipt * {
        color: #1E293B !important;
    }
    #printable-receipt .card-body:first-child,
    #printable-receipt .card-body:first-child * {
        color: #fff !important;
    }
}
</style>
@endsection

@endsection