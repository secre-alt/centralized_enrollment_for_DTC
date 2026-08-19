@extends('adminlte::page')
@include('partials.navbar')

@section('title', 'Payment')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h4 class="mb-0 font-weight-bold" style="color:var(--dtc-text);">Enrollment Payment</h4>
            <p class="mb-0" style="color:var(--dtc-text-secondary); font-size:13px;">
                Complete your official enrollment payment
            </p>
        </div>
        <a href="{{ route('portal.enrollment.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-arrow-left mr-1"></i> Back to Enrollment Status
        </a>
    </div>
@endsection

@section('content')

<div class="dtc-card dtc-review-standalone">
    @include('student.enrollment._payment-content')
</div>

@endsection
