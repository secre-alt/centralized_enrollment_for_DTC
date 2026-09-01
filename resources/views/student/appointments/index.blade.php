@extends('adminlte::page')
@include('partials.navbar')

@section('title', 'My Appointments')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h4 class="mb-0 font-weight-bold u-text">My Appointments</h4>
            <p class="mb-0 u-text-secondary-sm">Track your document requests and appointment bookings</p>
        </div>
        <a href="{{ route('portal.appointments.create') }}" class="btn btn-primary btn-sm dtc-header-btn">
            <i data-lucide="plus" class="mr-1"></i>
            <span class="dtc-header-btn-label">Book Appointment</span>
        </a>
    </div>
@endsection

@section('content')

@if ($appointments->isEmpty())
    <div class="card">
        <div class="card-body text-center py-5">
            <i data-lucide="calendar-x" class="mb-3 u-border-color" style="width:4em;height:4em;"></i>
            <h5 style="color:var(--dtc-text); font-weight:700;">No Appointments Yet</h5>
            <p style="color:var(--dtc-text-secondary); font-size:13px; max-width:360px; margin:0 auto 20px;">
                You haven't booked any appointments yet. Book one now to request your documents from the Registrar's Office.
            </p>
            <a href="{{ route('portal.appointments.create') }}" class="btn btn-primary">
                <i data-lucide="calendar-plus" class="mr-1"></i> Book Appointment
            </a>
        </div>
    </div>
@else
    <div class="row">
        @foreach ($appointments as $appointment)
        @php
            $pay    = $appointment->latestPayment;
            $color  = match($appointment->status) {
                'confirmed' => 'var(--dtc-success)',
                'cancelled' => 'var(--dtc-danger)',
                default     => 'var(--dtc-primary)',
            };
        @endphp
        <div class="col-lg-6 mb-3">
            <div class="card appt-card" style="border-left: 4px solid {{ $color }};">
                <div class="card-body">

                    {{-- ── Header row ──────────────────────────────── --}}
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div class="u-flex-center-gap-12">
                            {{-- Date chip --}}
                            <div class="dtc-date-chip" style="flex-shrink:0;">
                                <span class="dtc-date-chip-month">
                                    {{ \Carbon\Carbon::parse($appointment->slot->date)->format('M') }}
                                </span>
                                <span class="dtc-date-chip-day">
                                    {{ \Carbon\Carbon::parse($appointment->slot->date)->format('d') }}
                                </span>
                            </div>
                            <div>
                                <div style="font-size:14px; font-weight:700; color:var(--dtc-text);">
                                    {{ ucwords(str_replace('_', ' ', $appointment->document_type)) }}
                                </div>
                                <div class="u-text-xxs-secondary" style="display:flex; align-items:center; gap:4px; margin-top:2px;">
                                    <i data-lucide="clock" style="width:11px;height:11px;"></i>
                                    {{ \Carbon\Carbon::parse($appointment->slot->start_time)->format('h:i A') }}
                                    &mdash;
                                    {{ \Carbon\Carbon::parse($appointment->slot->end_time)->format('h:i A') }}
                                </div>
                                <div class="u-text-xxs-secondary" style="display:flex; align-items:center; gap:4px; margin-top:2px;">
                                    <i data-lucide="calendar" style="width:11px;height:11px;"></i>
                                    {{ \Carbon\Carbon::parse($appointment->slot->date)->format('l, F d, Y') }}
                                </div>
                            </div>
                        </div>
                        {{-- Status badge --}}
                        <div style="flex-shrink:0;">
                            @if($appointment->status === 'confirmed')
                                <x-dtc.status-badge status="Confirmed" variant="success" />
                            @elseif($appointment->status === 'cancelled')
                                <x-dtc.status-badge status="Cancelled" />
                            @else
                                <x-dtc.status-badge status="Pending" />
                            @endif
                        </div>
                    </div>

                    {{-- ── Purpose ──────────────────────────────────── --}}
                    @if($appointment->purpose)
                    <div style="background:var(--dtc-surface-soft); border-radius:8px; padding:10px 12px;
                                font-size:12px; color:var(--dtc-text-secondary); margin-bottom:12px;
                                display:flex; align-items:flex-start; gap:8px;">
                        <i data-lucide="info" style="width:13px;height:13px;flex-shrink:0;margin-top:1px;color:var(--dtc-primary);"></i>
                        <span>{{ $appointment->purpose }}</span>
                    </div>
                    @endif

                    {{-- ── Payment status strip ─────────────────────── --}}
                    @if($pay)
                    <div class="appt-pay-strip mb-3
                        {{ $pay->status === 'verified'  ? 'is-success' :
                           ($pay->status === 'rejected' ? 'is-danger'  :
                           ($pay->isGcash()             ? 'is-warning' : 'is-info')) }}">

                        <div style="display:flex; align-items:center; gap:8px; flex:1; min-width:0;">
                            <i data-lucide="{{ $pay->isGcash() ? 'smartphone' : 'building-2' }}"
                               style="width:14px;height:14px;flex-shrink:0;"></i>
                            <div>
                                <div style="font-size:11px; font-weight:700; line-height:1.2;">
                                    @if($pay->payment_method === 'walk_in')
                                        Walk-in Payment
                                    @elseif($pay->status === 'verified')
                                        GCash Verified
                                    @elseif($pay->status === 'pending')
                                        GCash — Awaiting Verification
                                    @elseif($pay->status === 'rejected')
                                        GCash Rejected
                                    @endif
                                </div>
                                @if($pay->reference_number)
                                <div style="font-size:10px; opacity:0.85; font-family:monospace;">
                                    Ref: {{ $pay->reference_number }}
                                </div>
                                @endif
                            </div>
                        </div>

                        {{-- View proof link --}}
                        @if($pay->proof_of_payment)
                        <a href="{{ route('portal.appointments.payment.proof', $pay) }}"
                           target="_blank"
                           style="font-size:10px; font-weight:700; text-decoration:none;
                                  opacity:0.85; white-space:nowrap; flex-shrink:0;
                                  display:flex; align-items:center; gap:4px;">
                            <i data-lucide="eye" style="width:11px;height:11px;"></i> View Proof
                        </a>
                        @endif
                    </div>

                    {{-- Rejection reason --}}
                    @if($pay->status === 'rejected' && $pay->rejection_reason)
                    <div class="alert alert-danger" style="padding:10px 12px; font-size:12px;
                                margin-bottom:12px; border-radius:8px;
                                display:flex; gap:8px; align-items:flex-start;">
                        <i data-lucide="alert-circle" style="width:14px;height:14px;flex-shrink:0;margin-top:1px;"></i>
                        <div>
                            <strong>Payment rejected:</strong> {{ $pay->rejection_reason }}
                            <div style="margin-top:6px;">
                                <button type="button"
                                        class="btn btn-sm btn-danger"
                                        style="font-size:11px; padding:4px 12px; border-radius:6px;"
                                        data-toggle="modal"
                                        data-target="#gcashModal{{ $appointment->id }}">
                                    <i data-lucide="upload" style="width:11px;height:11px;"></i>
                                    Resubmit GCash Proof
                                </button>
                            </div>
                        </div>
                    </div>
                    @endif
                    @endif

                    {{-- ── No payment yet & appointment not cancelled → offer GCash upload --}}
                    @if(!$pay && $appointment->status !== 'cancelled')
                    <div style="background:var(--dtc-surface-soft); border:1px dashed var(--dtc-border);
                                border-radius:8px; padding:10px 12px; font-size:12px;
                                color:var(--dtc-text-secondary); margin-bottom:12px;
                                display:flex; align-items:center; justify-content:space-between; gap:10px;">
                        <span>
                            <i data-lucide="credit-card" style="width:13px;height:13px;margin-right:4px;"></i>
                            No payment submitted yet.
                        </span>
                        <button type="button"
                                class="btn btn-sm btn-primary"
                                style="font-size:11px; padding:4px 12px; border-radius:6px; flex-shrink:0;"
                                data-toggle="modal"
                                data-target="#gcashModal{{ $appointment->id }}">
                            <i data-lucide="smartphone" style="width:11px;height:11px;"></i>
                            Pay via GCash
                        </button>
                    </div>
                    @endif

                    {{-- ── Confirmed notice ─────────────────────────── --}}
                    @if($appointment->status === 'confirmed')
                    <div class="alert alert-success" style="padding:10px 12px; font-size:12px;
                                margin-bottom:12px; border-radius:8px;
                                display:flex; gap:8px; align-items:flex-start;">
                        <i data-lucide="check-circle" style="width:14px;height:14px;flex-shrink:0;margin-top:1px;"></i>
                        Your appointment is confirmed. Please be at the Registrar's Office on time. Bring a valid ID.
                    </div>
                    @endif

                    {{-- ── Cancellation remarks ─────────────────────── --}}
                    @if($appointment->status === 'cancelled' && $appointment->remarks)
                    <div class="alert alert-danger" style="padding:10px 12px; font-size:12px;
                                margin-bottom:12px; border-radius:8px;
                                display:flex; gap:8px; align-items:flex-start;">
                        <i data-lucide="x-circle" style="width:14px;height:14px;flex-shrink:0;margin-top:1px;"></i>
                        <div><strong>Cancelled:</strong> {{ $appointment->remarks }}</div>
                    </div>
                    @endif

                    {{-- ── Footer ───────────────────────────────────── --}}
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="u-text-xs-muted">
                            Booked {{ $appointment->created_at->diffForHumans() }}
                        </span>
                        @if($appointment->status === 'pending')
                            <button type="button"
                                    class="dtc-status-badge"
                                    style="border:none; padding:6px 14px; font-size:11px; font-weight:600;
                                           cursor:pointer; display:inline-flex; align-items:center; gap:4px;
                                           background:var(--dtc-primary-soft); color:var(--dtc-primary);
                                           border-radius:999px;"
                                    data-toggle="modal"
                                    data-target="#editModal{{ $appointment->id }}">
                                <i data-lucide="pencil" style="width:11px;height:11px;"></i> Edit
                            </button>
                            <form id="cancel-appt-{{ $appointment->id }}"
                                  method="POST"
                                  action="{{ route('portal.appointments.cancel', $appointment) }}">
                                @csrf
                                <button type="button"
                                        class="dtc-status-badge is-danger"
                                        style="border:none; padding:6px 14px; font-size:11px;
                                               font-weight:600; cursor:pointer;"
                                        data-dtc-confirm
                                        data-dtc-confirm-title="Cancel Appointment?"
                                        data-dtc-confirm-message="Are you sure you want to cancel this appointment? You may need to book a new slot."
                                        data-dtc-confirm-ok="Cancel Appointment"
                                        data-dtc-confirm-cancel="Keep Appointment"
                                        data-dtc-confirm-form="#cancel-appt-{{ $appointment->id }}">
                                    <i data-lucide="x" style="width:11px;height:11px;"></i> Cancel
                                </button>
                            </form>
                        @endif
                    </div>

                </div>
            </div>
        </div>

        {{-- ── GCash Upload Modal (per appointment) ───────────────────── --}}
        @if($appointment->status !== 'cancelled')
        <div class="modal fade" id="gcashModal{{ $appointment->id }}" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form method="POST"
                          action="{{ route('portal.appointments.payment.gcash', $appointment) }}"
                          enctype="multipart/form-data">
                        @csrf
                        <div class="modal-header">
                            <h5 class="modal-title" style="font-size:14px; font-weight:700;">
                                <i data-lucide="smartphone" style="width:16px;height:16px;margin-right:6px;"></i>
                                Submit GCash Payment
                            </h5>
                            <button type="button" class="close" data-dismiss="modal">&times;</button>
                        </div>
                        <div class="modal-body">
                            {{-- GCash account info --}}
                            <div style="background:var(--dtc-surface-soft); border-radius:10px;
                                        padding:14px 16px; margin-bottom:16px; font-size:12px;
                                        color:var(--dtc-text-secondary); text-align:center;">
                                <div style="font-size:11px; color:var(--dtc-text-muted); margin-bottom:4px;">
                                    Send payment to
                                </div>
                                <img src="{{ route('portal.appointments.gcash.qr') }}"
                                     alt="GCash QR"
                                     onerror="this.style.display='none'"
                                     style="width:120px; height:120px; object-fit:contain;
                                            border:1px solid var(--dtc-border); border-radius:10px;
                                            padding:4px; display:block; margin:0 auto 10px;">
                                <div style="font-weight:700; font-size:15px; color:var(--dtc-text);">
                                    {{ \App\Models\Setting::get('gcash_name', '—') }}
                                </div>
                                <div style="font-size:13px; font-family:monospace; color:var(--dtc-primary);">
                                    {{ \App\Models\Setting::get('gcash_number', '—') }}
                                </div>
                            </div>

                            <div class="form-group mb-3">
                                <label style="font-size:12px; font-weight:600; color:var(--dtc-text);">
                                    GCash Reference Number <span style="color:var(--dtc-danger);">*</span>
                                </label>
                                <input type="text" name="reference_number"
                                       class="form-control" required
                                       placeholder="e.g. 1234567890"
                                       style="border-radius:8px; font-size:13px;">
                            </div>
                            <div class="form-group mb-0">
                                <label style="font-size:12px; font-weight:600; color:var(--dtc-text);">
                                    Proof of Payment
                                    <span style="color:var(--dtc-text-muted); font-weight:400;">(screenshot or PDF, max 5 MB)</span>
                                    <span style="color:var(--dtc-danger);">*</span>
                                </label>
                                <div class="dtc-file-upload" onclick="this.querySelector('input').click()">
                                    <input type="file" name="proof_of_payment"
                                           class="dtc-file-input" required
                                           accept=".jpg,.jpeg,.png,.pdf"
                                           onchange="dtcFileChange(this)">
                                    <i data-lucide="upload-cloud" class="dtc-file-icon"></i>
                                    <div class="dtc-file-label">
                                        <span class="dtc-file-btn">Choose file</span>
                                        <span class="dtc-file-name">No file chosen</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">
                                <i data-lucide="upload" style="width:13px;height:13px;margin-right:4px;"></i>
                                Submit Payment
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        @endif

        {{-- ── Edit Appointment Modal ─────────────────────────────────── --}}
        @if($appointment->status === 'pending')
        <div class="modal fade" id="editModal{{ $appointment->id }}" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <form method="POST"
                          action="{{ route('portal.appointments.update', $appointment) }}">
                        @csrf
                        @method('PUT')

                        <div class="modal-header">
                            <h5 class="modal-title" style="font-size:14px; font-weight:700;">
                                <i data-lucide="pencil" style="width:16px;height:16px;margin-right:6px;"></i>
                                Edit Appointment
                            </h5>
                            <button type="button" class="close" data-dismiss="modal">&times;</button>
                        </div>

                        <div class="modal-body">

                            {{-- Document Type --}}
                            <div class="mb-4">
                                <label class="font-weight-bold d-block mb-2" style="font-size:13px; color:var(--dtc-text);">
                                    Document Type <span style="color:var(--dtc-danger,#dc3545);">*</span>
                                </label>
                                <div class="row">
                                    @foreach([
                                        ['value' => 'transcript',    'label' => 'Transcript of Records', 'icon' => 'file-text'],
                                        ['value' => 'diploma',       'label' => 'Diploma',               'icon' => 'graduation-cap'],
                                        ['value' => 'certification', 'label' => 'Certification',          'icon' => 'award'],
                                        ['value' => 'tor',           'label' => 'True Copy of Records',  'icon' => 'copy'],
                                    ] as $doc)
                                    <div class="col-6 col-sm-3 mb-2">
                                        <label class="dtc-doc-card" style="cursor:pointer; display:block;">
                                            <input type="radio"
                                                   name="document_type"
                                                   value="{{ $doc['value'] }}"
                                                   class="edit-doc-radio-{{ $appointment->id }}"
                                                   style="position:absolute; opacity:0; width:0; height:0;"
                                                   {{ $appointment->document_type === $doc['value'] ? 'checked' : '' }}>
                                            <div class="dtc-doc-option" style="padding:10px 6px;">
                                                <div class="dtc-doc-icon">
                                                    <i data-lucide="{{ $doc['icon'] }}"></i>
                                                </div>
                                                <div class="dtc-doc-label" style="font-size:11px;">{{ $doc['label'] }}</div>
                                            </div>
                                        </label>
                                    </div>
                                    @endforeach
                                </div>
                            </div>

                            {{-- Schedule --}}
                            <div class="mb-4">
                                <label class="font-weight-bold d-block mb-2" style="font-size:13px; color:var(--dtc-text);">
                                    Schedule <span style="color:var(--dtc-danger,#dc3545);">*</span>
                                </label>
                                <div style="max-height:240px; overflow-y:auto; padding-right:4px;">
                                    {{-- Current slot always shown first --}}
                                    <label class="dtc-slot-card mb-2" style="cursor:pointer; display:block;">
                                        <input type="radio"
                                               name="appointment_slot_id"
                                               value="{{ $appointment->appointment_slot_id }}"
                                               style="position:absolute; opacity:0; width:0; height:0;"
                                               checked>
                                        <div class="dtc-slot-option">
                                            <div class="dtc-date-chip">
                                                <span class="dtc-date-chip-month">{{ \Carbon\Carbon::parse($appointment->slot->date)->format('M') }}</span>
                                                <span class="dtc-date-chip-day">{{ \Carbon\Carbon::parse($appointment->slot->date)->format('d') }}</span>
                                            </div>
                                            <div class="dtc-slot-info">
                                                <div class="dtc-slot-date">
                                                    {{ \Carbon\Carbon::parse($appointment->slot->date)->format('l, F d, Y') }}
                                                    <span style="font-size:10px; color:var(--dtc-primary); font-weight:600; margin-left:6px;">Current</span>
                                                </div>
                                                <div class="dtc-slot-time">
                                                    <i data-lucide="clock" style="width:12px;height:12px;"></i>
                                                    {{ \Carbon\Carbon::parse($appointment->slot->start_time)->format('h:i A') }}
                                                    &mdash;
                                                    {{ \Carbon\Carbon::parse($appointment->slot->end_time)->format('h:i A') }}
                                                </div>
                                            </div>
                                        </div>
                                    </label>

                                    {{-- Other available slots --}}
                                    @foreach($slots->where('id', '!=', $appointment->appointment_slot_id) as $slot)
                                    @php $left = $slot->availableSlots(); @endphp
                                    <label class="dtc-slot-card mb-2" style="cursor:pointer; display:block;">
                                        <input type="radio"
                                               name="appointment_slot_id"
                                               value="{{ $slot->id }}"
                                               style="position:absolute; opacity:0; width:0; height:0;">
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
                                                {{ $left }} left
                                            </div>
                                        </div>
                                    </label>
                                    @endforeach

                                    @if($slots->isEmpty())
                                    <div class="text-center py-2" style="font-size:12px; color:var(--dtc-text-muted);">
                                        No other slots available — keeping current schedule.
                                    </div>
                                    @endif
                                </div>
                            </div>

                            {{-- Purpose --}}
                            <div class="mb-1">
                                <label class="font-weight-bold d-block mb-1" style="font-size:13px; color:var(--dtc-text);">
                                    Purpose <span style="color:var(--dtc-danger,#dc3545);">*</span>
                                </label>
                                <textarea name="purpose"
                                          class="form-control"
                                          rows="2"
                                          placeholder="e.g. For employment application, scholarship, board exam..."
                                          style="border-radius:10px; font-size:13px; resize:none;"
                                          required>{{ $appointment->purpose }}</textarea>
                            </div>

                        </div>

                        <div class="modal-footer" style="justify-content:space-between;">
                            <div style="font-size:11px; color:var(--dtc-text-muted); display:flex; align-items:center; gap:6px;">
                                <i data-lucide="bell" style="width:12px;height:12px;"></i>
                                The Registrar will be notified of your changes.
                            </div>
                            <div style="display:flex; gap:8px;">
                                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Discard</button>
                                <button type="submit" class="btn btn-primary btn-sm">
                                    <i data-lucide="save" style="width:13px;height:13px;margin-right:4px;"></i>
                                    Save Changes
                                </button>
                            </div>
                        </div>

                    </form>
                </div>
            </div>
        </div>
        @endif

        @endforeach
    </div>
@endif

@endsection

@section('css')
    <link rel="stylesheet" href="{{ asset('css/appointments-shared.css') }}">
    <link rel="stylesheet" href="{{ asset('css/appointments-index.css') }}">
@endsection

@section('js')
    <script src="{{ asset('js/appointments-index.js') }}"></script>
@endsection
