@extends('adminlte::page')
@include('partials.navbar')

@section('title', 'Payment Settings')

@section('content_header')
    <div>
        <h4 class="mb-0 font-weight-bold" style="color:var(--dtc-text);">Payment Settings</h4>
        <p class="mb-0" style="color:var(--dtc-text-secondary); font-size:13px;">
            Configure enrollment fee and GCash payment details
        </p>
    </div>
@endsection

@section('content')
<div class="row justify-content-center">
<div class="col-lg-7">

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif
    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    {{-- ── Text Settings Form ─────────────────────────────────── --}}
    <div class="card mb-4">
        <div class="card-header font-weight-bold" style="color:var(--dtc-text);">
            <i class="fas fa-money-bill mr-2" style="color:#0F4CDB;"></i>
            Fee & GCash Details
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('admin.settings.payment.update') }}">
                @csrf
                @method('PUT')

                {{-- Enrollment Fee --}}
                <div class="form-group">
                    <label style="font-size:13px; font-weight:600; color:#374151;">
                        Enrollment Fee (₱)
                    </label>
                    <input type="number"
                           name="enrollment_fee"
                           step="0.01"
                           min="0"
                           value="{{ old('enrollment_fee', $settings->enrollment_fee ?? 500) }}"
                           class="form-control"
                           required>
                </div>

                {{-- GCash Account Name --}}
                <div class="form-group">
                    <label style="font-size:13px; font-weight:600; color:#374151;">
                        GCash Account Name
                    </label>
                    <input type="text"
                           name="gcash_name"
                           value="{{ old('gcash_name', $settings->gcash_name ?? '') }}"
                           class="form-control"
                           placeholder="e.g. DTC Cashier">
                </div>

                {{-- GCash Number --}}
                <div class="form-group">
                    <label style="font-size:13px; font-weight:600; color:#374151;">
                        GCash Number
                    </label>
                    <input type="text"
                           name="gcash_number"
                           value="{{ old('gcash_number', $settings->gcash_number ?? '') }}"
                           class="form-control"
                           placeholder="e.g. 09XXXXXXXXX"
                           maxlength="11">
                </div>

                {{-- Payment Deadline (days) --}}
                <div class="form-group">
                    <label style="font-size:13px; font-weight:600; color:#374151;">
                        Payment Deadline (days after approval)
                    </label>
                    <input type="number"
                           name="payment_deadline"
                           min="1"
                           value="{{ old('payment_deadline', $settings->payment_deadline ?? '') }}"
                           class="form-control"
                           placeholder="e.g. 7">
                </div>

                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save mr-1"></i> Save Settings
                </button>
            </form>
        </div>
    </div>

    {{-- ── QR Upload Form ──────────────────────────────────────── --}}
    <div class="card">
        <div class="card-header font-weight-bold" style="color:var(--dtc-text);">
            <i class="fas fa-qrcode mr-2" style="color:#0F4CDB;"></i>
            GCash QR Code
        </div>
        <div class="card-body">

            {{-- Current QR preview --}}
            @php
                $qrPath = $settings->gcash_qr_path ?? null;
                $qrExists = $qrPath && \Illuminate\Support\Facades\Storage::disk('local')->exists($qrPath);
            @endphp

            @if ($qrExists)
                <div class="mb-3">
                    <p style="font-size:12px; color:var(--dtc-text-secondary); margin-bottom:8px;">Current QR Code:</p>
                    <img src="{{ route('portal.enrollment.gcash.qr') }}"
                         alt="GCash QR Code"
                         style="width:160px; height:160px; object-fit:contain;
                                border:1.5px solid var(--dtc-border); border-radius:12px; padding:8px;">
                </div>
            @else
                <div style="background:#FEF9C3; border:1.5px solid #FDE68A; border-radius:8px;
                            padding:12px 16px; margin-bottom:16px; font-size:13px; color:#92400E;">
                    <i class="fas fa-exclamation-triangle mr-2"></i>
                    No QR code uploaded yet. Students will see a placeholder on the payment page.
                </div>
            @endif

            <form method="POST"
                  action="{{ route('admin.settings.payment.qr') }}"
                  enctype="multipart/form-data">
                @csrf

                <div class="form-group">
                    <label style="font-size:13px; font-weight:600; color:#374151;">
                        Upload New QR Image
                        <span style="font-size:11px; font-weight:400; color:var(--dtc-text-muted);">
                            (PNG or JPG, max 2 MB)
                        </span>
                    </label>
                    <input type="file"
                           name="gcash_qr"
                           accept=".jpg,.jpeg,.png"
                           class="form-control-file"
                           required>
                    @error('gcash_qr')
                        <p style="color:#DC2626; font-size:12px; margin-top:4px;">{{ $message }}</p>
                    @enderror
                </div>

                <button type="submit" class="btn btn-dark">
                    <i class="fas fa-upload mr-1"></i> Upload QR Code
                </button>
            </form>
        </div>
    </div>

</div>
</div>
@endsection