@php

    $fee = $latestPayment && $enrollment->is_paid
        ? $latestPayment->amount
        : \App\Models\Setting::get('enrollment_fee', 500);
@endphp

<div id="payment-content">

    {{-- ── Header ─────────────────────────────────────────────────────── --}}
    <div class="dtc-review-header">
        <div class="dtc-review-identity">
            <x-dtc.user-avatar :user="$enrollment->user" class="dtc-review-avatar" />
            <div class="dtc-review-identity-copy">
                <h5 class="dtc-review-name">{{ $enrollment->user->name }}</h5>
                <p class="dtc-review-email">{{ $enrollment->user->email }}</p>
            </div>
        </div>
        <div class="dtc-review-header-right">
            @if ($enrollment->is_paid)
                {{-- Paid state is already shown by the receipt action below; no badge needed. --}}
            @elseif ($latestPayment && $latestPayment->isGcash() && $latestPayment->isPending())
                <span class="dtc-status-badge is-warning"><span class="dtc-status-dot"></span> GCash Pending</span>
            @endif
            <button type="button" class="dtc-review-close" data-dismiss="modal" aria-label="Close">
                <i data-lucide="x"></i>
            </button>
        </div>
    </div>

    {{-- ── Body ───────────────────────────────────────────────────────── --}}
    <div class="dtc-review-body">

        @if ($errors->any())
            <div class="alert alert-danger">{{ $errors->first() }}</div>
        @endif

        @if ($enrollment->status === 'rejected' && $enrollment->is_paid)
            <div class="alert alert-warning" role="status">
                <strong>Refund follow-up</strong>
                <p class="mb-2">
                    The original verified payment and receipt are preserved.
                    @if ($refundFullyConfirmed)
                        The Cashier recorded confirmation of the full external refund.
                    @else
                        Record a refund only after confirming the full external refund was completed.
                    @endif
                </p>
                @foreach ($verifiedPayments as $verifiedPayment)
                    <div class="border-top pt-2 mt-2">
                        <div>Receipt {{ $verifiedPayment->receipt_no }} · ₱{{ number_format($verifiedPayment->amount, 2) }}</div>
                        @if ($verifiedPayment->refundIsPending())
                            <span class="d-block mb-2">Refund pending confirmation</span>
                            <form method="POST" action="{{ route('cashier.payments.confirm-refund', $verifiedPayment) }}">
                                @csrf
                                <label class="dtc-form-label" for="refund-amount-{{ $verifiedPayment->id }}">Full refund amount (must match ₱{{ number_format($verifiedPayment->amount, 2) }})</label>
                                <input id="refund-amount-{{ $verifiedPayment->id }}" class="dtc-form-control mb-2" name="refund_amount" type="number" min="0.01" step="0.01" required>
                                <label class="dtc-form-label" for="refund-reference-{{ $verifiedPayment->id }}">External refund reference / receipt</label>
                                <input id="refund-reference-{{ $verifiedPayment->id }}" class="dtc-form-control mb-2" name="refund_reference" maxlength="255" required>
                                <button type="submit" class="dtc-btn dtc-btn-success">Confirm completed refund</button>
                            </form>
                        @elseif ($verifiedPayment->refundIsComplete())
                            <span class="d-block">Full refund of ₱{{ number_format($verifiedPayment->refund_amount, 2) }} confirmed · {{ $verifiedPayment->refund_reference }} · {{ $verifiedPayment->refunded_at?->format('M d, Y h:i A') }} · {{ $verifiedPayment->refundConfirmer?->name ?? 'Cashier record' }}</span>
                        @else
                            <span class="d-block">Refund status needs reconciliation before another payment.</span>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
        @if ($outstandingTermPayments->isNotEmpty() && ! ($enrollment->status === 'rejected' && $enrollment->is_paid))
            <div class="alert alert-warning" role="alert">
                <strong>Payment blocked pending reconciliation.</strong>
                <p class="mb-2">Do not collect or verify another payment for this student and term until the existing record is resolved.</p>
                @foreach ($outstandingTermPayments as $outstandingPayment)
                    <a class="d-block" href="{{ route('cashier.payments.show', $outstandingPayment->enrollment) }}">
                        Enrollment #{{ $outstandingPayment->enrollment_id }} · Receipt {{ $outstandingPayment->receipt_no ?: 'not issued' }} ·
                        {{ $outstandingPayment->refundIsPending() ? 'Refund pending confirmation' : ($outstandingPayment->isPending() ? 'Payment proof pending verification' : 'Verified payment not refunded') }}
                    </a>
                @endforeach
            </div>
        @endif

        <div class="dtc-review-section">
            <div class="dtc-review-info-grid">
                <div class="dtc-review-info">
                    <span class="dtc-review-info-label">Program</span>
                    <span class="dtc-review-info-value">{{ $enrollment->program->name }}</span>
                </div>
                <div class="dtc-review-info">
                    <span class="dtc-review-info-label">Year / Semester</span>
                    <span class="dtc-review-info-value">Year {{ $enrollment->year_level }} — Sem {{ $enrollment->semester }}</span>
                </div>
                <div class="dtc-review-info">
                    <span class="dtc-review-info-label">Subjects</span>
                    <span class="dtc-review-info-value">{{ count($enrollment->subject_ids) }} subjects</span>
                </div>
                <div class="dtc-review-info">
                    <span class="dtc-review-info-label">Payment method</span>
                    <span class="dtc-review-info-value">
                        @if ($latestPayment && $latestPayment->isGcash() && $latestPayment->isPending())
                            GCash
                        @elseif ($enrollment->is_paid && $latestPayment)
                            {{ $latestPayment->isWalkIn() ? 'Walk-in / Cash' : 'GCash' }}
                        @else
                            Walk-in / Cash
                        @endif
                    </span>
                </div>
            </div>

            <div style="display:flex; justify-content:space-between; align-items:center;
                        margin-top:14px; padding-top:14px; border-top:1px dashed var(--dtc-border);">
                <span class="dtc-review-info-label u-text-sm" >Enrollment Fee</span>
                <span style="font-size:22px; font-weight:800; color:#15803D;">
                    ₱{{ number_format($fee, 2) }}
                </span>
            </div>
        </div>

        @if (! $enrollment->is_paid)
            <div class="dtc-payment-info-notice">
                <span>Cashier payment verification sends this enrollment to the Registrar for subject review.</span>
            </div>
        @endif

        @if (! $enrollment->is_paid && $latestPayment && $latestPayment->isPending() && $latestPayment->isGcash())
            {{-- ── GCash Review Branch ─────────────────────────────── --}}
            <div class="dtc-review-section">
                <div class="dtc-review-section-title">
                    <span>GCash details</span>
                </div>
                <div class="dtc-review-info-grid">
                    <div class="dtc-review-info">
                        <span class="dtc-review-info-label">Reference No.</span>
                        <span class="dtc-review-info-value">{{ $latestPayment->reference_number }}</span>
                    </div>
                    <div class="dtc-review-info">
                        <span class="dtc-review-info-label">Submitted</span>
                        <span class="dtc-review-info-value">{{ $latestPayment->created_at->format('M d, Y h:i A') }}</span>
                    </div>
                </div>
                <a href="{{ route('cashier.payments.proof', $latestPayment) }}" target="_blank"
                   class="dtc-btn dtc-btn-secondary" style="margin-top:12px;">
                    View proof
                </a>
            </div>

            {{-- Reject reason — hidden until "Reject" is clicked --}}
            <div class="dtc-review-reject-panel" id="rejectPanel-{{ $enrollment->id }}">
                <form method="POST" action="{{ route('cashier.payments.reject', $latestPayment) }}" id="rejectForm-{{ $enrollment->id }}">
                    @csrf
                    <label class="dtc-form-label" for="remarks-{{ $enrollment->id }}">
                        Rejection Reason <span class="dtc-required">*</span>
                    </label>
                    <textarea name="remarks" id="remarks-{{ $enrollment->id }}" class="dtc-form-control" rows="3" required
                              placeholder="e.g. Reference number not found in our GCash records."></textarea>
                    <p class="dtc-form-help">This message will be shared with the applicant.</p>
                </form>
            </div>

        @elseif (! $enrollment->is_paid && $outstandingTermPayments->isEmpty() && (! $latestPayment || $latestPayment->isRejected()))
            {{-- ── Walk-in Branch (also shown after a GCash rejection) ── --}}
            <form method="POST" action="{{ route('cashier.payments.store', $enrollment) }}" id="walkinForm-{{ $enrollment->id }}">
                @csrf
            </form>

        @elseif ($enrollment->is_paid)
            <div class="dtc-review-section">
                <button type="button" class="dtc-btn dtc-btn-secondary dtc-receipt-btn"
                        data-url="{{ route('cashier.payments.receipt', $enrollment) }}">
                    View receipt
                </button>
            </div>
        @endif

    </div>

    {{-- ── Footer ─────────────────────────────────────────────────────── --}}
    <div class="dtc-review-footer">

        <div class="dtc-review-footer-default">
            <button type="button" class="dtc-btn dtc-btn-secondary" data-dismiss="modal">Close</button>

            @if (! $enrollment->is_paid && $latestPayment && $latestPayment->isPending() && $latestPayment->isGcash())
                <div class="dtc-review-footer-right">
                    <button type="button" class="dtc-btn dtc-btn-outline-danger dtc-review-reject-trigger"
                            data-target="rejectPanel-{{ $enrollment->id }}">
                        Reject
                    </button>
                    <form id="verify-gcash-{{ $latestPayment->id }}"
                          method="POST"
                          action="{{ route('cashier.payments.verify', $latestPayment) }}">
                        @csrf
                        <button type="button"
                                class="dtc-btn dtc-btn-success"
                                data-dtc-confirm
                                data-dtc-confirm-title="Verify GCash payment?"
                                data-dtc-confirm-message="The GCash payment from {subject} will be verified and recorded. If the enrollment is pending, it will then be sent to the Registrar for subject review."
                                data-dtc-confirm-subject="{{ $enrollment->user->name }}"
                                data-dtc-confirm-ok="Verify"
                                data-dtc-confirm-tone="success"
                                data-dtc-confirm-form="#verify-gcash-{{ $latestPayment->id }}">
                            Verify payment
                        </button>
                    </form>
                </div>
            @elseif (! $enrollment->is_paid && $outstandingTermPayments->isEmpty() && (! $latestPayment || $latestPayment->isRejected()))
                <div class="dtc-review-footer-right">
                    <button type="button"
                            class="dtc-btn dtc-btn-success"
                            data-dtc-confirm
                            data-dtc-confirm-title="Confirm cash payment?"
                            data-dtc-confirm-message="Confirm that cash payment has been received from {subject}. Payment will be recorded, and any pending enrollment will then be sent to the Registrar for subject review."
                            data-dtc-confirm-subject="{{ $enrollment->user->name }}"
                            data-dtc-confirm-ok="Confirm"
                            data-dtc-confirm-tone="success"
                            data-dtc-confirm-form="#walkinForm-{{ $enrollment->id }}">
                        Confirm payment
                    </button>
                </div>
            @endif
        </div>

        <div class="dtc-review-footer-reject u-hidden" >
            <button type="button" class="dtc-btn dtc-btn-secondary dtc-review-reject-cancel">Cancel</button>
            <button type="button" class="dtc-btn dtc-btn-danger" data-dtc-confirm data-dtc-confirm-tone="danger" data-dtc-confirm-title="Reject payment?" data-dtc-confirm-message="The submitted GCash payment from {subject} will be rejected with the reason entered above." data-dtc-confirm-subject="{{ $enrollment->user->name }}" data-dtc-confirm-ok="Reject" data-dtc-confirm-form="#rejectForm-{{ $enrollment->id }}">
                Confirm rejection
            </button>
        </div>

    </div>
</div>
