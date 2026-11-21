@extends('adminlte::page')
@include('partials.navbar')
@section('title', 'Enrollment payment')
@push('css')
<link rel="stylesheet" href="{{ asset('css/portal-payments.css') }}">
<link rel="stylesheet" href="{{ asset('css/enrollment-status.css') }}">
@endpush
@section('content')
<main class="portal-payment-page">
    <a class="portal-payment-back" href="{{ route('portal.enrollment.index') }}">← Back to enrollment status</a>
    @if($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>@endif
    @if($enrollment->requires_section_assignment)
        <section class="portal-payment-card"><h1>Sections needed before payment</h1><p>The Registrar must assign your complete subject load first. Do not pay yet. Return to enrollment status to check progress.</p></section>
        @if($outstandingTermPayments->isNotEmpty())
            <div class="alert alert-warning" role="alert"><strong>Payment is blocked pending reconciliation.</strong><p class="mb-0">An earlier payment for this academic term also needs Cashier reconciliation. Section assignment does not confirm a refund or authorize a second payment.</p></div>
        @endif
    @elseif(!in_array($enrollment->status, ['pending', 'approved'], true) && !$enrollment->is_paid)
        <section class="portal-payment-card"><h1>Enrollment payment</h1><p>This enrollment is not eligible for payment. Contact the Registrar for guidance.</p></section>
    @else
        @include('student.enrollment._payment-content')
    @endif
</main>
@endsection
