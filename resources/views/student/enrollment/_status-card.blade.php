@php
    $payment = $enrollment->payment;
    $isApproved = $enrollment->status === 'approved';
    $isEnrolled = $isApproved && $enrollment->is_paid;
    $isRejected = $enrollment->status === 'rejected';
    $replacementRequest = $enrollment->replacementRequests
        ->sortByDesc('id')
        ->first();
    $isReplacementRequired = $enrollment->status === 'pending'
        && $replacementRequest
        && $replacementRequest->status === 'open';
    $isReplacementSubmitted = $enrollment->status === 'pending'
        && $replacementRequest
        && in_array($replacementRequest->status, ['submitted', 'approved'], true);
    $isRefundRequested = $replacementRequest && $replacementRequest->status === 'refund_requested';
    $refundFullyConfirmed = $isRejected && $enrollment->is_paid && (bool) ($enrollment->refund_fully_confirmed ?? false);
    $canPay = in_array($enrollment->status, ['pending', 'approved'], true);
    $isRegistrarReview = $enrollment->status === 'pending' && $enrollment->is_paid;
    $isPaymentReview = $canPay && !$enrollment->is_paid && $payment && $payment->status === 'pending';
    $isPaymentRejected = $canPay && !$enrollment->is_paid && $payment && $payment->status === 'rejected';
    $isPaymentMismatch = $canPay && !$enrollment->is_paid && $payment && $payment->status === 'verified';
    $hasOutstandingTermPayment = $canPay && !$enrollment->is_paid && (bool) ($enrollment->has_outstanding_term_payment ?? false);
    $isPaymentDue = $canPay && !$enrollment->is_paid && !$isPaymentReview && !$isPaymentRejected && !$isPaymentMismatch && !$hasOutstandingTermPayment;
    $state = $isEnrolled ? 'success' : ($isRejected ? 'danger' : ($isReplacementRequired ? 'warning' : (($isRegistrarReview || $isPaymentReview) ? 'info' : (($isPaymentDue || $isPaymentRejected || $isPaymentMismatch || $hasOutstandingTermPayment) ? 'warning' : 'neutral'))));
    $paymentStep = $enrollment->is_paid ? 'done' : ($canPay ? 'current' : 'upcoming');
    $registrarStep = $isApproved ? 'done' : ($isRegistrarReview ? 'current' : 'upcoming');
    $enrolledStep = $isEnrolled ? 'done' : 'upcoming';
@endphp

<article class="card dtc-enrollment-status-card mb-3">
    <div class="dtc-enrollment-program-zone">
        <div class="dtc-enrollment-program">
            <span class="dtc-icon-swatch is-primary dtc-enrollment-program-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <path d="m2.5 9 9.5-5 9.5 5-9.5 5-9.5-5Z"></path>
                    <path d="M6.5 11.1v5.1c3.4 2.6 7.6 2.6 11 0v-5.1"></path>
                    <path d="M21.5 9v6"></path>
                </svg>
            </span>
            <div class="dtc-enrollment-program-copy">
                <span class="dtc-enrollment-eyebrow">Program</span>
                <h2>{{ $enrollment->program->name }}</h2>
            </div>
        </div>

        <span class="dtc-status-badge is-{{ $state }} dtc-enrollment-status-pill">
            <span class="dtc-status-dot" aria-hidden="true"></span>
            @if($isEnrolled)
                Enrolled
            @elseif($isPaymentRejected)
                Payment action needed
            @elseif($isPaymentReview)
                In review
            @elseif($isReplacementRequired)
                Subject replacement required
            @elseif($isRegistrarReview)
                In review
            @elseif($isPaymentDue)
                Payment due
            @elseif($hasOutstandingTermPayment)
                Reconciliation needed
            @elseif($isRejected)
                Rejected
            @else
                Status unavailable
            @endif
        </span>

        <div class="dtc-enrollment-chips" aria-label="Enrollment details">
            <span class="dtc-enrollment-chip">Year {{ $enrollment->year_level }}</span>
            <span class="dtc-enrollment-chip">Semester {{ $enrollment->semester }}</span>
            <span class="dtc-enrollment-chip">{{ count($enrollment->subject_ids ?? []) }} subjects</span>
        </div>
    </div>

    <div class="dtc-enrollment-progress-zone">
        <h3 class="dtc-enrollment-zone-label">Progress</h3>
        <ol class="dtc-enrollment-progress" aria-label="Enrollment progress">
            <li class="dtc-enrollment-progress-step is-done {{ $isEnrolled ? 'has-complete-connector' : '' }}">
                <span class="dtc-enrollment-progress-marker" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12 4 4L19 6"></path></svg>
                </span>
                <span class="dtc-enrollment-progress-copy">
                    <strong>Submitted</strong>
                    <span>{{ $enrollment->created_at->format('M j, Y') }}</span>
                </span>
            </li>
            <li class="dtc-enrollment-progress-step is-{{ $paymentStep }} {{ $isEnrolled ? 'has-complete-connector' : '' }}" @if($paymentStep === 'current') aria-current="step" @endif>
                <span class="dtc-enrollment-progress-marker" aria-hidden="true">
                    @if($paymentStep === 'done')
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12 4 4L19 6"></path></svg>
                    @else
                        <span></span>
                    @endif
                </span>
                <span class="dtc-enrollment-progress-copy">
                    <strong>Payment</strong>
                    <span>{{ $enrollment->is_paid ? 'Confirmed' : ($isPaymentReview ? 'Verifying' : ($isPaymentRejected ? 'Action needed' : ($hasOutstandingTermPayment ? 'Blocked' : ($isPaymentMismatch ? 'Confirm with Cashier' : ($canPay ? 'Payment needed' : 'Not yet'))))) }}</span>
                </span>
            </li>
            <li class="dtc-enrollment-progress-step is-{{ $registrarStep }} {{ $isApproved ? 'has-complete-connector' : '' }}" @if($registrarStep === 'current') aria-current="step" @endif>
                <span class="dtc-enrollment-progress-marker" aria-hidden="true">
                    @if($registrarStep === 'done')
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12 4 4L19 6"></path></svg>
                    @else
                        <span></span>
                    @endif
                </span>
                <span class="dtc-enrollment-progress-copy">
                    <strong>Registrar review</strong>
                    <span>{{ $isApproved ? 'Approved' : ($isRegistrarReview ? 'Reviewing' : ($isRejected ? 'Rejected' : 'After payment')) }}</span>
                </span>
            </li>
            <li class="dtc-enrollment-progress-step is-{{ $enrolledStep }}" @if($enrolledStep === 'done') aria-current="step" @endif>
                <span class="dtc-enrollment-progress-marker" aria-hidden="true">
                    @if($enrolledStep === 'done')
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12 4 4L19 6"></path></svg>
                    @else
                        <span></span>
                    @endif
                </span>
                <span class="dtc-enrollment-progress-copy">
                    <strong>Enrolled</strong>
                    <span>{{ $isEnrolled ? 'This semester' : 'Not yet' }}</span>
                </span>
            </li>
        </ol>
    </div>

    <div class="dtc-enrollment-callout-zone">
        <div class="dtc-enrollment-callout is-{{ $state }} {{ $isEnrolled ? 'is-enrolled' : '' }}" @if($isRejected || $isPaymentRejected || $isPaymentMismatch) role="alert" @else role="status" @endif>
            @unless($isEnrolled)
                <span class="dtc-icon-swatch dtc-enrollment-callout-icon" aria-hidden="true">
                    @if($isRejected || $isPaymentRejected)
                    <i data-lucide="circle-alert"></i>
                    @elseif($isRegistrarReview || $isPaymentReview || $isReplacementRequired)
                    <i data-lucide="clock-3"></i>
                    @else
                    <i data-lucide="wallet"></i>
                    @endif
                </span>
            @endunless
            <div class="dtc-enrollment-callout-copy">
                @if($isEnrolled)
                    <strong>Payment confirmed</strong>
                    <span>You are officially enrolled for this semester.</span>
                @elseif($isRejected)
                    <strong>{{ $enrollment->is_paid ? ($refundFullyConfirmed ? 'Enrollment rejected · refund confirmed' : 'Enrollment rejected · Cashier follow-up needed') : 'Enrollment needs attention' }}</strong>
                    <span>Registrar’s reason: {{ $enrollment->remarks ?: 'No reason recorded. Contact the Registrar for clarification.' }}</span>
                    @if($enrollment->is_paid)
                        @include('student.enrollment._refund-next-steps')
                    @endif
                @elseif($isPaymentRejected)
                    <strong>Payment proof needs correction</strong>
                    <span>{{ $payment->rejection_reason ?: 'Review your payment details and submit corrected proof.' }}</span>
                @elseif($isPaymentMismatch)
                    <strong>Payment record needs confirmation</strong>
                    <span>A verified payment exists, but this enrollment is not marked paid. Contact the Cashier before making another payment.</span>
                @elseif($hasOutstandingTermPayment)
                    <strong>Payment blocked pending reconciliation</strong>
                    <span>A verified or pending payment already exists for this academic term. Contact the Cashier to resolve the existing payment or refund before paying again.</span>
                @elseif($isPaymentReview)
                    <strong>Payment under review</strong>
                    <span>The Cashier is checking your payment. No action needed.</span>
                @elseif($isReplacementRequired)
                    <strong>Subject Replacement Required</strong>
                    <span>{{ $replacementRequest->reason }} Your verified payment remains attached to this enrollment.</span>
                @elseif($isReplacementSubmitted)
                    <strong>Revised enrollment submitted</strong>
                    <span>Your updated class selections are back with the Registrar for review. Your verified payment remains attached.</span>
                @elseif($isRefundRequested)
                    <strong>Refund request pending Cashier reconciliation</strong>
                    <span>{{ $replacementRequest->student_reason }} Your original payment has not been marked refunded.</span>
                @elseif($isRegistrarReview)
                    <strong>Registrar review in progress</strong>
                    <span>Your payment is confirmed. The Registrar is reviewing your selected subjects.</span>
                @elseif($isPaymentDue)
                    <strong>Payment needed first</strong>
                    <span>Pay the enrollment fee at the Cashier or submit GCash proof. Registrar review begins after payment is verified.</span>
                @else
                    <strong>Status unavailable</strong>
                    <span>Please contact the Registrar for help with this enrollment.</span>
                @endif
            </div>

            @if($isEnrolled)
                <a href="{{ route('portal.cor.show', ['enrollment' => $enrollment->id]) }}" class="dtc-btn dtc-btn-primary dtc-enrollment-callout-action">
                    <i data-lucide="file-text" aria-hidden="true"></i>
                    View Certificate of Registration
                </a>
            @elseif($isPaymentRejected)
                <a href="{{ route('portal.enrollment.payment-info', $enrollment) }}" class="dtc-btn dtc-btn-primary dtc-enrollment-callout-action">
                    <i data-lucide="rotate-ccw" aria-hidden="true"></i>
                    Review and resubmit proof
                </a>
            @elseif($isPaymentMismatch)
                <a href="{{ route('portal.enrollment.payment-info', $enrollment) }}" class="dtc-btn dtc-btn-primary dtc-enrollment-callout-action">
                    <i data-lucide="wallet" aria-hidden="true"></i>
                    View payment details
                </a>
            @elseif($hasOutstandingTermPayment)
                <a href="{{ route('portal.enrollment.payment-info', $enrollment) }}" class="dtc-btn dtc-btn-primary dtc-enrollment-callout-action">
                    <i data-lucide="wallet" aria-hidden="true"></i>
                    Review payment reconciliation
                </a>
            @elseif($isPaymentDue)
                <a href="{{ route('portal.enrollment.payment-info', $enrollment) }}" class="dtc-btn dtc-btn-primary dtc-enrollment-callout-action">
                    <i data-lucide="wallet" aria-hidden="true"></i>
                    Pay tuition balance
                </a>
            @elseif($isRejected && !$enrollment->is_paid)
                <a href="{{ route('portal.enrollment.create') }}" class="dtc-btn dtc-btn-primary dtc-enrollment-callout-action">
                    <i data-lucide="rotate-ccw" aria-hidden="true"></i>
                    Correct enrollment
                </a>
            @elseif($isRejected && $enrollment->is_paid)
                <a href="{{ route('portal.enrollment.payment-info', $enrollment) }}" class="dtc-btn dtc-btn-primary dtc-enrollment-callout-action">
                    <i data-lucide="wallet" aria-hidden="true"></i>
                    {{ $refundFullyConfirmed ? 'View refund confirmation' : 'View payment details' }}
                </a>
            @elseif($isPaymentReview && $payment->proof_of_payment)
                <a href="{{ route('portal.enrollment.payment.proof', $payment) }}" target="_blank" rel="noopener" class="dtc-enrollment-proof-link">
                    <i data-lucide="file-image" aria-hidden="true"></i>
                    View submitted proof
                    <i data-lucide="arrow-up-right" aria-hidden="true"></i>
                    <span class="sr-only"> (opens in a new tab)</span>
                </a>
            @endif
        </div>
    </div>

    @if($isReplacementRequired || $isReplacementSubmitted || $isRefundRequested)
        <div class="card-body border-top">
            @if($isReplacementRequired)
                <h3 class="h6">Subject Replacement Required</h3>
                <p>{{ $replacementRequest->reason }}</p>
                <ul>
                    @foreach($replacementRequest->items as $item)
                        <li>{{ $item->originalSubject->subject_code }} — {{ $item->originalSubject->subject_name }}</li>
                    @endforeach
                </ul>
                <p class="small text-muted">Payment status: verified. Your degree program is unchanged.</p>
                <a href="{{ route('portal.enrollment.revise', $enrollment) }}" class="btn btn-primary">Replace subject / revise enrollment</a>
            @elseif($isReplacementSubmitted)
                <h3 class="h6">Revision history</h3>
                <p class="mb-1">Revision request #{{ $replacementRequest->id }}: {{ ucfirst($replacementRequest->status) }}</p>
                @foreach($replacementRequest->items as $item)
                    @if($item->selectedSchedule)
                        <p class="mb-1">{{ $item->originalSubject->subject_code }} → Section {{ $item->selectedSchedule->section_code }}
                            ({{ $item->selectedSchedule->courseSubject->subject_code }})
                            (hosted by {{ $item->selectedSchedule->hostingProgram->name ?? $item->selectedSchedule->courseSubject->program->name }})</p>
                    @endif
                @endforeach
            @else
                <h3 class="h6">Refund request</h3>
                <p class="mb-0">Your request is pending Cashier confirmation. The original verified payment and receipt remain in your payment history.</p>
            @endif
        </div>
    @endif
</article>
