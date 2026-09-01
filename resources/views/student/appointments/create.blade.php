@extends('adminlte::page')
@include('partials.navbar')

@section('title', 'Book Appointment')

@section('content_header')
    <div>
        <h4 class="mb-0 font-weight-bold u-text">Book an Appointment</h4>
        <p class="mb-0 u-text-secondary-sm">Request a certificate or document from the Registrar's Office</p>
    </div>
@endsection

@section('content')

{{-- Step indicator --}}
<div class="dtc-appt-steps mb-4">
    <div class="dtc-appt-step is-active" id="step-indicator-1">
        <div class="dtc-appt-step-circle">
            <i data-lucide="file-text"></i>
        </div>
        <span class="dtc-appt-step-label">Document</span>
    </div>
    <div class="dtc-appt-step-line"></div>
    <div class="dtc-appt-step" id="step-indicator-2">
        <div class="dtc-appt-step-circle">
            <i data-lucide="calendar"></i>
        </div>
        <span class="dtc-appt-step-label">Schedule</span>
    </div>
    <div class="dtc-appt-step-line"></div>
    <div class="dtc-appt-step" id="step-indicator-3">
        <div class="dtc-appt-step-circle">
            <i data-lucide="credit-card"></i>
        </div>
        <span class="dtc-appt-step-label">Payment</span>
    </div>
    <div class="dtc-appt-step-line"></div>
    <div class="dtc-appt-step" id="step-indicator-4">
        <div class="dtc-appt-step-circle">
            <i data-lucide="check"></i>
        </div>
        <span class="dtc-appt-step-label">Confirm</span>
    </div>
</div>

<div class="row">
    {{-- ── Main Form Column ─────────────────────────────────────────────── --}}
    <div class="col-lg-8">

        @if ($errors->any())
        <div class="alert alert-danger d-flex align-items-center mb-3" style="border-radius:12px; gap:10px;">
            <i data-lucide="alert-circle" style="flex-shrink:0; width:18px; height:18px;"></i>
            <div>{{ $errors->first() }}</div>
        </div>
        @endif

        <form method="POST" action="{{ route('portal.appointments.store') }}"
              enctype="multipart/form-data" id="apptForm">
            @csrf

            {{-- ── STEP 1: Document Type ──────────────────────────────── --}}
            <div class="card appt-step-panel" id="step-panel-1">
                <div class="card-header d-flex align-items-center" style="gap:10px;">
                    <div class="dtc-icon-swatch is-primary">
                        <i data-lucide="file-text"></i>
                    </div>
                    <div>
                        <div class="font-weight-bold u-text" style="font-size:14px;">Step 1 — Document Type</div>
                        <div class="u-text-secondary-sm" style="font-size:11px;">Choose the document you need</div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row" id="doc-grid">
                        @foreach([
                            ['value' => 'transcript',    'label' => 'Transcript of Records', 'icon' => 'file-text',      'desc' => 'Official academic transcript'],
                            ['value' => 'diploma',       'label' => 'Diploma',               'icon' => 'graduation-cap', 'desc' => 'Diploma replacement/authentication'],
                            ['value' => 'certification', 'label' => 'Certification',          'icon' => 'award',          'desc' => 'Certificate of enrollment/graduation'],
                            ['value' => 'tor',           'label' => 'True Copy of Records',  'icon' => 'copy',           'desc' => 'Certified true copy of records'],
                        ] as $doc)
                        <div class="col-6 col-sm-3 mb-3">
                            <label class="dtc-doc-card" style="cursor:pointer; display:block;">
                                <input type="radio" name="document_type"
                                       value="{{ $doc['value'] }}"
                                       class="doc-radio"
                                       style="position:absolute; opacity:0; width:0; height:0;"
                                       {{ old('document_type') === $doc['value'] ? 'checked' : '' }}>
                                <div class="dtc-doc-option">
                                    <div class="dtc-doc-icon">
                                        <i data-lucide="{{ $doc['icon'] }}"></i>
                                    </div>
                                    <div class="dtc-doc-label">{{ $doc['label'] }}</div>
                                    <div class="dtc-doc-desc">{{ $doc['desc'] }}</div>
                                </div>
                            </label>
                        </div>
                        @endforeach
                    </div>

                    <div class="d-flex justify-content-end mt-2">
                        <button type="button" class="btn btn-primary btn-step-next" data-next="2"
                                id="next-to-2">
                            Next: Choose Schedule <i data-lucide="arrow-right" class="ml-1"></i>
                        </button>
                    </div>
                </div>
            </div>

            {{-- ── STEP 2: Slot Selection ─────────────────────────────── --}}
            <div class="card appt-step-panel d-none" id="step-panel-2">
                <div class="card-header d-flex align-items-center" style="gap:10px;">
                    <div class="dtc-icon-swatch is-primary">
                        <i data-lucide="calendar"></i>
                    </div>
                    <div>
                        <div class="font-weight-bold u-text" style="font-size:14px;">Step 2 — Choose Schedule</div>
                        <div class="u-text-secondary-sm" style="font-size:11px;">Select an available date and time slot</div>
                    </div>
                </div>
                <div class="card-body">
                    @forelse ($slots as $slot)
                    @php $left = $slot->availableSlots(); @endphp
                    <label class="dtc-slot-card mb-2" style="cursor:pointer; display:block;">
                        <input type="radio" name="appointment_slot_id"
                               value="{{ $slot->id }}"
                               class="slot-radio"
                               style="position:absolute; opacity:0; width:0; height:0;"
                               {{ old('appointment_slot_id') == $slot->id ? 'checked' : '' }}>
                        <div class="dtc-slot-option">
                            <div class="dtc-date-chip">
                                <span class="dtc-date-chip-month">{{ \Carbon\Carbon::parse($slot->date)->format('M') }}</span>
                                <span class="dtc-date-chip-day">{{ \Carbon\Carbon::parse($slot->date)->format('d') }}</span>
                            </div>
                            <div class="dtc-slot-info">
                                <div class="dtc-slot-date">{{ \Carbon\Carbon::parse($slot->date)->format('l, F d, Y') }}</div>
                                <div class="dtc-slot-time">
                                    <i data-lucide="clock" style="width:12px;height:12px;"></i>
                                    {{ \Carbon\Carbon::parse($slot->start_time)->format('h:i A') }}
                                    &mdash;
                                    {{ \Carbon\Carbon::parse($slot->end_time)->format('h:i A') }}
                                </div>
                            </div>
                            <div class="dtc-slot-badge {{ $left <= 2 ? 'is-warning' : 'is-ok' }}">
                                <i data-lucide="{{ $left <= 2 ? 'alert-circle' : 'users' }}" style="width:12px;height:12px;"></i>
                                {{ $left }} slot{{ $left !== 1 ? 's' : '' }} left
                            </div>
                        </div>
                    </label>
                    @empty
                    <div class="dtc-empty-slot-state">
                        <i data-lucide="calendar-x"></i>
                        <p>No available slots at the moment. Please check back later.</p>
                    </div>
                    @endforelse

                    <div class="d-flex justify-content-between mt-3">
                        <button type="button" class="btn btn-secondary btn-step-back" data-back="1">
                            <i data-lucide="arrow-left" class="mr-1"></i> Back
                        </button>
                        <button type="button" class="btn btn-primary btn-step-next" data-next="3"
                                id="next-to-3">
                            Next: Payment <i data-lucide="arrow-right" class="ml-1"></i>
                        </button>
                    </div>
                </div>
            </div>

            {{-- ── STEP 3: Payment ────────────────────────────────────── --}}
            <div class="card appt-step-panel d-none" id="step-panel-3">
                <div class="card-header d-flex align-items-center" style="gap:10px;">
                    <div class="dtc-icon-swatch is-primary">
                        <i data-lucide="credit-card"></i>
                    </div>
                    <div>
                        <div class="font-weight-bold u-text" style="font-size:14px;">Step 3 — Payment Method</div>
                        <div class="u-text-secondary-sm" style="font-size:11px;">
                            @if($processingFee > 0)
                                Processing fee: <strong>₱{{ number_format($processingFee, 2) }}</strong>
                            @else
                                No processing fee required
                            @endif
                        </div>
                    </div>
                </div>
                <div class="card-body">

                    {{-- Purpose field lives here for UX flow --}}
                    <div class="form-group mb-4">
                        <label class="font-weight-bold" style="font-size:13px; color:var(--dtc-text);">
                            Purpose <span style="color:var(--dtc-danger,#dc3545);">*</span>
                        </label>
                        <textarea name="purpose" class="form-control @error('purpose') is-invalid @enderror" rows="2"
                                  placeholder="e.g. For employment application, scholarship, board exam..."
                                  style="border-radius:10px; font-size:13px; resize:none;"
                                  required>{{ old('purpose') }}</textarea>
                        @error('purpose') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    {{-- Payment method toggle --}}
                    <div class="row mb-1">
                        <div class="col-6">
                            <label class="dtc-pay-method-card" style="cursor:pointer; display:block;">
                                <input type="radio" name="payment_method" value="walk_in"
                                       id="pm-walkin" class="pay-radio"
                                       style="position:absolute; opacity:0; width:0; height:0;"
                                       {{ old('payment_method', 'walk_in') === 'walk_in' ? 'checked' : '' }}>
                                <div class="dtc-pay-option" id="pay-opt-walkin">
                                    <div class="dtc-icon-swatch is-primary" style="margin:0 auto 10px;">
                                        <i data-lucide="building-2"></i>
                                    </div>
                                    <div style="font-weight:700; font-size:13px; color:var(--dtc-text);">Walk-in</div>
                                    <div style="font-size:11px; color:var(--dtc-text-muted); margin-top:4px;">Pay at the Cashier's Office</div>
                                </div>
                            </label>
                        </div>
                        <div class="col-6">
                            <label class="dtc-pay-method-card" style="cursor:pointer; display:block;">
                                <input type="radio" name="payment_method" value="gcash"
                                       id="pm-gcash" class="pay-radio"
                                       style="position:absolute; opacity:0; width:0; height:0;"
                                       {{ old('payment_method') === 'gcash' ? 'checked' : '' }}>
                                <div class="dtc-pay-option" id="pay-opt-gcash">
                                    <div class="dtc-icon-swatch is-success" style="margin:0 auto 10px;">
                                        <i data-lucide="smartphone"></i>
                                    </div>
                                    <div style="font-weight:700; font-size:13px; color:var(--dtc-text);">GCash</div>
                                    <div style="font-size:11px; color:var(--dtc-text-muted); margin-top:4px;">Pay via GCash online</div>
                                </div>
                            </label>
                        </div>
                    </div>

                    {{-- Walk-in info panel --}}
                    <div id="walkin-details" class="dtc-pay-detail-panel mt-3"
                         style="{{ old('payment_method') === 'gcash' ? 'display:none;' : '' }}">
                        <div class="d-flex align-items-start" style="gap:12px;">
                            <div class="dtc-icon-swatch is-info" style="flex-shrink:0;">
                                <i data-lucide="info"></i>
                            </div>
                            <div style="font-size:13px; color:var(--dtc-text-secondary); line-height:1.7;">
                                Pay in person at the <strong>Cashier's Office</strong> before or on your appointment date.
                                Bring this confirmation and a valid ID.
                                @if($processingFee > 0)
                                    <br>Prepare <strong>₱{{ number_format($processingFee, 2) }}</strong> as the processing fee.
                                @else
                                    No payment is collected online — the Cashier will assist you on-site.
                                @endif
                            </div>
                        </div>
                    </div>

                    {{-- GCash details panel --}}
                    <div id="gcash-details" class="dtc-pay-detail-panel mt-3"
                         style="{{ old('payment_method') !== 'gcash' ? 'display:none;' : '' }}">
                        <div class="row">
                            <div class="col-md-5 text-center mb-3">
                                @if($gcashQrReady)
                                    <img src="{{ route('portal.appointments.gcash.qr') }}" alt="GCash QR Code"
                                         style="width:160px; height:160px; object-fit:contain; display:block; margin:0 auto 12px;
                                                border:1px solid var(--dtc-border); border-radius:12px; padding:6px;
                                                background:var(--dtc-surface);">
                                @else
                                    <div style="width:160px; height:160px; display:flex; align-items:center; justify-content:center;
                                                margin:0 auto 12px; border:2px dashed var(--dtc-border); border-radius:12px;
                                                background:var(--dtc-surface-soft); flex-direction:column; gap:8px;">
                                        <i data-lucide="qr-code" style="width:32px; height:32px; color:var(--dtc-text-muted);"></i>
                                        <span style="font-size:11px; color:var(--dtc-text-muted);">QR not configured</span>
                                    </div>
                                @endif
                                <div style="font-weight:700; font-size:14px; color:var(--dtc-text);">{{ $gcashName ?? '—' }}</div>
                                <div style="font-size:12px; color:var(--dtc-text-muted);">{{ $gcashNumber ?? '—' }}</div>
                            </div>
                            <div class="col-md-7">
                                <ol style="font-size:12px; color:var(--dtc-text-secondary); padding-left:18px; margin-bottom:16px; line-height:2;">
                                    <li>Open GCash → Send Money / Scan QR</li>
                                    @if($processingFee > 0)
                                    <li>Enter <strong>₱{{ number_format($processingFee, 2) }}</strong> as the amount</li>
                                    @else
                                    <li>Enter the processing fee amount as advised</li>
                                    @endif
                                    <li>Screenshot the successful transaction</li>
                                    <li>Fill in the reference number and upload below</li>
                                </ol>
                                <div class="form-group mb-2">
                                    <label style="font-size:12px; font-weight:600; color:var(--dtc-text);">GCash Reference Number</label>
                                    <input type="text" name="reference_number"
                                           id="gcash-ref"
                                           value="{{ old('reference_number') }}"
                                           class="form-control @error('reference_number') is-invalid @enderror"
                                           placeholder="e.g. 1234567890"
                                           style="border-radius:8px; font-size:13px;">
                                    @error('reference_number') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="form-group mb-0">
                                    <label style="font-size:12px; font-weight:600; color:var(--dtc-text);">
                                        Proof of Payment <span style="font-weight:400; color:var(--dtc-text-muted);">(screenshot or PDF, max 5 MB)</span>
                                    </label>
                                    <div class="dtc-file-upload" onclick="this.querySelector('input').click()">
                                        <input type="file" name="proof_of_payment"
                                               id="gcash-proof"
                                               class="dtc-file-input @error('proof_of_payment') is-invalid @enderror"
                                               accept=".jpg,.jpeg,.png,.pdf"
                                               onchange="dtcFileChange(this)">
                                        <i data-lucide="upload-cloud" class="dtc-file-icon"></i>
                                        <div class="dtc-file-label">
                                            <span class="dtc-file-btn">Choose file</span>
                                            <span class="dtc-file-name">No file chosen</span>
                                        </div>
                                    </div>
                                    @error('proof_of_payment') <div class="text-danger mt-1" style="font-size:12px;">{{ $message }}</div> @enderror
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between mt-4">
                        <button type="button" class="btn btn-secondary btn-step-back" data-back="2">
                            <i data-lucide="arrow-left" class="mr-1"></i> Back
                        </button>
                        <button type="button" class="btn btn-primary btn-step-next" data-next="4" id="next-to-4">
                            Review Booking <i data-lucide="arrow-right" class="ml-1"></i>
                        </button>
                    </div>
                </div>
            </div>

            {{-- ── STEP 4: Confirm ────────────────────────────────────── --}}
            <div class="card appt-step-panel d-none" id="step-panel-4">
                <div class="card-header d-flex align-items-center" style="gap:10px;">
                    <div class="dtc-icon-swatch is-success">
                        <i data-lucide="check-circle"></i>
                    </div>
                    <div>
                        <div class="font-weight-bold u-text" style="font-size:14px;">Step 4 — Review & Confirm</div>
                        <div class="u-text-secondary-sm" style="font-size:11px;">Double-check your booking before submitting</div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="dtc-review-summary mb-4">
                        <div class="dtc-review-row">
                            <span class="dtc-review-label"><i data-lucide="file-text"></i> Document</span>
                            <span class="dtc-review-value" id="summary-doc">—</span>
                        </div>
                        <div class="dtc-review-row">
                            <span class="dtc-review-label"><i data-lucide="calendar"></i> Date</span>
                            <span class="dtc-review-value" id="summary-date">—</span>
                        </div>
                        <div class="dtc-review-row">
                            <span class="dtc-review-label"><i data-lucide="clock"></i> Time</span>
                            <span class="dtc-review-value" id="summary-time">—</span>
                        </div>
                        <div class="dtc-review-row">
                            <span class="dtc-review-label"><i data-lucide="credit-card"></i> Payment</span>
                            <span class="dtc-review-value" id="summary-payment">—</span>
                        </div>
                        <div class="dtc-review-row" id="summary-purpose-row">
                            <span class="dtc-review-label"><i data-lucide="info"></i> Purpose</span>
                            <span class="dtc-review-value" id="summary-purpose">—</span>
                        </div>
                    </div>

                    <div class="alert d-flex align-items-center mb-4"
                         style="border-radius:10px; background:var(--dtc-primary-soft);
                                border:1px solid rgba(var(--dtc-primary-rgb,59,130,246),0.25);
                                gap:10px; font-size:13px; color:var(--dtc-text);">
                        <i data-lucide="info" style="flex-shrink:0; color:var(--dtc-primary); width:18px; height:18px;"></i>
                        Submitting this form books your slot. The Registrar will confirm your appointment within 1–2 business days.
                    </div>

                    <div class="d-flex justify-content-between">
                        <button type="button" class="btn btn-secondary btn-step-back" data-back="3">
                            <i data-lucide="arrow-left" class="mr-1"></i> Back
                        </button>
                        <button type="submit" class="btn btn-primary dtc-loading-button"
                                data-loading-text="Booking…"
                                {{ $slots->isEmpty() ? 'disabled' : '' }}>
                            <i data-lucide="calendar-check" class="mr-1"></i> Confirm Booking
                        </button>
                    </div>
                </div>
            </div>

        </form>
    </div>

    {{-- ── Sidebar ───────────────────────────────────────────────────────── --}}
    <div class="col-lg-4 mt-3 mt-lg-0">

        {{-- Guide card --}}
        <div class="card u-primary-gradient-btn mb-3">
            <div class="card-body p-4">
                <h5 style="font-weight:700; margin-bottom:14px; color:#fff; font-size:14px;">
                    <i data-lucide="info" class="mr-2"></i> Appointment Guide
                </h5>
                <div style="font-size:12px; line-height:1.9; opacity:0.92; color:#fff;">
                    <div class="d-flex mb-1" style="gap:8px; align-items:flex-start;">
                        <i data-lucide="check-circle" style="flex-shrink:0;width:14px;height:14px;margin-top:3px;color:#FCD34D;"></i>
                        <span>Select your document type and preferred time slot.</span>
                    </div>
                    <div class="d-flex mb-1" style="gap:8px; align-items:flex-start;">
                        <i data-lucide="check-circle" style="flex-shrink:0;width:14px;height:14px;margin-top:3px;color:#FCD34D;"></i>
                        <span>Wait for the Registrar to confirm your booking.</span>
                    </div>
                    <div class="d-flex mb-1" style="gap:8px; align-items:flex-start;">
                        <i data-lucide="check-circle" style="flex-shrink:0;width:14px;height:14px;margin-top:3px;color:#FCD34D;"></i>
                        <span>You will receive a notification once confirmed.</span>
                    </div>
                    <div class="d-flex mb-1" style="gap:8px; align-items:flex-start;">
                        <i data-lucide="check-circle" style="flex-shrink:0;width:14px;height:14px;margin-top:3px;color:#FCD34D;"></i>
                        <span>Bring a valid ID on your appointment date.</span>
                    </div>
                    <div class="d-flex" style="gap:8px; align-items:flex-start;">
                        <i data-lucide="check-circle" style="flex-shrink:0;width:14px;height:14px;margin-top:3px;color:#FCD34D;"></i>
                        <span>Processing fees may apply upon pickup.</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Office info --}}
        <div class="card">
            <div class="card-header font-weight-bold u-text" style="font-size:13px;">
                <i data-lucide="building-2" class="mr-2 u-link"></i> Office Information
            </div>
            <div class="card-body" style="font-size:13px; color:var(--dtc-text-secondary); line-height:2.2;">
                <div class="d-flex align-items-center mb-1" style="gap:10px;">
                    <i data-lucide="map-pin" style="color:var(--dtc-primary); width:14px; height:14px; flex-shrink:0;"></i>
                    Registrar's Office, DTC Main Building
                </div>
                <div class="d-flex align-items-center mb-1" style="gap:10px;">
                    <i data-lucide="clock" style="color:var(--dtc-primary); width:14px; height:14px; flex-shrink:0;"></i>
                    Monday – Friday, 8:00 AM – 5:00 PM
                </div>
                <div class="d-flex align-items-center" style="gap:10px;">
                    <i data-lucide="phone" style="color:var(--dtc-primary); width:14px; height:14px; flex-shrink:0;"></i>
                    Contact Registrar for inquiries
                </div>
            </div>
        </div>

        {{-- Payment fee card (shown if fee > 0) --}}
        @if($processingFee > 0)
        <div class="card mt-3" style="border-left:4px solid var(--dtc-success);">
            <div class="card-body py-3" style="font-size:13px;">
                <div class="d-flex align-items-center" style="gap:10px;">
                    <div class="dtc-icon-swatch is-success" style="flex-shrink:0;">
                        <i data-lucide="coins"></i>
                    </div>
                    <div>
                        <div style="font-weight:700; color:var(--dtc-text);">Processing Fee</div>
                        <div style="font-size:18px; font-weight:800; color:var(--dtc-success);">
                            ₱{{ number_format($processingFee, 2) }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @endif

    </div>
</div>

@endsection

@section('css')
    <link rel="stylesheet" href="{{ asset('css/appointments-shared.css') }}">
    <link rel="stylesheet" href="{{ asset('css/appointments-create.css') }}">
    <link rel="stylesheet" href="{{ asset('css/appointments-index.css') }}">
@endsection

@section('js')
    <script>
        window.dtcApptData = {
            slots: {
                @foreach($slots as $slot)
                {{ $slot->id }}: {
                    date : '{{ \Carbon\Carbon::parse($slot->date)->format('l, F d, Y') }}',
                    start: '{{ \Carbon\Carbon::parse($slot->start_time)->format('h:i A') }}',
                    end  : '{{ \Carbon\Carbon::parse($slot->end_time)->format('h:i A') }}',
                },
                @endforeach
            },
            initialStep: {{ $errors->any() ? (old('payment_method') ? 3 : (old('appointment_slot_id') ? 2 : 1)) : 1 }},
        };
    </script>
    <script src="{{ asset('js/appointments-create.js') }}"></script>
@endsection
