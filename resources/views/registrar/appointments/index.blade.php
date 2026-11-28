@extends('adminlte::page')
@include('partials.navbar')

@section('title', 'Appointment Requests')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h4 class="mb-0 font-weight-bold u-text">{{ $kind === 'historical' ? 'Historical document appointments' : 'Registrar service appointments' }}</h4>
            <p class="mb-0 u-text-secondary-sm">{{ $kind === 'historical' ? 'Read-only appointment history; continue document and payment actions in their own workspaces.' : 'Manage in-person consultation requests and lifecycle.' }}</p>
        </div>
        <a href="{{ route('registrar.appointments.slots') }}" class="btn btn-primary dtc-header-btn rg-manage-slots">
            <i data-lucide="clock" class="mr-1"></i>
            <span class="dtc-header-btn-label">Manage Slots</span>
        </a>
        <a href="{{ route('registrar.appointments.services.index') }}" class="btn btn-outline-primary ml-2">Manage services</a>
    </div>
@endsection

@push('css')
<link rel="stylesheet" href="{{ asset('css/dtc-filters.css') }}">
<link rel="stylesheet" href="{{ asset('css/registrar-workspace.css') }}">
@endpush
@section('content')
<div class="registrar-workspace rg-appointments">
<div class="card card-body mb-3">
    <strong>{{ $kind === 'historical' ? 'Legacy records' : 'Consultation appointments' }}</strong>
    <p class="mb-2">{{ $kind === 'historical' ? 'Historical document-related appointments remain readable here. Do not modify their appointment or financial history.' : 'Consultations are free. Document requests and walk-in collection are managed separately and do not need an appointment.' }}</p>
    <a href="{{ route('registrar.appointments.index', ['kind' => $kind === 'historical' ? 'consultation' : 'historical']) }}">{{ $kind === 'historical' ? 'Open consultation appointments' : 'View historical document appointments' }}</a>
    · <a href="{{ route('registrar.documents.index') }}">Open document requests</a>
</div>
@if($kind === 'consultation')
<section class="card card-body mb-3" aria-labelledby="appointmentReportHeading">
    <h5 id="appointmentReportHeading">Consultation summary</h5>
    <form method="GET" action="{{ route('registrar.appointments.index') }}" class="form-inline mb-2">
        <input type="hidden" name="kind" value="consultation">
        <label class="mr-2" for="appointmentReportFrom">From</label><input id="appointmentReportFrom" class="form-control mr-2" type="date" name="from" value="{{ request('from') }}">
        <label class="mr-2" for="appointmentReportTo">To</label><input id="appointmentReportTo" class="form-control mr-2" type="date" name="to" value="{{ request('to') }}">
        <button class="btn btn-outline-primary" type="submit">Apply range</button>
    </form>
    <div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Service</th><th>Status</th><th>Appointments</th></tr></thead><tbody>
    @forelse($report as $row)
        <tr><td>{{ $row->service?->name ?? 'Unavailable service' }}</td><td>{{ str_replace('_', ' ', ucfirst($row->status)) }}</td><td>{{ $row->total }}</td></tr>
    @empty
        <tr><td colspan="3">No appointments in this range.</td></tr>
    @endforelse
    </tbody></table></div>
</section>
@endif
@hasanyrole('registrar|admin')
<p><a class="btn btn-outline-secondary" href="{{ route('registrar.claiming-appointments.index') }}">Historical claiming visits</a></p>
@endhasanyrole

<form method="GET" action="{{ route('registrar.appointments.index') }}" class="dtc-fl rg-appointment-search" aria-label="Search and filter appointments">
    <input type="hidden" name="status" value="{{ $status }}">
    <div class="dtc-fl-toolbar">
        <div class="dtc-pay-search dtc-fl-search">
            <i data-lucide="search" class="dtc-pay-search-icon" aria-hidden="true"></i>
            <input type="search" name="search" class="dtc-pay-search-input" value="{{ request('search') }}" placeholder="Search appointments" aria-label="Search appointments">
        </div>
        <button type="button" class="dtc-fl-toggle" data-toggle="collapse" data-target="#appointmentFilters" aria-expanded="false" aria-controls="appointmentFilters">
            <i data-lucide="sliders-horizontal" aria-hidden="true"></i><span>Filters</span>
        </button>
        <button type="submit" class="sr-only">Search appointments</button>
    </div>
    <div class="collapse" id="appointmentFilters">
        <div class="dtc-fl-panel">
            @include('partials.table-rows', ['tableRecords' => $appointments, 'tableId' => 'registrar-table-2', 'fixedRows' => true])
            <div class="dtc-fl-actions">
                <button type="submit" class="btn btn-primary">Apply</button>
                <a href="{{ route('registrar.appointments.index', ['kind' => $kind, 'status' => $status]) }}" class="dtc-fl-clear">Reset</a>
            </div>
        </div>
    </div>
</form>



{{-- ── Pending GCash Payments Alert ──────────────────────────────────────── --}}
@if($pendingPayments->isNotEmpty())
<div class="card mb-3" style="border-left: 4px solid var(--dtc-warning, #F59E0B);">
    <div class="card-header d-flex align-items-center" style="gap:10px;">
        <div class="dtc-icon-swatch is-warning">
            <i data-lucide="smartphone"></i>
        </div>
        <div class="flex-1">
            <div class="font-weight-bold u-text" style="font-size:13px;">
                GCash Payments Awaiting Verification
            </div>
            <div class="u-text-secondary-sm">{{ $pendingPayments->count() }} submission{{ $pendingPayments->count() !== 1 ? 's' : '' }} need review</div>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            @include('partials.table-rows', ['tableRecords' => $pendingPayments, 'tableId' => 'registrar-table-1', 'fixedRows' => true])
<table id="registrar-table-1" class="rg-record-table table mb-0">
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Document / service</th>
                        <th>Date</th>
                        <th>Reference No.</th>
                        <th>Submitted</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($pendingPayments as $payment)
                    <tr>
                        <td data-reg-label="Student">
                            <div class="u-flex-center-gap-10">
                                <x-dtc.user-avatar :user="$payment->appointment->user" style="width:30px;height:30px;border-radius:50%;
                                            background:var(--dtc-primary);
                                            display:flex;align-items:center;justify-content:center;
                                            color:#fff;font-weight:600;font-size:12px;flex-shrink:0;" />
                                <div>
                                    <div class="u-text-sm-bold-primary">{{ $payment->appointment->user->name }}</div>
                                    <div class="u-text-xs-secondary">{{ $payment->appointment->user->email }}</div>
                                </div>
                            </div>
                        </td>
                        <td data-reg-label="Document">
                            <span class="dtc-status-badge is-info" style="font-weight:600;">
                                {{ ucfirst($payment->appointment->document_type) }}
                            </span>
                        </td>
                        <td data-reg-label="Date" style="font-size:12px;color:var(--dtc-text);">
                            {{ \Carbon\Carbon::parse($payment->appointment->slot->date)->format('M d, Y') }}
                        </td>
                        <td data-reg-label="Reference No.">
                            <span style="font-size:12px;font-weight:600;font-family:monospace;
                                         color:var(--dtc-text);letter-spacing: 0;">
                                {{ $payment->reference_number }}
                            </span>
                        </td>
                        <td data-reg-label="Submitted" style="font-size:12px;color:var(--dtc-text-muted);">
                            {{ $payment->created_at->diffForHumans() }}
                        </td>
                        <td data-reg-label="Actions">
                            <div class="u-actions-gap">
                                {{-- View Proof --}}
                                <a href="{{ route('registrar.appointments.payments.proof', $payment) }}"
                                   target="_blank"
                                   class="dtc-icon-btn"
                                   title="View proof of payment">
                                    <i data-lucide="eye"></i>
                                </a>

                                {{-- Verify --}}
                                <form id="verify-pay-{{ $payment->id }}"
                                      method="POST"
                                      action="{{ route('registrar.appointments.payments.verify', $payment) }}">
                                    @csrf
                                    <button type="button"
                                            class="dtc-icon-btn success"
                                            title="Verify payment"
                                            data-dtc-confirm
                                            data-dtc-confirm-title="Verify GCash payment?"
                                            data-dtc-confirm-message="Verify the GCash payment from {subject} after confirming reference {{ $payment->reference_number }} in the payment records."
                                            data-dtc-confirm-subject="{{ $payment->appointment->user->name }}"
                                            data-dtc-confirm-ok="Verify"
                                            data-dtc-confirm-tone="success"
                                            data-dtc-confirm-form="#verify-pay-{{ $payment->id }}">
                                        <i data-lucide="check-circle"></i>
                                    </button>
                                </form>

                                {{-- Reject --}}
                                <button type="button"
                                        class="dtc-icon-btn danger"
                                        title="Reject payment"
                                        data-toggle="modal"
                                        data-target="#rejectPayModal{{ $payment->id }}">
                                    <i data-lucide="x-circle"></i>
                                </button>
                            </div>

                            {{-- Reject Payment Modal --}}
                            <div class="modal fade" id="rejectPayModal{{ $payment->id }}" tabindex="-1">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <form method="POST" id="reject-appointment-payment-{{ $payment->id }}"
                                              action="{{ route('registrar.appointments.payments.reject', $payment) }}">
                                            @csrf
                                            <div class="modal-header">
                                                <h5 class="modal-title">Reject GCash Payment</h5>
                                                <button type="button" class="close" data-dismiss="modal">&times;</button>
                                            </div>
                                            <div class="modal-body">
                                                <div class="d-flex align-items-center mb-3"
                                                     style="gap:10px; background:var(--dtc-surface-soft);
                                                            border-radius:10px; padding:12px;">
                                                    <i data-lucide="smartphone"
                                                       style="color:var(--dtc-primary);width:18px;height:18px;flex-shrink:0;"></i>
                                                    <div style="font-size:12px;color:var(--dtc-text-secondary);">
                                                        <strong style="color:var(--dtc-text);">{{ $payment->appointment->user->name }}</strong>
                                                        submitted ref <strong>#{{ $payment->reference_number }}</strong>
                                                        {{ $payment->created_at->diffForHumans() }}
                                                    </div>
                                                </div>
                                                <div class="form-group mb-0">
                                                    <label for="registrar-appointments-index-rejection-reason-{{ $payment->id }}" style="font-size:13px;font-weight:600;color:var(--dtc-text);">
                                                        Rejection Reason <span style="color:var(--dtc-danger);">*</span>
                                                    </label>
                                                    <textarea id="registrar-appointments-index-rejection-reason-{{ $payment->id }}" name="rejection_reason"
                                                              class="form-control" rows="3" required
                                                              placeholder="e.g. Reference number does not match our records..."></textarea>
                                                </div>
                                            </div>
                                            <div class="modal-footer dtc-action-footer">
                                                <button type="button" class="btn btn-secondary"
                                                        data-dismiss="modal">Keep payment</button>
                                                <button type="button" class="btn btn-danger" data-dtc-confirm data-dtc-confirm-tone="danger" data-dtc-confirm-title="Reject payment?" data-dtc-confirm-message="The GCash payment from {subject} will be rejected with the entered reason." data-dtc-confirm-subject="{{ $payment->appointment->user->name }}" data-dtc-confirm-ok="Reject" data-dtc-confirm-form="#reject-appointment-payment-{{ $payment->id }}">
                                                    Reject payment
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endif

{{-- ── Status Filter Tabs ─────────────────────────────────────────────────── --}}
        <nav class="rg-appointment-tabs" aria-label="Appointment status">
            @foreach([
                ['key' => 'pending',   'label' => 'Pending'],
                ['key' => 'confirmed', 'label' => 'Confirmed'],
                ['key' => 'checked_in', 'label' => 'Checked in'],
                ['key' => 'completed', 'label' => 'Completed'],
                ['key' => 'cancelled', 'label' => 'Cancelled'],
                ['key' => 'rejected', 'label' => 'Rejected'],
                ['key' => 'no_show', 'label' => 'No-show'],
                ['key' => 'all',       'label' => 'All'],
            ] as $tab)
            <a href="{{ route('registrar.appointments.index', array_merge(request()->only(['search', 'per_page']), ['status' => $tab['key'], 'kind' => $kind])) }}"
               aria-current="{{ $status === $tab['key'] ? 'page' : 'false' }}"
               class="rg-appointment-tab {{ $status === $tab['key'] ? 'is-active' : '' }}"
               >
                @if($tab['key'] === 'all')
                    <i data-lucide="list" aria-hidden="true"></i>
                @endif
                {{ $tab['label'] }}
                @if(isset($counts[$tab['key']]) && $counts[$tab['key']] > 0)
                    <span style="background:rgba(0,0,0,0.15); border-radius:20px;
                                 padding:1px 7px; margin-left:4px; font-size:12px;">
                        {{ $counts[$tab['key']] }}
                    </span>
                @endif
            </a>
            @endforeach
        </nav>
<div class="card rg-appointment-queue">
    <div class="card-header rg-appointment-toolbar">
        <div class="rg-appointment-options">
            <span class="u-text-secondary-sm">{{ $appointments->total() }} {{ $appointments->total() === 1 ? 'booking' : 'bookings' }}</span>

        </div>
    </div>

    <div class="card-body p-0">
        <div class="table-responsive">
<table id="registrar-table-2" class="rg-record-table table mb-0">
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Document</th>
                        <th>Date &amp; Time</th>
                        <th>Purpose</th>
                        <th>Payment</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($appointments as $appointment)
                    @php $pay = $appointment->latestPayment; @endphp
                    <tr>
                        <td data-reg-label="Student">
                            <div class="u-flex-center-gap-10 rg-appointment-student">
                                <x-dtc.user-avatar :user="$appointment->user" style="width:34px;height:34px;border-radius:50%;
                                            background:var(--dtc-primary);
                                            display:flex;align-items:center;justify-content:center;
                                            color:#fff;font-weight:600;font-size:12px;flex-shrink:0;" />
                                <div class="rg-appointment-student-info">
                                    <div class="u-text-sm-bold-primary rg-appointment-student-name">{{ $appointment->user->name }}</div>
                                    <div class="u-text-xs-secondary rg-appointment-student-email">{{ $appointment->user->email }}</div>
                                    <div class="u-text-xs-secondary rg-appointment-student-role">{{ $appointment->user->hasRole('alumni') ? 'Alumni' : ($appointment->user->hasRole('student') ? 'Student' : 'Applicant') }}</div>
                                </div>
                            </div>
                        </td>
                        <td data-reg-label="Document / service">
                            @if ($appointment->appointment_service_id)
                                <span class="dtc-status-badge is-info rg-document-name">{{ $appointment->service?->name }}</span>
                            @else
                            @if ($appointment->documentRequests->isNotEmpty())
                                @foreach ($appointment->documentRequests as $documentRequest)
                                    <div class="rg-document-entry">
                                        <span class="dtc-status-badge is-info rg-document-name">{{ $documentRequest->document_label }} × {{ $documentRequest->copies }}</span>
                                        <span class="rg-document-meta">{{ ucfirst($documentRequest->status) }} <span aria-hidden="true">·</span> ₱{{ number_format($documentRequest->fee, 2) }}</span>
                                    </div>
                                @endforeach
                            @else
                                <span class="dtc-status-badge is-info rg-document-name">{{ ucfirst($appointment->document_type) }}</span>
                            @endif
                            @endif
                        </td>
                        <td data-reg-label="Date &amp; Time">
                            <div class="rg-appointment-date">
                                {{ \Carbon\Carbon::parse($appointment->slot->date)->format('M d, Y') }}
                            </div>
                            <div class="rg-appointment-time">
                                {{ \Carbon\Carbon::parse($appointment->slot->start_time)->format('h:i A') }}
                            </div>
                        </td>
                        <td data-reg-label="Purpose" class="rg-appointment-purpose">
                            @if($appointment->appointment_service_id)<small>Reference: {{ $appointment->reference_number }}</small><br>@endif
                            {{ $appointment->purpose ?? '—' }}
                            @if($appointment->rejection_reason)<div class="text-danger">Reason: {{ $appointment->rejection_reason }}</div>@endif
                            @if($appointment->cancellation_reason)<div class="text-muted">Reason: {{ $appointment->cancellation_reason }}</div>@endif
                            @if($appointment->staff_remarks)<div class="text-muted"><strong>Staff-only:</strong> {{ $appointment->staff_remarks }}</div>@endif
                        </td>
                        <td data-reg-label="Payment">
                            @if ($appointment->appointment_service_id)
                                <span class="dtc-status-badge is-info rg-payment-status">No fee</span>
                            @else
                            @if($appointment->paymentNotRequired())
                                <span class="dtc-status-badge is-info rg-payment-status">No Payment Required</span>
                            @elseif(!$pay)
                                <span class="rg-payment-empty">—</span>
                            @elseif($pay->payment_method === 'walk_in' && $pay->status !== 'verified')
                                <span class="dtc-status-badge is-warning rg-payment-status">Walk-in unpaid</span>
                            @elseif($pay->payment_method === 'walk_in')
                                <span class="dtc-status-badge is-success rg-payment-status">
                                    <i data-lucide="building-2" aria-hidden="true"></i> Walk-in paid
                                </span>
                            @elseif($pay->status === 'verified')
                                <span class="dtc-status-badge is-success rg-payment-status">
                                    <i data-lucide="check-circle" aria-hidden="true"></i> GCash verified
                                </span>
                            @elseif($pay->status === 'pending')
                                <span class="dtc-status-badge is-warning rg-payment-status">
                                    <i data-lucide="clock" aria-hidden="true"></i> {{ $pay->is_document_bundle ? 'Awaiting Cashier' : 'GCash pending' }}
                                </span>
                            @elseif($pay->status === 'rejected')
                                <span class="dtc-status-badge is-danger rg-payment-status">
                                    <i data-lucide="x-circle" aria-hidden="true"></i> GCash rejected
                                </span>
                            @else
                                <span class="dtc-status-badge is-neutral rg-payment-status">Payment status unavailable</span>
                            @endif
                            @if($pay && $pay->is_document_bundle)
                                <div class="rg-payment-total"><span>Total due</span><strong>₱{{ number_format($pay->amount, 2) }}</strong></div>
                            @endif
                            @endif
                        </td>
                        <td data-reg-label="Status">
                            <x-dtc.status-badge :status="ucfirst($appointment->status)" />
                        </td>
                        <td data-reg-label="Actions">
                            <div class="u-actions-gap">
                                @if($appointment->appointment_service_id)
                                    @if($appointment->status === 'pending')
                                        <form method="POST" action="{{ route('registrar.appointments.confirm', $appointment) }}">
                                            @csrf @include('partials.appointment-command', ['lifecycleAppointment' => $appointment])
                                            <button class="btn btn-sm btn-success" type="submit">Confirm</button>
                                        </form>
                                        <form method="POST" action="{{ route('registrar.appointments.reject', $appointment) }}" class="d-flex" style="gap:4px">
                                            @csrf @include('partials.appointment-command', ['lifecycleAppointment' => $appointment])
                                            <label class="sr-only" for="reject-reason-{{ $appointment->id }}">Rejection reason</label>
                                            <input class="form-control form-control-sm" id="reject-reason-{{ $appointment->id }}" name="rejection_reason" maxlength="500" placeholder="Reason to reject" required>
                                            <button class="btn btn-sm btn-outline-danger" type="submit">Reject</button>
                                        </form>
                                    @elseif($appointment->status === 'confirmed')
                                        <form method="POST" action="{{ route('registrar.appointments.check-in', $appointment) }}">
                                            @csrf @include('partials.appointment-command', ['lifecycleAppointment' => $appointment])
                                            <button class="btn btn-sm btn-outline-primary" type="submit">Check in</button>
                                        </form>
                                        @if($appointment->scheduleHasPassed())
                                            <form method="POST" action="{{ route('registrar.appointments.no-show', $appointment) }}">
                                                @csrf @include('partials.appointment-command', ['lifecycleAppointment' => $appointment])
                                                <button class="btn btn-sm btn-outline-warning" type="submit">No-show</button>
                                            </form>
                                        @endif
                                    @elseif($appointment->status === 'checked_in')
                                        <form method="POST" action="{{ route('registrar.appointments.complete', $appointment) }}">
                                            @csrf @include('partials.appointment-command', ['lifecycleAppointment' => $appointment])
                                            <button class="btn btn-sm btn-success" type="submit">Complete</button>
                                        </form>
                                    @endif
                                    @if(in_array($appointment->status, ['pending', 'confirmed'], true))
                                        <button type="button" class="btn btn-sm btn-outline-danger" data-toggle="modal" data-target="#cancelModal{{ $appointment->id }}">Cancel</button>
                                    @endif
                                    @if(in_array($appointment->status, ['pending', 'confirmed', 'checked_in', 'completed'], true))
                                        <form method="POST" action="{{ route('registrar.appointments.remarks', $appointment) }}" class="mt-2">
                                            @csrf @include('partials.appointment-command', ['lifecycleAppointment' => $appointment])
                                            <label for="staff-remarks-{{ $appointment->id }}">Staff-only remarks</label>
                                            <textarea class="form-control form-control-sm" id="staff-remarks-{{ $appointment->id }}" name="staff_remarks" maxlength="4000" rows="2">{{ $appointment->staff_remarks }}</textarea>
                                            <button class="btn btn-sm btn-outline-secondary mt-1" type="submit">Save remarks</button>
                                        </form>
                                    @endif
                                @elseif($appointment->visit_type === 'claiming')
                                    @hasanyrole('registrar|admin')
                                    <a href="{{ route('registrar.claiming-appointments.index') }}" class="btn btn-sm btn-outline-primary">View handover</a>
                                    @endhasanyrole
                                @else
                                    <span style="font-size:12px;color:var(--dtc-text-muted);">—</span>
                                @endif
                            </div>

                            {{-- Cancel Modal --}}
                            @if($appointment->appointment_service_id && in_array($appointment->status, ['pending', 'confirmed'], true))
                            <div class="modal fade" id="cancelModal{{ $appointment->id }}" tabindex="-1">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <form method="POST" id="cancel-appointment-{{ $appointment->id }}"
                                              action="{{ route('registrar.appointments.cancel', $appointment) }}">
                                            @csrf
                                            @include('partials.appointment-command', ['lifecycleAppointment' => $appointment])
                                            <div class="modal-header">
                                                <h5 class="modal-title">Cancel Appointment</h5>
                                                <button type="button" class="close" data-dismiss="modal">&times;</button>
                                            </div>
                                            <div class="modal-body">
                                                <p style="font-size:13px;color:var(--dtc-text-secondary);margin-bottom:12px;">
                                                    Provide a reason for cancelling
                                                    <strong>{{ $appointment->user->name }}</strong>'s appointment.
                                                </p>
                                                <div class="form-group">
                                                    <label for="registrar-appointments-index-remarks-{{ $appointment->id }}">Reason / Remarks</label>
                                                    <textarea id="registrar-appointments-index-remarks-{{ $appointment->id }}" name="remarks" class="form-control" rows="3"
                                                              required
                                                              placeholder="e.g. Slot unavailable, rescheduling required..."></textarea>
                                                </div>
                                            </div>
                                            <div class="modal-footer dtc-action-footer">
                                                <button type="button" class="btn btn-secondary"
                                                        data-dismiss="modal">Keep appointment</button>
                                                <button type="button" class="btn btn-danger" data-dtc-confirm data-dtc-confirm-tone="danger" data-dtc-confirm-title="Cancel appointment?" data-dtc-confirm-message="The appointment for {subject} will be cancelled with the entered reason." data-dtc-confirm-subject="{{ $appointment->user->name }}" data-dtc-confirm-ok="Cancel" data-dtc-confirm-cancel="Keep Appointment" data-dtc-confirm-form="#cancel-appointment-{{ $appointment->id }}">
                                                    Cancel appointment
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center py-5">
                            <i data-lucide="calendar-check" class="mb-3 u-border-color" style="width:3em;height:3em;"></i>
                            <p style="color:var(--dtc-text-muted);font-size:13px;">
                                No {{ $status !== 'all' ? $status : '' }} appointments found.
                            </p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card-footer u-panel-footer">
        <div class="d-flex justify-content-between align-items-center flex-wrap u-gap-15">
            <small class="u-text-secondary">
                Showing
                <strong>{{ $appointments->firstItem() ?? 0 }}</strong>
                to
                <strong>{{ $appointments->lastItem() ?? 0 }}</strong>
                of
                <strong>{{ $appointments->total() }}</strong>
                appointments
            </small>
            @if($appointments->hasPages())
            <div>{{ $appointments->onEachSide(1)->links('pagination::bootstrap-4') }}</div>
            @endif
        </div>
    </div>
</div>


</div>
@endsection

@section('css')
<style>
.dtc-tab-active {
    box-shadow: inset 0 0 0 2px currentColor;
    font-weight: 600 !important;
}
</style>
@endsection
