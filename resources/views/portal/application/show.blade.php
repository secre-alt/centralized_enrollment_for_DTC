@extends('adminlte::page')
@include('partials.navbar')

@section('title', 'My Application')

@section('content_header')
    @php
        $statusBadges = [
            'submitted'         => 'badge-secondary',
            'under_review'      => 'badge-primary',
            'revision_required' => 'badge-warning',
            'approved'          => 'badge-success',
            'rejected'          => 'badge-danger',
        ];

        $applicantTypeLabels = [
            'new_student'    => 'New Student / First Year',
            'transferee'     => 'Transferee',
            'shiftee'        => 'Shiftee',
            'returnee'       => 'Returnee / Readmitted',
            'cross_enrollee' => 'Cross-Enrollee',
        ];
    @endphp

    <div class="d-flex justify-content-between align-items-center flex-wrap">
        <div>
            <h4 class="mb-0 font-weight-bold u-text" >
                My Application
            </h4>
            <p class="mb-0 u-text-secondary-sm" >
                View your submitted pre-enrollment application and its current status.
            </p>
        </div>

        <a href="{{ route('portal.dashboard') }}" class="btn btn-secondary btn-sm">
            ← Back to Dashboard
        </a>
    </div>
@endsection

@section('content')

<div class="row">
    <div class="col-lg-8">

        {{-- ===================================================== --}}
        {{-- SECTION 1 — APPLICATION SUMMARY                        --}}
        {{-- ===================================================== --}}
        <div class="card">
            <div class="card-header">
                <span class="font-weight-bold">Pre-Enrollment Application</span>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <div class="text-muted small">Reference Number</div>
                        <div class="font-weight-bold">{{ $application->reference_no }}</div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="text-muted small">Status</div>
                        <div>
                            <span class="badge {{ $statusBadges[$application->status] ?? 'badge-secondary' }}">
                                {{ ucwords(str_replace('_', ' ', $application->status)) }}
                            </span>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="text-muted small">Applicant Type</div>
                        <div>{{ $applicantTypeLabels[$application->academic_status] ?? ucwords(str_replace('_', ' ', $application->academic_status)) }}</div>
                    </div>

                    <div class="col-md-4 mb-3">
                        <div class="text-muted small">Program Applied For</div>
                        <div>{{ $application->program->name ?? 'Not provided' }}</div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="text-muted small">Date Submitted</div>
                        <div>{{ $application->created_at ? $application->created_at->format('M d, Y h:i A') : 'Not provided' }}</div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="text-muted small">Date Reviewed</div>
                        <div>{{ $application->reviewed_at ? $application->reviewed_at->format('M d, Y h:i A') : 'Not yet reviewed' }}</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ===================================================== --}}
        {{-- SECTION 2 — PERSONAL INFORMATION                       --}}
        {{-- ===================================================== --}}
        <div class="card">
            <div class="card-header">
                <span class="font-weight-bold">Personal Information</span>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <div class="text-muted small">Full Name</div>
                        <div>
                            {{ $application->first_name }}
                            {{ $application->middle_name ? $application->middle_name . ' ' : '' }}
                            {{ $application->last_name }}
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="text-muted small">Gender</div>
                        <div>{{ $application->gender ? ucfirst($application->gender) : 'Not provided' }}</div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="text-muted small">Age</div>
                        <div>{{ $application->age ?? 'Not provided' }}</div>
                    </div>

                    <div class="col-md-4 mb-3">
                        <div class="text-muted small">Birthdate</div>
                        <div>{{ $application->birthdate ? $application->birthdate->format('M d, Y') : 'Not provided' }}</div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="text-muted small">Place of Birth</div>
                        <div>{{ $application->birth_place ?: 'Not provided' }}</div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="text-muted small">Religion</div>
                        <div>{{ $application->religion ?: 'Not provided' }}</div>
                    </div>

                    <div class="col-md-3 mb-3">
                        <div class="text-muted small">Nationality</div>
                        <div>{{ $application->nationality ?: 'Not provided' }}</div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="text-muted small">LRN</div>
                        <div>{{ $application->lrn ?: 'Not provided' }}</div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="text-muted small">Marital Status</div>
                        <div>{{ $application->marital_status ? ucfirst($application->marital_status) : 'Not provided' }}</div>
                    </div>
                    @if($application->marital_status === 'married')
                    <div class="col-md-3 mb-3">
                        <div class="text-muted small">Spouse Name</div>
                        <div>{{ $application->spouse_name ?: 'Not provided' }}</div>
                    </div>
                    @endif

                    <div class="col-md-4 mb-3">
                        <div class="text-muted small">Disability</div>
                        <div>{{ $application->disability ?: 'Not provided' }}</div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="text-muted small">PWD ID</div>
                        <div>{{ $application->pwd_id ?: 'Not provided' }}</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ===================================================== --}}
        {{-- SECTION 3 — CONTACT INFORMATION                        --}}
        {{-- ===================================================== --}}
        <div class="card">
            <div class="card-header">
                <span class="font-weight-bold">Contact Information</span>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <div class="text-muted small">Email</div>
                        <div>{{ $application->email }}</div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <div class="text-muted small">Contact Number</div>
                        <div>{{ $application->phone ?: 'Not provided' }}</div>
                    </div>

                    <div class="col-md-6 mb-3">
                        <div class="text-muted small">Current Address</div>
                        <div>{{ $application->current_address ?: 'Not provided' }}</div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="text-muted small">City / Municipality</div>
                        <div>{{ $application->city ?: 'Not provided' }}</div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="text-muted small">Province</div>
                        <div>{{ $application->province ?: 'Not provided' }}</div>
                    </div>

                    <div class="col-md-6 mb-3">
                        <div class="text-muted small">Postal Code</div>
                        <div>{{ $application->postal_code ?: 'Not provided' }}</div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <div class="text-muted small">Country</div>
                        <div>{{ $application->country ?: 'Not provided' }}</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ===================================================== --}}
        {{-- SECTION 4 — FAMILY / PARENT INFORMATION                --}}
        {{-- ===================================================== --}}
        <div class="card">
            <div class="card-header">
                <span class="font-weight-bold">Family / Parent Information</span>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <div class="text-muted small">Father's Name</div>
                        <div>{{ $application->father_name ?: 'Not provided' }}</div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <div class="text-muted small">Father's Occupation</div>
                        <div>{{ $application->father_occupation ?: 'Not provided' }}</div>
                    </div>

                    <div class="col-md-6 mb-3">
                        <div class="text-muted small">Mother's Name</div>
                        <div>{{ $application->mother_name ?: 'Not provided' }}</div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <div class="text-muted small">Mother's Occupation</div>
                        <div>{{ $application->mother_occupation ?: 'Not provided' }}</div>
                    </div>

                    <div class="col-md-6 mb-3">
                        <div class="text-muted small">Parent's Address</div>
                        <div>{{ $application->parent_address ?: 'Not provided' }}</div>
                    </div>
                    <div class="col-md-6 mb-0">
                        <div class="text-muted small">Parent's Contact</div>
                        <div>{{ $application->parent_contact ?: 'Not provided' }}</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ===================================================== --}}
        {{-- SECTION 6 — SUPPORTING DOCUMENTS                       --}}
        {{-- ===================================================== --}}
        <div class="card">
            <div class="card-header">
                <span class="font-weight-bold">Supporting Documents</span>
            </div>
            <div class="card-body p-0">
                @php
                    $documentLabels = [
                        'form_138'              => 'Form 138 / Equivalent',
                        'good_moral_certificate' => 'Certificate of Good Moral Character',
                        'birth_certificate'      => 'PSA Birth Certificate',
                        'marriage_certificate'   => 'Marriage Certificate (PSA)',
                        'transfer_credentials'   => 'Certificate of Transfer Credentials',
                        'transcript_of_records'  => 'Transcript of Records',
                    ];
                @endphp

                @forelse ($application->documents as $document)
                    <div class="d-flex justify-content-between align-items-center px-3 py-2 {{ !$loop->last ? 'border-bottom' : '' }}">
                        <div>
                            <div class="font-weight-bold u-text-sm" >
                                {{ $documentLabels[$document->document_type] ?? ucwords(str_replace('_', ' ', $document->document_type)) }}
                            </div>
                            <div class="text-muted u-text-xxs" >
                                {{ $document->original_name }}
                                @if($document->size_bytes)
                                    &nbsp;·&nbsp;
                                    @php
                                        $sizeKb = $document->size_bytes / 1024;
                                    @endphp
                                    {{ $sizeKb >= 1024 ? number_format($sizeKb / 1024, 2) . ' MB' : number_format($sizeKb, 1) . ' KB' }}
                                @endif
                            </div>
                        </div>

                        <span class="badge badge-success">
                            <i class="fas fa-check"></i> Submitted
                        </span>
                    </div>
                @empty
                    <div class="text-center text-muted py-4 u-text-sm" >
                        No supporting documents found.
                    </div>
                @endforelse
            </div>
        </div>

    </div>

    <div class="col-lg-4">

        {{-- ===================================================== --}}
        {{-- SECTION 7 — REGISTRAR REVIEW                           --}}
        {{-- ===================================================== --}}
        <div class="card">
            <div class="card-header">
                <span class="font-weight-bold">Registrar Review</span>
            </div>
            <div class="card-body">
                @if($application->reviewed_at)
                    <div class="mb-3">
                        <div class="text-muted small">Reviewed By</div>
                        <div>{{ $application->reviewer->name ?? 'Not provided' }}</div>
                    </div>
                    <div class="mb-3">
                        <div class="text-muted small">Reviewed Date</div>
                        <div>{{ $application->reviewed_at->format('M d, Y h:i A') }}</div>
                    </div>
                    <div class="mb-0">
                        <div class="text-muted small">Registrar Remarks</div>
                        <div>{{ $application->remarks ?: 'No remarks provided.' }}</div>
                    </div>
                @else
                    <div class="text-muted u-text-sm" >
                        Not yet reviewed
                    </div>
                @endif
            </div>
        </div>

    </div>
</div>

@endsection