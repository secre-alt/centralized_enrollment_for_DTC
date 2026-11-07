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
    @if(!in_array($enrollment->status, ['pending', 'approved'], true) && !$enrollment->is_paid)
        <section class="portal-payment-card"><h1>Enrollment payment</h1><p>This enrollment is not eligible for payment. Contact the Registrar for guidance.</p></section>
    @else
        @include('student.enrollment._payment-content')
    @endif
</main>
@endsection
