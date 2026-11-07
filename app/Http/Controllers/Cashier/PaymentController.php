<?php

namespace App\Http\Controllers\Cashier;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\StudentProfile;
use App\Models\User;
use App\Services\NotificationService;
use App\Support\PrivateFilePreview;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PaymentController extends Controller
{
    // ── index() ───────────────────────────────────────────────────

    public function index(Request $request)
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'receipt' => ['nullable', 'integer', 'min:1'],
            'filter' => ['nullable', 'in:all,walk_in,gcash_pending,verified,rejected,unpaid,refund_follow_up,refunded'],
        ]);
        $query = Enrollment::with(['user', 'program', 'payment'])
            ->where(function ($enrollments) {
                $enrollments->whereIn('status', ['pending', 'approved'])
                    ->orWhere(function ($rejected) {
                        $rejected->where('status', 'rejected')
                            ->where('is_paid', true)
                            ->whereHas('payments', fn ($payments) => $payments->where('status', 'verified'));
                    });
            });

        if ($request->filled('search')) {
            $search = trim($request->query('search'));
            $query->where(function ($query) use ($search) {
                $query->whereHas('user', fn ($user) => $user->where(function ($user) use ($search) {
                    $user->where('name', 'like', '%' . $search . '%')->orWhere('email', 'like', '%' . $search . '%');
                }))->orWhereHas('program', fn ($program) => $program->where('name', 'like', '%' . $search . '%'));
            });
        }

        $filter = $filters['filter'] ?? 'all';

        $query = match ($filter) {
            'walk_in'       => $query->whereHas('payment', fn($q) => $q->where('payment_method', 'walk_in')),
            'gcash_pending' => $query->whereHas('payment', fn($q) => $q->where('payment_method', 'gcash')->where('status', 'pending')),
            'verified'      => $query->whereHas('payment', fn($q) => $q->where('status', 'verified')),
            'rejected'      => $query->whereHas('payment', fn($q) => $q->where('status', 'rejected')),
            'unpaid'        => $query->where('is_paid', false)->whereDoesntHave('payment', fn($q) => $q->where('status', 'pending')),
            'refund_follow_up' => $query->where('status', 'rejected')->whereHas('payments', fn ($q) => $q
                ->where('status', 'verified')->where('refund_status', 'pending')),
            'refunded' => $query->where('status', 'rejected')
                ->whereHas('payments', fn ($q) => $q->where('status', 'verified'))
                ->whereDoesntHave('payments', fn ($q) => $q
                    ->where('status', 'verified')->where('refund_status', '!=', 'refunded')),
            default         => $query,
        };

        $enrollments = $query->latest()->paginate(\App\Support\TablePagination::perPage(20))->appends($request->except('receipt'));
        $enrollments->each(function ($row) {
            $row->has_outstanding_term_payment = Payment::outstandingForTerm($row)->exists();
            $refundStatuses = Payment::where('enrollment_id', $row->id)
                ->where('status', 'verified')
                ->pluck('refund_status');
            $row->has_verified_payment = $refundStatuses->isNotEmpty();
            $row->has_pending_refund = $refundStatuses->contains('pending');
            $row->refunds_fully_confirmed = $refundStatuses->isNotEmpty()
                && $refundStatuses->every(fn ($status) => $status === 'refunded');
        });
        $receiptEnrollment = $request->filled('receipt')
            ? Enrollment::whereKey($request->integer('receipt'))->whereIn('id', Payment::select('enrollment_id')->where('status', 'verified'))->first()
            : null;

        return view('cashier.payments.index', compact('enrollments', 'filter', 'receiptEnrollment'));
    }

    // ── show() ────────────────────────────────────────────────────

    public function show(Enrollment $enrollment)
    {
        $enrollment->load(['user', 'program', 'payment.verifier']);

        $latestPayment = Payment::where('enrollment_id', $enrollment->id)
            ->latest()
            ->first();
        $outstandingTermPayments = Payment::outstandingForTerm($enrollment)
            ->with('enrollment')
            ->latest()
            ->get();
        $verifiedPayments = Payment::where('enrollment_id', $enrollment->id)
            ->where('status', 'verified')
            ->with('refundConfirmer')
            ->oldest()
            ->get();
        $refundFullyConfirmed = $verifiedPayments->isNotEmpty()
            && $verifiedPayments->every(fn ($verifiedPayment) => $verifiedPayment->refundIsComplete());

        return view('cashier.payments.show', compact('enrollment', 'latestPayment', 'verifiedPayments', 'outstandingTermPayments', 'refundFullyConfirmed'));
    }

    // ── store() — walk-in ─────────────────────────────────────────

    public function store(Request $request, Enrollment $enrollment)
    {
        $amount    = (float) Setting::get('enrollment_fee', 500);
        $receiptNo = 'OR-' . date('Ymd') . '-' . Str::upper(Str::random(5));

        DB::transaction(function () use ($enrollment, $amount, $receiptNo) {
            User::whereKey($enrollment->user_id)->lockForUpdate()->firstOrFail();
            $lockedEnrollment = Enrollment::whereKey($enrollment->id)->lockForUpdate()->firstOrFail();

            if (! in_array($lockedEnrollment->status, ['pending', 'approved'], true)) {
                throw ValidationException::withMessages(['payment' => 'This enrollment is not eligible for payment.']);
            }

            if ($lockedEnrollment->is_paid) {
                throw ValidationException::withMessages(['payment' => 'This enrollment is already paid.']);
            }

            if (Payment::unresolvedRefundsForTerm($lockedEnrollment)->exists()) {
                throw ValidationException::withMessages(['payment' => 'A previous verified payment for this academic term has not been refunded. No new payment can be recorded until the Cashier confirms the refund.']);
            }
            if (Payment::outstandingForTerm($lockedEnrollment)->exists()) {
                throw ValidationException::withMessages(['payment' => 'Another payment for this academic term is already pending or verified. Contact the Cashier to resolve it before recording another payment.']);
            }

            $attempts = Payment::where('enrollment_id', $lockedEnrollment->id)
                ->lockForUpdate()
                ->get();

            if ($attempts->contains('status', 'verified')) {
                throw ValidationException::withMessages(['payment' => 'A verified payment already exists for this enrollment. Contact the Cashier if the records do not match.']);
            }

            if ($attempts->contains('status', 'pending')) {
                throw ValidationException::withMessages(['payment' => 'A GCash payment is awaiting verification. Resolve that payment before recording a walk-in payment.']);
            }

            $user = $lockedEnrollment->user;
            if ($user->hasRole('new_applicant')) {
                $approvedApplicationExists = Application::where('user_id', $user->id)
                    ->where('program_id', $lockedEnrollment->program_id)
                    ->where('status', 'approved')
                    ->exists();

                if (! $approvedApplicationExists) {
                    throw ValidationException::withMessages(['payment' => 'This applicant does not have an approved application for the enrollment program.']);
                }
            }

            Payment::create([
                'enrollment_id'    => $lockedEnrollment->id,
                'payment_method'   => 'walk_in',
                'reference_number' => null,
                'proof_of_payment' => null,
                'status'           => 'verified',
                'processed_by'     => Auth::id(),
                'verified_by'      => Auth::id(),
                'verified_at'      => now(),
                'amount'           => $amount,
                'receipt_no'       => $receiptNo,
                'paid_at'          => now(),
            ]);

            $lockedEnrollment->update(['is_paid' => true]);

            if ($user->hasRole('new_applicant') && $lockedEnrollment->status === 'approved') {
                $user->syncRoles(['student']);
                \App\Services\StudentWelcome::record($user);
            } elseif ($lockedEnrollment->status === 'approved' && ! $user->hasAnyRole(['student', 'alumni', 'admin', 'registrar', 'cashier'])) {
                $user->assignRole('student');
            }

            if ($lockedEnrollment->status === 'approved') {
                StudentProfile::createForEnrollmentIfMissing($user, $lockedEnrollment);
            }
        });

        $payment = Payment::where('enrollment_id', $enrollment->id)->latest()->first();
        $awaitingRegistrarReview = $enrollment->fresh()->status === 'pending';
        if ($awaitingRegistrarReview) {
            $this->notifyRegistrarEnrollmentReady($enrollment->fresh());
        }

        NotificationService::sendUserUpdateSafely(
            $enrollment->user,
            'enrollment-payment-recorded:'.$payment->id,
            'Payment Confirmed',
            $awaitingRegistrarReview
                ? "Your enrollment payment of ₱{$amount} has been recorded. It is now awaiting Registrar review. Receipt: {$receiptNo}."
                : "Your enrollment payment of ₱{$amount} has been recorded. Receipt: {$receiptNo}.",
            'success',
            route('portal.enrollment.payment-info', $enrollment),
            'View payment record'
        );
        foreach (\App\Models\User::role('admin')->get() as $admin) {
            NotificationService::send($admin, 'Payment Recorded', "Walk-in payment recorded for {$enrollment->user->name}.", 'info', route('cashier.payments.index', ['receipt' => $enrollment->id]));
        }
        foreach (\App\Models\User::role('registrar')->get() as $reg) {
            if (! $awaitingRegistrarReview) {
                NotificationService::send($reg, 'Payment Recorded', "Walk-in payment recorded for {$enrollment->user->name}.", 'info', route('registrar.enrollments.show', $enrollment));
            }
        }

        return redirect()->route('cashier.payments.index')
            ->with('success', 'Payment recorded. Receipt available in the payment list.');
    }

    // ── verify() — GCash verification ─────────────────────────────

    public function verify(Request $request, Payment $payment)
    {
        if ($payment->status !== 'pending') {
            return redirect()->route('cashier.payments.show', $payment->enrollment_id)
                ->with('error', 'Payment already processed.');
        }

        $receiptNo = 'OR-' . date('Ymd') . '-' . Str::upper(Str::random(5));
        $amount    = $payment->amount;

        $userId = Enrollment::whereKey($payment->enrollment_id)->value('user_id');
        abort_unless($userId, 404);

        DB::transaction(function () use ($payment, $receiptNo, $userId) {
            User::whereKey($userId)->lockForUpdate()->firstOrFail();
            $lockedEnrollment = Enrollment::whereKey($payment->enrollment_id)->lockForUpdate()->firstOrFail();
            $lockedPayment = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($lockedPayment->status !== 'pending' || $lockedPayment->payment_method !== 'gcash') {
                abort(422, 'This payment is no longer eligible for verification.');
            }

            if (! in_array($lockedEnrollment->status, ['pending', 'approved'], true) || $lockedEnrollment->is_paid) {
                abort(422, 'This enrollment is no longer eligible for payment verification.');
            }

            if (Payment::unresolvedRefundsForTerm($lockedEnrollment)->exists()) {
                throw ValidationException::withMessages(['payment' => 'A previous verified payment for this academic term has not been refunded. Resolve the refund before verifying another payment.']);
            }
            if (Payment::outstandingForTerm($lockedEnrollment)->exists()) {
                throw ValidationException::withMessages(['payment' => 'Another payment for this academic term is already pending or verified. Resolve the other payment before verifying this one.']);
            }

            if (Payment::where('enrollment_id', $lockedEnrollment->id)
                ->where('status', 'verified')
                ->where('id', '!=', $lockedPayment->id)
                ->exists()) {
                abort(422, 'A verified payment already exists for this enrollment.');
            }

            $lockedPayment->update([
                'status'       => 'verified',
                'verified_by'  => Auth::id(),
                'verified_at'  => now(),
                'processed_by' => Auth::id(),
                'receipt_no'   => $receiptNo,
                'paid_at'      => now(),
            ]);

            $lockedEnrollment->update(['is_paid' => true]);
            $user = $lockedEnrollment->user;
            if ($user->hasRole('new_applicant')) {
                $approvedApplicationExists = Application::where('user_id', $user->id)
                    ->where('program_id', $lockedEnrollment->program_id)
                    ->where('status', 'approved')
                    ->exists();

                if (! $approvedApplicationExists) {
                    abort(422, 'This applicant does not have an approved application for the enrollment program.');
                }
            }

            if ($user->hasRole('new_applicant') && $lockedEnrollment->status === 'approved') {
                $user->syncRoles(['student']);
                \App\Services\StudentWelcome::record($user);
            } elseif ($lockedEnrollment->status === 'approved' && ! $user->hasAnyRole(['student', 'alumni', 'admin', 'registrar', 'cashier'])) {
                $user->assignRole('student');
            }

            if ($lockedEnrollment->status === 'approved') {
                StudentProfile::createForEnrollmentIfMissing($user, $lockedEnrollment);
            }
        });

        $enrollment = $payment->fresh()->enrollment;
        if ($enrollment->status === 'pending') {
            $this->notifyRegistrarEnrollmentReady($enrollment);
        }

        NotificationService::sendUserUpdateSafely(
            $enrollment->user,
            'enrollment-payment-verified:'.$payment->id,
            'GCash Payment Verified',
            $enrollment->status === 'pending'
                ? "Your GCash payment of ₱{$amount} has been verified. Your enrollment is now awaiting Registrar review. Receipt: {$receiptNo}."
                : "Your GCash payment of ₱{$amount} has been verified. Receipt: {$receiptNo}.",
            'success',
            route('portal.enrollment.payment-info', $enrollment),
            'View payment record'
        );

        return redirect()->route('cashier.payments.index')
            ->with('success', 'GCash payment verified. Receipt available in the payment list.');
    }

    // ── reject() ──────────────────────────────────────────────────

    public function reject(Request $request, Payment $payment)
    {
        if ($payment->status !== 'pending') {
            return redirect()->route('cashier.payments.show', $payment->enrollment_id)
                ->with('error', 'Payment already processed.');
        }

        $validated = $request->validate([
            'remarks' => ['required', 'string', 'max:1000'],
        ]);

        $userId = Enrollment::whereKey($payment->enrollment_id)->value('user_id');
        abort_unless($userId, 404);

        $enrollment = DB::transaction(function () use ($payment, $validated, $userId) {
            User::whereKey($userId)->lockForUpdate()->firstOrFail();
            $lockedEnrollment = Enrollment::whereKey($payment->enrollment_id)->lockForUpdate()->firstOrFail();
            $lockedPayment = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($lockedPayment->status !== 'pending' || $lockedPayment->payment_method !== 'gcash') {
                abort(422, 'This payment is no longer eligible for rejection.');
            }

            $lockedPayment->update([
                'status' => 'rejected',
                'rejection_reason' => $validated['remarks'],
                'verified_by' => Auth::id(),
                'verified_at' => now(),
                'processed_by' => Auth::id(),
            ]);

            return $lockedEnrollment;
        });

        NotificationService::sendUserUpdateSafely(
            $enrollment->user,
            'enrollment-payment-rejected:'.$payment->id,
            'GCash Payment Rejected',
            "Your GCash payment proof was rejected. Reason: {$validated['remarks']}. Please resubmit with a valid proof.",
            'danger',
            route('portal.enrollment.payment-info', $enrollment),
            'Review payment details'
        );

        return redirect()->route('cashier.payments.show', $enrollment)
            ->with('success', 'Payment rejected. Applicant has been notified.');
    }

    public function confirmRefund(Request $request, Payment $payment)
    {
        $validated = $request->validate([
            'refund_amount' => ['required', 'numeric', 'decimal:0,2', 'gt:0'],
            'refund_reference' => ['required', 'string', 'max:255', 'not_regex:/^\s*$/'],
        ]);
        $userId = Enrollment::whereKey($payment->enrollment_id)->value('user_id');
        abort_unless($userId, 404);

        $payment = DB::transaction(function () use ($payment, $validated, $userId) {
            User::whereKey($userId)->lockForUpdate()->firstOrFail();
            $enrollment = Enrollment::whereKey($payment->enrollment_id)->lockForUpdate()->firstOrFail();
            $lockedPayment = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($enrollment->status !== 'rejected'
                || ! $enrollment->is_paid
                || $lockedPayment->enrollment_id !== $enrollment->id
                || ! $lockedPayment->isVerified()
                || ! $lockedPayment->refundIsPending()) {
                throw ValidationException::withMessages([
                    'refund_reference' => 'Only a verified payment with a pending refund for a rejected enrollment can be marked refunded.',
                ]);
            }

            if (number_format((float) $validated['refund_amount'], 2, '.', '') !== number_format((float) $lockedPayment->amount, 2, '.', '')) {
                throw ValidationException::withMessages([
                    'refund_amount' => 'Only a full refund equal to the verified payment amount can be confirmed. Partial refunds do not release the payment block.',
                ]);
            }

            $lockedPayment->update([
                'refund_status' => 'refunded',
                'refund_amount' => $validated['refund_amount'],
                'refund_reference' => trim($validated['refund_reference']),
                'refunded_by' => Auth::id(),
                'refunded_at' => now(),
            ]);

            return $lockedPayment->fresh(['enrollment.user']);
        });

        NotificationService::sendUserUpdateSafely(
            $payment->enrollment->user,
            'enrollment-refund-confirmed:'.$payment->id,
            'Refund Confirmed',
            'The Cashier recorded confirmation of the refund for your rejected enrollment. You may submit a new enrollment request for this academic term.',
            'success',
            route('portal.enrollment.index'),
            'View enrollment status'
        );

        return redirect()->route('cashier.payments.show', $payment->enrollment)
            ->with('success', 'Refund confirmation recorded. The original verified payment and receipt remain in the audit history.');
    }

    // ── viewProof() ───────────────────────────────────────────────

    public function viewProof(Request $request, Payment $payment)
    {
        if (! $payment->proof_of_payment || ! Storage::disk('local')->exists($payment->proof_of_payment)) {
            abort(404);
        }

        return PrivateFilePreview::respond(
            $request,
            Storage::disk('local')->path($payment->proof_of_payment),
            'Payment proof',
            route('cashier.payments.show', $payment->enrollment),
            basename($payment->proof_of_payment)
        );
    }

    // ── receipt() ─────────────────────────────────────────────────

    public function receipt(Request $request, Enrollment $enrollment)
    {
        $enrollment->load(['user', 'program']);

        $payment = Payment::where('enrollment_id', $enrollment->id)
            ->where('status', 'verified')
            ->latest()
            ->first();

        if (! $payment) {
            $message = 'No verified payment found for this enrollment.';

            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['message' => $message], 404);
            }

            return redirect()->route('cashier.payments.show', $enrollment)
                ->with('error', $message);
        }

        if ($request->ajax()) {
            return view('cashier.payments._receipt-content', compact('enrollment', 'payment'));
        }

        return redirect()->route('cashier.payments.index', ['receipt' => $enrollment->id]);
    }

    private function notifyRegistrarEnrollmentReady(Enrollment $enrollment): void
    {
        foreach (\App\Models\User::role(['registrar', 'admin'])->get() as $registrar) {
            NotificationService::sendForEvent(
                $registrar,
                'enrollment-paid-awaiting-registrar:'.$enrollment->id,
                'Enrollment Ready for Review',
                $enrollment->user->name . "'s payment is verified. Review the selected subjects before approving enrollment.",
                'info',
                route('registrar.enrollments.index', ['review' => $enrollment->id])
            );
        }
    }

}
