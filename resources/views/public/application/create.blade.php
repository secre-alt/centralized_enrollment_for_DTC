@extends('layouts.public')

@section('title', 'Pre-Enrollment Application')

@section('content')

    <div class="container">
        <div class="container-fluid px-0">

    {{-- Page Header --}}
    <div class="d-flex justify-content-between align-items-start mb-4 gap-2">
        <div>
            <h1 class="mb-1">Pre-Enrollment Application</h1>
            <p class="text-muted mb-0">
                Submit your application for preliminary screening.
            </p>
        </div>
        <div class="page-header-actions">
            <a href="{{ route('landing') }}" class="btn btn-outline-secondary back-btn" aria-label="Back">
                <i class="fas fa-arrow-left" aria-hidden="true"></i>
                <span class="back-btn-label">Back</span>
            </a>
        </div>
    </div>

    {{-- Validation Errors --}}
    @if ($errors->any())
        <div class="alert alert-danger shadow-sm">
            <div class="font-weight-bold mb-2">
                <i class="fas fa-exclamation-circle mr-1"></i>
                Please correct the following:
            </div>

            <ul class="mb-0 pl-4">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Preliminary Screening Notice --}}
    <div class="alert alert-warning shadow-sm border-0">
        <div class="d-flex">
            <div class="mr-3">
                <i class="fas fa-info-circle fa-lg"></i>
            </div>

            <div>
                <strong>Important:</strong>
                Uploaded documents are for preliminary screening only.
                You must still present the required original/physical documents
                to the Registrar's Office for verification before official enrollment.
            </div>
        </div>
    </div>

    <form
        action="{{ route('public.application.store') }}"
        method="POST"
        enctype="multipart/form-data"
        id="applicationForm"
    >
        @csrf

        {{-- ============================================================= --}}
        {{-- 1. APPLICANT TYPE & PROGRAM                                   --}}
        {{-- ============================================================= --}}

        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white border-bottom">
                <h5 class="mb-0 font-weight-bold">
                    <i class="fas fa-user-graduate text-primary mr-2"></i>
                    Applicant Type & Program
                </h5>
                <small class="text-muted">
                    Tell us what type of applicant you are and which program you intend to apply for.
                </small>
            </div>

            <div class="card-body">

                <div class="row">

                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="academic_status">
                                Applicant Type
                                <span class="text-danger">*</span>
                            </label>

                            <select
                                name="academic_status"
                                id="academic_status"
                                class="form-control @error('academic_status') is-invalid @enderror"
                                required
                            >
                                <option value="">Select applicant type</option>

                                <option value="new_student"
                                    {{ old('academic_status') === 'new_student' ? 'selected' : '' }}>
                                    New Student / First Year
                                </option>

                                <option value="transferee"
                                    {{ old('academic_status') === 'transferee' ? 'selected' : '' }}>
                                    Transferee
                                </option>

                                <option value="shiftee"
                                    {{ old('academic_status') === 'shiftee' ? 'selected' : '' }}>
                                    Shiftee
                                </option>

                                <option value="returnee"
                                    {{ old('academic_status') === 'returnee' ? 'selected' : '' }}>
                                    Returnee / Readmitted
                                </option>

                                <option value="cross_enrollee"
                                    {{ old('academic_status') === 'cross_enrollee' ? 'selected' : '' }}>
                                    Cross-Enrollee
                                </option>
                            </select>

                            @error('academic_status')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="program_id">
                                Intended Program
                                <span class="text-danger">*</span>
                            </label>

                            <select
                                name="program_id"
                                id="program_id"
                                class="form-control @error('program_id') is-invalid @enderror"
                                required
                            >
                                <option value="">Select program</option>

                                @foreach ($programs as $program)
                                    <option
                                        value="{{ $program->id }}"
                                        {{ old('program_id') == $program->id ? 'selected' : '' }}
                                    >
                                        {{ $program->name }}
                                    </option>
                                @endforeach
                            </select>

                            @error('program_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                </div>

                {{-- Cross-Enrollee Notice --}}
                <div
                    id="crossEnrolleeNotice"
                    class="alert alert-info mb-0 d-none"
                >
                    <i class="fas fa-info-circle mr-1"></i>

                    <strong>Cross-Enrollee:</strong>
                    Requirements for Cross-Enrollees are subject to confirmation
                    by the Registrar's Office. Please proceed with your application,
                    and the Registrar will provide further instructions.
                </div>

            </div>
        </div>


        {{-- ============================================================= --}}
        {{-- 2. PERSONAL INFORMATION                                       --}}
        {{-- ============================================================= --}}

        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white border-bottom">
                <h5 class="mb-0 font-weight-bold">
                    <i class="fas fa-id-card text-primary mr-2"></i>
                    Personal Information
                </h5>
                <small class="text-muted">
                    Please provide your personal information accurately.
                </small>
            </div>

            <div class="card-body">

                <div class="row">

                    {{-- Last Name --}}
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="last_name">
                                Last Name
                                <span class="text-danger">*</span>
                            </label>

                            <input
                                type="text"
                                name="last_name"
                                id="last_name"
                                class="form-control @error('last_name') is-invalid @enderror"
                                value="{{ old('last_name') }}"
                                required
                                placeholder="Dela Cruz"
                            >

                            @error('last_name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    {{-- First Name --}}
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="first_name">
                                First Name
                                <span class="text-danger">*</span>
                            </label>

                            <input
                                type="text"
                                name="first_name"
                                id="first_name"
                                class="form-control @error('first_name') is-invalid @enderror"
                                value="{{ old('first_name') }}"
                                required
                                placeholder="Juan"
                            >

                            @error('first_name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    {{-- Middle Name --}}
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="middle_name">
                                Middle Name
                                <small class="text-muted">(Optional)</small>
                            </label>

                            <input
                                type="text"
                                name="middle_name"
                                id="middle_name"
                                class="form-control @error('middle_name') is-invalid @enderror"
                                value="{{ old('middle_name') }}"
                                placeholder="Santos"
                            >

                            @error('middle_name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                </div>


                <div class="row">

                    {{-- Gender --}}
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="gender">
                                Gender
                                <span class="text-danger">*</span>
                            </label>

                            <select
                                name="gender"
                                id="gender"
                                class="form-control @error('gender') is-invalid @enderror"
                                required
                            >
                                <option value="">Select gender</option>
                                <option value="male" {{ old('gender') === 'male' ? 'selected' : '' }}>
                                    Male
                                </option>
                                <option value="female" {{ old('gender') === 'female' ? 'selected' : '' }}>
                                    Female
                                </option>
                            </select>

                            @error('gender')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    {{-- Birthdate --}}
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="birthdate">
                                Date of Birth
                                <span class="text-danger">*</span>
                            </label>

                            <input
                                type="date"
                                name="birthdate"
                                id="birthdate"
                                class="form-control @error('birthdate') is-invalid @enderror"
                                value="{{ old('birthdate') }}"
                                required
                            >

                            @error('birthdate')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    {{-- Birth Place --}}
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="birth_place">
                                Place of Birth
                                <small class="text-muted">(Optional)</small>
                            </label>

                            <input
                                type="text"
                                name="birth_place"
                                id="birth_place"
                                class="form-control"
                                value="{{ old('birth_place') }}"
                                placeholder="City / Municipality, Province"
                            >
                        </div>
                    </div>

                </div>


                <div class="row">

                    {{-- Religion --}}
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="religion">
                                Religion
                                <small class="text-muted">(Optional)</small>
                            </label>

                            <input
                                type="text"
                                name="religion"
                                id="religion"
                                class="form-control"
                                value="{{ old('religion') }}"
                                placeholder="Roman Catholic"
                            >
                        </div>
                    </div>

                    {{-- Nationality --}}
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="nationality">
                                Nationality
                                <small class="text-muted">(Optional)</small>
                            </label>

                            <input
                                type="text"
                                name="nationality"
                                id="nationality"
                                class="form-control"
                                value="{{ old('nationality', 'Filipino') }}"
                                placeholder="Filipino"
                            >
                        </div>
                    </div>

                    {{-- LRN --}}
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="lrn">
                                Learner Reference Number (LRN)
                                <small class="text-muted">(Optional)</small>
                            </label>

                            <input
                                type="text"
                                name="lrn"
                                id="lrn"
                                class="form-control"
                                value="{{ old('lrn') }}"
                                maxlength="12"
                                placeholder="e.g. 136041123456"
                            >

                            <small class="form-text text-muted">
                                Applicable primarily to learners from the basic education system.
                            </small>
                        </div>
                    </div>

                </div>


                <div class="row">

                    {{-- Marital Status --}}
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="marital_status">
                                Marital Status
                                <small class="text-muted">(Optional)</small>
                            </label>

                            <select
                                name="marital_status"
                                id="marital_status"
                                class="form-control"
                            >
                                <option value="">Select status</option>
                                <option value="single" {{ old('marital_status') === 'single' ? 'selected' : '' }}>
                                    Single
                                </option>
                                <option value="married" {{ old('marital_status') === 'married' ? 'selected' : '' }}>
                                    Married
                                </option>
                                <option value="divorced" {{ old('marital_status') === 'divorced' ? 'selected' : '' }}>
                                    Divorced
                                </option>
                                <option value="widowed" {{ old('marital_status') === 'widowed' ? 'selected' : '' }}>
                                    Widowed
                                </option>
                            </select>
                        </div>
                    </div>

                    {{-- Spouse --}}
                    <div
                        class="col-md-8"
                        id="spouseField"
                    >
                        <div class="form-group">
                            <label for="spouse_name">
                                Spouse Name
                                <small class="text-muted">(Optional)</small>
                            </label>

                            <input
                                type="text"
                                name="spouse_name"
                                id="spouse_name"
                                class="form-control"
                                value="{{ old('spouse_name') }}"
                                placeholder="Spouse's full name"
                            >
                        </div>
                    </div>

                </div>

            </div>
        </div>


        {{-- ============================================================= --}}
        {{-- 3. CONTACT INFORMATION                                        --}}
        {{-- ============================================================= --}}

        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white border-bottom">
                <h5 class="mb-0 font-weight-bold">
                    <i class="fas fa-address-book text-primary mr-2"></i>
                    Contact Information
                </h5>
            </div>

            <div class="card-body">

                <div class="row">

                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="email">
                                Email Address
                                <span class="text-danger">*</span>
                            </label>

                            <input
                                type="email"
                                name="email"
                                id="email"
                                class="form-control @error('email') is-invalid @enderror"
                                value="{{ old('email') }}"
                                required
                            >

                            @error('email')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="phone">
                                Contact Number
                                <small class="text-muted">(Optional)</small>
                            </label>

                            <input
                                type="text"
                                name="phone"
                                id="phone"
                                class="form-control"
                                value="{{ old('phone') }}"
                                placeholder="09XXXXXXXXX"
                            >
                        </div>
                    </div>

                </div>


                <div class="form-group">
                    <label for="current_address">
                        Current Address
                        <small class="text-muted">(Optional)</small>
                    </label>

                    <input
                        type="text"
                        name="current_address"
                        id="current_address"
                        class="form-control"
                        value="{{ old('current_address') }}"
                        placeholder="House No., Street, Barangay"
                    >
                </div>


                <div class="row">

                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="city">City / Municipality</label>

                            <input
                                type="text"
                                name="city"
                                id="city"
                                class="form-control"
                                value="{{ old('city') }}"
                                placeholder="Danao City"
                            >
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="province">Province</label>

                            <input
                                type="text"
                                name="province"
                                id="province"
                                class="form-control"
                                value="{{ old('province') }}"
                                placeholder="Cebu"
                            >
                        </div>
                    </div>

                    <div class="col-md-2">
                        <div class="form-group">
                            <label for="postal_code">Postal Code</label>

                            <input
                                type="text"
                                name="postal_code"
                                id="postal_code"
                                class="form-control"
                                value="{{ old('postal_code') }}"
                                placeholder="6004"
                            >
                        </div>
                    </div>

                    <div class="col-md-2">
                        <div class="form-group">
                            <label for="country">Country</label>

                            <input
                                type="text"
                                name="country"
                                id="country"
                                class="form-control"
                                value="{{ old('country', 'Philippines') }}"
                                placeholder="Philippines"
                            >
                        </div>
                    </div>

                </div>

            </div>
        </div>


        {{-- ============================================================= --}}
        {{-- 4. FAMILY / PARENT INFORMATION                                --}}
        {{-- ============================================================= --}}

        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white border-bottom">
                <h5 class="mb-0 font-weight-bold">
                    <i class="fas fa-users text-primary mr-2"></i>
                    Family / Parent Information
                </h5>
                <small class="text-muted">
                    Optional information for the Registrar's records.
                </small>
            </div>

            <div class="card-body">

                <div class="row">

                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="father_name">Father's Name</label>

                            <input
                                type="text"
                                name="father_name"
                                id="father_name"
                                class="form-control"
                                value="{{ old('father_name') }}"
                                placeholder="Father's full name"
                            >
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="father_occupation">Father's Occupation</label>

                            <input
                                type="text"
                                name="father_occupation"
                                id="father_occupation"
                                class="form-control"
                                value="{{ old('father_occupation') }}"
                                placeholder="e.g. Driver, Farmer, OFW"
                            >
                        </div>
                    </div>

                </div>


                <div class="row">

                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="mother_name">Mother's Name</label>

                            <input
                                type="text"
                                name="mother_name"
                                id="mother_name"
                                class="form-control"
                                value="{{ old('mother_name') }}"
                                placeholder="Mother's full name"
                            >
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="mother_occupation">Mother's Occupation</label>

                            <input
                                type="text"
                                name="mother_occupation"
                                id="mother_occupation"
                                class="form-control"
                                value="{{ old('mother_occupation') }}"
                                placeholder="e.g. Housewife, Teacher, OFW"
                            >
                        </div>
                    </div>

                </div>


                <div class="row">

                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="parent_address">Parent's Address</label>

                            <input
                                type="text"
                                name="parent_address"
                                id="parent_address"
                                class="form-control"
                                value="{{ old('parent_address') }}"
                                placeholder="Same as current address, if applicable"
                            >
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="parent_contact">Parent's Contact</label>

                            <input
                                type="text"
                                name="parent_contact"
                                id="parent_contact"
                                class="form-control"
                                value="{{ old('parent_contact') }}"
                                placeholder="09XXXXXXXXX"
                            >
                        </div>
                    </div>

                </div>

            </div>
        </div>


        {{-- ============================================================= --}}
        {{-- 5. OTHER INFORMATION                                           --}}
        {{-- ============================================================= --}}

        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white border-bottom">
                <h5 class="mb-0 font-weight-bold">
                    <i class="fas fa-clipboard-list text-primary mr-2"></i>
                    Other Information
                </h5>
            </div>

            <div class="card-body">

                <div class="row">

                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="occupation">
                                Occupation
                                <small class="text-muted">(Optional)</small>
                            </label>

                            <input
                                type="text"
                                name="occupation"
                                id="occupation"
                                class="form-control"
                                value="{{ old('occupation') }}"
                                placeholder="e.g. Student, Employed, Self-employed"
                            >
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="disability">
                                Disability
                                <small class="text-muted">(Optional)</small>
                            </label>

                            <input
                                type="text"
                                name="disability"
                                id="disability"
                                class="form-control"
                                value="{{ old('disability') }}"
                                placeholder="If applicable"
                            >
                        </div>
                    </div>

                </div>


                <div
                    class="form-group d-none"
                    id="pwdIdField"
                >
                    <label for="pwd_id">
                        PWD ID Number
                        <small class="text-muted">(Optional)</small>
                    </label>

                    <input
                        type="text"
                        name="pwd_id"
                        id="pwd_id"
                        class="form-control"
                        value="{{ old('pwd_id') }}"
                        placeholder="PWD ID number, if applicable"
                    >
                </div>

            </div>
        </div>


        {{-- ============================================================= --}}
        {{-- 6. SUPPORTING DOCUMENTS                                       --}}
        {{-- ============================================================= --}}

        <div
            class="card shadow-sm border-0 mb-4"
            id="documentsCard"
        >
            <div class="card-header bg-white border-bottom">
                <h5 class="mb-0 font-weight-bold">
                    <i class="fas fa-file-upload text-primary mr-2"></i>
                    Supporting Documents
                </h5>

                <small class="text-muted">
                    Optional digital uploads for preliminary screening.
                </small>
            </div>

            <div class="card-body">

                <div
                    id="noDocumentNotice"
                    class="alert alert-secondary mb-0"
                >
                    <i class="fas fa-info-circle mr-1"></i>
                    Select your applicant type above to see the applicable
                    document uploads.
                </div>


                {{-- New Student --}}
                <div
                    class="document-section d-none"
                    data-type="new_student"
                >

                    <div class="alert alert-info">
                        <strong>New Student / First Year</strong>
                        <br>
                        The following documents may be uploaded for preliminary screening.
                        Uploading them is optional.
                    </div>

                    @include('public.application.partials.document-input', [
                        'name' => 'form_138',
                        'label' => 'Form 138 or Equivalent',
                        'description' => 'Issued by your originating school.'
                    ])

                    @include('public.application.partials.document-input', [
                        'name' => 'good_moral_certificate',
                        'label' => 'Certificate of Good Moral Character',
                        'description' => 'Issued by your originating school.'
                    ])

                    @include('public.application.partials.document-input', [
                        'name' => 'birth_certificate',
                        'label' => 'PSA Birth Certificate',
                        'description' => 'Digital copy for preliminary screening.'
                    ])

                </div>


                {{-- Transferee --}}
                <div
                    class="document-section d-none"
                    data-type="transferee"
                >

                    <div class="alert alert-info">
                        <strong>Transferee</strong>
                        <br>
                        The following documents may be uploaded for preliminary screening.
                        Uploading them is optional.
                    </div>

                    @include('public.application.partials.document-input', [
                        'name' => 'transfer_credentials',
                        'label' => 'Certificate of Transfer Credentials',
                        'description' => 'Issued by your originating school.'
                    ])

                    @include('public.application.partials.document-input', [
                        'name' => 'transcript_of_records',
                        'label' => 'Transcript of Records',
                        'description' => 'For preliminary evaluation.'
                    ])

                    @include('public.application.partials.document-input', [
                        'name' => 'good_moral_certificate',
                        'label' => 'Certificate of Good Moral Character',
                        'description' => 'Issued by your originating school.'
                    ])

                    @include('public.application.partials.document-input', [
                        'name' => 'birth_certificate',
                        'label' => 'PSA Birth Certificate',
                        'description' => 'Digital copy for preliminary screening.'
                    ])

                </div>


                {{-- Shiftee --}}
                <div
                    class="document-section d-none"
                    data-type="shiftee"
                >
                    <div class="alert alert-secondary mb-0">
                        <i class="fas fa-info-circle mr-1"></i>
                        No digital document upload is required at this
                        pre-enrollment stage for Shiftees.
                        The Registrar's Office will provide further instructions.
                    </div>
                </div>


                {{-- Returnee --}}
                <div
                    class="document-section d-none"
                    data-type="returnee"
                >
                    <div class="alert alert-secondary mb-0">
                        <i class="fas fa-info-circle mr-1"></i>
                        No additional digital document upload is required at this
                        pre-enrollment stage for Returnees / Readmitted students.
                        The Registrar's Office will provide further instructions.
                    </div>
                </div>


                {{-- Cross-Enrollee --}}
                <div
                    class="document-section d-none"
                    data-type="cross_enrollee"
                >
                    <div class="alert alert-info mb-0">
                        <i class="fas fa-info-circle mr-1"></i>

                        <strong>Cross-Enrollee:</strong>
                        Requirements are subject to confirmation by the Registrar's Office.
                        Please proceed with your application, and the Registrar will provide
                        further instructions.
                    </div>
                </div>


                {{-- Marriage Certificate --}}
                <div
                    id="marriageDocument"
                    class="mt-4 d-none"
                >
                    <hr>

                    <h6 class="font-weight-bold">
                        <i class="fas fa-ring mr-1"></i>
                        Marriage Certificate
                    </h6>

                    <p class="text-muted small">
                        Applicable only to married applicants.
                        This upload is optional and intended for preliminary screening.
                    </p>

                    @include('public.application.partials.document-input', [
                        'name' => 'marriage_certificate',
                        'label' => 'Marriage Certificate (PSA)',
                        'description' => 'Upload if applicable.'
                    ])
                </div>

            </div>
        </div>


        {{-- ============================================================= --}}
        {{-- 7. PHYSICAL REQUIREMENTS                                      --}}
        {{-- ============================================================= --}}

        <div
            id="physicalRequirementsCard"
            class="card shadow-sm border-0 mb-4 d-none"
        >

            <div class="card-header bg-white border-bottom">
                <h5 class="mb-0 font-weight-bold">
                    <i class="fas fa-folder-open text-primary mr-2"></i>
                    Physical Requirements
                </h5>

                <small class="text-muted">
                    Please prepare these requirements for physical verification
                    at the Registrar's Office.
                </small>
            </div>

            <div class="card-body">

                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle mr-1"></i>
                    These physical requirements apply regardless of whether
                    you uploaded a digital copy above.
                </div>

                <ul
                    id="physicalRequirementsList"
                    class="mb-0 pl-4"
                ></ul>

            </div>
        </div>


        {{-- ============================================================= --}}
        {{-- 8. FINAL NOTICE + SUBMIT                                      --}}
        {{-- ============================================================= --}}

        <div class="card shadow-sm border-0 mb-5">

            <div class="card-body">

                <div class="alert alert-warning border-0">
                    <i class="fas fa-info-circle mr-1"></i>

                    <strong>Important:</strong>
                    Uploaded documents are for preliminary screening only.
                    You must still present the required original/physical documents
                    to the Registrar's Office for verification before official enrollment.
                </div>

                <div class="custom-control custom-checkbox mb-4">
                    <input
                        type="checkbox"
                        class="custom-control-input"
                        id="acknowledgement"
                        required
                    >

                    <label
                        class="custom-control-label"
                        for="acknowledgement"
                    >
                        I understand that submission of this application does not
                        constitute official enrollment and that I may still be required
                        to present the original physical requirements to the Registrar's Office.
                    </label>
                </div>

                <div class="d-flex justify-content-end">

                    <button
                        type="submit"
                        class="btn btn-primary btn-lg px-4"
                        id="submitButton"
                    >
                        <i class="fas fa-paper-plane mr-1"></i>
                        Submit Application
                    </button>

                </div>

            </div>
        </div>

    </form>

</div>

    </div>
@stop


@section('styles')

<style>

    .card {
        border-radius: 12px;
    }
    .card-header {
        padding: 1rem 1.25rem;
    }
    .card-body {
        padding: 1.5rem;
    }
    .form-control {
        border-radius: 8px;
        min-height: 42px;
    }
    .form-control:focus {
        box-shadow: 0 0 0 0.15rem rgba(0, 123, 255, 0.12);
    }
    .physical-requirement-item {
        margin-bottom: 0.5rem;
    }

    /* ── Page header ───────────────────────────────────────── */
    .back-btn {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        white-space: nowrap;
    }

    /* ── Document upload ─────────────────────────────────── */
    .document-input {
        border: 1px solid #dee2e6;
        border-radius: 10px;
        padding: 1rem;
        margin-bottom: 1rem;
        background: #fafafa;
    }
    .document-input:last-child {
        margin-bottom: 0;
    }
    .document-input label {
        font-weight: 600;
    }

    .document-input-control {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 0.75rem;
        border: 1.5px dashed #cbd5e1;
        border-radius: 8px;
        padding: 0.6rem 0.75rem;
        background: #ffffff;
        position: relative;
        transition: border-color 0.2s ease, background 0.2s ease;
    }
    .document-input-control:focus-within {
        border-color: #0F4CDB;
        border-style: solid;
        box-shadow: 0 0 0 0.15rem rgba(15, 76, 219, 0.12);
    }
    .document-input-control.is-invalid {
        border-color: #dc3545;
        border-style: solid;
    }
    .document-input-btn {
        display: inline-flex;
        align-items: center;
        gap: 0.45rem;
        margin: 0;
        padding: 0.45rem 0.9rem;
        background: #eef2f7;
        border: 1px solid #d7dee7;
        border-radius: 6px;
        font-size: 0.85rem;
        font-weight: 600;
        color: #334155;
        cursor: pointer;
        white-space: nowrap;
        transition: background 0.2s ease, color 0.2s ease;
    }
    .document-input-control:focus-within .document-input-btn {
        background: #dbe6ff;
        color: #0F4CDB;
    }
    .document-input-filename {
        flex: 1 1 160px;
        min-width: 0;
        font-size: 0.85rem;
        color: #94A3B8;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .document-input-control.has-file .document-input-filename {
        color: #1E293B;
        font-weight: 500;
    }
    .document-input-file {
        position: absolute;
        inset: 0;
        width: 100%;
        height: 100%;
        opacity: 0;
        cursor: pointer;
        margin: 0;
    }

    /* ── Dark mode ────────────────────────────────────────── */
    body.dtc-dark .document-input {
        background: var(--dtc-surface-soft, #1E293B);
        border-color: var(--dtc-border, #334155);
    }
    body.dtc-dark .document-input-control {
        background: var(--dtc-surface, #111827);
        border-color: #475569;
    }
    body.dtc-dark .document-input-control:focus-within {
        border-color: #6C9BFF;
        box-shadow: 0 0 0 0.15rem rgba(108, 155, 255, 0.18);
    }
    body.dtc-dark .document-input-btn {
        background: #334155;
        border-color: #475569;
        color: #E2E8F0;
    }
    body.dtc-dark .document-input-control:focus-within .document-input-btn {
        background: #3B4F72;
        color: #BFD3FF;
    }
    body.dtc-dark .document-input-filename {
        color: #64748B;
    }
    body.dtc-dark .document-input-control.has-file .document-input-filename {
        color: #F8FAFC;
    }

    /* ── Mobile / responsive polish ───────────────────────── */
    @media (max-width: 575.98px) {
        .card-body {
            padding: 1.1rem;
        }
        .card-header {
            padding: 0.85rem 1.1rem;
        }
        .document-input {
            padding: 0.85rem;
        }
        .document-input-btn {
            width: 100%;
            justify-content: center;
        }
        .document-input-filename {
            flex-basis: 100%;
            text-align: center;
        }
        .page-header-actions {
            flex-shrink: 0;
        }
        .back-btn {
            padding: 0.5rem 0.65rem;
            gap: 0;
        }
        .back-btn-label {
            display: none;
        }
        .back-btn i {
            margin: 0;
        }
    }
</style>

@stop


@section('scripts')

<script>

document.addEventListener('DOMContentLoaded', function () {

    // Custom "Choose File" control: reflect the selected filename and
    // forward clicks on the styled button to the (visually hidden)
    // native file input so it still opens the OS file picker.
    document.querySelectorAll('.document-input-file').forEach(function (input) {
        const wrap = input.closest('.document-input-control');
        const filenameEl = wrap ? wrap.querySelector('.document-input-filename') : null;

        input.addEventListener('change', function () {
            if (!filenameEl) return;
            if (input.files && input.files.length > 0) {
                filenameEl.textContent = input.files[0].name;
                wrap.classList.add('has-file');
            } else {
                filenameEl.textContent = filenameEl.dataset.placeholder || 'No file chosen';
                wrap.classList.remove('has-file');
            }
        });
    });

    const applicantType = document.getElementById('academic_status');
    const maritalStatus = document.getElementById('marital_status');
    const disability = document.getElementById('disability');

    const documentSections =
        document.querySelectorAll('.document-section');

    const noDocumentNotice =
        document.getElementById('noDocumentNotice');

    const marriageDocument =
        document.getElementById('marriageDocument');

    const spouseField =
        document.getElementById('spouseField');

    const pwdIdField =
        document.getElementById('pwdIdField');

    const crossEnrolleeNotice =
        document.getElementById('crossEnrolleeNotice');

    const physicalRequirementsCard =
        document.getElementById('physicalRequirementsCard');

    const physicalRequirementsList =
        document.getElementById('physicalRequirementsList');


    /*
     * Physical requirements confirmed by the Registrar.
     */
    const physicalRequirements = {

        new_student: [
            'Form 138 or equivalent — original/required physical copy',
            'Certificate of Good Moral Character — original/required physical copy',
            'PSA Birth Certificate — required physical copy',
            'Marriage Certificate — if applicable',
            '2 copies recent 2×2 picture',
            '2 copies recent 1×1 picture',
            '2 pcs Long Brown Envelope'
        ],

        transferee: [
            'Certificate of Transfer Credentials',
            'Transcript of Records',
            'Certificate of Good Moral Character',
            'PSA Birth Certificate',
            'Marriage Certificate — if applicable',
            '2 copies recent 2×2 picture',
            '2 copies recent 1×1 picture',
            '2 pcs Long Brown Envelope'
        ],

        shiftee: [],

        returnee: [],

        cross_enrollee: []

    };


    /*
     * Update applicant-type-specific sections.
     */
    function updateApplicantType() {

        const selectedType = applicantType.value;

        let hasDocuments = false;

        documentSections.forEach(function (section) {

            const sectionType =
                section.getAttribute('data-type');

            if (sectionType === selectedType) {

                section.classList.remove('d-none');

                /*
                 * Shiftee, Returnee and Cross-Enrollee sections
                 * are informational rather than upload sections.
                 */
                if (
                    selectedType === 'new_student' ||
                    selectedType === 'transferee'
                ) {
                    hasDocuments = true;
                }

            } else {

                section.classList.add('d-none');

            }

        });


        if (selectedType) {

            noDocumentNotice.classList.add('d-none');

        } else {

            noDocumentNotice.classList.remove('d-none');

        }


        /*
         * Cross-Enrollee special notice.
         */
        if (selectedType === 'cross_enrollee') {

            crossEnrolleeNotice.classList.remove('d-none');

        } else {

            crossEnrolleeNotice.classList.add('d-none');

        }


        /*
         * Physical requirements.
         */
        const requirements =
            physicalRequirements[selectedType] || [];

        physicalRequirementsList.innerHTML = '';

        if (requirements.length > 0) {

            requirements.forEach(function (requirement) {

                const li =
                    document.createElement('li');

                li.className =
                    'physical-requirement-item';

                li.textContent =
                    requirement;

                physicalRequirementsList.appendChild(li);

            });

            physicalRequirementsCard.classList.remove('d-none');

        } else {

            physicalRequirementsCard.classList.add('d-none');

        }

    }


    /*
     * Marriage-specific fields.
     */
    function updateMaritalStatus() {

        if (maritalStatus.value === 'married') {

            spouseField.classList.remove('d-none');
            marriageDocument.classList.remove('d-none');

        } else {

            spouseField.classList.add('d-none');
            marriageDocument.classList.add('d-none');

            /*
             * Clear spouse input when not married.
             */
            document.getElementById('spouse_name').value = '';

        }

    }


    /*
     * PWD ID is shown only when disability is provided.
     */
    function updateDisability() {

        if (disability.value.trim() !== '') {

            pwdIdField.classList.remove('d-none');

        } else {

            pwdIdField.classList.add('d-none');

        }

    }


    applicantType.addEventListener(
        'change',
        updateApplicantType
    );

    maritalStatus.addEventListener(
        'change',
        updateMaritalStatus
    );

    disability.addEventListener(
        'input',
        updateDisability
    );


    /*
     * Preserve the user's selections after validation errors.
     */
    updateApplicantType();
    updateMaritalStatus();
    updateDisability();

});

</script>

@stop