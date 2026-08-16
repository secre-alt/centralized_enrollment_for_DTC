@extends('adminlte::page')
@include('partials.navbar')

@section('title', 'Process Payment')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h4 class="mb-0 font-weight-bold" style="color:#1E293B;">Process Payment</h4>
            <p class="mb-0" style="color:#64748B; font-size:13px;">
                Confirm and record enrollment fee collection
            </p>
        </div>
        <a href="{{ route('cashier.payments.index') }}" class="btn btn-secondary btn-sm">
            <i class="fas fa-arrow-left mr-1"></i> Back
        </a>
    </div>
@endsection

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-7">

        @if ($errors->any())
            <div class="alert alert-danger">{{ $errors->first() }}</div>
        @endif

        {{-- Student Info Card --}}
        <div class="card mb-3"
             style="background:linear-gradient(135deg,#0F4CDB,#1a5feb); border:none;">
            <div class="card-body p-4">
                <div style="display:flex; align-items:center; gap:16px;">
                    <div style="width:56px; height:56px; border-radius:50%;
                                background:rgba(255,255,255,0.2); display:flex;
                                align-items:center; justify-content:center;
                                color:#fff; font-weight:800; font-size:22px; flex-shrink:0;
                                border:2px solid rgba(255,255,255,0.3);">
                        {{ strtoupper(substr($enrollment->user->name, 0, 1)) }}
                    </div>
                    <div>
                        <div style="font-size:16px; font-weight:700; color:#fff;">
                            {{ $enrollment->user->name }}
                        </div>
                        <div style="font-size:13px; color:rgba(255,255,255,0.75);">
                            {{ $enrollment->user->email }}
                        </div>
                        <div style="font-size:12px; color:#FFC72C; margin-top:4px; font-weight:600;">
                            {{ $enrollment->program->name }}
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header font-weight-bold" style="color:#1E293B;">
                Payment Summary
            </div>
            <div class="card-body">
                <table style="width:100%; font-size:13px;">
                    <tr style="border-bottom:1px solid #F1F5F9;">
                        <td style="padding:12px 0; color:#64748B;">Program</td>
                        <td style="padding:12px 0; font-weight:600; color:#1E293B; text-align:right;">
                            {{ $enrollment->program->name }}
                        </td>
                    </tr>
                    <tr style="border-bottom:1px solid #F1F5F9;">
                        <td style="padding:12px 0; color:#64748B;">Year / Semester</td>
                        <td style="padding:12px 0; font-weight:600; color:#1E293B; text-align:right;">
                            Year {{ $enrollment->year_level }} — Sem {{ $enrollment->semester }}
                        </td>
                    </tr>
                    <tr style="border-bottom:1px solid #F1F5F9;">
                        <td style="padding:12px 0; color:#64748B;">Subjects Enrolled</td>
                        <td style="padding:12px 0; font-weight:600; color:#1E293B; text-align:right;">
                            {{ count($enrollment->subject_ids) }} subjects
                        </td>
                    </tr>
                    <tr style="border-bottom:1px solid #F1F5F9;">
                        <td style="padding:12px 0; color:#64748B;">Payment Type</td>
                        <td style="padding:12px 0; font-weight:600; color:#1E293B; text-align:right;">
                            Walk-in / Cash
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:16px 0; font-size:16px; font-weight:700; color:#1E293B;">
                            Enrollment Fee
                        </td>
                        <td style="padding:16px 0; font-size:24px; font-weight:800;
                                   color:#15803D; text-align:right;">
                            ₱500.00
                        </td>
                    </tr>
                </table>

                <div style="background:#F0FDF4; border:1.5px solid #BBF7D0; border-radius:12px;
                            padding:14px 16px; margin-bottom:20px; font-size:13px; color:#15803D;">
                    <i class="fas fa-info-circle mr-2"></i>
                    Upon confirmation, the student's account will be upgraded to
                    <strong>Student</strong> status and they will be officially enrolled.
                </div>

                @if (! $enrollment->is_paid && $latestPayment && $latestPayment->isPending() && $latestPayment->isGcash())
                    {{-- ── GCash Review Branch ─────────────────────────────── --}}
                    <div class="mt-6 p-6 bg-white border border-gray-200 rounded-xl">
                        <h2 class="text-lg font-semibold mb-4">GCash Payment Review</h2>

                        <dl class="grid grid-cols-2 gap-x-4 gap-y-2 text-sm mb-4">
                            <dt class="text-gray-500">Reference No.</dt>
                            <dd class="font-mono text-gray-900">{{ $latestPayment->reference_number }}</dd>
                            <dt class="text-gray-500">Amount</dt>
                            <dd class="text-gray-900">₱{{ number_format($latestPayment->amount, 2) }}</dd>
                            <dt class="text-gray-500">Submitted</dt>
                            <dd class="text-gray-900">{{ $latestPayment->created_at->format('M d, Y h:i A') }}</dd>
                        </dl>

                        <a href="{{ route('cashier.payments.proof', $latestPayment) }}" target="_blank"
                        class="inline-block mb-6 text-sm text-blue-600 underline hover:text-blue-800">
                            View Proof of Payment ↗
                        </a>

                        {{-- Verify button --}}
                        <form method="POST" action="{{ route('cashier.payments.verify', $latestPayment) }}" class="mb-3">
                            @csrf
                            <button type="submit"
                                class="w-full bg-green-600 hover:bg-green-700 text-white font-medium py-2 rounded-lg transition"
                                onclick="return confirm('Verify this GCash payment and promote applicant to student?')">
                                ✓ Verify Payment
                            </button>
                        </form>

                        {{-- Reject form --}}
                        <details class="border border-red-200 rounded-lg p-4 bg-red-50">
                            <summary class="cursor-pointer text-sm font-medium text-red-700">✗ Reject Payment</summary>
                            <form method="POST" action="{{ route('cashier.payments.reject', $latestPayment) }}" class="mt-3 space-y-3">
                                @csrf
                                <div>
                                    <label class="block text-xs font-medium text-gray-700 mb-1">Rejection Reason (sent to applicant)</label>
                                    <textarea name="remarks" rows="3" required
                                        class="w-full border-gray-300 rounded-md text-sm shadow-sm"
                                        placeholder="e.g. Reference number not found in our GCash records."></textarea>
                                    @error('remarks') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                                </div>
                                <button type="submit"
                                    class="w-full bg-red-600 hover:bg-red-700 text-white text-sm font-medium py-2 rounded-lg transition"
                                    onclick="return confirm('Reject this payment? The applicant will be notified.')">
                                    Reject & Notify Applicant
                                </button>
                            </form>
                        </details>
                    </div>

                @elseif (! $enrollment->is_paid && (! $latestPayment || $latestPayment->isRejected()))
                    {{-- ── Walk-in Branch (also shown when GCash was rejected — Cashier can still record walk-in) ── --}}
                    <div class="mt-6 p-6 bg-white border border-gray-200 rounded-xl">
                        <h2 class="text-lg font-semibold mb-4">Record Walk-in Payment</h2>
                        <p class="text-sm text-gray-600 mb-4">
                            Enrollment Fee: <strong>₱{{ number_format(\App\Models\Setting::get('enrollment_fee', 500), 2) }}</strong>
                        </p>
                        <form method="POST" action="{{ route('cashier.payments.store', $enrollment) }}">
                            @csrf
                            <button type="submit"
                                class="w-full bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 rounded-lg transition"
                                onclick="return confirm('Confirm cash payment received?')">
                                Confirm Payment Received
                            </button>
                        </form>
                    </div>

                @elseif ($enrollment->is_paid)
                    {{-- Already paid --}}
                    <div class="mt-6 p-4 bg-green-50 border border-green-300 rounded-lg text-green-800 text-sm">
                        ✓ This enrollment has been paid. <a href="{{ route('cashier.payments.receipt', $enrollment) }}" class="underline ml-1">View Receipt</a>
                    </div>
                @endif
                <a href="{{ route('cashier.payments.index') }}"
                   class="btn btn-secondary btn-block mt-2">Cancel</a>
            </div>
        </div>
    </div>
</div>
@endsection