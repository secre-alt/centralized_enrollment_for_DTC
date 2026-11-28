@extends('adminlte::page')
@include('partials.navbar')

@section('title', 'Enrollment Advising')

@section('content_header')
    <x-dtc.page-header title="Enrollment advising" subtitle="Request time with the Registrar or an academic adviser." />
@endsection

@section('content')
<div class="container-fluid" style="max-width:1100px">
    <div class="card"><div class="card-body">
        <p>Use this service for enrollment guidance, transfer credit questions, or returning-student concerns. Any credit evaluation is preliminary until official records and school rules are reviewed. Document pickup remains walk-in; no appointment is needed to claim ready documents.</p>
        @if($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>@endif
        @if(session('success'))<div class="alert alert-success" role="status">{{ session('success') }}</div>@endif
        @if($slots->isEmpty())
            <div class="alert alert-info">There are no open advising times right now. Please check again later or contact the Registrar.</div>
        @else
        <form method="POST" action="{{ route('portal.advising.store') }}" class="row">
            @csrf
            @include('partials.appointment-command')
            <div class="form-group col-12 col-md-6">
                <label for="advising-type">What do you need help with?</label>
                <select class="form-control" id="advising-type" name="consultation_type" required>
                    <option value="">Choose a topic</option>
                    <option value="enrollment_guidance" @selected(old('consultation_type') === 'enrollment_guidance')>Enrollment guidance</option>
                    <option value="transfer_credit" @selected(old('consultation_type') === 'transfer_credit')>Transfer credit questions</option>
                    <option value="returning_student" @selected(old('consultation_type') === 'returning_student')>Returning student</option>
                    <option value="other" @selected(old('consultation_type') === 'other')>Other enrollment question</option>
                </select>
            </div>
            <div class="form-group col-12 col-md-6">
                <label for="advising-slot">Available date and time</label>
                <select class="form-control" id="advising-slot" name="appointment_slot_id" required>
                    <option value="">Choose an open time</option>
                    @foreach($slots as $slot)
                        <option value="{{ $slot->id }}" @selected((string) old('appointment_slot_id') === (string) $slot->id)>
                            {{ \Carbon\Carbon::parse($slot->date)->format('D, M j, Y') }} · {{ \Carbon\Carbon::parse($slot->start_time)->format('g:i A') }}–{{ \Carbon\Carbon::parse($slot->end_time)->format('g:i A') }}
                        </option>
                    @endforeach
                </select>
                <small class="form-text text-muted">Times are shown in Philippine time. Your request is pending until staff confirm it.</small>
            </div>
            <div class="form-group col-12">
                <label for="advising-purpose">Briefly describe your question</label>
                <textarea class="form-control" id="advising-purpose" name="purpose" maxlength="500" rows="3" required>{{ old('purpose') }}</textarea>
                <small class="form-text text-muted">Do not include sensitive personal information. Bring relevant school records to the consultation.</small>
            </div>
            <div class="col-12"><button class="dtc-btn dtc-btn-primary" type="submit">Request advising appointment</button></div>
        </form>
        @endif
    </div></div>

    <h2 class="h5 mt-4">My advising appointments</h2>
    @forelse($appointments as $appointment)
        <div class="card"><div class="card-body d-flex flex-wrap justify-content-between" style="gap:12px">
            <div><strong>{{ ucfirst($appointment->status) }}</strong> · {{ \Carbon\Carbon::parse($appointment->slot->date)->format('M j, Y') }} at {{ \Carbon\Carbon::parse($appointment->slot->start_time)->format('g:i A') }}<p class="mb-0 mt-2">{{ $appointment->purpose }}</p>
                @if($appointment->status === 'confirmed')<small class="text-muted">Bring records relevant to your question. Any evaluation is preliminary until official documents and rules are checked.</small>@endif
                @if($appointment->remarks)<p class="text-muted mb-0">{{ $appointment->remarks }}</p>@endif
            </div>
            @if($appointment->status === 'pending')
                <form method="POST" id="advising-cancel-{{ $appointment->id }}" action="{{ route('portal.appointments.cancel', $appointment) }}">
                    @csrf
                    @include('partials.appointment-command', ['lifecycleAppointment' => $appointment])
                    <button type="button" class="btn btn-outline-danger" data-dtc-confirm data-dtc-confirm-title="Cancel advising request?" data-dtc-confirm-message="Your pending advising appointment request will be cancelled." data-dtc-confirm-ok="Cancel request" data-dtc-confirm-cancel="Keep request" data-dtc-confirm-tone="danger" data-dtc-confirm-form="#advising-cancel-{{ $appointment->id }}" id="advising-cancel-button-{{ $appointment->id }}">Cancel request</button>
                </form>
            @endif
        </div></div>
    @empty
        <p class="text-muted">You have no advising appointments yet.</p>
    @endforelse
</div>
@endsection
