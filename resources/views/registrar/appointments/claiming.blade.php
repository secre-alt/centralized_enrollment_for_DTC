@extends('adminlte::page')
@include('partials.navbar')

@section('title', 'Document handover')

@section('content_header')
    <x-dtc.page-header title="Document handover" subtitle="Record physical release of the exact documents selected for a claiming visit." />
@endsection

@section('content')
    <p><a href="{{ route('registrar.appointments.index') }}">Back to appointment requests</a></p>
    @if ($errors->any())
        <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
    @endif
    @if (session('success'))
        <div class="alert alert-success" role="status">{{ session('success') }}</div>
    @endif
    @forelse ($appointments as $appointment)
        @php
            $released = $appointment->documentItems->whereNotNull('released_at')->count();
            $active = in_array($appointment->status, ['pending', 'confirmed'], true);
        @endphp
        <div class="card" id="claim-{{ $appointment->id }}">
            <div class="card-header"><h2 class="card-title">Claiming appointment #{{ $appointment->id }} - {{ $appointment->user->name }}</h2></div>
            <div class="card-body">
                <p>{{ $appointment->user->email }} - {{ $appointment->user->hasRole('alumni') ? 'Alumni' : 'Student' }}</p>
                @if ($appointment->slot)
                    <p>{{ \Carbon\Carbon::parse($appointment->slot->date)->format('M d, Y') }}, {{ \Carbon\Carbon::parse($appointment->slot->start_time)->format('g:i A') }}-{{ \Carbon\Carbon::parse($appointment->slot->end_time)->format('g:i A') }}</p>
                @endif
                <p>Status: {{ ucfirst($appointment->status) }}. <strong>{{ $released }} of {{ $appointment->documentItems->count() }} released</strong>.</p>
                <form id="release-claim-{{ $appointment->id }}" method="POST" action="{{ route('registrar.claiming-appointments.release', $appointment) }}">
                    @csrf
                    @include('partials.appointment-command', ['lifecycleAppointment' => $appointment])
                    <fieldset>
                        <legend class="h6">Exact documents for this visit</legend>
                        @foreach ($appointment->documentItems as $item)
                            @php
                                $document = $item->documentRequest;
                                $eligible = $active && !$item->resolution && !$item->released_at && !$item->released_by
                                    && $document->user_id === $appointment->user_id && $document->status === 'ready'
                                    && $document->pending_claim_appointment_id === $appointment->id && $document->paymentSatisfied();
                            @endphp
                            <div class="border rounded p-3 mb-2">
                                @if ($eligible)
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="document_ids[]" value="{{ $document->id }}" id="release-document-{{ $item->id }}">
                                        <label class="form-check-label" for="release-document-{{ $item->id }}">#{{ $document->id }} - {{ $document->document_label }} ({{ $document->copies }} copies)</label>
                                    </div>
                                @else
                                    <p class="mb-1">#{{ $document->id }} - {{ $document->document_label }} ({{ $document->copies }} copies)</p>
                                @endif
                                <p class="mb-0">Document status: {{ ucfirst($document->status) }}.</p>
                                @if ($item->released_at)
                                    <p class="mb-0">Released {{ $item->released_at->timezone('Asia/Manila')->format('M d, Y g:i A') }} by {{ $item->releaser?->name ?? 'Recorded staff' }}.</p>
                                @elseif (!$eligible)
                                    <p class="text-warning mb-0">Not eligible for handover. Review readiness, payment, ownership, and reservation.</p>
                                @else
                                    <p class="text-success mb-0">Ready for handover; payment requirement satisfied.</p>
                                @endif
                            </div>
                        @endforeach
                    </fieldset>
                    @if ($active && $released < $appointment->documentItems->count())
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" name="handover_confirmed" value="1" id="handover-{{ $appointment->id }}" required>
                            <label class="form-check-label" for="handover-{{ $appointment->id }}">I confirm physical handover to this claimant of only the selected documents.</label>
                        </div>
                        <button type="button" class="btn btn-primary" data-dtc-confirm
                            data-dtc-confirm-title="Record physical handover?"
                            data-dtc-confirm-message="Only the checked documents for {subject} will be recorded as released. Unselected documents remain reserved."
                            data-dtc-confirm-subject="{{ $appointment->user->name }}"
                            data-dtc-confirm-ok="Record handover" data-dtc-confirm-tone="success"
                            data-dtc-confirm-form="#release-claim-{{ $appointment->id }}">Record selected handover</button>
                    @endif
                </form>
            </div>
        </div>
    @empty
        <p>No claiming appointments.</p>
    @endforelse
    {{ $appointments->links() }}
@endsection
