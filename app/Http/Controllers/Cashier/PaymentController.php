<?php

namespace App\Http\Controllers\Cashier;

use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\Setting;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PaymentController extends Controller
{
    // ── index() — adds method/status filter ───────────────────────

    public function index(Request $request)
    {
        $query = Enrollment::where('status', 'approved')
            ->with(['user', 'program', 'payment']);

        // Filter by payment method / status using ?filter= query param
        $filter = $request->query('filter', 'all');

        $query = match ($filter) {
            'walk_in'        => $query->whereHas('payment', fn($q) => $q->where('payment_method', 'walk_in')),
            'gcash_pending'  => $query->whereHas('payment', fn($q) => $q->where('payment_method', 'gcash')->where('status', 'pending')),
            'verified'       => $query->whereHas('payment', fn($q) => $q->where('status', 'verified')),
            'rejected'       => $query->whereHas('payment', fn($q) => $q->where('status', 'rejected')),
            'unpaid'         => $query->where('is_paid', false)->whereDoesntHave('payment', fn($q) => $q->where('status', 'pending')),
            default          => $query,
        };

        $enrollments = $query->latest()->paginate(20)->withQueryString();

        return view('cashier.payments.index', compact('enrollments', 'filter'));
    }

    // ── show() — now branches on walk-in vs GCash pending ─────────

    public function show(Enrollment $enrollment)
    {
        $enrollment->load(['user', 'program', 'payment.verifier']);

        $latestPayment = Payment::where('enrollment_id', $enrollment->id)
            ->latest()
            ->first();

        return view('cashier.payments.show', compact('enrollment', 'latestPayment'));
    }

    // ── store() — walk-in, fee now from Setting ───────────────────

    public function store(Request $request, Enrollment $enrollment)
    {
        if ($enrollment->is_paid) {
            return redirect()->route('cashier.payments.show', $enrollment)
                ->with('error', 'This enrollment is already paid.');
        }

        $amount    = (float) Setting::get('enrollment_fee', 500);
        $receiptNo = 'OR-' . date('Ymd') . '-' . Str::upper(Str::random(5));

        DB::transaction(function () use ($enrollment, $amount, $receiptNo) {
            Payment::create([
                'enrollment_id'    => $enrollment->id,
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

            $enrollment->update(['is_paid' => true]);
            $enrollment->user->syncRoles(['student']);
        });

        $payment = Payment::where('enrollment_id', $enrollment->id)->latest()->first();

        // Notify student, all admins, all registrars — unchanged
        NotificationService::send(
            $enrollment->user,
            'Payment Confirmed',
            "Your enrollment payment of ₱{$amount} has been recorded. Receipt: {$receiptNo}.",
            route('student.enrollment.payment-info', $enrollment)
        );
        foreach (\App\Models\User::role('admin')->get() as $admin) {
            NotificationService::send($admin, 'Payment Recorded', "Walk-in payment recorded for {$enrollment->user->name}.", route('cashier.payments.receipt', $enrollment));
        }
        foreach (\App\Models\User::role('registrar')->get() as $reg) {
            NotificationService::send($reg, 'Payment Recorded', "Walk-in payment recorded for {$enrollment->user->name}.", route('cashier.payments.receipt', $enrollment));
        }

        return redirect()->route('cashier.payments.receipt', $enrollment)
            ->with('success', 'Payment recorded successfully.');
    }

    // ── verify() — NEW ────────────────────────────────────────────

    public function verify(Request $request, Payment $payment)
    {
        if ($payment->status !== 'pending') {
            return redirect()->route('cashier.payments.show', $payment->enrollment_id)
                ->with('error', 'This payment has already been processed.');
        }

        $receiptNo = 'OR-' . date('Ymd') . '-' . Str::upper(Str::random(5));
        $amount    = $payment->amount;

        DB::transaction(function () use ($payment, $receiptNo) {
            $payment->update([
                'status'       => 'verified',
                'verified_by'  => Auth::id(),
                'verified_at'  => now(),
                'processed_by' => Auth::id(),
                'receipt_no'   => $receiptNo,
                'paid_at'      => now(),
            ]);

            $enrollment = $payment->enrollment;
            $enrollment->update(['is_paid' => true]);
            $enrollment->user->syncRoles(['student']);
        });

        $enrollment = $payment->fresh()->enrollment;

        NotificationService::send(
            $enrollment->user,
            'GCash Payment Verified',
            "Your GCash payment of ₱{$amount} has been verified. Receipt: {$receiptNo}.",
            route('student.enrollment.payment-info', $enrollment)
        );

        return redirect()->route('cashier.payments.receipt', $enrollment)
            ->with('success', 'GCash payment verified. Student role assigned.');
    }

    // ── reject() — NEW ────────────────────────────────────────────

    public function reject(Request $request, Payment $payment)
    {
        if ($payment->status !== 'pending') {
            return redirect()->route('cashier.payments.show', $payment->enrollment_id)
                ->with('error', 'This payment has already been processed.');
        }

        $request->validate([
            'remarks' => ['required', 'string', 'max:1000'],
        ]);

        $payment->update([
            'status'       => 'rejected',
            'verified_by'  => Auth::id(),
            'verified_at'  => now(),
            'processed_by' => Auth::id(),
        ]);

        // Store rejection reason — reuse enrollment.remarks convention
        // (Payment model has no remarks column — store on the payment's enrollment or
        //  notify directly; we notify and include the remarks in the message)
        $enrollment = $payment->enrollment;

        NotificationService::send(
            $enrollment->user,
            'GCash Payment Rejected',
            "Your GCash payment proof was rejected. Reason: {$request->remarks}. Please resubmit with a valid proof.",
            route('student.enrollment.payment-info', $enrollment)
        );

        return redirect()->route('cashier.payments.show', $enrollment)
            ->with('success', 'Payment rejected. Applicant has been notified.');
    }

    // ── viewProof() — NEW (Cashier viewing applicant's proof) ─────

    public function viewProof(Payment $payment)
    {
        if (! $payment->proof_of_payment || ! Storage::disk('local')->exists($payment->proof_of_payment)) {
            abort(404);
        }

        return response()->file(
            Storage::disk('local')->path($payment->proof_of_payment)
        );
    }

    // ── receipt() — gains GCash method line, otherwise unchanged ──

    public function receipt(Request $request, Enrollment $enrollment)
    {
        $enrollment->load(['user', 'program']);

        $payment = Payment::where('enrollment_id', $enrollment->id)
            ->where('status', 'verified')
            ->latest()
            ->first();

        if (! $payment) {
            $message = 'No verified payment found for this enrollment.';

            // AJAX (modal) callers get a real error status + message instead
            // of silently following a redirect to a page with no receipt
            // markup on it, which used to surface as a generic, unhelpful
            // "couldn't load" with no indication of the actual cause.
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['message' => $message], 404);
            }

            return redirect()->route('cashier.payments.show', $enrollment)
                ->with('error', $message);
        }

        return view('cashier.payments.receipt', compact('enrollment', 'payment'));
    }
}