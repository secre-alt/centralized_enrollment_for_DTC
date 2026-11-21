@php
    $subjectCount = count($subjects);
    $hasOpenReplacement = $enrollment->replacementRequests->contains('status', 'open');
    $canReview = $enrollment->status === 'pending'
        && $enrollment->is_paid
        && $enrollment->payment?->status === 'verified'
        && ! $hasOpenReplacement;
    $unclassifiedSubjects = $subjects->reject(fn ($subject) => in_array($subject->classification, ['major', 'minor', 'general_education'], true));
    $sectionAssignments = $enrollment->subjectOfferings()->with('classSchedule')->get()->keyBy('curriculum_subject_id');
    $canAssignBeforePayment = $enrollment->status === 'pending' && $enrollment->requires_section_assignment
        && !$enrollment->is_paid && !$enrollment->payments()->exists() && !$hasOpenReplacement;
    $canRecoverRejection = $enrollment->status === 'rejected' && $enrollment->is_paid && $enrollment->payment?->status === 'verified'
        && ! $enrollment->replacementRequests->contains(fn ($case) => in_array($case->status, ['open', 'refund_requested', 'refunded'], true))
        && ! $enrollment->payments()->where(fn ($payments) => $payments->where('refund_status', 'refunded')->orWhereNotNull('refunded_at')->orWhereNotNull('refunded_by')->orWhereNotNull('refund_reference')->orWhereNotNull('refund_amount'))->exists();
@endphp

<div id="review-content">

    {{-- ── Header ─────────────────────────────────────────────────────── --}}
    <div class="dtc-review-header">
        <div class="dtc-review-identity">
            <x-dtc.user-avatar :user="$enrollment->user" class="dtc-review-avatar" />
            <div class="dtc-review-identity-copy">
                <h5 class="dtc-review-name">{{ $enrollment->user->name }}</h5>
                <p class="dtc-review-email"><i data-lucide="mail"></i> {{ $enrollment->user->email }}</p>
            </div>
        </div>
        <div class="dtc-review-header-right">
            @if($canAssignBeforePayment)
                <span class="dtc-status-badge is-warning">Sections needed before payment</span>
            @elseif($canReview)
                <span class="dtc-status-badge is-success"><span class="dtc-status-dot"></span> Paid — Ready for Review</span>
            @elseif($hasOpenReplacement)
                <span class="dtc-status-badge is-warning"><span class="dtc-status-dot"></span> Subject Replacement Required</span>
            @elseif($enrollment->is_paid)
                <span class="dtc-status-badge is-success"><span class="dtc-status-dot"></span> Enrolled &amp; Paid</span>
            @elseif($enrollment->status === 'pending')
                <span class="dtc-status-badge is-warning"><span class="dtc-status-dot"></span> Awaiting Cashier</span>
            @elseif($enrollment->status === 'approved')
                <span class="dtc-status-badge is-info"><span class="dtc-status-dot"></span> Approved — Awaiting Payment</span>
            @elseif($enrollment->status === 'rejected')
                <span class="dtc-status-badge is-danger"><span class="dtc-status-dot"></span> Rejected</span>
            @endif
            @if($enrollment->status === 'approved' && $enrollment->is_paid)
                <a href="{{ route('registrar.enrollments.cor.show', $enrollment) }}" class="btn btn-primary btn-sm">
                    <i data-lucide="file-badge" class="mr-1"></i> View COR
                </a>
            @endif
            <button type="button" class="dtc-review-close" data-dismiss="modal" aria-label="Close">
                <i data-lucide="x"></i>
            </button>
        </div>
    </div>

    {{-- ── Body ───────────────────────────────────────────────────────── --}}
    <div class="dtc-review-body">
        @if($errors->any())
            <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
        @endif

        <div class="dtc-review-section">
            <div class="dtc-review-section-title">
                <span><i data-lucide="graduation-cap"></i> Enrollment Details</span>
            </div>
            <div class="dtc-review-info-grid">
                <div class="dtc-review-info">
                    <span class="dtc-review-info-label">Program</span>
                    <span class="dtc-review-info-value">{{ $enrollment->program->name }}</span>
                </div>
                <div class="dtc-review-info">
                    <span class="dtc-review-info-label">Year Level</span>
                    <span class="dtc-review-info-value">Year {{ $enrollment->year_level }}</span>
                </div>
                <div class="dtc-review-info">
                    <span class="dtc-review-info-label">Semester</span>
                    <span class="dtc-review-info-value">Semester {{ $enrollment->semester }}</span>
                </div>
                <div class="dtc-review-info">
                    <span class="dtc-review-info-label">Submitted</span>
                    <span class="dtc-review-info-value">{{ $enrollment->created_at->format('M d, Y · h:i A') }}</span>
                </div>
                <div class="dtc-review-info">
                    <span class="dtc-review-info-label">Classification</span>
                    <span class="dtc-review-info-value">
                        @php $classification = $enrollment->resolveClassification(); @endphp
                        @if($classification === 'new_student')
                            <span class="dtc-status-badge is-neutral">New student</span>
                        @else
                            <span class="dtc-status-badge is-info">{{ ucfirst(str_replace('_', ' ', $classification)) }}</span>
                        @endif
                    </span>
                </div>
                <div class="dtc-review-info">
                    <span class="dtc-review-info-label">Subject Load</span>
                    <span class="dtc-review-info-value">
                        @if($enrollment->is_irregular)
                            <span class="dtc-status-badge is-warning">Irregular subjects</span>
                        @else
                            <span class="dtc-status-badge is-neutral">Regular subjects</span>
                        @endif
                    </span>
                </div>
            </div>
        </div>

        <div class="dtc-review-section">
            @if($classification === 'transferee')
                <p class="u-text-secondary-sm">Transferee: verify the selected subjects against evaluated transfer records before approval. Admission classification and subject selection are separate.</p>
            @endif
            @if($canReview)
                <div class="dtc-payment-info-notice">
                    The Cashier has verified payment. Review the selected subjects before approving or returning an unavailable subject for replacement.
                    @if(! $replacementRequest)
                        <p class="mt-2 mb-0"><strong>One or more classes full?</strong> Return only the affected subjects for replacement. Keep the existing payment and unaffected subjects.</p>
                        <a class="dtc-btn dtc-btn-secondary mt-2" href="#return-subject-replacement-{{ $enrollment->id }}">Choose subjects to replace</a>
                    @endif
                </div>
            @elseif($hasOpenReplacement)
                <div class="dtc-payment-info-notice">
                    The enrollment is waiting for the student's replacement or refund decision. The verified payment remains unchanged.
                </div>
            @elseif($enrollment->status === 'pending')
                <div class="dtc-payment-info-notice">
                    Registrar review is available after the Cashier verifies the enrollment fee.
                </div>
            @endif
            <div class="dtc-review-section-title">
                <span><i data-lucide="book"></i> Selected Subjects</span>
                <span class="dtc-review-subject-count">{{ $subjectCount }} subject{{ $subjectCount === 1 ? '' : 's' }}</span>
            </div>

            <div class="dtc-review-subject-list">
                @forelse ($subjects as $subject)
                    <div class="dtc-review-subject-item">
                        <span class="dtc-review-subject-code">{{ $subject->subject_code }}</span>
                        <span class="dtc-review-subject-name">{{ $subject->subject_name }}</span>
                        @if($subject->classification !== 'unclassified')
                            <span class="dtc-status-badge is-neutral">{{ ucfirst(str_replace('_', ' ', $subject->classification)) }}</span>
                        @endif
                        @if($enrollment->is_irregular)
                            <span class="dtc-status-badge is-neutral" style="margin-left:auto; font-size:12px;">Y{{ $subject->year_level }} · Sem {{ $subject->semester }}</span>
                        @endif
                    </div>
                    @if($canReview || $canAssignBeforePayment)
                        @if($sectionAssignments->has($subject->id))
                            <p class="dtc-review-section-assignment">Section: {{ $sectionAssignments[$subject->id]->classSchedule?->section_code ?: 'Needs reconciliation' }}</p>
                        @else
                            <div class="form-group dtc-review-section-choice">
                                <label for="approval-section-{{ $enrollment->id }}-{{ $subject->id }}">Section for {{ $subject->subject_code }}</label>
                                <select id="approval-section-{{ $enrollment->id }}-{{ $subject->id }}" name="section_choices[{{ $subject->id }}]" form="approve-enrollment-{{ $enrollment->id }}" class="form-control" required>
                                    <option value="">Select a recorded section</option>
                                    @foreach($subject->schedules->where('school_year', $enrollment->school_year)->where('semester', $enrollment->semester)->where('is_active', true)->whereNotNull('section_code')->where('capacity', '>', 0) as $offering)
                                        <option value="{{ $offering->id }}" @selected((string) old('section_choices.'.$subject->id) === (string) $offering->id)>{{ $offering->section_code }} · {{ $offering->day_pattern }} {{ $offering->time_start }}–{{ $offering->time_end }}</option>
                                    @endforeach
                                </select>
                                <small class="text-muted">If no section is listed, reconcile the offering before approval. Seats and schedules are checked when you approve.</small>
                            </div>
                        @endif
                    @endif
                @empty
                    <p class="text-muted mb-0" style="font-size:12.5px;">No subjects selected.</p>
                @endforelse
            </div>
        </div>

        @if(($canReview || $canAssignBeforePayment) && $subjects->contains(fn ($subject) => ! $sectionAssignments->has($subject->id)))
            <div class="dtc-review-section dtc-equivalency-setup-note">
                <strong>{{ $canAssignBeforePayment ? 'Assign selected subjects' : 'Missing section records' }}</strong>
                @if($canAssignBeforePayment)
                    <p>Assign the full selected load before payment. Seats, term, subject identity and timetable conflicts are checked together. This does not approve the enrollment.</p>
                @else
                    <p>Select the sections above, then record them for reconciliation. This keeps enrollment pending; it does not confirm available seats while other assignments are unknown. Repeat for the other affected records before approving.</p>
                @endif
                @unless($canAssignBeforePayment)
                    <button type="submit" form="approve-enrollment-{{ $enrollment->id }}" name="record_sections" value="1" class="dtc-btn dtc-btn-secondary">Record sections</button>
                @endunless
            </div>
        @endif
        @if($replacementRequest)
            <div class="alert alert-warning" role="status">
                <strong>Subject Replacement Required</strong>
                <p class="mb-1">{{ $replacementRequest->reason }}</p>
                <span>The verified payment remains attached. The student must choose eligible offerings or request refund follow-up.</span>
            </div>
        @endif

        @if(($canReview && ! $replacementRequest) || $canRecoverRejection)
            <div class="dtc-review-section">
                <div class="dtc-review-section-title">
                    <span><i data-lucide="rotate-ccw"></i> Return for Subject Replacement</span>
                </div>
                <p class="text-muted">Use this when one or more classes are full or unavailable. Select only the affected subjects and provide a clear reason. The student chooses another eligible section or approved equivalent, then submits the revised load for review. Unaffected subjects, the official program and verified payment stay attached.</p>
                <p class="text-muted">A section change alone does not make the student Irregular. The final assignments, approved equivalencies and preserved load classification determine the treatment. Returning subjects does not reject the enrollment or start a refund.</p>
                <form id="return-subject-replacement-{{ $enrollment->id }}" method="POST" action="{{ route('registrar.enrollments.subject-replacement', $enrollment) }}">
                    @csrf
                    @if($canRecoverRejection)
                        <div class="alert alert-warning">
                            <strong>Correct a rejection caused by unavailable subjects</strong>
                            <p>Original rejection: {{ $enrollment->remarks ?: 'No reason recorded' }}</p>
                            <p>Confirm with the Cashier that no refund has been issued. This cancels the pending refund follow-up and returns the same paid enrollment to the student. Recorded refunds and student refund requests cannot be recovered here.</p>
                            <input type="hidden" name="recover_rejection" value="1">
                            <label><input type="checkbox" name="confirm_no_refund" value="1" required> I confirmed with the Cashier that no external refund has been issued.</label>
                        </div>
                    @endif
                    <div class="form-group">
                        <span class="d-block font-weight-bold mb-2">Unavailable subject(s)</span>
                        @foreach($subjects as $subject)
                            <div class="custom-control custom-checkbox mb-1">
                                <input class="custom-control-input" type="checkbox"
                                       id="replacement-subject-{{ $enrollment->id }}-{{ $subject->id }}"
                                       name="affected_subject_ids[]" value="{{ $subject->id }}">
                                <label class="custom-control-label" for="replacement-subject-{{ $enrollment->id }}-{{ $subject->id }}">
                                    {{ $subject->subject_code }} — {{ $subject->subject_name }}
                                    ({{ ucfirst(str_replace('_', ' ', $subject->classification)) }})
                                </label>
                            </div>
                            <div class="form-group ml-4">
                                <label for="unavailable-section-{{ $enrollment->id }}-{{ $subject->id }}">Unavailable section, if known</label>
                                <select id="unavailable-section-{{ $enrollment->id }}-{{ $subject->id }}"
                                        name="unavailable_schedule_ids[{{ $subject->id }}]" class="form-control">
                                    <option value="">Not recorded</option>
                                    @foreach($subject->schedules->where('school_year', $enrollment->school_year)->where('semester', $enrollment->semester)->whereNotNull('section_code') as $offering)
                                        <option value="{{ $offering->id }}">{{ $offering->section_code }} · {{ $offering->day_pattern }} · capacity {{ $offering->capacity }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endforeach
                    </div>
                    <div class="form-group">
                        <label for="replacement-reason-{{ $enrollment->id }}">Reason for return</label>
                        <textarea id="replacement-reason-{{ $enrollment->id }}" name="reason"
                                  class="form-control" rows="3" minlength="10" maxlength="2000"
                                  required placeholder="Explain why each selected subject cannot be enrolled in."></textarea>
                    </div>
                    <button type="button" class="dtc-btn dtc-btn-warning"
                            data-dtc-confirm
                            data-dtc-confirm-title="Return enrollment for subject replacement?"
                            data-dtc-confirm-message="This keeps the verified payment and gives the student a replacement or refund choice."
                            data-dtc-confirm-ok="Return for replacement"
                            data-dtc-confirm-tone="warning"
                            data-dtc-confirm-form="#return-subject-replacement-{{ $enrollment->id }}">
                        <i data-lucide="rotate-ccw"></i> Return for Subject Replacement
                    </button>
                </form>
            </div>
        @endif

        @if($canReview)
            <details class="dtc-review-section dtc-review-equivalencies">
                <summary>Academic equivalencies <span class="text-muted">Optional</span></summary>
                <p class="text-muted">Only needed when a different subject will satisfy a curriculum subject. Ordinary same-subject enrollment does not require an equivalency.</p>
                <p class="text-muted">Use only an official academic decision, never a similar title or unit count alone. The approval is directed from the selected curriculum subject to the target subject.</p>
                @if($unclassifiedSubjects->isNotEmpty())
                    <p class="dtc-equivalency-setup-note">To record equivalencies, first classify these subjects in subject management: {{ $unclassifiedSubjects->pluck('subject_code')->join(', ') }}. This does not block ordinary same-subject approval.</p>
                @endif
                @foreach($subjects as $subject)
                    @php
                        $candidates = $equivalentSubjects->filter(fn ($candidate) => $subject->units !== null && $candidate->units !== null && (float) $candidate->units === (float) $subject->units && (int) $candidate->id !== (int) $subject->id && ! $subject->equivalencies->contains('equivalent_subject_id', $candidate->id));
                    @endphp
                    @if(in_array($subject->classification, ['major', 'minor', 'general_education'], true) && $candidates->isNotEmpty())
                        <form method="POST" action="{{ route('registrar.subjects.equivalencies.store', $subject) }}" class="border rounded p-3 mb-2">
                            @csrf
                            <input type="hidden" name="return_enrollment_id" value="{{ $enrollment->id }}">
                            <strong>{{ $subject->subject_code }} — {{ $subject->subject_name }} ({{ ucfirst(str_replace('_', ' ', $subject->classification)) }})</strong>
                            <div class="form-group mt-2">
                                <label for="equivalent-subject-{{ $enrollment->id }}-{{ $subject->id }}">Officially equivalent subject</label>
                                <select id="equivalent-subject-{{ $enrollment->id }}-{{ $subject->id }}" name="equivalent_subject_id" class="form-control" required>
                                    <option value="">Select a subject</option>
                                    @foreach($candidates as $candidate)
                                        <option value="{{ $candidate->id }}">{{ $candidate->subject_code }} — {{ $candidate->subject_name }} ({{ $candidate->program->code }})</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="equivalency-reason-{{ $enrollment->id }}-{{ $subject->id }}">Academic approval reason</label>
                                <textarea id="equivalency-reason-{{ $enrollment->id }}-{{ $subject->id }}" name="approval_reason" class="form-control" rows="2" maxlength="2000" required></textarea>
                            </div>
                            <div class="form-group">
                                <label for="equivalency-irregular-{{ $enrollment->id }}-{{ $subject->id }}">Does the approved mapping require Irregular enrollment?</label>
                                <select id="equivalency-irregular-{{ $enrollment->id }}-{{ $subject->id }}" name="requires_irregular" class="form-control" required>
                                    <option value="">Select approved load treatment</option>
                                    <option value="1">Yes</option>
                                    <option value="0">No</option>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-outline-primary btn-sm">Record equivalency approval</button>
                        </form>
                    @endif
                @endforeach
                <p class="small text-muted">Only matching-unit subjects without an existing directed approval appear as choices.</p>
            </details>
        @endif

        @if($enrollment->replacementRequests->isNotEmpty())
            <div class="dtc-review-section">
                <div class="dtc-review-section-title">
                    <span><i data-lucide="history"></i> Revision History</span>
                </div>
                @foreach($enrollment->replacementRequests->sortBy('id') as $revision)
                    <div class="border rounded p-3 mb-2">
                        <strong>Request #{{ $revision->id }} · {{ ucfirst(str_replace('_', ' ', $revision->status)) }}</strong>
                        <p class="mb-1">{{ $revision->reason }}</p>
                        @if($revision->student_reason)
                            <p class="mb-1"><strong>Student refund reason:</strong> {{ $revision->student_reason }}</p>
                        @endif
                        <ul class="mb-0">
                            @foreach($revision->items as $item)
                                <li>
                                    {{ $item->originalSubject->subject_code }} — {{ $item->originalSubject->subject_name }}
                                    @if($item->originalSchedule)
                                        (unavailable section {{ $item->originalSchedule->section_code }})
                                    @endif
                                    @if($item->selectedSchedule)
                                        → {{ $item->selectedSchedule->courseSubject->subject_code }}
                                        / Section {{ $item->selectedSchedule->section_code }}
                                        (hosted by {{ $item->selectedSchedule->hostingProgram->name ?? $item->selectedSchedule->courseSubject->program->name }})
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </div>
        @endif

        {{-- Credited / Waived Subjects — documentation only, does not affect COR or payment --}}
        @if(in_array($classification, ['transferee', 'shiftee', 'cross_enrollee', 'returnee']))
        <div class="dtc-review-section">
            <div class="dtc-review-section-title">
                <span><i data-lucide="badge-check"></i> Credited Subjects</span>
                <span class="dtc-review-subject-count">{{ $enrollment->creditedSubjects->count() }} recorded</span>
            </div>
            <p class="text-muted mb-2" style="font-size:11.5px;">
                Documentation only — recording a credit here does not change the COR or the enrollment fee.
            </p>

            <div class="dtc-review-subject-list mb-3">
                @forelse ($enrollment->creditedSubjects as $credit)
                    <div class="dtc-review-subject-item" style="align-items:flex-start; flex-direction:column; gap:4px;">
                        <div style="display:flex; align-items:center; gap:8px; width:100%;">
                            <span class="dtc-review-subject-code">{{ $credit->courseSubject->subject_code }}</span>
                            <span class="dtc-review-subject-name">{{ $credit->courseSubject->subject_name }}</span>
                            <div style="margin-left:auto; display:flex; gap:6px;">
                                <button type="button" class="dtc-icon-btn js-edit-credit"
                                        title="Edit"
                                        data-url="{{ route('registrar.enrollments.credited-subjects.update', $credit) }}"
                                        data-subject-label="{{ $credit->courseSubject->subject_code }} — {{ $credit->courseSubject->subject_name }}"
                                        data-equivalent-subject="{{ $credit->equivalent_subject }}"
                                        data-equivalent-school="{{ $credit->equivalent_school }}"
                                        data-credited-units="{{ $credit->credited_units }}"
                                        data-remarks="{{ $credit->remarks }}">
                                    <i data-lucide="pencil"></i>
                                </button>
                                <form method="POST" action="{{ route('registrar.enrollments.credited-subjects.destroy', $credit) }}" id="remove-credit-{{ $credit->id }}">
                                    @csrf @method('DELETE')
                                    <button type="button" class="dtc-icon-btn danger" title="Remove" data-dtc-confirm data-dtc-confirm-tone="danger" data-dtc-confirm-title="Remove credited subject?" data-dtc-confirm-message="This credited subject record will be removed." data-dtc-confirm-ok="Remove Subject" data-dtc-confirm-form="#remove-credit-{{ $credit->id }}">
                                        <i data-lucide="trash-2"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                        <div style="font-size:11.5px; color:var(--dtc-text-muted); padding-left:2px;">
                            @if($credit->equivalent_subject) Equivalent: {{ $credit->equivalent_subject }} @endif
                            @if($credit->equivalent_school) · {{ $credit->equivalent_school }} @endif
                            @if($credit->credited_units) · {{ $credit->credited_units }} unit(s) @endif
                            @if($credit->remarks) <br>{{ $credit->remarks }} @endif
                        </div>
                    </div>
                @empty
                    <p class="text-muted mb-0" style="font-size:12.5px;">No credited subjects recorded.</p>
                @endforelse
            </div>

            {{-- Add credited subject --}}
            <form method="POST" action="{{ route('registrar.enrollments.credited-subjects.store', $enrollment) }}"
                  style="border:1px dashed var(--dtc-border); border-radius:10px; padding:12px;">
                @csrf
                <div class="form-row">
                    <div class="form-group col-md-6 mb-2">
                        <label for="registrar-enrollments-_review-content-course-subject-id-1" class="dtc-form-label" style="font-size:12px;">DTC Subject</label>
                        <select id="registrar-enrollments-_review-content-course-subject-id-1" name="course_subject_id" class="dtc-form-control" required>
                            <option value="">— Select subject —</option>
                            @foreach ($programSubjects as $ps)
                                <option value="{{ $ps->id }}">{{ $ps->subject_code }} — {{ $ps->subject_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group col-md-6 mb-2">
                        <label for="registrar-enrollments-_review-content-credited-units-2" class="dtc-form-label" style="font-size:12px;">Credited Units</label>
                        <input id="registrar-enrollments-_review-content-credited-units-2" type="number" name="credited_units" class="dtc-form-control" step="0.5" min="0" max="6">
                    </div>
                    <div class="form-group col-md-6 mb-2">
                        <label for="registrar-enrollments-_review-content-equivalent-subject-3" class="dtc-form-label" style="font-size:12px;">Equivalent Subject</label>
                        <input id="registrar-enrollments-_review-content-equivalent-subject-3" type="text" name="equivalent_subject" class="dtc-form-control" placeholder="e.g. Mathematics 101">
                    </div>
                    <div class="form-group col-md-6 mb-2">
                        <label for="registrar-enrollments-_review-content-equivalent-school-4" class="dtc-form-label" style="font-size:12px;">Previous School</label>
                        <input id="registrar-enrollments-_review-content-equivalent-school-4" type="text" name="equivalent_school" class="dtc-form-control" placeholder="e.g. ABC College">
                    </div>
                    <div class="form-group col-12 mb-2">
                        <label for="registrar-enrollments-_review-content-remarks-5" class="dtc-form-label" style="font-size:12px;">Remarks</label>
                        <textarea id="registrar-enrollments-_review-content-remarks-5" name="remarks" class="dtc-form-control" rows="2" placeholder="Optional notes"></textarea>
                    </div>
                </div>
                <button type="submit" class="dtc-btn dtc-btn-secondary" style="font-size:12px;">
                    <i data-lucide="plus"></i> Add Credited Subject
                </button>
            </form>
        </div>
        @endif

        {{-- ── Promote to Alumni ─────────────────────────────────────────── --}}
        {{-- Only shown when: enrollment approved + paid + user still has student role --}}
        @if($enrollment->status === 'approved' && $enrollment->is_paid && $enrollment->user->hasRole('student'))
        <div class="dtc-review-section" id="promote-alumni-section-{{ $enrollment->id }}">
            <div class="dtc-review-section-title">
                <span><i data-lucide="user-check"></i> Graduate Promotion</span>
            </div>

            {{-- Collapsed trigger row --}}
            <div id="promote-alumni-trigger-{{ $enrollment->id }}">
                <p style="font-size:12.5px; color:var(--dtc-text-secondary); margin-bottom:10px;">
                    Mark this student as a graduate and activate their Alumni portal access.
                </p>
                <button type="button"
                        class="dtc-btn dtc-btn-outline-primary js-promote-trigger"
                        style="font-size:12px;"
                        data-target="promote-alumni-form-{{ $enrollment->id }}">
                    <i data-lucide="graduation-cap"></i> Promote to Alumni…
                </button>
            </div>

            {{-- Inline form — hidden by default --}}
            <div id="promote-alumni-form-{{ $enrollment->id }}"
                 style="display:none; margin-top:14px; border:1px dashed var(--dtc-border); border-radius:10px; padding:14px;">
                <form method="POST"
                      action="{{ route('registrar.students.promote-alumni', $enrollment->user) }}"
                      id="promote-alumni-submit-{{ $enrollment->id }}">
                    @csrf
                    <div class="form-row align-items-end">
                        <div class="form-group col-md-6 mb-2">
                            <label for="registrar-enrollments-_review-content-graduation-year-6" class="dtc-form-label" style="font-size:12px;">
                                Graduation Year <span class="dtc-required">*</span>
                            </label>
                            <select id="registrar-enrollments-_review-content-graduation-year-6" name="graduation_year" class="dtc-form-control" required>
                                <option value="">— Select year —</option>
                                @for($y = date('Y') + 1; $y >= 1990; $y--)
                                    <option value="{{ $y }}">{{ $y }}</option>
                                @endfor
                            </select>
                        </div>
                        <div class="form-group col-md-6 mb-2 d-flex" style="gap:8px; align-items:flex-end;">
                            <button type="button"
                                    class="dtc-btn dtc-btn-secondary js-promote-cancel"
                                    style="font-size:12px;"
                                    data-target="promote-alumni-form-{{ $enrollment->id }}"
                                    data-trigger="promote-alumni-trigger-{{ $enrollment->id }}">
                                Cancel
                            </button>
                            <button type="button"
                                    class="dtc-btn dtc-btn-primary"
                                    style="font-size:12px;"
                                    data-dtc-confirm
                                    data-dtc-confirm-title="Promote to Alumni?"
                                    data-dtc-confirm-message="The account for {subject} will be promoted to Alumni and the graduation year will be recorded."
                                    data-dtc-confirm-subject="{{ $enrollment->user->name }}"
                                    data-dtc-confirm-ok="Promote"
                                    data-dtc-confirm-tone="success"
                                    data-dtc-confirm-form="#promote-alumni-submit-{{ $enrollment->id }}">
                                <i data-lucide="user-check"></i> Confirm Promotion
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        @endif

        {{-- Reject reason — hidden until "Reject" is clicked --}}
        <div class="dtc-review-reject-panel rg-rejection-panel" id="rejectPanel-{{ $enrollment->id }}">
            <p><strong>Reject ends this enrollment request.</strong> Its verified payment stays recorded and requires Cashier refund reconciliation. This does not give the student a subject replacement choice.</p>
            @if(! $replacementRequest)
                <p>If the problem is a full or unavailable class, <a href="#return-subject-replacement-{{ $enrollment->id }}">return the affected subjects for replacement</a> instead.</p>
            @endif
            <form method="POST" action="{{ route('registrar.enrollments.reject', $enrollment) }}" id="rejectForm-{{ $enrollment->id }}">
                @csrf
                <label class="dtc-form-label" for="remarks-{{ $enrollment->id }}">
                    Reason for rejection <span class="dtc-required">*</span>
                </label>
                <textarea name="remarks" id="remarks-{{ $enrollment->id }}" class="dtc-form-control" rows="4" required aria-describedby="rejection-help-{{ $enrollment->id }}"
                          placeholder="Explain what needs to be corrected."></textarea>
                <p class="dtc-form-help" id="rejection-help-{{ $enrollment->id }}">The applicant will receive this reason.</p>
            </form>
        </div>

    </div>

    {{-- ── Footer ─────────────────────────────────────────────────────── --}}
    @if($canReview || $canAssignBeforePayment)
    <div class="dtc-review-footer rg-enrollment-decisions">

        <div class="dtc-review-footer-default">

            @if($canAssignBeforePayment)
                <form id="approve-enrollment-{{ $enrollment->id }}" method="POST" action="{{ route('registrar.enrollments.approve', $enrollment) }}" class="dtc-review-footer-right">
                    @csrf
                    <button type="submit" name="record_sections" value="1" class="dtc-btn dtc-btn-primary">Assign sections</button>
                </form>
            @endif

            @if($canReview)
            <div class="dtc-review-footer-right">
                <button type="button" class="dtc-btn dtc-btn-outline-danger dtc-review-reject-trigger"
                        data-target="rejectPanel-{{ $enrollment->id }}">
                    Reject
                </button>

                <form id="approve-enrollment-{{ $enrollment->id }}"
                      method="POST"
                      action="{{ route('registrar.enrollments.approve', $enrollment) }}">
                    @csrf
                    <button type="button"
                            class="dtc-btn dtc-btn-success"
                            data-dtc-confirm
                            data-dtc-confirm-title="Approve enrollment?"
                            data-dtc-confirm-message="The enrollment for {subject} will be approved and become official."
                            data-dtc-confirm-subject="{{ $enrollment->user->name }}"
                            data-dtc-confirm-ok="Approve"
                            data-dtc-confirm-tone="success"
                            data-dtc-confirm-form="#approve-enrollment-{{ $enrollment->id }}">
                        Approve
                    </button>
                </form>
            </div>
            @endif
        </div>

        @if($canReview)
        <div class="dtc-review-footer-reject u-hidden" >
            <div class="rg-rejection-buttons">
            <button type="button" class="dtc-btn dtc-btn-secondary dtc-review-reject-cancel">Cancel</button>
            <button type="button" class="dtc-btn dtc-btn-danger" data-dtc-confirm data-dtc-confirm-tone="danger" data-dtc-confirm-title="Reject paid enrollment?" data-dtc-confirm-message="This ends the enrollment request for {subject} and requires Cashier refund reconciliation. For full classes, cancel and use Return for Subject Replacement to preserve the paid enrollment." data-dtc-confirm-subject="{{ $enrollment->user->name }}" data-dtc-confirm-ok="Reject enrollment" data-dtc-confirm-form="#rejectForm-{{ $enrollment->id }}">
                Confirm rejection
            </button>
            </div>
        </div>
        @endif

    </div>
    @endif
</div>

{{-- Edit Credited Subject modal — shared, repopulated by JS per row --}}
<div class="modal fade" id="editCreditModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <form method="POST" id="edit-credit-form" action="">
                @csrf @method('PUT')
                <div class="modal-header">
                    <h5 class="modal-title u-text">
                        <i data-lucide="badge-check" class="mr-2" style="color:var(--dtc-primary);"></i>
                        Edit Credited Subject
                    </h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <p id="edit-credit-subject-label" class="font-weight-bold u-text mb-3"></p>
                    <div class="form-group">
                        <label for="edit-credit-units">Credited Units</label>
                        <input type="number" name="credited_units" id="edit-credit-units" class="form-control" step="0.5" min="0" max="6">
                    </div>
                    <div class="form-group">
                        <label for="edit-credit-equiv-subject">Equivalent Subject</label>
                        <input type="text" name="equivalent_subject" id="edit-credit-equiv-subject" class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="edit-credit-equiv-school">Previous School</label>
                        <input type="text" name="equivalent_school" id="edit-credit-equiv-school" class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="edit-credit-remarks">Remarks</label>
                        <textarea name="remarks" id="edit-credit-remarks" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer dtc-balanced-footer">
                    <button type="button" class="dtc-btn dtc-btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="dtc-btn dtc-btn-primary">
                        <i data-lucide="save"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
(function initCreditedSubjectScripts() {
    document.querySelectorAll('.js-edit-credit').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.getElementById('edit-credit-form').action = this.dataset.url;
            document.getElementById('edit-credit-subject-label').textContent = this.dataset.subjectLabel;
            document.getElementById('edit-credit-units').value = this.dataset.creditedUnits || '';
            document.getElementById('edit-credit-equiv-subject').value = this.dataset.equivalentSubject || '';
            document.getElementById('edit-credit-equiv-school').value = this.dataset.equivalentSchool || '';
            document.getElementById('edit-credit-remarks').value = this.dataset.remarks || '';
            $('#editCreditModal').modal('show');
        });
    });
})();

// ── Promote to Alumni — inline form expand/collapse ──────────────────
document.querySelectorAll('.js-promote-trigger').forEach(function (btn) {
    btn.addEventListener('click', function () {
        var formEl    = document.getElementById(this.dataset.target);
        var triggerEl = this.closest('[id^="promote-alumni-trigger-"]');
        if (formEl)    formEl.style.display = '';
        if (triggerEl) triggerEl.style.display = 'none';
    });
});

document.querySelectorAll('.js-promote-cancel').forEach(function (btn) {
    btn.addEventListener('click', function () {
        var formEl    = document.getElementById(this.dataset.target);
        var triggerEl = document.getElementById(this.dataset.trigger);
        if (formEl)    formEl.style.display = 'none';
        if (triggerEl) triggerEl.style.display = '';
    });
});
</script>
