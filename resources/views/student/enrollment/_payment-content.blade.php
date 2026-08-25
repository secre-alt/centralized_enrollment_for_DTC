{{-- Shared "Enrollment Payment" content.
     Rendered both as the standalone payment-info page and, via AJAX,
     inside the lazy-loaded payment modal (see _payment-modal.blade.php). --}}

<div id="payment-content">

    {{-- ── Header ─────────────────────────────────────────────────────── --}}
    <div class="dtc-review-header">
        <div class="dtc-review-identity">
            <div class="dtc-review-avatar">
                <i class="fas fa-wallet"></i>
            </div>
            <div class="dtc-review-identity-copy">
                <h5 class="dtc-review-name">Enrollment Payment</h5>
                <p class="dtc-review-email">
                    <i class="fas fa-graduation-cap"></i>
                    {{ $enrollment->program->name }} — Year {{ $enrollment->year_level }}, {{ $enrollment->semester }} Semester
                </p>
            </div>
        </div>
        <div class="dtc-review-header-right">
            <button type="button" class="dtc-review-close" data-dismiss="modal" aria-label="Close">
                <i class="fas fa-times"></i>
            </button>
        </div>
    </div>

    {{-- ── Body ───────────────────────────────────────────────────────── --}}
    <div class="dtc-review-body">

        {{-- Flash messages --}}
        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        {{-- Payment Status Block --}}
        @if ($latestPayment)
            @php
                $statusMap = [
                    'verified' => ['bg' => '#DCFCE7', 'color' => '#15803D', 'icon' => 'fa-check-circle', 'label' => 'Payment Verified'],
                    'pending'  => ['bg' => '#FEF9C3', 'color' => '#A16207', 'icon' => 'fa-hourglass-half', 'label' => 'Payment Pending Verification'],
                    'rejected' => ['bg' => '#FEE2E2', 'color' => '#DC2626', 'icon' => 'fa-times-circle', 'label' => 'Payment Rejected — Please resubmit below'],
                ];
                $statusKey = $latestPayment->isVerified() ? 'verified' : ($latestPayment->isPending() ? 'pending' : 'rejected');
                $s = $statusMap[$statusKey];
            @endphp
            <div class="dtc-review-section">
                <div class="card mb-0" style="border-left:4px solid {{ $s['color'] }};">
                    <div class="card-body d-flex align-items-center" style="gap:14px;">
                        <div style="width:44px; height:44px; border-radius:12px; flex-shrink:0; background:{{ $s['bg'] }};
                                    display:flex; align-items:center; justify-content:center;">
                            <i class="fas {{ $s['icon'] }}" style="font-size:18px; color:{{ $s['color'] }};"></i>
                        </div>
                        <div>
                            <div style="font-weight:700; font-size:14px; color:{{ $s['color'] }};">{{ $s['label'] }}</div>
                            @if ($latestPayment->isVerified())
                                <div  class="u-text-xxs-secondary">
                                    Receipt No: {{ $latestPayment->receipt_no }} &mdash; ₱{{ number_format($latestPayment->amount, 2) }}
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        @endif

        @if (! $enrollment->is_paid)
        {{-- Payment Options --}}
        <div class="dtc-review-section">
            <div class="dtc-review-section-title">
                <span><i class="fas fa-coins"></i> Enrollment Fee</span>
                <span class="dtc-review-subject-count">₱{{ number_format($fee, 2) }}</span>
            </div>

            <div class="row">

                {{-- Walk-in Card --}}
                <div class="col-md-6 mb-3">
                    <div class="card h-100">
                        <div class="card-header font-weight-bold">
                            <i class="fas fa-building mr-1"></i> Walk-in (Cashier)
                        </div>
                        <div class="card-body">
                            <p  class="u-text-sm-secondary-primary">
                                Pay in person at the Cashier's Office. Bring this confirmation and a valid ID.
                            </p>
                            <p style="font-size:12px; color:var(--dtc-text-muted); margin:0;">
                                No online action needed — the Cashier will record your payment.
                            </p>
                        </div>
                    </div>
                </div>

                {{-- GCash Card --}}
                <div class="col-md-6 mb-3">
                    <div class="card h-100">
                        <div class="card-header font-weight-bold">
                            <i class="fas fa-mobile-alt mr-1"></i> GCash
                        </div>
                        <div class="card-body">

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

                            <p class="text-center mb-1" style="font-size:14px; font-weight:700; color:var(--dtc-text);">
                                {{ $gcashName ?? '—' }}
                            </p>
                            <p class="text-center mb-3 u-text-secondary-sm" >
                                {{ $gcashNumber ?? '—' }}
                            </p>

                            <ol style="font-size:12px; color:var(--dtc-text-secondary); padding-left:18px; margin-bottom:16px;">
                                <li>Open GCash → Send Money / Scan QR</li>
                                <li>Enter ₱{{ number_format($fee, 2) }} as the amount</li>
                                <li>Take a screenshot of the successful transaction</li>
                                <li>Fill in the form below and upload your screenshot</li>
                            </ol>

                            @php $pendingExists = $latestPayment && $latestPayment->isPending(); @endphp

                            @if ($pendingExists)
                                <div style="font-size:12px; color:#A16207; background:#FEF9C3; border:1px solid #FDE68A;
                                            border-radius:8px; padding:10px 12px;">
                                    Your proof has been submitted and is awaiting Cashier review.
                                    <a href="{{ route('portal.enrollment.payment.proof', $latestPayment) }}" target="_blank"
                                       style="color:#A16207; font-weight:600; text-decoration:underline;">
                                        View submitted proof
                                    </a>
                                </div>
                            @else
                                <form method="POST" action="{{ route('portal.enrollment.payment.gcash', $enrollment) }}" enctype="multipart/form-data">
                                    @csrf
                                    <div class="form-group">
                                        <label style="font-size:12px; font-weight:600; color:var(--dtc-text);">GCash Reference Number</label>
                                        <input type="text" name="reference_number" value="{{ old('reference_number') }}"
                                            class="form-control @error('reference_number') is-invalid @enderror"
                                            placeholder="e.g. 1234567890" required>
                                        @error('reference_number') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    </div>
                                    <div class="form-group mb-2">
                                        <label style="font-size:12px; font-weight:600; color:var(--dtc-text);">
                                            Proof of Payment (screenshot / PDF, max 5 MB)
                                        </label>
                                        <input type="file" name="proof_of_payment" accept=".jpg,.jpeg,.png,.pdf"
                                            class="form-control @error('proof_of_payment') is-invalid @enderror" required>
                                        @error('proof_of_payment') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    </div>
                                    <button type="submit" class="btn btn-primary btn-block">
                                        Submit GCash Proof
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
