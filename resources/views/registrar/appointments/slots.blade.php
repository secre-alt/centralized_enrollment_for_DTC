@extends('adminlte::page')
@include('partials.navbar')

@section('title', 'Manage Appointment Slots')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center flex-wrap" style="gap:10px;">
        <div>
            <h4 class="mb-0 font-weight-bold" style="color:var(--dtc-text);">Manage Appointment Slots</h4>
            <p class="mb-0" style="color:var(--dtc-text-secondary); font-size:13px;">Set available dates and times for student appointments</p>
        </div>
        <div class="d-flex flex-wrap" style="gap:8px;">
            <button type="button" class="btn btn-primary btn-sm" data-toggle="modal" data-target="#addSlotModal">
                <i class="fas fa-plus mr-1"></i> Add Slot
            </button>
            <a href="{{ route('registrar.appointments.index') }}" class="btn btn-secondary btn-sm">
                <i class="fas fa-calendar-check mr-1"></i> View Requests
            </a>
        </div>
    </div>
@endsection

@section('content')

@if (session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

{{-- Slots List --}}
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap" style="gap:8px;">
        <span class="font-weight-bold" style="color:var(--dtc-text);">
            <i class="fas fa-clock mr-2" style="color:#0F4CDB;"></i> Available Slots
        </span>
        <span style="font-size:13px; color:var(--dtc-text-secondary);">{{ $slots->total() }} total</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
        <table class="table mb-0">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Time</th>
                    <th>Max</th>
                    <th>Booked</th>
                    <th>Available</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($slots as $slot)
                <tr>
                    <td>
                        <div style="display:flex; align-items:center; gap:10px;">
                            <div style="background:#EEF2FF; color:#0F4CDB; border-radius:10px;
                                        padding:6px 10px; font-size:11px; font-weight:700;
                                        text-align:center; line-height:1.3; min-width:42px;">
                                {{ \Carbon\Carbon::parse($slot->date)->format('M') }}<br>
                                <span style="font-size:16px;">{{ \Carbon\Carbon::parse($slot->date)->format('d') }}</span>
                            </div>
                            <div style="font-size:12px; color:var(--dtc-text-secondary);">
                                {{ \Carbon\Carbon::parse($slot->date)->format('D, Y') }}
                            </div>
                        </div>
                    </td>
                    <td style="font-size:13px; font-weight:500; color:var(--dtc-text); white-space:nowrap;">
                        {{ \Carbon\Carbon::parse($slot->start_time)->format('h:i A') }}
                        <span style="color:var(--dtc-text-muted);">—</span>
                        {{ \Carbon\Carbon::parse($slot->end_time)->format('h:i A') }}
                    </td>
                    <td>
                        <span style="font-size:13px; font-weight:600; color:var(--dtc-text);">
                            {{ $slot->max_bookings }}
                        </span>
                    </td>
                    <td>
                        <span style="font-size:13px; font-weight:600; color:#0F4CDB;">
                            {{ $slot->appointments_count }}
                        </span>
                    </td>
                    <td>
                        @php $available = $slot->availableSlots(); @endphp
                        <span style="font-size:13px; font-weight:600;
                                     color:{{ $available > 0 ? '#15803D' : '#DC2626' }};">
                            {{ $available }}
                        </span>
                    </td>
                    <td>
                        @if($slot->availableSlots() > 0)
                            <span class="badge badge-success">Available</span>
                        @elseif($slot->appointments_count >= $slot->max_bookings)
                            <span class="badge badge-danger">Full</span>
                        @else
                            <span class="badge badge-warning">Nearly Full</span>
                        @endif
                    </td>
                    <td>
                        <form method="POST"
                              action="{{ route('registrar.appointments.slots.delete', $slot) }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit"
                                    style="background:#FEE2E2; color:#DC2626; border:none;
                                           padding:5px 10px; border-radius:8px; font-size:11px;
                                           font-weight:600; cursor:pointer;"
                                    onclick="return confirm('Delete this slot?')">
                                <i class="fas fa-trash"></i>
                            </button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="text-center py-5">
                        <i class="fas fa-calendar-times fa-3x mb-3" style="color:var(--dtc-border);"></i>
                        <p style="color:var(--dtc-text-muted); font-size:13px;">No slots added yet.</p>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
        </div>
    </div>

    {{-- Pagination --}}
    <div class="card-footer" style="background:var(--dtc-surface); border-top:1px solid var(--dtc-border);">
        <div class="d-flex justify-content-between align-items-center flex-wrap" style="gap:15px;">
            <small style="color:var(--dtc-text-secondary);">
                Showing
                <strong>{{ $slots->firstItem() ?? 0 }}</strong>
                to
                <strong>{{ $slots->lastItem() ?? 0 }}</strong>
                of
                <strong>{{ $slots->total() }}</strong>
                slots
            </small>

            @if($slots->hasPages())
            <div>
                {{ $slots->onEachSide(1)->links('pagination::bootstrap-4') }}
            </div>
            @endif
        </div>
    </div>
</div>

{{-- ADD SLOT MODAL --}}
<div class="modal fade" id="addSlotModal" tabindex="-1" role="dialog" aria-labelledby="addSlotModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <form method="POST" action="{{ route('registrar.appointments.slots.store') }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="addSlotModalLabel">
                        <i class="fas fa-plus-circle mr-2" style="color:#0F4CDB;"></i> Add New Slot
                    </h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">

                    @if ($errors->any() && (old('date') || old('start_time')))
                        <div class="alert alert-danger">{{ $errors->first() }}</div>
                    @endif

                    <div class="form-group">
                        <label>Date</label>
                        <input type="date" name="date" class="form-control"
                               min="{{ today()->toDateString() }}"
                               value="{{ old('date') }}" required>
                    </div>

                    <div class="row">
                        <div class="col-6">
                            <div class="form-group">
                                <label>Start Time</label>
                                <input type="time" name="start_time" class="form-control"
                                       value="{{ old('start_time') }}" required>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="form-group">
                                <label>End Time</label>
                                <input type="time" name="end_time" class="form-control"
                                       value="{{ old('end_time') }}" required>
                            </div>
                        </div>
                    </div>

                    <div class="form-group mb-0">
                        <label>Max Bookings</label>
                        <input type="number" name="max_bookings" class="form-control"
                               value="{{ old('max_bookings', 5) }}" min="1" max="50" required>
                        <small style="color:var(--dtc-text-muted); font-size:11px;">
                            Maximum students per slot
                        </small>
                    </div>

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-plus mr-1"></i> Add Slot
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@section('js')
<script>
    @if($errors->any() && (old('date') || old('start_time') || old('max_bookings')))
        $(document).ready(function () {
            $('#addSlotModal').modal('show');
        });
    @endif
</script>
@endsection