@extends('adminlte::page')
@include('partials.navbar')
@section('title', 'Request Documents')
@section('content_header')
    <div class="dr dr-head dtc-booking-header"><div>
        <a href="{{ route('portal.documents.index') }}" class="dr-back"><i data-lucide="chevron-left" aria-hidden="true"></i> My document requests</a>
        <h1>Request a document</h1>
        <p>Choose your documents and arrange payment. No appointment is required.</p>
    </div></div>
@endsection
@section('content')
    <div class="dr dr-create dtc-booking-page">
        @include('portal.partials.document-submission', [
            'submissionRoute' => 'portal.appointments.store',
            'qrRoute' => 'portal.appointments.gcash.qr',
            'documentOptions' => ['transcript' => 'Transcript of Records', 'diploma' => 'Diploma', 'certification' => 'Certification', 'tor' => 'True Copy of Records'],
        ])
    </div>
@endsection
@section('css')
    <link rel="stylesheet" href="{{ asset('css/document-request-flow.css') }}">
    <link rel="stylesheet" href="{{ asset('css/booking-create.css') }}">
@endsection
@section('js')
    <script src="{{ asset('js/document-submission.js') }}" defer></script>
@endsection
