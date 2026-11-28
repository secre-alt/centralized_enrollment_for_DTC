@extends('adminlte::page')
@include('partials.navbar')

@section('title', 'Reschedule Appointment')

@section('content_header')
    <x-dtc.page-header title="Reschedule appointment" subtitle="Keep the same appointment reference while choosing another available time." />
@endsection

@section('content')
<div class="container-fluid" style="max-width:900px">
    <div class="card"><div class="card-body">
        <p><strong>{{ $appointment->service?->name }}</strong> · {{ $appointment->reference_number }}</p>
        @if($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>@endif
        <form method="POST" action="{{ route('portal.appointments.update', $appointment) }}">
            @csrf @method('PUT')
            @include('partials.appointment-command', ['lifecycleAppointment' => $appointment])
            <div class="form-group">
                <label for="reschedule-slot">Available date and time</label>
                <select id="reschedule-slot" name="appointment_slot_id" class="form-control" required>
                    @foreach($slots as $slot)
                        <option value="{{ $slot->id }}" @selected((int) old('appointment_slot_id', $appointment->appointment_slot_id) === $slot->id)>
                            {{ \Carbon\Carbon::parse($slot->date)->format('D, M j, Y') }} · {{ \Carbon\Carbon::parse($slot->start_time)->format('g:i A') }}–{{ \Carbon\Carbon::parse($slot->end_time)->format('g:i A') }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label for="reschedule-purpose">Concern</label>
                <textarea id="reschedule-purpose" name="purpose" class="form-control" maxlength="500" rows="3" required>{{ old('purpose', $appointment->purpose) }}</textarea>
            </div>
            <button class="btn btn-primary" type="submit">Save new time</button>
            <a class="btn btn-outline-secondary" href="{{ route('portal.advising.index') }}">Back</a>
        </form>
    </div></div>
</div>
@endsection
