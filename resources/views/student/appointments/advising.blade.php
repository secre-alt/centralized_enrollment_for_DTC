@extends('adminlte::page')
@include('partials.navbar')

@section('title', 'Appointments')

@section('content_header')
    <x-dtc.page-header title="Book an appointment" subtitle="Choose a Registrar service and an available time." />
@endsection

@section('content')
<div class="dr dr-index dtc-advising-page">
    <section class="card dtc-advising-panel" aria-labelledby="advising-intro">
        <div class="card-body">
        <div class="dtc-advising-intro">
            <h2 id="advising-intro">Registrar consultation</h2>
            <p>Select an active service to request an in-person appointment. Consultation appointments do not require payment.</p>
            <p class="dtc-advising-note">Ready document pickup is walk-in. You do not need an appointment to claim documents.</p>
        </div>
        @if($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>@endif
        @if(session('success'))<div class="alert alert-success" role="status">{{ session('success') }}</div>@endif
        @if($services->isNotEmpty())
            <ul class="list-unstyled mb-3">
                @foreach($services as $service)
                    <li class="mb-2"><strong>{{ $service->name }}</strong>@if($service->description) — {{ $service->description }}@endif @if($service->instructions)<div class="text-muted">{{ $service->instructions }}</div>@endif</li>
                @endforeach
            </ul>
        @endif
        @if($services->isEmpty())
            <div class="dtc-advising-empty" role="status"><strong>No appointment services are currently available.</strong><p>Contact the Registrar if you need assistance.</p></div>
        @elseif($slots->isEmpty())
            <div class="dtc-advising-empty" role="status">
                <i data-lucide="calendar-clock" aria-hidden="true"></i>
                <div><strong>No open advising times</strong><p>There are no available times right now. Please check again later or contact the Registrar.</p></div>
            </div>
        @else
        <form method="POST" action="{{ route('portal.advising.store') }}" class="row">
            @csrf
            @include('partials.appointment-command')
            <div class="form-group col-12 col-md-6">
                <label for="appointment-service">Appointment service</label>
                <select class="form-control" id="appointment-service" name="appointment_service_id" required>
                    <option value="">Choose a service</option>
                    @foreach($services as $service)
                        <option value="{{ $service->id }}" @selected((string) old('appointment_service_id') === (string) $service->id)>{{ $service->name }}</option>
                    @endforeach
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
                <small class="form-text text-muted">Times are shown in Philippine time. The service may require Registrar confirmation.</small>
            </div>
            <div class="form-group col-12">
                <label for="advising-purpose">Briefly describe your question</label>
                <textarea class="form-control" id="advising-purpose" name="purpose" maxlength="500" rows="3" required>{{ old('purpose') }}</textarea>
                <small class="form-text text-muted">Do not include sensitive personal information. Bring relevant school records to the consultation.</small>
            </div>
            <div class="col-12"><button class="dtc-btn dtc-btn-primary" type="submit">Request appointment</button></div>
        </form>
        @endif
        </div>
    </section>

    <section class="dtc-advising-history" id="appointments-history" aria-labelledby="advising-history-title">
    <div class="dtc-advising-section-heading"><h2 id="advising-history-title">My appointments</h2><p>Your consultation requests and appointment history.</p></div>
    @forelse($appointments as $appointment)
        <article class="card dtc-advising-appointment"><div class="card-body d-flex flex-wrap justify-content-between">
            <div><strong>{{ ucfirst(str_replace('_', ' ', $appointment->status)) }}</strong> · {{ $appointment->service?->name ?? 'Historical appointment' }}<p class="mb-0 mt-1">Reference: {{ $appointment->reference_number ?? 'Historical record' }}</p>
                <p class="mb-0 mt-2">{{ \Carbon\Carbon::parse($appointment->slot->date)->format('M j, Y') }} at {{ \Carbon\Carbon::parse($appointment->slot->start_time)->format('g:i A') }}</p>
                <p class="mb-0 mt-2">{{ $appointment->purpose }}</p>
                @if($appointment->rejection_reason)<p class="text-danger mb-0">Reason: {{ $appointment->rejection_reason }}</p>@endif
                @if($appointment->cancellation_reason)<p class="text-muted mb-0">Reason: {{ $appointment->cancellation_reason }}</p>@endif
            </div>
            @if($appointment->appointment_service_id && in_array($appointment->status, ['pending', 'confirmed'], true))
                <a class="btn btn-outline-primary align-self-start" href="{{ route('portal.appointments.edit', $appointment) }}">Reschedule</a>
            @endif
            @if($appointment->appointment_service_id && in_array($appointment->status, ['pending', 'confirmed'], true))
                <form method="POST" id="advising-cancel-{{ $appointment->id }}" action="{{ route('portal.appointments.cancel', $appointment) }}">
                    @csrf
                    @include('partials.appointment-command', ['lifecycleAppointment' => $appointment])
                    <button type="button" class="btn btn-outline-danger" data-dtc-confirm data-dtc-confirm-title="Cancel appointment?" data-dtc-confirm-message="Your appointment will be cancelled and its slot released." data-dtc-confirm-ok="Cancel appointment" data-dtc-confirm-cancel="Keep appointment" data-dtc-confirm-tone="danger" data-dtc-confirm-form="#advising-cancel-{{ $appointment->id }}" id="advising-cancel-button-{{ $appointment->id }}">Cancel appointment</button>
                </form>
            @endif
        </div></article>
    @empty
        <div class="dtc-advising-history-empty">You have no advising appointments yet.</div>
    @endforelse
    </section>
</div>
@endsection

@section('css')
    <link rel="stylesheet" href="{{ asset('css/advising-appointments.css') }}?v={{ filemtime(public_path('css/advising-appointments.css')) }}">
@endsection
