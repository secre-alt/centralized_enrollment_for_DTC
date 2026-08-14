@extends('adminlte::page')

@section('title', 'Applications')

@section('content_header')
    <div>
        <h4 class="mb-0 font-weight-bold" style="color:#1E293B;">Applications</h4>
        <p class="mb-0" style="color:#64748B; font-size:13px;">
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
            <label for="status" class="mr-2 mb-0" style="font-size:13px; font-weight:600; color:#374151;">
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
        <span class="font-weight-bold" style="color:#1E293B;">All Applications</span>
        <span style="font-size:13px; color:#64748B;">{{ $applications->total() }} total</span>
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

                    $statusBadges = [
                        'submitted'         => 'badge-secondary',
                        'under_review'      => 'badge-primary',
                        'revision_required' => 'badge-warning',
                        'approved'          => 'badge-success',
                        'rejected'          => 'badge-danger',
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
                        <span class="badge {{ $statusBadges[$application->status] ?? 'badge-secondary' }}">
                            {{ ucwords(str_replace('_', ' ', $application->status)) }}
                        </span>
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
    <div class="card-footer">
        {{ $applications->links() }}
    </div>
    @endif
</div>

@endsection