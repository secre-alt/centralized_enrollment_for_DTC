@php
    $initials = collect(preg_split('/\s+/', trim($enrollment->user->name)))
        ->filter()
        ->map(fn($p) => mb_strtoupper(mb_substr($p, 0, 1)))
        ->take(2)
        ->implode('');
    $subjectCount = count($subjects);
@endphp

<div id="review-content">

    {{-- ── Header ─────────────────────────────────────────────────────── --}}
    <div class="dtc-review-header">
        <div class="dtc-review-identity">
            <div class="dtc-review-avatar">{{ $initials ?: '?' }}</div>
            <div class="dtc-review-identity-copy">
                <h5 class="dtc-review-name">{{ $enrollment->user->name }}</h5>
                <p class="dtc-review-email"><i data-lucide="mail"></i> {{ $enrollment->user->email }}</p>
            </div>
        </div>
        <div class="dtc-review-header-right">
            @if($enrollment->is_paid)
                <span class="dtc-status-badge is-success"><span class="dtc-status-dot"></span> Enrolled &amp; Paid</span>
            @elseif($enrollment->status === 'approved')
                <span class="dtc-status-badge is-info"><span class="dtc-status-dot"></span> Approved — Awaiting Payment</span>
            @elseif($enrollment->status === 'rejected')
                <span class="dtc-status-badge is-danger"><span class="dtc-status-dot"></span> Rejected</span>
            @else
                <span class="dtc-status-badge is-warning"><span class="dtc-status-dot"></span> Pending Review</span>
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
            </div>
        </div>

        <div class="dtc-review-section">
            <div class="dtc-review-section-title">
                <span><i data-lucide="book"></i> Selected Subjects</span>
                <span class="dtc-review-subject-count">{{ $subjectCount }} subject{{ $subjectCount === 1 ? '' : 's' }}</span>
            </div>

            <div class="dtc-review-subject-list">
                @forelse ($subjects as $subject)
                    <div class="dtc-review-subject-item">
                        <span class="dtc-review-subject-code">{{ $subject->subject_code }}</span>
                        <span class="dtc-review-subject-name">{{ $subject->subject_name }}</span>
                    </div>
                @empty
                    <p class="text-muted mb-0" style="font-size:12.5px;">No subjects selected.</p>
                @endforelse
            </div>
        </div>

        {{-- Reject reason — hidden until "Reject" is clicked --}}
        <div class="dtc-review-reject-panel" id="rejectPanel-{{ $enrollment->id }}">
            <form method="POST" action="{{ route('registrar.enrollments.reject', $enrollment) }}" id="rejectForm-{{ $enrollment->id }}">
                @csrf
                <label class="dtc-form-label" for="remarks-{{ $enrollment->id }}">
                    Reason / Remarks <span class="dtc-required">*</span>
                </label>
                <textarea name="remarks" id="remarks-{{ $enrollment->id }}" class="dtc-form-control" rows="3" required
                          placeholder="Let the applicant know why this enrollment is being rejected…"></textarea>
                <p class="dtc-form-help">This message will be shared with the applicant.</p>
            </form>
        </div>

    </div>

    {{-- ── Footer ─────────────────────────────────────────────────────── --}}
    <div class="dtc-review-footer">

        <div class="dtc-review-footer-default">
            <button type="button" class="dtc-btn dtc-btn-secondary" data-dismiss="modal">Close</button>

            <div class="dtc-review-footer-right">
                <button type="button" class="dtc-btn dtc-btn-outline-danger dtc-review-reject-trigger"
                        data-target="rejectPanel-{{ $enrollment->id }}">
                    <i data-lucide="x"></i> Reject
                </button>

                <form method="POST" action="{{ route('registrar.enrollments.approve', $enrollment) }}">
                    @csrf
                    <button type="submit" class="dtc-btn dtc-btn-success"
                            onclick="return confirm('Approve this enrollment?')">
                        <i data-lucide="check"></i> Approve
                    </button>
                </form>
            </div>
        </div>

        <div class="dtc-review-footer-reject u-hidden" >
            <button type="button" class="dtc-btn dtc-btn-secondary dtc-review-reject-cancel">Cancel</button>
            <button type="submit" form="rejectForm-{{ $enrollment->id }}" class="dtc-btn dtc-btn-danger">
                <i data-lucide="check"></i> Confirm Rejection
            </button>
        </div>

    </div>
</div>