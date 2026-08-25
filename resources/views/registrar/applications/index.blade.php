@extends('adminlte::page')
@extends('partials.navbar')
@section('title', 'Applications')

@section('content_header')
    <div>
        <h4 class="mb-0 font-weight-bold u-text" >Applications</h4>
        <p class="mb-0 u-text-secondary-sm" >
            Pre-enrollment applications for Registrar review.
        </p>
    </div>
@endsection

@section('content')

@if (session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

@if (session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif

{{-- Status Filter --}}
<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" action="{{ route('registrar.applications.index') }}" class="form-inline">
            <label for="status" class="mr-2 mb-0 u-text-sm-bold-primary" >
                Status
            </label>

            <select name="status" id="status" class="form-control form-control-sm mr-2" onchange="this.form.submit()">
                <option value="" {{ request('status') === null || request('status') === '' ? 'selected' : '' }}>
                    All
                </option>
                <option value="submitted" {{ request('status') === 'submitted' ? 'selected' : '' }}>
                    Submitted
                </option>
                <option value="under_review" {{ request('status') === 'under_review' ? 'selected' : '' }}>
                    Under Review
                </option>
                <option value="revision_required" {{ request('status') === 'revision_required' ? 'selected' : '' }}>
                    Revision Required
                </option>
                <option value="approved" {{ request('status') === 'approved' ? 'selected' : '' }}>
                    Approved
                </option>
                <option value="rejected" {{ request('status') === 'rejected' ? 'selected' : '' }}>
                    Rejected
                </option>
            </select>

            <button type="submit" class="btn btn-sm btn-primary">
                Filter
            </button>

            @if(request()->filled('status'))
                <a href="{{ route('registrar.applications.index') }}" class="btn btn-sm btn-link">
                    Clear
                </a>
            @endif
        </form>
    </div>
</div>

{{-- Applications Table --}}
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span class="font-weight-bold u-text" >All Applications</span>
        <span  class="u-text-secondary-sm">{{ $applications->total() }} total</span>
    </div>

    <div class="card-body p-0">
        <div class="table-responsive">
        <table class="table table-bordered mb-0">
            <thead>
                <tr>
                    <th>Reference No.</th>
                    <th>Applicant</th>
                    <th>Applicant Type</th>
                    <th>Intended Program</th>
                    <th>Email</th>
                    <th>Status</th>
                    <th>Submitted</th>
                    <th>Action</th>
                </tr>
            </thead>

            <tbody>
                @forelse ($applications as $application)
                @php
                    $applicantTypeLabels = [
                        'new_student'    => 'New Student / First Year',
                        'transferee'     => 'Transferee',
                        'shiftee'        => 'Shiftee',
                        'returnee'       => 'Returnee / Readmitted',
                        'cross_enrollee' => 'Cross-Enrollee',
                    ];

                    $statusVariants = [
                        'submitted'         => 'neutral',
                        'under_review'      => 'warning',
                        'revision_required' => 'warning',
                        'approved'          => 'success',
                        'rejected'          => 'danger',
                    ];
                @endphp
                <tr>
                    <td>{{ $application->reference_no }}</td>
                    <td>
                        {{ $application->first_name }}
                        {{ $application->middle_name ? $application->middle_name . ' ' : '' }}
                        {{ $application->last_name }}
                    </td>
                    <td>{{ $applicantTypeLabels[$application->academic_status] ?? ucwords(str_replace('_', ' ', $application->academic_status)) }}</td>
                    <td>{{ $application->program->name ?? '—' }}</td>
                    <td>{{ $application->email }}</td>
                    <td>
                        <x-dtc.status-badge :status="$application->status" :variant="$statusVariants[$application->status] ?? 'neutral'" />
                    </td>
                    <td>{{ $application->created_at->format('M d, Y h:i A') }}</td>
                    <td>
                        <a href="{{ route('registrar.applications.show', $application) }}" class="btn btn-sm btn-primary">
                            Review
                        </a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="text-center text-muted py-4">
                        @if(request()->filled('status'))
                            No applications found for the selected status.
                        @else
                            No applications found.
                        @endif
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
        </div>
    </div>

    @if($applications->hasPages())
    <div class="card-footer u-panel-footer" >
        <div class="d-flex justify-content-between align-items-center flex-wrap u-gap-15" >
            <small  class="u-text-secondary">
                Showing
                <strong>{{ $applications->firstItem() }}</strong>
                to
                <strong>{{ $applications->lastItem() }}</strong>
                of
                <strong>{{ $applications->total() }}</strong>
                applications
            </small>

            <div>
                {{ $applications->onEachSide(1)->links('pagination::bootstrap-4') }}
            </div>
        </div>
    </div>
    @endif
</div>

@endsection