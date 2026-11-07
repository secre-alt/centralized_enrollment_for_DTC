@extends('layouts.public')

@section('title', 'Online Admission Application')

@section('styles')
<link rel="stylesheet" href="{{ asset('css/application-wizard.css') }}">
<link rel="stylesheet" href="{{ asset('css/physical-requirements.css') }}">
@stop


@section('content')
<div class="app-wizard-page admission-wizard">

    {{-- â”€â”€ Top bar â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ --}}
    <section class="admission-card" aria-label="Online admission application">
        <div class="admission-progress-heading">
            <span id="wizardStepCount" aria-live="polite">Step 1 of 7</span>
            <span id="wizardSectionName">Admission</span>
        </div>

    {{-- â”€â”€ Step Tracker â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ --}}
    <nav class="app-wizard-tracker" id="wizardTracker" aria-label="Application steps">
        <button type="button" class="wiz-step active" data-step="1" aria-controls="step-1" aria-current="step">
            <span class="wiz-step-dot"><i data-lucide="graduation-cap" aria-hidden="true"></i></span>
            <span class="wiz-step-label">Admission</span>
        </button>
        <div class="wiz-connector"></div>
        <button type="button" class="wiz-step" data-step="2" aria-controls="step-2">
            <span class="wiz-step-dot"><i data-lucide="user" aria-hidden="true"></i></span>
            <span class="wiz-step-label">Personal</span>
        </button>
        <div class="wiz-connector"></div>
        <button type="button" class="wiz-step" data-step="3" aria-controls="step-3">
            <span class="wiz-step-dot"><i data-lucide="phone" aria-hidden="true"></i></span>
            <span class="wiz-step-label">Contact</span>
        </button>
        <div class="wiz-connector"></div>
<div class="wiz-connector"></div>
        <button type="button" class="wiz-step" data-step="4" aria-controls="step-4">
            <span class="wiz-step-dot"><i data-lucide="school" aria-hidden="true"></i></span>
            <span class="wiz-step-label">Education</span>
        </button>
        <div class="wiz-connector"></div>
        <button type="button" class="wiz-step" data-step="5" aria-controls="step-5">
            <span class="wiz-step-dot"><i data-lucide="users" aria-hidden="true"></i></span>
            <span class="wiz-step-label">Family</span>
        </button>
        <div class="wiz-connector"></div>
        <button type="button" class="wiz-step" data-step="6" aria-controls="step-6">
            <span class="wiz-step-dot"><i data-lucide="file-up" aria-hidden="true"></i></span>
            <span class="wiz-step-label">Documents</span>
        </button>
        <div class="wiz-connector"></div>
        <button type="button" class="wiz-step" data-step="7" aria-controls="step-7">
            <span class="wiz-step-dot"><i data-lucide="clipboard-check" aria-hidden="true"></i></span>
            <span class="wiz-step-label">Review</span>
        </button>
    </nav>

    {{-- â”€â”€ Progress Bar â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ --}}

    {{-- â”€â”€ Server-side Validation Errors â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ --}}
    @if ($errors->any())
        <div class="wiz-server-errors" id="serverErrors" role="alert" tabindex="-1">
            <div class="wiz-server-errors-icon"><i data-lucide="alert-triangle"></i></div>
            <div class="wiz-server-errors-body">
                <p class="wiz-server-errors-title">Your application couldn't be submitted</p>
                <p class="wiz-server-errors-sub">Please fix the following before resubmitting:</p>
                <ul class="wiz-server-errors-list">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    {{-- â”€â”€ Screening Notice â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ --}}
    {{-- â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• --}}
    <form
        action="{{ route('public.application.store') }}"
        method="POST"
        enctype="multipart/form-data"
        id="applicationForm"
        novalidate
        autocomplete="on"
    >
        @csrf
        <input type="hidden" name="submission_token" value="{{ $submissionToken }}">

        {{-- â•”â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•—
             â•‘  STEP 1 â€” APPLICANT TYPE & PROGRAM              â•‘
             â•šâ•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• --}}
        <div class="wiz-panel active" id="step-1">
            <div class="wiz-panel-header">
                <div>
                    <h2 class="wiz-panel-title">Applicant Type &amp; Program</h2>
                    <p class="wiz-panel-sub">Choose your applicant type and intended program.</p>
                </div>
            </div>
            <div class="wiz-panel-body">
                <p class="wiz-hint">Fields marked <span class="req">*</span> are required. Other fields are optional.</p>


                <div class="wiz-row wiz-row--2">
                    <div class="wiz-field" id="field-academic_status">
                        <label for="academic_status">Applicant Type <span class="req">*</span></label>
                        <select name="academic_status" id="academic_status"
                            class="wiz-input @error('academic_status') wiz-invalid @enderror"
                            autocomplete="off" required>
                            <option value="">Select applicant type</option>
                            <option value="new_student"    {{ old('academic_status') === 'new_student'    ? 'selected' : '' }}>New Student / First Year</option>
                            <option value="transferee"     {{ old('academic_status') === 'transferee'     ? 'selected' : '' }}>Transferee</option>
                            <option value="shiftee"        {{ old('academic_status') === 'shiftee'        ? 'selected' : '' }}>Shiftee</option>
                            <option value="returnee"       {{ old('academic_status') === 'returnee'       ? 'selected' : '' }}>Returnee / Readmitted</option>
                            <option value="cross_enrollee" {{ old('academic_status') === 'cross_enrollee' ? 'selected' : '' }}>Cross-Enrollee</option>
                        </select>
                        @error('academic_status')
                            <span class="wiz-err-msg"><i data-lucide="alert-circle"></i>{{ $message }}</span>
                        @enderror
                    </div>
                    <div class="wiz-field" id="field-program_id">
                        <label for="program_id">Intended Program <span class="req">*</span></label>
                        <select name="program_id" id="program_id"
                            class="wiz-input @error('program_id') wiz-invalid @enderror"
                            autocomplete="off" required>
                            <option value="">Select program</option>
                            @foreach ($programs as $program)
                                <option value="{{ $program->id }}" {{ old('program_id') == $program->id ? 'selected' : '' }}>
                                    {{ $program->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('program_id')
                            <span class="wiz-err-msg"><i data-lucide="alert-circle"></i>{{ $message }}</span>
                        @enderror
                    </div>
                </div>

                <div id="crossEnrolleeNotice" class="wiz-inline-notice wiz-inline-notice--info d-none">
                    <i data-lucide="info"></i>
                    <div><strong>Cross-Enrollee:</strong> Requirements are subject to confirmation by the Registrar's Office. Please proceed â€” the Registrar will provide further instructions.</div>
                </div>

            </div>
        </div>


        {{-- â•”â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•—
             â•‘  STEP 2 â€” PERSONAL INFORMATION                  â•‘
             â•šâ•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• --}}
        @php
            $optionalPersonalFields = ['birth_place', 'religion', 'nationality', 'lrn', 'marital_status', 'spouse_name', 'occupation', 'disability', 'pwd_id'];
            $personalDetailsExpanded = collect($optionalPersonalFields)->contains(function ($field) use ($errors) {
                $value = old($field);
                return $errors->has($field) || (is_string($value) && trim($value) !== '');
            });
        @endphp
        <div class="wiz-panel" id="step-2">
            <div class="wiz-panel-header">
                <div>
                    <h2 class="wiz-panel-title">Personal Information</h2>
                    <p class="wiz-panel-sub">Use the details shown on your official documents.</p>
                </div>
            </div>
            <div class="wiz-panel-body">

                {{-- Each name control has its own label, input, and validation message. --}}
                <section class="wiz-name-section" aria-label="Your name">
                    <div class="wiz-personal-names">
                        <div class="wiz-name-composite wiz-name-composite--first">
                            <div class="wiz-field wiz-name-honorific">
                                <label for="honorific">Honorific</label>
                                <input type="text" name="honorific" id="honorific" placeholder="e.g. Mr."
                                    class="wiz-input @error('honorific') wiz-invalid @enderror"
                                    value="{{ old('honorific') }}" autocomplete="honorific-prefix" maxlength="20">
                                @error('honorific')<span class="wiz-err-msg"><i data-lucide="alert-circle"></i>{{ $message }}</span>@enderror
                            </div>
                            <div class="wiz-field wiz-name-required" id="field-first_name">
                                <label for="first_name">First Name <span class="req">*</span></label>
                                <input type="text" name="first_name" id="first_name" placeholder="Juan"
                                    class="wiz-input @error('first_name') wiz-invalid @enderror"
                                    value="{{ old('first_name') }}" autocomplete="given-name" required>
                                @error('first_name')<span class="wiz-err-msg"><i data-lucide="alert-circle"></i>{{ $message }}</span>@enderror
                            </div>
                        </div>
                        <div class="wiz-name-composite wiz-name-composite--last">
                            <div class="wiz-field wiz-name-required" id="field-last_name">
                                <label for="last_name">Last Name <span class="req">*</span></label>
                                <input type="text" name="last_name" id="last_name" placeholder="Dela Cruz"
                                    class="wiz-input @error('last_name') wiz-invalid @enderror" value="{{ old('last_name') }}"
                                    autocomplete="family-name" required>
                                @error('last_name')<span class="wiz-err-msg"><i data-lucide="alert-circle"></i>{{ $message }}</span>@enderror
                            </div>
                            <div class="wiz-field wiz-name-suffix">
                                <label for="suffix">Suffix</label>
                                <input type="text" name="suffix" id="suffix" placeholder="e.g. Jr."
                                    class="wiz-input @error('suffix') wiz-invalid @enderror"
                                    value="{{ old('suffix') }}" autocomplete="honorific-suffix" maxlength="20">
                                @error('suffix')<span class="wiz-err-msg"><i data-lucide="alert-circle"></i>{{ $message }}</span>@enderror
                            </div>
                        </div>
                        <div class="wiz-field wiz-name-middle">
                            <label for="middle_name">Middle Name</label>
                            <input type="text" name="middle_name" id="middle_name" placeholder="Santos"
                                class="wiz-input @error('middle_name') wiz-invalid @enderror"
                                value="{{ old('middle_name') }}" autocomplete="additional-name">
                            @error('middle_name')<span class="wiz-err-msg"><i data-lucide="alert-circle"></i>{{ $message }}</span>@enderror
                        </div>
                    </div>
                </section>
                <section class="wiz-personal-section" aria-labelledby="identity-details-heading">
                    <h3 class="wiz-personal-section-title" id="identity-details-heading">Identity details</h3>
                    <div class="wiz-personal-pair">
                        <div class="wiz-field" id="field-gender">
                            <label for="gender">Gender <span class="req">*</span></label>
                            <select name="gender" id="gender"
                                class="wiz-input @error('gender') wiz-invalid @enderror"
                                autocomplete="sex" required>
                                <option value="">Select gender</option>
                                <option value="male"   {{ old('gender') === 'male'   ? 'selected' : '' }}>Male</option>
                                <option value="female" {{ old('gender') === 'female' ? 'selected' : '' }}>Female</option>
                            </select>
                            @error('gender')<span class="wiz-err-msg"><i data-lucide="alert-circle"></i>{{ $message }}</span>@enderror
                        </div>
                        <div class="wiz-field" id="field-birthdate">
                            <label for="birthdate">Date of Birth <span class="req">*</span></label>
                            <input type="date" name="birthdate" id="birthdate"
                                class="wiz-input @error('birthdate') wiz-invalid @enderror"
                                value="{{ old('birthdate') }}"
                                min="{{ \App\Support\AdmissionValidation::earliestBirthdate() }}"
                                max="{{ \App\Support\AdmissionValidation::latestBirthdate() }}"
                                autocomplete="off" required>
                            @error('birthdate')<span class="wiz-err-msg"><i data-lucide="alert-circle"></i>{{ $message }}</span>@enderror
                        </div>
                    </div>

                </section>

                <section class="wiz-personal-disclosure" aria-labelledby="personalDetailsToggle">
                    <button type="button" class="wiz-personal-toggle" id="personalDetailsToggle"
                        aria-expanded="{{ $personalDetailsExpanded ? 'true' : 'false' }}" aria-controls="personalDetailsContent">
                        <span><span id="personal-additional-heading">Additional personal details</span>
                            <span class="wiz-personal-toggle-hint">Optional background and accessibility details</span>
                        </span>
                        <i data-lucide="chevron-down" aria-hidden="true"></i>
                    </button>
                    <div id="personalDetailsContent" class="wiz-personal-content" data-has-old-input="{{ session()->hasOldInput() ? 'true' : 'false' }}" @if (!$personalDetailsExpanded) hidden @endif>
                        <div class="wiz-personal-pair">
                            <div class="wiz-field">
                                <label for="birth_place">Place of Birth</label>
                                <input type="text" name="birth_place" id="birth_place" placeholder="e.g. Remedios, Danao, Bohol"
                                    class="wiz-input @error('birth_place') wiz-invalid @enderror" value="{{ old('birth_place') }}" autocomplete="off">
                                @error('birth_place')<span class="wiz-err-msg">{{ $message }}</span>@enderror
                            </div>
                            <div class="wiz-field">
                                <label for="religion">Religion</label>
                                <input type="text" name="religion" id="religion"
                                    placeholder="e.g. Roman Catholic"
                                    class="wiz-input @error('religion') wiz-invalid @enderror" value="{{ old('religion') }}"
                                    autocomplete="off">
                                @error('religion')<span class="wiz-err-msg">{{ $message }}</span>@enderror
                            </div>
                            <div class="wiz-field">
                                <label for="nationality">Nationality</label>
                                <input type="text" name="nationality" id="nationality" placeholder="Filipino"
                                    class="wiz-input @error('nationality') wiz-invalid @enderror" value="{{ old('nationality') }}"
                                    autocomplete="off">
                                @error('nationality')<span class="wiz-err-msg">{{ $message }}</span>@enderror
                            </div>
                            <div class="wiz-field">
                                <label for="lrn">LRN</label>
                                <input type="text" name="lrn" id="lrn" inputmode="numeric" placeholder="136041123456"
                                    class="wiz-input @error('lrn') wiz-invalid @enderror" value="{{ old('lrn') }}" maxlength="12"
                                    autocomplete="off">
                                @error('lrn')<span class="wiz-err-msg">{{ $message }}</span>@enderror
                                <span class="wiz-hint">Learner Reference Number for DepEd graduates.</span>
                            </div>
                        </div>

                        <div class="wiz-personal-pair wiz-marital-row">
                            <div class="wiz-field" id="field-marital_status">
                                <label for="marital_status">Marital Status</label>
                                <select name="marital_status" id="marital_status" class="wiz-input @error('marital_status') wiz-invalid @enderror" autocomplete="off">
                                    <option value="">Select status</option>
                                    <option value="single"   {{ old('marital_status') === 'single'   ? 'selected' : '' }}>Single</option>
                                    <option value="married"  {{ old('marital_status') === 'married'  ? 'selected' : '' }}>Married</option>
                                    <option value="divorced" {{ old('marital_status') === 'divorced' ? 'selected' : '' }}>Divorced</option>
                                    <option value="widowed"  {{ old('marital_status') === 'widowed'  ? 'selected' : '' }}>Widowed</option>
                                </select>
                                @error('marital_status')<span class="wiz-err-msg">{{ $message }}</span>@enderror
                            </div>
                            <div class="wiz-field" id="spouseField">
                                <label for="spouse_name">Spouse Name</label>
                                <input type="text" name="spouse_name" id="spouse_name"
                                    placeholder="Spouse's full name"
                                    class="wiz-input @error('spouse_name') wiz-invalid @enderror" value="{{ old('spouse_name') }}"
                                    autocomplete="off">
                                @error('spouse_name')<span class="wiz-err-msg">{{ $message }}</span>@enderror
                            </div>
                        </div>
                <div class="wiz-personal-pair">
                    <div class="wiz-field">
                        <label for="occupation">Your occupation (optional)</label>
                        <input type="text" name="occupation" id="occupation"
                            placeholder="e.g. Student, Employed, Self-employed" class="wiz-input"
                            value="{{ old('occupation') }}" autocomplete="organization-title">
                    @error('occupation')<span class="wiz-err-msg">{{ $message }}</span>@enderror
</div>
                    <div class="wiz-field">
                        <label for="disability">Disability or accessibility needs (optional)</label>
                        <input type="text" name="disability" id="disability"
                            placeholder="If applicable" class="wiz-input"
                            value="{{ old('disability') }}" autocomplete="off">
                    @error('disability')<span class="wiz-err-msg">{{ $message }}</span>@enderror
</div>
                </div>

                <div class="wiz-field d-none" id="pwdIdField">
                    <label for="pwd_id">PWD ID Number (optional)</label>
                    <input type="text" name="pwd_id" id="pwd_id"
                        placeholder="PWD ID number, if applicable" class="wiz-input"
                        value="{{ old('pwd_id') }}" autocomplete="off">
                @error('pwd_id')<span class="wiz-err-msg">{{ $message }}</span>@enderror
</div>
                    </div>
                </section>

            </div>
        </div>


        {{-- â•”â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•—
             â•‘  STEP 3 â€” CONTACT INFORMATION                   â•‘
             â•šâ•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• --}}
        <div class="wiz-panel" id="step-3">
            <div class="wiz-panel-header">
                <div>
                    <h2 class="wiz-panel-title">Contact Information</h2>
                    <p class="wiz-panel-sub">Enter your contact details and current address.</p>
                </div>
            </div>
            <div class="wiz-panel-body">

                <div class="wiz-row wiz-row--2">
                    <div class="wiz-field" id="field-email">
                        <label for="email">Email Address <span class="req">*</span></label>
                        <input type="email" name="email" id="email" placeholder="juandelacruz@email.com"
                            class="wiz-input @error('email') wiz-invalid @enderror"
                            value="{{ old('email') }}"
                            autocomplete="email" required>
                        @error('email')<span class="wiz-err-msg"><i data-lucide="alert-circle"></i>{{ $message }}</span>@enderror
                    </div>
                    <div class="wiz-field">
                        <label for="phone">Contact Number</label>
                        <input type="tel" name="phone" id="phone" placeholder="09XXXXXXXXX"
                            class="wiz-input" value="{{ old('phone') }}"
                            autocomplete="tel-national">
                        @error('phone')<span class="wiz-err-msg">{{ $message }}</span>@enderror
                    </div>
                </div>

                {{-- â”€â”€ Street / House address â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ --}}
                <div class="wiz-address-section">
                <div class="wiz-address-heading">
                <h3 class="wiz-subsection-title">Current Address</h3>
                <p class="wiz-panel-sub">Select your location or enter it manually. Include your street, purok, or sitio.</p>
                </div>
                    <div class="wiz-field">
                        <label for="country">Country <span class="req">*</span></label>
                        <input type="text" name="country" id="country" required list="countryOptions" placeholder="Search country" aria-describedby="countryHint"
                            class="wiz-input" value="{{ old('country', 'Philippines') }}"
                            data-draft-default="Philippines" data-has-old-input="{{ session()->hasOldInput('country') ? 'true' : 'false' }}"
                            autocomplete="country-name">
                        <datalist id="countryOptions">
                            @foreach (\App\Support\AdmissionValidation::countries() as $countryName)
                                <option value="{{ $countryName }}"></option>
                            @endforeach
                        </datalist>
                        <span class="wiz-hint" id="countryHint">Start typing to find your country.</span>
                        @error('country')<span class="wiz-err-msg">{{ $message }}</span>@enderror
                    </div>
                <p id="locationHint" class="wiz-hint" role="status" aria-live="polite">Select your region to see matching provinces and cities.</p>


                <div class="wiz-row wiz-row--2">
                    <div class="wiz-field">
                        <label for="region_text">Region</label>
                        <input type="text" name="region" id="region_text" list="regionOptions" aria-describedby="locationHint" placeholder="e.g. Central Visayas" class="wiz-input" value="{{ old('region') }}" maxlength="255" autocomplete="off">
                        <input type="hidden" name="region_code" id="region_code" value="{{ old('region_code') }}">
                        @error('region_code')<span class="wiz-err-msg">{{ $message }}</span>@enderror
                    </div>
                    <div class="wiz-field">
                        <label for="province_text">Province / Independent City</label>
                        <input type="text" name="province" id="province_text" list="provinceOptions" aria-describedby="locationHint" placeholder="e.g. Bohol" class="wiz-input" value="{{ old('province') }}" maxlength="255" autocomplete="address-level1">
                        <input type="hidden" name="province_code" id="province_code" value="{{ old('province_code') }}">
                        @error('province_code')<span class="wiz-err-msg">{{ $message }}</span>@enderror
                    </div>
                </div>
                <div class="wiz-row wiz-row--2">
                    <div class="wiz-field">
                        <label for="city_text">City / Municipality</label>
                        <input type="text" name="city" id="city_text" list="cityOptions" aria-describedby="locationHint" placeholder="e.g. Danao" class="wiz-input" value="{{ old('city') }}" maxlength="255" autocomplete="address-level2">
                        <input type="hidden" name="city_code" id="city_code" value="{{ old('city_code') }}">
                        @error('city_code')<span class="wiz-err-msg">{{ $message }}</span>@enderror
                    @error('city')<span class="wiz-err-msg">{{ $message }}</span>@enderror
                    </div>
                    <div class="wiz-field">
                        <label for="barangay_text">Barangay</label>
                        <input type="text" name="barangay" id="barangay_text" list="barangayOptions" aria-describedby="barangayHint" data-url="{{ route('public.application.locations.barangays', ['city' => 'CITY']) }}" placeholder="Search or enter barangay" class="wiz-input" value="{{ old('barangay') }}" maxlength="255" autocomplete="address-level3">
                        <input type="hidden" name="barangay_code" id="barangay_code" value="{{ old('barangay_code') }}">
                        <datalist id="barangayOptions"></datalist>
                        <span class="wiz-hint" id="barangayHint" data-state="ready" role="status" aria-live="polite">Choose a city to see barangays, or enter one manually.</span>
                        @error('barangay_code')<span class="wiz-err-msg">{{ $message }}</span>@enderror
                    </div>
                </div>
                <div class="wiz-field" id="internationalLocationField" hidden>
                    <label for="international_location">City / Province outside the Philippines</label>
                    <input type="text" name="international_location" id="international_location" placeholder="e.g. City, State or Province" class="wiz-input" value="{{ old('international_location') }}" maxlength="255" autocomplete="address-level2">
                    @error('international_location')<span class="wiz-err-msg">{{ $message }}</span>@enderror
                </div>
                <div class="wiz-row wiz-row--2 wiz-address-postal">
                <div class="wiz-field">
                    <label for="current_address">House No., Street, Purok / Sitio <span class="req">*</span></label>
                    <input type="text" name="current_address" id="current_address" required
                        placeholder="e.g. 0161 Mahayahay St."
                        class="wiz-input" value="{{ old('current_address') }}"
                        autocomplete="street-address">
                @error('current_address')<span class="wiz-err-msg">{{ $message }}</span>@enderror
                    </div>
                    <div class="wiz-field">
                        <label for="postal_code">Postal Code</label>
                        <input type="text" name="postal_code" id="postal_code" placeholder="e.g. 6344" aria-describedby="postalCodeHint"
                            class="wiz-input" value="{{ old('postal_code') }}"
                            autocomplete="postal-code" inputmode="text" maxlength="20">
                        @error('postal_code')<span class="wiz-err-msg">{{ $message }}</span>@enderror
                        <span class="wiz-hint" id="postalCodeHint">Enter a valid postal code for your address (optional).</span>
                    </div>

                                </div>


            </div>
        </div>


        {{-- â•”â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•—
             â•‘  STEP 4 â€” EDUCATIONAL BACKGROUND            â•‘
             â•šâ•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• --}}
        </div>
        @php
            $requiredEducation = \App\Support\AdmissionValidation::REQUIRED_EDUCATION[old('academic_status', '')] ?? [];
        @endphp
        <div class="wiz-panel" id="step-4">
            <div class="wiz-panel-header">
                <div>
                    <h2 class="wiz-panel-title" id="educationHeading">Educational Background</h2>
                    <p class="wiz-panel-sub" id="educationDescription" aria-live="polite">Select an applicant type to see the school details needed.</p>
                </div>
            </div>
            <div class="wiz-panel-body">
                <div class="wiz-row wiz-row--2">
                    <div class="wiz-field">
                        <label for="education_level">Highest or current education level</label>
                        <select name="education_level" id="education_level" class="wiz-input @error('education_level') wiz-invalid @enderror" aria-describedby="educationLevelHint" autocomplete="off">
                            <option value="">Select education level</option>
                            @foreach(\App\Support\ApplicationEducation::levelsFor(is_string(old('academic_status')) ? old('academic_status') : null) as $level)
                                <option value="{{ $level }}" {{ old('education_level') === $level ? 'selected' : '' }}>{{ $level === 'Other' ? 'Other / equivalent qualification' : $level }}</option>
                            @endforeach
                        </select>
                        <span class="wiz-hint" id="educationLevelHint" aria-live="polite">Education levels depend on your applicant type.</span>
                        @error('education_level')<span class="wiz-err-msg">{{ $message }}</span>@enderror
                    </div>
                    <div class="wiz-field">
                        <label for="education_status">Completion status @if(in_array('education_status', $requiredEducation, true))<span class="req">*</span>@endif</label>
                        <select name="education_status" id="education_status" @if(in_array('education_status', $requiredEducation, true)) required @endif class="wiz-input" autocomplete="off">
                            <option value="">Select status</option>
                            <option value="Graduated" {{ old('education_status') === 'Graduated' ? 'selected' : '' }}>Graduated</option>
                            <option value="Currently attending" {{ old('education_status') === 'Currently attending' ? 'selected' : '' }}>Currently attending</option>
                            <option value="Not completed" {{ old('education_status') === 'Not completed' ? 'selected' : '' }}>Not completed</option>
                        </select>
                    @error('education_status')<span class="wiz-err-msg">{{ $message }}</span>@enderror
                    </div>
                </div>
                <div class="wiz-row wiz-row--2">
                    <div class="wiz-field">
                        <label for="most_recent_school">Most recent school attended @if(in_array('most_recent_school', $requiredEducation, true))<span class="req">*</span>@endif</label>
                        <input type="text" name="most_recent_school" id="most_recent_school" @if(in_array('most_recent_school', $requiredEducation, true)) required @endif class="wiz-input" value="{{ old('most_recent_school') }}" maxlength="255" autocomplete="organization">
                    @error('most_recent_school')<span class="wiz-err-msg">{{ $message }}</span>@enderror
                    </div>
                    <div class="wiz-field">
                        <label for="school_year_completed">Last School Year Attended @if(in_array('school_year_completed', $requiredEducation, true))<span class="req">*</span>@endif</label>
                        <input type="text" name="school_year_completed" id="school_year_completed" list="schoolYearOptions" aria-describedby="schoolYearHint" @if(in_array('school_year_completed', $requiredEducation, true)) required @endif class="wiz-input" value="{{ old('school_year_completed') }}" maxlength="30" placeholder="e.g. 2025-2026" inputmode="text" autocomplete="off">
                        <datalist id="schoolYearOptions">
                            @foreach(\App\Support\AdmissionValidation::recentSchoolYears() as $schoolYear)
                                <option value="{{ $schoolYear }}">
                            @endforeach
                        </datalist>
                        <span class="wiz-hint" id="schoolYearHint">Enter the most recent school year you attended, whether or not you graduated. Use YYYY-YYYY format.</span>
                        @error('school_year_completed')<span class="wiz-err-msg">{{ $message }}</span>@enderror
                    </div>
                </div>
                <div class="wiz-field">
                    <label for="school_location">School location</label>
                    <input type="text" name="school_location" id="school_location" class="wiz-input" value="{{ old('school_location') }}" maxlength="255" placeholder="City or municipality, province" autocomplete="off">
                </div>
                <div class="education-section d-none" data-education-for="new_student">
                    <div class="wiz-divider"></div>
                    <div class="wiz-field">
                        <label for="shs_strand">Senior High School strand</label>
                        <input type="text" name="shs_strand" id="shs_strand" class="wiz-input" value="{{ old('shs_strand') }}" maxlength="100" placeholder="e.g. HUMSS, STEM, TVL" autocomplete="off">
                    </div>
                </div>
                <div class="education-section d-none" data-education-for="transferee,shiftee,returnee">
                    <div class="wiz-divider"></div>
                    <div class="wiz-field">
                        <label for="previous_program">Previous or current program @if(in_array('previous_program', $requiredEducation, true))<span class="req">*</span>@endif</label>
                        <input type="text" name="previous_program" id="previous_program" @if(in_array('previous_program', $requiredEducation, true)) required @endif class="wiz-input" value="{{ old('previous_program') }}" maxlength="255" autocomplete="off">
                    @error('previous_program')<span class="wiz-err-msg">{{ $message }}</span>@enderror
                    </div>
                </div>
                <div class="education-section d-none" data-education-for="cross_enrollee">
                    <div class="wiz-divider"></div>

                    <div class="wiz-row wiz-row--2">
                        <div class="wiz-field">
                            <label for="home_institution">School or institution @if(in_array('home_institution', $requiredEducation, true))<span class="req">*</span>@endif</label>
                            <input type="text" name="home_institution" id="home_institution" @if(in_array('home_institution', $requiredEducation, true)) required @endif class="wiz-input" value="{{ old('home_institution') }}" maxlength="255" autocomplete="organization">
                        @error('home_institution')<span class="wiz-err-msg">{{ $message }}</span>@enderror
                    </div>
                        <div class="wiz-field">
                            <label for="home_program">Current course or program @if(in_array('home_program', $requiredEducation, true))<span class="req">*</span>@endif</label>
                            <input type="text" name="home_program" id="home_program" @if(in_array('home_program', $requiredEducation, true)) required @endif class="wiz-input" value="{{ old('home_program') }}" maxlength="255" autocomplete="off">
                        @error('home_program')<span class="wiz-err-msg">{{ $message }}</span>@enderror
                    </div>
                    </div>
                </div>
                <div class="education-section d-none" data-education-for="transferee,shiftee,returnee,cross_enrollee">
                    <div class="wiz-field">
                        <label for="last_year_level">Last year level and semester attended @if(in_array('last_year_level', $requiredEducation, true))<span class="req">*</span>@endif</label>
                        <input type="text" name="last_year_level" id="last_year_level" @if(in_array('last_year_level', $requiredEducation, true)) required @endif class="wiz-input" value="{{ old('last_year_level') }}" maxlength="100" placeholder="e.g. 2nd year, 1st semester" autocomplete="off">
                    @error('last_year_level')<span class="wiz-err-msg">{{ $message }}</span>@enderror
                    </div>
                </div>
            </div>
        </div>




        {{-- â•”â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•—
             â•‘  STEP 5 â€” FAMILY & SUPPORTING DOCUMENTS           â•‘
             â•šâ•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• --}}
        <div class="wiz-panel" id="step-5">
            <div class="wiz-panel-header">
                <div>
                    <h2 class="wiz-panel-title">Family Information</h2>
                    <p class="wiz-panel-sub">Parent and guardian details are optional.</p>
                </div>
                <details id="guardianDetails" class="wiz-field" {{ old('guardian_name') || old('guardian_contact') || $errors->hasAny(['guardian_name', 'guardian_contact']) ? 'open' : '' }}>
                    <summary class="wiz-guardian-toggle">Guardian details <i data-lucide="chevron-down" aria-hidden="true"></i></summary>
                    <div class="wiz-row wiz-row--2">
                        <div class="wiz-field">
                            <label for="guardian_name">Guardian's Name</label>
                            <input type="text" name="guardian_name" id="guardian_name" class="wiz-input @error('guardian_name') wiz-invalid @enderror"
                                value="{{ old('guardian_name') }}" placeholder="Guardian's full name" maxlength="255" autocomplete="off">
                            @error('guardian_name')<span class="wiz-err-msg">{{ $message }}</span>@enderror
                        </div>
                        <div class="wiz-field">
                            <label for="guardian_contact">Guardian's Contact Number</label>
                            <input type="tel" name="guardian_contact" id="guardian_contact" class="wiz-input @error('guardian_contact') wiz-invalid @enderror"
                                value="{{ old('guardian_contact') }}" placeholder="e.g. 09171234567" maxlength="50" autocomplete="off">
                            @error('guardian_contact')<span class="wiz-err-msg">{{ $message }}</span>@enderror
                        </div>
                    </div>
                </details>
            </div>
            <div class="wiz-panel-body">

                {{-- â”€â”€ Tab Navigation â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ --}}
                <section class="wiz-family-section" aria-label="Family and other details">



                <div class="wiz-row wiz-row--2">
                    <div class="wiz-field">
                        <label for="father_name">Father's Name</label>
                        <input type="text" name="father_name" id="father_name"
                            placeholder="Father's full name" class="wiz-input"
                            value="{{ old('father_name') }}" autocomplete="off">
                        @error('father_name')<span class="wiz-err-msg">{{ $message }}</span>@enderror
                    </div>
                    <div class="wiz-field">
                        <label for="father_occupation">Father's Occupation</label>
                        <input type="text" name="father_occupation" id="father_occupation"
                            placeholder="e.g. Driver, Farmer, OFW" class="wiz-input"
                            value="{{ old('father_occupation') }}" autocomplete="off">
                    </div>
                </div>

                <div class="wiz-row wiz-row--2">
                    <div class="wiz-field">
                        <label for="mother_name">Mother's Name</label>
                        <input type="text" name="mother_name" id="mother_name"
                            placeholder="Mother's full name" class="wiz-input"
                            value="{{ old('mother_name') }}" autocomplete="off">
                        @error('mother_name')<span class="wiz-err-msg">{{ $message }}</span>@enderror
                    </div>
                    <div class="wiz-field">
                        <label for="mother_occupation">Mother's Occupation</label>
                        <input type="text" name="mother_occupation" id="mother_occupation"
                            placeholder="e.g. Housewife, Teacher, OFW" class="wiz-input"
                            value="{{ old('mother_occupation') }}" autocomplete="off">
                    </div>
                </div>

                <div class="wiz-row wiz-row--2">
                    <div class="wiz-field">
                        <label for="parent_address">Parent's Address</label>
                        <input type="text" name="parent_address" id="parent_address"
                            placeholder="Same as current address, if applicable" class="wiz-input"
                            value="{{ old('parent_address') }}" autocomplete="off">
                    </div>
                    <div class="wiz-field">
                        <label for="parent_contact">Parent's Contact</label>
                        <input type="tel" name="parent_contact" id="parent_contact"
                            placeholder="09XXXXXXXXX" class="wiz-input"
                            value="{{ old('parent_contact') }}" autocomplete="off">
                        @error('parent_contact')<span class="wiz-err-msg">{{ $message }}</span>@enderror
                    </div>
                </div>




                </section>
            </div>
        </div>
        <div class="wiz-panel" id="step-6">
            <div class="wiz-panel-header">
                <div>
                    <h2 class="wiz-panel-title">Supporting Documents</h2>
                    <p class="wiz-panel-sub">Optional preliminary uploads: PDF, JPG, JPEG or PNG, up to {{ \App\Support\AdmissionValidation::uploadLabel() }} each. Present originals to the Registrar before official enrollment.</p>
                </div>
            </div>
            <div class="wiz-panel-body">
<div class="doc-tabs" role="tablist">
                    <button type="button" class="doc-tab-btn active"
                        data-tab="uploads" role="tab" aria-selected="true">
                        <i data-lucide="upload-cloud"></i> Upload Documents
                    </button>
                    <button type="button" class="doc-tab-btn"
                        data-tab="checklist" role="tab" aria-selected="false">
                        <i data-lucide="clipboard-check"></i> Requirements Checklist
                    </button>
                </div>

                {{-- â”€â”€ Tab Panel: Upload Documents â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ --}}
                <div class="doc-tab-panel active" data-tab-panel="uploads" role="tabpanel">

                    <div id="noDocumentNotice" class="wiz-inline-notice wiz-inline-notice--neutral">
                        <i data-lucide="info"></i>
                        <span>Select your applicant type in Step 1 to see the applicable document uploads.</span>
                    </div>

                    {{-- New Student --}}
                    <div class="document-section d-none" data-type="new_student">
                        @include('public.application.partials.document-input', ['group'=>'new_student','name'=>'form_138','label'=>'Form 138 or Equivalent','description'=>'Issued by your originating school.'])
                        @include('public.application.partials.document-input', ['group'=>'new_student','name'=>'good_moral_certificate','label'=>'Certificate of Good Moral Character','description'=>'Issued by your originating school.'])
                        @include('public.application.partials.document-input', ['group'=>'new_student','name'=>'birth_certificate','label'=>'PSA Birth Certificate','description'=>'Digital copy for preliminary screening.'])
                    </div>

                    {{-- Transferee --}}
                    <div class="document-section d-none" data-type="transferee">
                        @include('public.application.partials.document-input', ['group'=>'transferee','name'=>'transfer_credentials','label'=>'Certificate of Transfer Credentials','description'=>'Issued by your originating school.'])
                        @include('public.application.partials.document-input', ['group'=>'transferee','name'=>'transcript_of_records','label'=>'Transcript of Records','description'=>'For preliminary evaluation.'])
                        @include('public.application.partials.document-input', ['group'=>'transferee','name'=>'good_moral_certificate','label'=>'Certificate of Good Moral Character','description'=>'Issued by your originating school.'])
                        @include('public.application.partials.document-input', ['group'=>'transferee','name'=>'birth_certificate','label'=>'PSA Birth Certificate','description'=>'Digital copy for preliminary screening.'])
                    </div>

                    {{-- Shiftee --}}
                    <div class="document-section d-none" data-type="shiftee">
                        <div class="wiz-inline-notice wiz-inline-notice--neutral">
                            <i data-lucide="info"></i>
                            <span>No document upload required for Shiftees at this stage. The Registrar's Office will provide further instructions.</span>
                        </div>
                    </div>

                    {{-- Returnee --}}
                    <div class="document-section d-none" data-type="returnee">
                        <div class="wiz-inline-notice wiz-inline-notice--neutral">
                            <i data-lucide="info"></i>
                            <span>No additional document upload required for Returnees. The Registrar's Office will provide further instructions.</span>
                        </div>
                    </div>

                    {{-- Cross-Enrollee --}}
                    <div class="document-section d-none" data-type="cross_enrollee">
                        <div class="wiz-inline-notice wiz-inline-notice--info">
                            <i data-lucide="info"></i>
                            <div><strong>Cross-Enrollee:</strong> Requirements are subject to confirmation by the Registrar's Office.</div>
                        </div>
                    </div>

                    {{-- Marriage Certificate --}}
                    <div id="marriageDocument" class="d-none" style="margin-top:20px">
                        <div class="wiz-divider"></div>
                        <p class="wiz-group-label"><i data-lucide="bell-ring"></i> Marriage Certificate</p>
                        <p style="font-size:13px;color:#64748b;margin:-8px 0 14px">Applicable only to married applicants. Upload is optional.</p>
                        @include('public.application.partials.document-input', ['name'=>'marriage_certificate','label'=>'Marriage Certificate (PSA)','description'=>'Upload if applicable.'])
                    </div>

                </div>{{-- end uploads tab panel --}}

                {{-- â”€â”€ Tab Panel: Requirements Checklist â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ --}}
                <div class="doc-tab-panel" data-tab-panel="checklist" role="tabpanel">

                    <div id="physicalRequirementsCard" class="wiz-reqs physical-requirements-panel">
                        <div class="wiz-reqs-header">
                            <i data-lucide="folder-open"></i>
                            Physical Requirements to Bring
                        </div>
                        <p class="wiz-reqs-sub">Bring physical copies to the Registrar's Office, even if uploaded. Check items as you prepare them; this does not confirm submission.</p>
                        <ul id="physicalRequirementsList" class="wiz-reqs-checklist">
                            <li>
                                <span class="wiz-reqs-empty">
                                    Select your applicant type in Step 1 to see the requirements list.
                                </span>
                            </li>
                        </ul>
                    </div>

                </div>{{-- end checklist tab panel --}}


            </div>
        </div>

        {{-- â•”â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•—
             â•‘  STEP 6 â€” REVIEW & SUBMIT                        â•‘
             â•šâ•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• --}}
        <div class="wiz-panel" id="step-7">
            <div class="wiz-panel-header">
                <div>
                    <h2 class="wiz-panel-title">Review Your Application</h2>
                    <p class="wiz-panel-sub">Check your details and choose Edit to make changes. Counts include optional fields, which may be left blank.</p>
                </div>
            </div>
            <div class="wiz-panel-body">

                {{-- â”€â”€ Review Summary (populated by JS buildReview()) â”€â”€ --}}
                <div id="reviewSummary" class="rev-summary">
                    <div class="rev-loading">
                        <i data-lucide="loader"></i> Loading your summaryâ€¦
                    </div>
                </div>

                <div class="wiz-divider"></div>

                {{-- â”€â”€ Simple Acknowledgement with Clickable Links â”€â”€ --}}
                <div class="simple-ack-bar">
                    <input type="hidden" id="ack_enrollment" name="ack_enrollment" value="">
                    <p class="simple-ack-text" id="applicationAcknowledgement" style="margin:0">
                        By clicking <strong>Continue</strong>, you confirm accurate details, accept the <a href="#" class="simple-ack-link" id="openTermsModal">Terms</a>, and acknowledge the <a href="#" class="simple-ack-link" id="openPrivacyModal">Privacy Policy</a>.
                    </p>
                    @error('ack_enrollment')<p class="wiz-err-msg">Please continue and confirm submission to acknowledge the Terms and Privacy Policy.</p>@enderror
                </div>

            </div>
        </div>

        {{-- â”€â”€ Wizard Navigation â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ --}}
        <div class="wiz-nav">
            <button type="button" class="wiz-btn wiz-btn--ghost" id="wizPrev" style="visibility:hidden">
                <i data-lucide="arrow-left"></i> Previous
            </button>
            <button type="button" class="wiz-btn wiz-btn--primary" id="wizNext">
                Next <i data-lucide="arrow-right"></i>
            </button>
            <button type="button" class="wiz-btn wiz-btn--submit d-none" id="wizSubmit" aria-describedby="applicationAcknowledgement">
                Continue <i data-lucide="arrow-right"></i>
            </button>
        </div>

    </form>
    </section>

    {{-- â”€â”€ Submit Confirmation Modal â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ --}}

    {{-- â”€â”€ Privacy Policy Modal â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ --}}
    <div class="privacy-modal-overlay" id="privacyModal">
        <div class="privacy-modal" role="dialog" aria-modal="true" aria-labelledby="privacyModalTitle" tabindex="-1">
            <div class="privacy-modal-header">
                <h3 class="privacy-modal-title" id="privacyModalTitle">Privacy Policy</h3>
            </div>
            <div class="privacy-modal-body">
                <p>
                    We take your privacy seriously. When you fill out this enrollment form, you're sharing personal information with Danao Technological College (DTC). This notice explains what we collect, why we need it, and how we keep it safe. This follows the Data Privacy Act of 2012 (RA 10173).
                </p>

                <div class="privacy-section">
                    <h4>What we collect</h4>
                    <p>Your name, birthdate, address, phone number, family information, academic records, and the documents you upload here. Only what you choose to share with us.</p>
                </div>

                <div class="privacy-section">
                    <h4>Why we need it</h4>
                    <p>To process your enrollment, verify your credentials, send you updates about your application, and maintain your official student records as required by CHED.</p>
                </div>

                <div class="privacy-section">
                    <h4>How we protect it</h4>
                    <p>Your information stays with DTC. Only our staff who need it for your enrollment can access it. We don't sell your data to anyone, and we won't share it without your permission.</p>
                </div>

                <div class="privacy-section">
                    <h4>Your rights</h4>
                    <p>You can ask to see, correct, or delete your information anytime. Just visit the Registrar's Office. If you have concerns, you can also contact the National Privacy Commission at complaints@privacy.gov.ph.</p>
                </div>
            </div>
            <div class="privacy-modal-footer">
                <button type="button" class="wiz-btn wiz-btn--primary" id="closePrivacyModal">
                    I Understand
                </button>
            </div>
        </div>
    </div>

    {{-- â”€â”€ Terms Modal â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ --}}
    <div class="privacy-modal-overlay" id="termsModal">
        <div class="privacy-modal" role="dialog" aria-modal="true" aria-labelledby="termsModalTitle" tabindex="-1">
            <div class="privacy-modal-header">
                <h3 class="privacy-modal-title" id="termsModalTitle">Terms of Service</h3>
            </div>
            <div class="privacy-modal-body">
                <div class="privacy-section">
                    <h4>About this form</h4>
                    <p>This is a preliminary application - it doesn't guarantee admission. You'll still need to bring your original documents to the Registrar's Office. We review all applications and may reject those with incorrect information.</p>
                </div>

                <div class="privacy-section">
                    <h4>Your responsibilities</h4>
                    <p>By submitting this form, you certify that all information provided is true and accurate to the best of your knowledge. You agree to provide original documents when requested by the Registrar's Office.</p>
                </div>

                <div class="privacy-section">
                    <h4>Data collection</h4>
                    <p>You consent to the collection and processing of your personal information as described in our Privacy Policy. This includes providing accurate and complete information for enrollment purposes.</p>
                </div>

                <div class="privacy-section">
                    <h4>Changes to terms</h4>
                    <p>DTC reserves the right to update these terms as needed. Continued use of our enrollment system constitutes acceptance of any changes.</p>
                </div>
            </div>
            <div class="privacy-modal-footer">
                <button type="button" class="wiz-btn wiz-btn--primary" id="closeTermsModal">
                    I Understand
                </button>
            </div>
        </div>
    </div>
</div>
@stop


@section('scripts')
<script>
    window.dtcApplicationTitleCaseFields = @json($titleCaseFields);
    window.dtcAdmissionValidation = @json(\App\Support\AdmissionValidation::clientRules());
</script>
<script>window.dtcApplicationEducationLevels = @json(\App\Support\ApplicationEducation::LEVELS);</script>
<script src="{{ asset('js/dtc-name-normalizer.js') }}" defer></script>
<script src="{{ asset('js/application-wizard.js') }}" defer></script>
@php
    $locationCatalog = app(\App\Services\PsgcLocationCatalog::class);
    $locationRegions = $locationCatalog->regions();
    $locationAreas = [];
    $locationCities = [];
    foreach ($locationRegions as $locationRegion) {
        $locationAreas[$locationRegion['code']] = $locationCatalog->areas($locationRegion['code']);
        foreach ($locationAreas[$locationRegion['code']] as $locationArea) {
            if ($locationArea['type'] === 'province') {
                $locationCities[$locationArea['code']] = $locationCatalog->cities($locationArea['code']);
            }
        }
    }
@endphp
<datalist id="regionOptions"></datalist>
<datalist id="provinceOptions"></datalist>
<datalist id="cityOptions"></datalist>
<script>window.dtcLocations = @json(['regions' => $locationRegions, 'areas' => $locationAreas, 'cities' => $locationCities]);</script>
<script src="{{ asset('js/admission-location.js') }}?v={{ filemtime(public_path('js/admission-location.js')) }}" defer></script>
<script src="{{ asset('js/admission-combobox.js') }}?v={{ filemtime(public_path('js/admission-combobox.js')) }}" defer></script>
@php
    $firstErrorKey = $errors->keys()[0] ?? '';
    $errorStep = match (true) {
        in_array($firstErrorKey, ['academic_status', 'program_id'], true) => 1,
        in_array($firstErrorKey, ['first_name', 'last_name', 'middle_name', 'honorific', 'suffix', 'gender', 'birthdate', 'birth_place', 'religion', 'nationality', 'lrn', 'marital_status', 'spouse_name', 'occupation', 'disability', 'pwd_id'], true) => 2,
        in_array($firstErrorKey, ['current_address', 'region', 'province', 'city', 'region_code', 'province_code', 'city_code', 'international_location', 'barangay', 'barangay_code', 'postal_code', 'country'], true) => 3,
        in_array($firstErrorKey, ['email', 'phone'], true) => 3,
        in_array($firstErrorKey, ['education_level', 'education_status', 'most_recent_school', 'school_year_completed', 'school_location', 'shs_strand', 'previous_program', 'last_year_level', 'home_institution', 'home_program'], true) => 4,
        $firstErrorKey === 'documents' || str_starts_with($firstErrorKey, 'documents.') => 6,
        in_array($firstErrorKey, ['guardian_name', 'guardian_contact', 'father_name', 'father_occupation', 'mother_name', 'mother_occupation', 'parent_address', 'parent_contact', 'ack_enrollment'], true) => in_array($firstErrorKey, ['ack_enrollment'], true) ? 7 : 5,
        default => 1,
    };
@endphp
<script>
    /* Tell the wizard which step to open first.
       Runs after application-wizard.js because it is defer-loaded
       but this inline script runs after DOMContentLoaded as well. */
    document.addEventListener('DOMContentLoaded', function () {
        if (window._wizGoTo) {
            @if ($errors->any())
                window._wizGoTo({{ $errorStep ?? 1 }}, true);
                setTimeout(function () {
                    var keys = @json($errors->keys());
                    var form = document.getElementById('applicationForm');
                    var firstField = null;
                    keys.some(function (key) {
                        var field = form && form.elements.namedItem(key);
                        if (field && field.type === 'hidden' && key.endsWith('_code')) field = document.getElementById(key.replace('_code', '_text'));
                        if (!field && key.indexOf('documents.') === 0) {
                            field = form && form.elements.namedItem('documents[' + key.substring(10) + ']');
                        }
                        if (field && typeof field.focus !== 'function' && typeof field.length === 'number') {
                            field = Array.from(field).find(function (input) { return !input.disabled && !input.closest('.d-none'); });
                        }
                        if (field && typeof field.focus === 'function') { firstField = field; return true; }
                        return false;
                    });
                    if (firstField) {
                        firstField.focus();
                        firstField.scrollIntoView({ block: 'center', behavior: 'auto' });
                    }
                    else document.getElementById('serverErrors')?.focus();
                }, 0);
            @else
                window._wizGoTo(1, true);
            @endif
        }
    });
</script>
@stop
