@extends('adminlte::page')
@include('partials.navbar')

@section('title', 'Document Appointment History')

@section('content_header')
    <x-dtc.page-header title="Document appointment history" subtitle="Review scheduled visits connected to your document requests.">
        <x-slot name="actions">
            <a href="{{ route('portal.documents.index') }}" class="dtc-btn dtc-btn-primary">Document requests</a>
            <a href="{{ route('portal.claiming-appointments.index') }}" class="dtc-btn dtc-btn-secondary">Past claiming bookings</a>
        </x-slot>
    </x-dtc.page-header>
@endsection

@section('content')
@php
    $items = method_exists($appointments, 'getCollection') ? $appointments->getCollection() : collect($appointments);
    $groupFor = static function ($appointment) {
        if ($appointment->status === 'cancelled') return 'cancelled';
        $slot = $appointment->slot;
        $end = $slot ? \Carbon\Carbon::parse($slot->date.' '.$slot->end_time, 'Asia/Manila') : null;
        return $end && $end->gt(now('Asia/Manila')) && in_array($appointment->status, ['pending', 'confirmed'], true)
            ? 'upcoming'
            : 'past';
    };
    $counts = ['all' => $items->count(), 'upcoming' => 0, 'past' => 0, 'cancelled' => 0];
    foreach ($items as $appointment) $counts[$groupFor($appointment)]++;
@endphp
<div class="dr dr-index dtc-appointments-page dr-alumni-history dr-appointment-history">
    <div class="dr-history-intro">
        This page is only for scheduled document visits. Enrollment advising has its own <a href="{{ route('portal.advising.index') }}">Enrollment advising</a> page. Document status, fees, and release details are in <a href="{{ route('portal.documents.index') }}">Document Requests</a>. Ready documents can be collected during posted office hours without booking.
    </div>

    @if (session('success'))<div class="alert alert-success" role="status">{{ session('success') }}</div>@endif
    @if ($errors->any())<div class="dr-alert dr-alert--error" role="alert"><i data-lucide="circle-alert" aria-hidden="true"></i><div>{{ $errors->first() }}</div></div>@endif

    @if ($items->isEmpty())
        <div class="dr-empty dr-appointments-empty dr-visit-empty">
            <span class="dr-appointments-empty-icon" aria-hidden="true"><i data-lucide="calendar-days"></i></span>
            <h2>No document appointments</h2>
            <p>Ready documents can be collected during posted office hours without an appointment. Check Document Requests for progress or Enrollment Advising to request a consultation.</p>
            <a href="{{ route('portal.documents.index') }}" class="dr-btn dr-btn--primary">View document requests</a>
        </div>
    @else
        <div class="dr-filters" role="group" aria-label="Filter document appointments" data-dr-filters>
            <button type="button" class="dr-filter" data-filter="all" aria-pressed="true">All <span class="dr-filter-count">{{ $counts['all'] }}</span></button>
            @foreach (['upcoming' => 'Upcoming', 'past' => 'Past', 'cancelled' => 'Cancelled'] as $key => $label)
                @if ($counts[$key] > 0)
                    <button type="button" class="dr-filter" data-filter="{{ $key }}" aria-pressed="false">{{ $label }} <span class="dr-filter-count">{{ $counts[$key] }}</span></button>
                @endif
            @endforeach
        </div>

        <ul class="dr-list">
            @foreach ($items as $appointment)
                @php
                    $slot = $appointment->slot;
                    $group = $groupFor($appointment);
                    $documentRequests = $appointment->documentRequests;
                    $documents = $documentRequests->map(fn ($document) => $document->document_label.' × '.$document->copies)->unique()->values();
                @endphp
                <li class="dr-req {{ $appointment->status === 'cancelled' ? 'is-muted' : '' }}" data-group="{{ $group }}">
                    <div class="dr-req-top">
                        <div>
                            <h2 class="dr-req-name">Document appointment #{{ $appointment->id }}</h2>
                            <p class="dr-req-meta">{{ ucfirst($group) }} · Recorded {{ $appointment->created_at->timezone('Asia/Manila')->format('M j, Y g:i A') }}</p>
                        </div>
                        <span class="dtc-status-badge is-{{ $appointment->status === 'confirmed' || $appointment->status === 'completed' ? 'success' : ($appointment->status === 'pending' ? 'warning' : 'neutral') }}">{{ ucfirst($appointment->status) }}</span>
                    </div>

                    @if ($slot)
                        <p class="dr-req-purpose"><strong>Scheduled for:</strong> {{ \Carbon\Carbon::parse($slot->date, 'Asia/Manila')->format('D, M j, Y') }} · {{ \Carbon\Carbon::parse($slot->start_time)->format('g:i A') }}–{{ \Carbon\Carbon::parse($slot->end_time)->format('g:i A') }}</p>
                    @else
                        <p class="dr-req-purpose"><strong>Schedule:</strong> Not available</p>
                    @endif
                    @if ($appointment->purpose)<p class="dr-req-purpose"><strong>Purpose:</strong> {{ $appointment->purpose }}</p>@endif
                    @if ($documents->isNotEmpty())
                        <p class="dr-req-purpose"><strong>Linked documents:</strong> {{ $documents->implode(', ') }}. View their current status in <a href="{{ route('portal.documents.index') }}">Document Requests</a>.</p>
                    @endif
                    @if ($appointment->status === 'cancelled' && $documentRequests->contains(fn ($document) => $document->status !== 'cancelled'))
                        <p class="dr-step-note">This appointment was cancelled. Your linked document request remains separate; check Document Requests for its current status.</p>
                    @endif
                </li>
            @endforeach
        </ul>

        <div class="dr-empty dr-empty--filter" id="drFilterEmpty" role="status"><p>No document appointments match this filter.</p></div>
    @endif
</div>
@endsection

@section('css')
    <link rel="stylesheet" href="{{ asset('css/appointments-shared.css') }}">
    <link rel="stylesheet" href="{{ asset('css/appointments-index.css') }}">
    <link rel="stylesheet" href="{{ asset('css/document-requests.css') }}">
    <link rel="stylesheet" href="{{ asset('css/document-request-flow.css') }}">
    <link rel="stylesheet" href="{{ asset('css/alumni-request-history.css') }}?v={{ filemtime(public_path('css/alumni-request-history.css')) }}">
@endsection

@section('js')
    <script src="{{ asset('js/document-request.js') }}" defer></script>
@endsection
