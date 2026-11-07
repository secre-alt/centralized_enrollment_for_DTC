{{-- Shared enrollment payment details, rendered on the dedicated payment page. --}}

<div id="payment-content">

    {{-- ── Header ─────────────────────────────────────────────────────── --}}
    <header class="portal-payment-heading">
        <h1>Enrollment payment</h1>
        <p class="portal-payment-description">{{ $enrollment->program->name }}</p>
        <p class="portal-payment-description">Year {{ $enrollment->year_level }} &middot; {{ $enrollment->semester }} Semester</p>
    </header>
    @if($enrollment->status === 'rejected' && $enrollment->is_paid)
        <div class="alert alert-warning" role="alert">
            @if ($refundFullyConfirmed)
                The Cashier recorded confirmation of the full refund. You may submit a fresh enrollment request for this term.
            @else
                The Registrar rejected this enrollment after payment was verified. Contact the Cashier to arrange and confirm a full refund before paying again.
            @endif
        </div>
    @endif
    {{-- ── Body ───────────────────────────────────────────────────────── --}}
    <div class="portal-payment-body">

        @if ($outstandingTermPayments->isNotEmpty())
            <div class="alert alert-warning" role="alert">
                <strong>Payment is blocked pending reconciliation.</strong>
                <p class="mb-2">A separate payment record exists for you in this academic term. Do not pay again until the Cashier resolves it.</p>
                @foreach ($outstandingTermPayments as $outstandingPayment)
                    <div class="border-top pt-2 mt-2">
                        Enrollment #{{ $outstandingPayment->enrollment_id }} · Receipt {{ $outstandingPayment->receipt_no ?: 'not issued' }} ·
                        @if ($outstandingPayment->refundIsPending())
                            Refund pending Cashier confirmation
                        @elseif ($outstandingPayment->refundIsComplete())
                            Refund confirmed
                        @elseif ($outstandingPayment->isPending())
                            Payment proof awaiting Cashier verification
                        @else
                            Verified payment requires Cashier reconciliation
                        @endif
                    </div>
                @endforeach
                <p class="mt-2 mb-0">For a rejected paid enrollment, the Cashier must confirm the external refund and record its reference before you submit another payment.</p>
            </div>
        @endif

        {{-- Payment Status Block --}}
        @if($enrollment->is_paid && !$latestPayment)
            <section class="portal-payment-card"><h2>Payment verified</h2><p>Your enrollment payment has been recorded.</p></section>
        @endif
        @if ($latestPayment)
            @php
                $statusToneMap = [
                    'verified' => ['tone' => 'success', 'icon' => 'check-circle', 'label' => 'Payment verified'],
                    'pending'  => ['tone' => 'warning', 'icon' => 'hourglass',     'label' => 'Payment under review'],
                    'rejected' => ['tone' => 'danger',  'icon' => 'x-circle',      'label' => 'Payment Rejected — Please resubmit below'],
                ];
                $statusKey = $latestPayment->isVerified() ? 'verified' : ($latestPayment->isPending() ? 'pending' : 'rejected');
                $s = $statusToneMap[$statusKey];
            @endphp
            <div class="portal-payment-section" data-pay-status="{{ $statusKey }}">
                @if ($statusKey === 'pending')
                    <div class="dtc-enrollment-callout is-info" role="status">
                        <span class="dtc-icon-swatch dtc-enrollment-callout-icon" aria-hidden="true">
                            <i data-lucide="clock-3"></i>
                        </span>
                        <div class="dtc-enrollment-callout-copy">
                            <strong>{{ $s['label'] }}</strong>
                            <span>The Cashier is checking your payment. No action is needed while it is under review.</span>
                        </div>
                        @if($latestPayment->proof_of_payment)
                            <a href="{{ route('portal.enrollment.payment.proof', $latestPayment) }}" target="_blank" rel="noopener" class="dtc-enrollment-proof-link">
                                <i data-lucide="file-image" aria-hidden="true"></i>
                                View submitted proof
                                <i data-lucide="arrow-up-right" aria-hidden="true"></i>
                                <span class="sr-only"> (opens in a new tab)</span>
                            </a>
                        @endif
                    </div>
                @elseif ($statusKey === 'verified')
                    <div class="dtc-enrollment-callout is-success" role="status">
                        <span class="dtc-icon-swatch dtc-enrollment-callout-icon" aria-hidden="true">
                            <i data-lucide="check-circle-2"></i>
                        </span>
                        <div class="dtc-enrollment-callout-copy">
                            <strong>{{ $s['label'] }}</strong>
                            <span>The Cashier confirmed your enrollment payment.</span>
                            <span>Receipt: {{ $latestPayment->receipt_no ?: 'Not available' }} &middot; ₱{{ number_format($latestPayment->amount, 2) }}</span>
                        </div>
                        @if($latestPayment->proof_of_payment)
                            <a href="{{ route('portal.enrollment.payment.proof', $latestPayment) }}" target="_blank" rel="noopener" class="dtc-enrollment-proof-link">
                                <i data-lucide="file-image" aria-hidden="true"></i>
                                View submitted proof
                                <i data-lucide="arrow-up-right" aria-hidden="true"></i>
                                <span class="sr-only"> (opens in a new tab)</span>
                            </a>
                        @endif
                    </div>
                @else
                    <section class="portal-payment-card portal-payment-status">
                        <h2>{{ $s['label'] }}</h2>
                        @if($latestPayment->proof_of_payment)
                            <a href="{{ route('portal.enrollment.payment.proof', $latestPayment) }}" target="_blank" rel="noopener">View submitted proof</a>
                        @endif
                    </section>
                @endif
            </div>
            @if ($statusKey === 'rejected')
                <div class="alert alert-danger mt-3" role="alert">
                    <strong>Cashier’s note:</strong> {{ $latestPayment->rejection_reason ?: 'The Cashier could not verify this proof. Check your payment details before submitting a corrected proof.' }}
                    <div class="mt-1"><strong>Next step:</strong> Submit corrected proof below. If you paid in person, contact the Cashier instead of uploading another proof.</div>
                </div>
            @endif
        @endif

        @if (in_array($enrollment->status, ['pending', 'approved'], true) && ! $enrollment->is_paid && $outstandingTermPayments->isEmpty() && ! ($latestPayment && ($latestPayment->isVerified() || $latestPayment->isPending())))
        {{-- Payment Options --}}
        <div class="portal-payment-section">
            <div class="portal-payment-total">
                <span>Enrollment fee</span>
                <span class="portal-payment-fee">₱{{ number_format($fee, 2) }}</span>
            </div>

            <div class="portal-payment-methods">

                {{-- Walk-in Card --}}
                <div class="portal-payment-method">
                    <div class="portal-payment-card">
                        <div class="portal-payment-method-title">
                            Walk-in payment
                        </div>
                        <div class="portal-payment-method-body">
                            <p  class="u-text-sm-secondary-primary">
                                Pay in person at the Cashier's Office. Bring your enrollment details and a valid ID.
                            </p>
                            <p style="font-size:12px; color:var(--dtc-text-muted); margin:0;">
                                The Cashier records your payment. No proof upload needed.
                            </p>
                        </div>
                    </div>
                </div>

                {{-- GCash Card --}}
                <div class="portal-payment-method">
                    <div class="portal-payment-card">
                        <div class="portal-payment-method-title">
                            GCash payment
                        </div>
                        <div class="portal-payment-method-body">

                            <p class="u-text-sm-secondary-primary">Send your payment through GCash, then submit the reference number and payment proof below for Cashier verification.</p>

                            <div class="dtc-gcash-mobile-note">
                                <div><strong>Paying on this phone?</strong> Use the GCash number below. You do not need to scan the QR.</div>
                            </div>

                            <div class="dtc-gcash-account">
                                <div>
                                    <span>GCash account</span>
                                    <strong>{{ $gcashName ?? 'Not configured' }}</strong>
                                    <code>{{ $gcashNumber ?? 'Not configured' }}</code>
                                </div>
                                @if($gcashNumber)
                                    <button type="button" class="dtc-gcash-copy" data-copy-value="{{ $gcashNumber }}" data-copy-label="GCash number">
                                        <i data-lucide="copy" aria-hidden="true"></i>
                                        <span>Copy number</span>
                                    </button>
                                @endif
                            </div>

                            <div class="dtc-gcash-amount">
                                <span>Amount to send</span>
                                <strong>₱{{ number_format($fee, 2) }}</strong>
                                <button type="button" data-copy-value="{{ number_format((float) $fee, 2, '.', '') }}" data-copy-label="Amount">Copy</button>
                            </div>

                            @if ($gcashQrReady)
                                <img src="{{ route('portal.enrollment.gcash.qr') }}" alt="GCash QR Code"
                                     style="width:150px; height:150px; object-fit:contain; display:block; margin:0 auto 12px;
                                            border:1px solid var(--dtc-border); border-radius:10px;">
                            @else
                                <div style="width:150px; height:150px; display:flex; align-items:center; justify-content:center;
                                            margin:0 auto 12px; border:1px solid var(--dtc-border); border-radius:10px;
                                            background:var(--dtc-surface-soft); font-size:12px; color:var(--dtc-text-muted);">
                                    QR not yet configured
                                </div>
                            @endif

                            @if($gcashQrReady)
                                <a href="{{ route('portal.enrollment.gcash.qr', ['download' => 1]) }}" class="dtc-gcash-save-qr">
                                    <i data-lucide="download" aria-hidden="true"></i> Save QR to phone
                                </a>
                            @endif

                            @php $pendingExists = $latestPayment && $latestPayment->isPending(); @endphp
                            @unless($pendingExists)
                                <p class="dtc-gcash-proof-guide">After paying, enter the reference number and upload the successful transaction screenshot.</p>
                            @endunless

                            @if ($pendingExists)
                                <div class="dtc-proof-review">
                                    <div class="dtc-proof-review-heading" role="status"><strong>Payment under review</strong></div>
                                    <p>Your proof is with the Cashier. No action needed.</p>
                                    @if($latestPayment->proof_of_payment)
                                        <a href="{{ route('portal.enrollment.payment.proof', $latestPayment) }}" target="_blank" rel="noopener" class="dtc-proof-review-link">
                                            <i data-lucide="file-image" aria-hidden="true"></i>View submitted proof<i data-lucide="arrow-up-right" aria-hidden="true"></i><span class="sr-only"> (opens in a new tab)</span>
                                        </a>
                                    @endif
                                </div>
                            @else
                                <form method="POST" action="{{ route('portal.enrollment.payment.gcash', $enrollment) }}" enctype="multipart/form-data">
                                    @csrf
                                    <div class="form-group">
                                        <label for="enrollment-gcash-reference" style="font-size:12px; font-weight:600; color:var(--dtc-text);">GCash Reference Number</label>
                                        <input id="enrollment-gcash-reference" type="text" name="reference_number" value="{{ old('reference_number') }}"
                                            class="form-control @error('reference_number') is-invalid @enderror"
                                            placeholder="e.g. 1234567890" required>
                                        @error('reference_number') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    </div>
                                    <div class="form-group enrollment-proof-group">
                                        <label for="enrollment-payment-proof" style="font-size:12px; font-weight:600; color:var(--dtc-text);">
                                            Proof of Payment (screenshot / PDF, max 5 MB)
                                        </label>
                                        <label class="dtc-file-label enrollment-proof-picker @error('proof_of_payment') is-invalid @enderror">
                                            <span class="enrollment-proof-picker-action">
                                                <i data-lucide="upload" aria-hidden="true"></i>
                                                Choose file
                                            </span>
                                            <span class="dtc-file-text enrollment-proof-file-name" aria-live="polite">No file chosen</span>
                                            <input id="enrollment-payment-proof" type="file" name="proof_of_payment" accept=".jpg,.jpeg,.png,.pdf"
                                                class="dtc-file-input @error('proof_of_payment') is-invalid @enderror"
                                                required
                                                onchange="this.closest('label').querySelector('.dtc-file-text').textContent = this.files[0] ? this.files[0].name : 'No file chosen'">
                                        </label>
                                        @error('proof_of_payment') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                                    </div>
                                    <button type="submit" class="btn btn-primary btn-block">
                                        Submit proof
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>
                </div>

            </div>
        </div>
        @endif

    </div>

</div>
