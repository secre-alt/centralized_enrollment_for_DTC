@extends('adminlte::page')
@include('partials.navbar')

@section('title', 'Appointment Requests')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h4 class="mb-0 font-weight-bold u-text" >Appointment Requests</h4>
            <p class="mb-0 u-text-secondary-sm" >Review and confirm student appointment bookings</p>
        </div>
        <a href="{{ route('registrar.appointments.slots') }}" class="btn btn-primary btn-sm dtc-header-btn">
            <i data-lucide="clock" class="mr-1"></i>
            <span class="dtc-header-btn-label">Manage Slots</span>
        </a>
    </div>
@endsection

@section('content')

@if (session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap u-gap-8" >
        <span class="font-weight-bold u-text" >
            <i data-lucide="hourglass" class="mr-2 u-link"></i> Pending Requests
        </span>
        <span  class="u-text-secondary-sm">{{ $appointments->total() }} total</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
        <table class="table mb-0">
            <thead>
                <tr>
                    <th>Student</th>
                    <th>Document Type</th>
                    <th>Date</th>
                    <th>Time</th>
                    <th>Purpose</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($appointments as $appointment)
                <tr>
                    <td>
                        <div  class="u-flex-center-gap-10">
                            <div style="width:34px; height:34px; border-radius:50%;
                                        background:linear-gradient(135deg,#0F4CDB,#1a5feb);
                                        display:flex; align-items:center; justify-content:center;
                                        color:#fff; font-weight:700; font-size:12px; flex-shrink:0;">
                                {{ strtoupper(substr($appointment->user->name, 0, 1)) }}
                            </div>
                            <div>
                                <div  class="u-text-sm-bold-primary">
                                    {{ $appointment->user->name }}
                                </div>
                                <div  class="u-text-xs-secondary">
                                    {{ $appointment->user->email }}
                                </div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <span class="dtc-status-badge is-info" style="font-weight:600;">
                            {{ ucfirst($appointment->document_type) }}
                        </span>
                    </td>
                    <td style="font-size:13px; font-weight:500; color:var(--dtc-text);">
                        {{ \Carbon\Carbon::parse($appointment->slot->date)->format('M d, Y') }}
                    </td>
                    <td  class="u-text-secondary-sm">
                        {{ \Carbon\Carbon::parse($appointment->slot->start_time)->format('h:i A') }}
                    </td>
                    <td style="font-size:12px; color:var(--dtc-text-secondary); max-width:150px;">
                        {{ $appointment->purpose ?? '—' }}
                    </td>
                    <td>
                        <x-dtc.status-badge status="Pending" />
                    </td>
                    <td>
                        <div  class="u-actions-gap">
                            {{-- Confirm --}}
                            <form method="POST"
                                  action="{{ route('registrar.appointments.confirm', $appointment) }}">
                                @csrf
                                <button type="submit"
                                        class="dtc-status-badge is-success"
                                        style="border:none;
                                               padding:5px 12px; font-size:11px;
                                               font-weight:600; cursor:pointer;"
                                        onclick="return confirm('Confirm this appointment?')">
                                    <i data-lucide="check" class="mr-1"></i> Confirm
                                </button>
                            </form>

                            {{-- Cancel --}}
                            <button type="button"
                                    class="dtc-status-badge is-danger"
                                    style="border:none;
                                           padding:5px 12px; font-size:11px;
                                           font-weight:600; cursor:pointer;"
                                    data-toggle="modal"
                                    data-target="#cancelModal{{ $appointment->id }}">
                                <i data-lucide="x" class="mr-1"></i> Cancel
                            </button>
                        </div>

                        {{-- Cancel Modal --}}
                        <div class="modal fade" id="cancelModal{{ $appointment->id }}" tabindex="-1">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <form method="POST"
                                          action="{{ route('registrar.appointments.cancel', $appointment) }}">
                                        @csrf
                                        <div class="modal-header">
                                            <h5 class="modal-title">Cancel Appointment</h5>
                                            <button type="button" class="close" data-dismiss="modal">
                                                &times;
                                            </button>
                                        </div>
                                        <div class="modal-body">
                                            <p style="font-size:13px; color:var(--dtc-text-secondary); margin-bottom:12px;">
                                                Please provide a reason for cancelling
                                                <strong>{{ $appointment->user->name }}</strong>'s appointment.
                                            </p>
                                            <div class="form-group">
                                                <label>Reason / Remarks</label>
                                                <textarea name="remarks" class="form-control"
                                                          rows="3" required
                                                          placeholder="e.g. Slot unavailable, rescheduling required..."></textarea>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary"
                                                    data-dismiss="modal">Back</button>
                                            <button type="submit" class="btn btn-danger">
                                                Confirm Cancel
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="text-center py-5">
                        <i data-lucide="calendar-check" class="mb-3 u-border-color" style="width:3em;height:3em"></i>
                        <p style="color:var(--dtc-text-muted); font-size:13px;">No pending appointment requests.</p>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
        </div>
    </div>

    {{-- Pagination --}}
    <div class="card-footer u-panel-footer" >
        <div class="d-flex justify-content-between align-items-center flex-wrap u-gap-15" >
            <small  class="u-text-secondary">
                Showing
                <strong>{{ $appointments->firstItem() ?? 0 }}</strong>
                to
                <strong>{{ $appointments->lastItem() ?? 0 }}</strong>
                of
                <strong>{{ $appointments->total() }}</strong>
                requests
            </small>

            @if($appointments->hasPages())
            <div>
                {{ $appointments->onEachSide(1)->links('pagination::bootstrap-4') }}
            </div>
            @endif
        </div>
    </div>
</div>

@endsection