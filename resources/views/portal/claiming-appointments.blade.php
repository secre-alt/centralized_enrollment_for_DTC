@extends('adminlte::page')
@include('partials.navbar')

@section('title', 'Claiming history')

@section('content_header')
    <x-dtc.page-header title="Claiming history" subtitle="Your previous document pickup bookings." />
@endsection

@section('content')
    <p><a href="{{ route('portal.appointments.index') }}">&larr; Back to appointments</a></p>
    @if ($errors->any())
        <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
    @endif
    @if (session('success'))
        <div class="alert alert-success" role="status">{{ session('success') }}</div>
    @endif
    @include('portal.partials.registrar-contact')
    <div class="dr dr-alumni-history">
    @forelse ($appointments as $appointment)
        <div class="card">
            <div class="card-header"><h2 class="card-title">Claiming appointment #{{ $appointment->id }}</h2></div>
            <div class="card-body">
                <p>Purpose: Document claiming. Status: {{ ucfirst($appointment->status) }}.</p>
                <p>{{ $appointment->documentItems->whereNotNull('released_at')->count() }} of {{ $appointment->documentItems->count() }} released.</p>
                @if ($appointment->slot)
                    <p>{{ \Carbon\Carbon::parse($appointment->slot->date)->format('M d, Y') }}, {{ \Carbon\Carbon::parse($appointment->slot->start_time)->format('g:i A') }}–{{ \Carbon\Carbon::parse($appointment->slot->end_time)->format('g:i A') }}</p>
                @endif
                <ul>
                    @foreach ($appointment->documentItems as $item)
                        <li>#{{ $item->documentRequest->id }} — {{ $item->documentRequest->document_label }} × {{ $item->documentRequest->copies }} — {{ ucfirst($item->documentRequest->status) }}
                            @if ($item->released_at)
                                <span>Released {{ $item->released_at->timezone('Asia/Manila')->format('M d, Y g:i A') }} by {{ $item->releaser?->name ?? 'Recorded staff' }}.</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
                <p>Historical reservations are reconciled by the Registrar only when the selected documents are physically handed over.</p>
            </div>
        </div>
    @empty
        <div class="dr-empty dr-appointments-empty dr-visit-empty">
            <h2>No claiming history</h2>
            <p>Your past pickup bookings will appear here.</p>
        </div>
    @endforelse
    </div>
@endsection

@section('css')
    <link rel="stylesheet" href="{{ asset('css/document-request-flow.css') }}">
    <link rel="stylesheet" href="{{ asset('css/alumni-request-history.css') }}?v={{ filemtime(public_path('css/alumni-request-history.css')) }}">
@endsection
