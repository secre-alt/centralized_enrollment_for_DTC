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
                    Enter your application reference number and email address
                    to check the current status of your application.
                </p>
            </div>

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

        </div>
    </div>
</div>
@endsection