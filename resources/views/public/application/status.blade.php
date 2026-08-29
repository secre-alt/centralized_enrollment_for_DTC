@extends('layouts.public')

@section('title', 'Check Application Status')

@section('content')
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-7 col-md-9">

            {{-- Header --}}
            <div class="text-center mb-4">
                <h2 class="fw-bold">Check Application Status</h2>
                <p class="text-muted mb-0">
                    @if(isset($application))
                        Here is the current status of your application.
                    @else
                        Enter your application reference number and email address
                        to check the current status of your application.
                    @endif
                </p>
            </div>

            @if(isset($application))

                {{-- Application Status Result --}}
                <div class="card border-0 shadow-sm rounded-4 mb-4">
                    <div class="card-body p-4 p-md-5">

                        <div class="d-flex justify-content-between align-items-start mb-4">
                            <div>
                                <div class="text-muted small text-uppercase fw-semibold mb-1">
                                    Reference Number
                                </div>
                                <div class="fs-4 fw-bold text-primary">
                                    {{ $application->reference_no }}
                                </div>
                            </div>

                            <span class="badge bg-warning text-dark fs-6 px-3 py-2">
                                {{ ucwords(str_replace('_', ' ', $application->status)) }}
                            </span>
                        </div>

                        <div class="border rounded-4 p-4 mb-4">
                            <div class="row g-3">

                                <div class="col-md-6">
                                    <div class="text-muted small">
                                        Applicant
                                    </div>
                                    <div class="fw-semibold">
                                        {{ $application->first_name }}
                                        {{ $application->middle_name ? $application->middle_name . ' ' : '' }}
                                        {{ $application->last_name }}
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="text-muted small">
                                        Applicant Type
                                    </div>
                                    <div class="fw-semibold">
                                        {{ ucwords(str_replace('_', ' ', $application->academic_status)) }}
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="text-muted small">
                                        Program
                                    </div>
                                    <div class="fw-semibold">
                                        {{ $application->program->name ?? '—' }}
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="text-muted small">
                                        Email
                                    </div>
                                    <div class="fw-semibold">
                                        {{ $application->email }}
                                    </div>
                                </div>

                            </div>
                        </div>

                        {{-- Physical Requirements Reminder --}}
                        @if(!empty($physicalRequirements))
                            <div class="border rounded-4 p-4">
                                <h5 class="fw-bold mb-2">
                                    Physical Documents & Requirements
                                </h5>

                                <p class="text-muted small mb-3">
                                    Please prepare the following requirements for presentation
                                    to the Registrar's Office.
                                </p>

                                <ul class="small mb-0">
                                    @foreach($physicalRequirements as $requirement)
                                        <li class="mb-2">{{ $requirement }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                    </div>
                </div>

                <div class="text-center mb-4">
                    <a href="{{ route('public.application.status.form') }}" class="btn btn-outline-secondary px-4">
                        <i data-lucide="search" class="me-2"></i>
                        Check Another Application
                    </a>
                </div>

            @else

            {{-- Search Form --}}
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body p-4 p-md-5">

                    @if(session('error'))
                        <div class="alert alert-danger">
                            {{ session('error') }}
                        </div>
                    @endif

                    @if($errors->any())
                        <div class="alert alert-danger">
                            <ul class="mb-0 ps-3">
                                @foreach($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('public.application.status') }}">
                        @csrf

                        {{-- Reference Number --}}
                        <div class="mb-3">
                            <label for="reference_no" class="form-label fw-semibold">
                                Application Reference Number
                            </label>

                            <input
                                type="text"
                                name="reference_no"
                                id="reference_no"
                                class="form-control form-control-lg"
                                value="{{ old('reference_no') }}"
                                placeholder="e.g. APP-2026-ABC123"
                                required
                            >

                            <small class="text-muted">
                                Enter the reference number provided after submitting your application.
                            </small>
                        </div>

                        {{-- Email --}}
                        <div class="mb-4">
                            <label for="email" class="form-label fw-semibold">
                                Email Address
                            </label>

                            <input
                                type="email"
                                name="email"
                                id="email"
                                class="form-control form-control-lg"
                                value="{{ old('email') }}"
                                placeholder="you@example.com"
                                required
                            >
                        </div>

                        {{-- Submit --}}
                        <button type="submit" class="btn btn-primary btn-lg w-100">
                            Check Application Status
                        </button>
                    </form>

                </div>
            </div>

            {{-- Notice --}}
            <div class="text-center mt-4">
                <small class="text-muted">
                    If you have not submitted an application yet,
                    <a href="{{ route('public.application.create') }}">
                        apply here
                    </a>.
                </small>
            </div>

            @endif

        </div>
    </div>
</div>
@endsection