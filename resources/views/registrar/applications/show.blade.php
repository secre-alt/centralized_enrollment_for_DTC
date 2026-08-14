@extends('adminlte::page')

@section('title', 'Review Application')

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

        $isFinalized = in_array($application->status, ['approved', 'rejected']);
    @endphp

    <div class="d-flex justify-content-between align-items-center flex-wrap">
        <div>
            <h4 class="mb-0 font-weight-bold" style="color:#1E293B;">
                Pre-Enrollment Application
            </h4>
            <p class="mb-0" style="color:#64748B; font-size:13px;">
                Reference No: <strong>{{ $application->reference_no }}</strong>
                &nbsp;·&nbsp;
                <span class="badge {{ $statusBadges[$application->status] ?? 'badge-secondary' }}">
                    {{ ucwords(str_replace('_', ' ', $application->status)) }}
                </span>
                &nbsp;·&nbsp;
                {{ $applicantTypeLabels[$application->academic_status] ?? ucwords(str_replace('_', ' ', $application->academic_status)) }}
                &nbsp;·&nbsp;
                {{ $application->program->name ?? '—' }}
            </p>
        </div>

        <a href="{{ route('registrar.applications.index') }}" class="btn btn-secondary btn-sm">
            ← Back to List
        </a>
    </div>
@endsection

@section('content')

@if (session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

@if (session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif

<div class="row">
    <div class="col-lg-8">

        {{-- ===================================================== --}}
        {{-- SECTION 1 — APPLICANT INFORMATION                      --}}
        {{-- ===================================================== --}}
        <div class="card">
            <div class="card-header">
                <span class="font-weight-bold">Applicant Information</span>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4 mb-2">
                        <div class="text-muted small">Last Name</div>
                        <div>{{ $application->last_name }}</div>
                    </div>
                    <div class="col-md-4 mb-2">
                        <div class="text-muted small">First Name</div>
                        <div>{{ $application->first_name }}</div>
                    </div>
                    <div class="col-md-4 mb-2">
                        <div class="text-muted small">Middle Name</div>
                        <div>{{ $application->middle_name ?: '—' }}</div>
                    </div>

                    <div class="col-md-4 mb-2">
                        <div class="text-muted small">Gender</div>
                        <div>{{ $application->gender ? ucfirst($application->gender) : '—' }}</div>
                    </div>
                    <div class="col-md-4 mb-2">
                        <div class="text-muted small">Birthdate</div>
                        <div>{{ $application->birthdate ? $application->birthdate->format('M d, Y') : '—' }}</div>
                    </div>
                    <div class="col-md-4 mb-2">
                        <div class="text-muted small">Age</div>
                        <div>{{ $application->age ?? '—' }}</div>
                    </div>

                    <div class="col-md-4 mb-2">
                        <div class="text-muted small">Place of Birth</div>
                        <div>{{ $application->birth_place ?: '—' }}</div>
                    </div>
                    <div class="col-md-4 mb-2">
                        <div class="text-muted small">Religion</div>
                        <div>{{ $application->religion ?: '—' }}</div>
                    </div>
                    <div class="col-md-4 mb-2">
                        <div class="text-muted small">Nationality</div>
                        <div>{{ $application->nationality ?: '—' }}</div>
                    </div>

                    <div class="col-md-4 mb-2">
                        <div class="text-muted small">LRN</div>
                        <div>{{ $application->lrn ?: '—' }}</div>
                    </div>
                    <div class="col-md-4 mb-2">
                        <div class="text-muted small">Marital Status</div>
                        <div>{{ $application->marital_status ? ucfirst($application->marital_status) : '—' }}</div>
                    </div>
                    @if($application->marital_status === 'married')
                    <div class="col-md-4 mb-2">
                        <div class="text-muted small">Spouse Name</div>
                        <div>{{ $application->spouse_name ?: '—' }}</div>
                    </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- ===================================================== --}}
        {{-- SECTION 2 — CONTACT INFORMATION                        --}}
        {{-- ===================================================== --}}
        <div class="card">
            <div class="card-header">
                <span class="font-weight-bold">Contact Information</span>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-2">
                        <div class="text-muted small">Email</div>
                        <div>{{ $application->email }}</div>
                    </div>
                    <div class="col-md-6 mb-2">
                        <div class="text-muted small">Phone</div>
                        <div>{{ $application->phone ?: '—' }}</div>
                    </div>

                    <div class="col-md-6 mb-2">
                        <div class="text-muted small">Current Address</div>
                        <div>{{ $application->current_address ?: '—' }}</div>
                    </div>
                    <div class="col-md-3 mb-2">
                        <div class="text-muted small">City / Municipality</div>
                        <div>{{ $application->city ?: '—' }}</div>
                    </div>
                    <div class="col-md-3 mb-2">
                        <div class="text-muted small">Province</div>
                        <div>{{ $application->province ?: '—' }}</div>
                    </div>

                    <div class="col-md-6 mb-2">
                        <div class="text-muted small">Postal Code</div>
                        <div>{{ $application->postal_code ?: '—' }}</div>
                    </div>
                    <div class="col-md-6 mb-2">
                        <div class="text-muted small">Country</div>
                        <div>{{ $application->country ?: '—' }}</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ===================================================== --}}
        {{-- SECTION 3 — FAMILY / PARENT INFORMATION                --}}
        {{-- ===================================================== --}}
        <div class="card">
            <div class="card-header">
                <span class="font-weight-bold">Family / Parent Information</span>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-2">
                        <div class="text-muted small">Father's Name</div>
                        <div>{{ $application->father_name ?: '—' }}</div>
                    </div>
                    <div class="col-md-6 mb-2">
                        <div class="text-muted small">Father's Occupation</div>
                        <div>{{ $application->father_occupation ?: '—' }}</div>
                    </div>

                    <div class="col-md-6 mb-2">
                        <div class="text-muted small">Mother's Name</div>
                        <div>{{ $application->mother_name ?: '—' }}</div>
                    </div>
                    <div class="col-md-6 mb-2">
                        <div class="text-muted small">Mother's Occupation</div>
                        <div>{{ $application->mother_occupation ?: '—' }}</div>
                    </div>

                    <div class="col-md-6 mb-2">
                        <div class="text-muted small">Parent's Address</div>
                        <div>{{ $application->parent_address ?: '—' }}</div>
                    </div>
                    <div class="col-md-6 mb-2">
                        <div class="text-muted small">Parent's Contact</div>
                        <div>{{ $application->parent_contact ?: '—' }}</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ===================================================== --}}
        {{-- SECTION 4 — OTHER INFORMATION                          --}}
        {{-- ===================================================== --}}
        <div class="card">
            <div class="card-header">
                <span class="font-weight-bold">Other Information</span>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4 mb-2">
                        <div class="text-muted small">Occupation</div>
                        <div>{{ $application->occupation ?: '—' }}</div>
                    </div>
                    <div class="col-md-4 mb-2">
                        <div class="text-muted small">Disability</div>
                        <div>{{ $application->disability ?: '—' }}</div>
                    </div>
                    <div class="col-md-4 mb-2">
                        <div class="text-muted small">PWD ID</div>
                        <div>{{ $application->pwd_id ?: '—' }}</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ===================================================== --}}
        {{-- SECTION 5 — SUPPORTING DOCUMENTS                       --}}
        {{-- ===================================================== --}}
        <div class="card">
            <div class="card-header">
                <span class="font-weight-bold">Supporting Documents</span>
            </div>
            <div class="card-body p-0">
                @php
                    $documentLabels = [
                        'form_138'                => 'Form 138 / Equivalent',
                        'good_moral_certificate'   => 'Certificate of Good Moral Character',
                        'birth_certificate'        => 'PSA Birth Certificate',
                        'marriage_certificate'     => 'Marriage Certificate (PSA)',
                        'transfer_credentials'     => 'Certificate of Transfer Credentials',
                        'transcript_of_records'    => 'Transcript of Records',
                    ];
                @endphp

                @forelse ($application->documents as $document)
                    <div class="d-flex justify-content-between align-items-center px-3 py-2 {{ !$loop->last ? 'border-bottom' : '' }}">
                        <div>
                            <div class="font-weight-bold" style="font-size:13px;">
                                {{ $documentLabels[$document->document_type] ?? ucwords(str_replace('_', ' ', $document->document_type)) }}
                            </div>
                            <div class="text-muted" style="font-size:12px;">
                                {{ $document->original_name }}
                                &nbsp;·&nbsp;
                                Uploaded {{ $document->created_at->format('M d, Y h:i A') }}
                                @if($document->size_bytes)
                                    &nbsp;·&nbsp;
                                    @php
                                        $sizeKb = $document->size_bytes / 1024;
                                    @endphp
                                    {{ $sizeKb >= 1024 ? number_format($sizeKb / 1024, 2) . ' MB' : number_format($sizeKb, 1) . ' KB' }}
                                @endif
                            </div>
                        </div>

                        <a href="{{ route('registrar.applications.documents.show', [$application, $document]) }}"
                           class="btn btn-sm btn-outline-primary">
                            View / Download
                        </a>
                    </div>
                @empty
                    <div class="text-center text-muted py-4" style="font-size:13px;">
                        No documents were uploaded with this application.
                    </div>
                @endforelse
            </div>
        </div>

        {{-- ===================================================== --}}
        {{-- SECTION 6 — PHYSICAL REQUIREMENTS                      --}}
        {{-- ===================================================== --}}
        <div class="card">
            <div class="card-header">
                <span class="font-weight-bold">Physical Requirements</span>
            </div>
            <div class="card-body">

                @if(in_array($application->academic_status, ['new_student', 'transferee']))

                    <div class="alert alert-warning mb-3">
                        These physical requirements apply regardless of whether the
                        applicant uploaded a digital copy above.
                    </div>

                    @if($application->academic_status === 'new_student')
                        <ul class="mb-0 pl-4">
                            <li>Form 138 or equivalent</li>
                            <li>Certificate of Good Moral Character</li>
                            <li>PSA Birth Certificate</li>
                            @if($application->marital_status === 'married')
                                <li>Marriage Certificate — if applicable</li>
                            @endif
                            <li>2 copies recent 2×2 picture</li>
                            <li>2 copies recent 1×1 picture</li>
                            <li>2 pcs Long Brown Envelope</li>
                        </ul>
                    @elseif($application->academic_status === 'transferee')
                        <ul class="mb-0 pl-4">
                            <li>Certificate of Transfer Credentials</li>
                            <li>Transcript of Records</li>
                            <li>Certificate of Good Moral Character</li>
                            <li>PSA Birth Certificate</li>
                            @if($application->marital_status === 'married')
                                <li>Marriage Certificate — if applicable</li>
                            @endif
                            <li>2 copies recent 2×2 picture</li>
                            <li>2 copies recent 1×1 picture</li>
                            <li>2 pcs Long Brown Envelope</li>
                        </ul>
                    @endif

                @elseif($application->academic_status === 'cross_enrollee')

                    <div class="alert alert-info mb-0">
                        Requirements for Cross-Enrollees are subject to confirmation
                        by the Registrar's Office.
                    </div>

                @else

                    <div class="text-muted" style="font-size:13px;">
                        No additional physical documents are required at this
                        pre-enrollment stage for this applicant type.
                    </div>

                @endif

            </div>
        </div>

    </div>

    <div class="col-lg-4">

        {{-- ===================================================== --}}
        {{-- SECTION 7 — REVIEW INFORMATION                         --}}
        {{-- ===================================================== --}}
        <div class="card">
            <div class="card-header">
                <span class="font-weight-bold">Review Information</span>
            </div>
            <div class="card-body">
                @if($application->reviewed_at)
                    <div class="mb-2">
                        <div class="text-muted small">Reviewer</div>
                        <div>{{ $application->reviewer->name ?? '—' }}</div>
                    </div>
                    <div class="mb-2">
                        <div class="text-muted small">Review Date</div>
                        <div>{{ $application->reviewed_at->format('M d, Y h:i A') }}</div>
                    </div>
                    <div class="mb-0">
                        <div class="text-muted small">Registrar Remarks</div>
                        <div>{{ $application->remarks ?: '—' }}</div>
                    </div>
                @else
                    <div class="text-muted" style="font-size:13px;">
                        Not yet reviewed
                    </div>
                @endif
            </div>
        </div>

        {{-- Approved account info --}}
        @if($application->status === 'approved')
        <div class="card">
            <div class="card-header">
                <span class="font-weight-bold">Linked Account</span>
            </div>
            <div class="card-body">
                <div class="mb-2">
                    <div class="text-muted small">Name</div>
                    <div>{{ $application->user->name ?? '—' }}</div>
                </div>
                <div class="mb-2">
                    <div class="text-muted small">Email</div>
                    <div>{{ $application->user->email ?? '—' }}</div>
                </div>
                <div class="mb-0">
                    <div class="text-muted small">Status</div>
                    <div>
                        <span class="badge badge-success">
                            {{ $application->user->status ? ucfirst($application->user->status) : '—' }}
                        </span>
                    </div>
                </div>
            </div>
        </div>
        @endif

        {{-- ===================================================== --}}
        {{-- SECTION 8 — ACTIONS                                    --}}
        {{-- ===================================================== --}}
        @unless($isFinalized)
        <div class="card">
            <div class="card-header">
                <span class="font-weight-bold">Registrar Actions</span>
            </div>
            <div class="card-body">

                <form method="POST" action="{{ route('registrar.applications.approve', $application) }}" class="mb-2">
                    @csrf
                    <button type="submit" class="btn btn-success btn-block"
                            onclick="return confirm('Approve this application and create the applicant account?');">
                        Approve
                    </button>
                </form>

                <button type="button" class="btn btn-warning btn-block mb-2" data-toggle="modal" data-target="#revisionModal">
                    Request Revision
                </button>

                <button type="button" class="btn btn-danger btn-block" data-toggle="modal" data-target="#rejectModal">
                    Reject
                </button>

            </div>
        </div>
        @else
        <div class="card">
            <div class="card-body">
                <div class="text-muted" style="font-size:13px;">
                    @if($application->status === 'approved')
                        This application has already been approved. No further action is available.
                    @else
                        This application has already been rejected.
                    @endif
                </div>
            </div>
        </div>
        @endunless

    </div>
</div>

{{-- Reject Modal --}}
<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('registrar.applications.reject', $application) }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Reject Application</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <label>Reason / Remarks</label>
                    <textarea name="remarks" class="form-control" rows="3" maxlength="500" required></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Reject Application</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Revision Modal --}}
<div class="modal fade" id="revisionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('registrar.applications.revision', $application) }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Request Revision</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <label>Revision Instructions / Required Corrections</label>
                    <textarea name="remarks" class="form-control" rows="3" maxlength="500" required></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">Request Revision</button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection