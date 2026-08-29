@extends('adminlte::page')
@include('partials.navbar')

@section('title', 'My Appointments')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h4 class="mb-0 font-weight-bold u-text" >My Appointments</h4>
            <p class="mb-0 u-text-secondary-sm" >Track your document requests and appointment bookings</p>
        </div>
        <a href="{{ route('portal.appointments.create') }}" class="btn btn-primary btn-sm">
            <i data-lucide="plus" class="mr-1"></i> Book Appointment
        </a>
    </div>
@endsection

@section('content')

@if (session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

@if ($appointments->isEmpty())
    {{-- Empty State --}}
    <div class="card">
        <div class="card-body text-center py-5">
            <i data-lucide="calendar-x" class="mb-3 u-border-color" style="width:4em;height:4em"></i>
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
        <div class="col-lg-6 mb-3">
            <div class="card"
                 style="border-left:4px solid
                    {{ $appointment->status === 'confirmed' ? 'var(--dtc-success)' :
                       ($appointment->status === 'pending' ? 'var(--dtc-accent)' :
                       ($appointment->status === 'cancelled' ? 'var(--dtc-danger)' : 'var(--dtc-primary)')) }};">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div  class="u-flex-center-gap-12">
                            <div style="background:var(--dtc-primary-soft); color:var(--dtc-primary); border-radius:12px;
                                        padding:10px 14px; font-size:11px; font-weight:700;
                                        text-align:center; line-height:1.3; min-width:52px;">
                                {{ \Carbon\Carbon::parse($appointment->slot->date)->format('M') }}<br>
                                <span style="font-size:20px; display:block; line-height:1;">
                                    {{ \Carbon\Carbon::parse($appointment->slot->date)->format('d') }}
                                </span>
                            </div>
                            <div>
                                <div style="font-size:14px; font-weight:700; color:var(--dtc-text);">
                                    {{ ucfirst($appointment->document_type) }}
                                </div>
                                <div  class="u-text-xxs-secondary">
                                    <i data-lucide="clock" class="mr-1"></i>
                                    {{ \Carbon\Carbon::parse($appointment->slot->start_time)->format('h:i A') }}
                                    —
                                    {{ \Carbon\Carbon::parse($appointment->slot->end_time)->format('h:i A') }}
                                </div>
                                <div  class="u-text-xxs-secondary">
                                    <i data-lucide="calendar" class="mr-1"></i>
                                    {{ \Carbon\Carbon::parse($appointment->slot->date)->format('l, F d, Y') }}
                                </div>
                            </div>
                        </div>
                        <div>
                            @if($appointment->status === 'confirmed')
                                <x-dtc.status-badge status="Confirmed" variant="success" />
                            @else
                                <x-dtc.status-badge :status="$appointment->status" />
                            @endif
                        </div>
                    </div>

                    @if($appointment->purpose)
                    <div style="background:var(--dtc-surface-soft); border-radius:8px; padding:10px 12px;
                                font-size:12px; color:var(--dtc-text-secondary); margin-bottom:12px;">
                        <i data-lucide="info" class="mr-1 u-link"></i>
                        {{ $appointment->purpose }}
                    </div>
                    @endif

                    @if($appointment->remarks)
                    <div class="alert alert-danger" style="padding:10px 12px; font-size:12px; margin-bottom:12px;">
                        <i data-lucide="alert-circle" class="mr-1"></i>
                        {{ $appointment->remarks }}
                    </div>
                    @endif

                    @if($appointment->status === 'confirmed')
                    <div class="alert alert-success" style="padding:10px 12px; font-size:12px; margin-bottom:12px;">
                        <i data-lucide="check-circle" class="mr-1"></i>
                        Your appointment is confirmed. Please be at the Registrar's Office on time.
                        Bring a valid ID.
                    </div>
                    @endif

                    <div class="d-flex justify-content-between align-items-center">
                        <span  class="u-text-xs-muted">
                            Booked {{ $appointment->created_at->diffForHumans() }}
                        </span>
                        @if($appointment->status === 'pending')
                            <form method="POST"
                                  action="{{ route('portal.appointments.cancel', $appointment) }}">
                                @csrf
                                <button type="submit"
                                        class="dtc-status-badge is-danger"
                                        style="border:none;
                                               padding:6px 14px; font-size:11px;
                                               font-weight:600; cursor:pointer;"
                                        onclick="return confirm('Cancel this appointment?')">
                                    <i data-lucide="x" class="mr-1"></i> Cancel
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        @endforeach
    </div>
@endif

@endsection