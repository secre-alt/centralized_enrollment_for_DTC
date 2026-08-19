<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\Program;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class EnrollmentController extends Controller
{
    // ── create() ──────────────────────────────────────────────────

    public function create()
    {
        $user = Auth::user();
        $fee = Setting::get('enrollment_fee', 500);

        if ($user->hasRole('new_applicant')) {
            // Fetch the single approved application for this user
            $application = $user->applications()
                ->where('status', 'approved')
                ->with('program')
                ->latest()
                ->first();

            if (! $application) {
                return redirect()->route('portal.dashboard')
                    ->with('error', 'You do not have an approved application. Please contact the Registrar.');
            }

            // Guard: block re-enrollment if an active enrollment already exists
            $existingEnrollment = Enrollment::where('user_id', $user->id)
                ->whereIn('status', ['pending', 'approved'])
                ->first();

            if ($existingEnrollment) {
                return redirect()->route('portal.dashboard')
                    ->with('error', 'You already have an active enrollment.');
            }

            return view('student.enrollment.create', [
                'lockedProgram' => $application->program,
                'application'   => $application,
                'programs'      => null,   // signals the Blade to render locked UI
                'fee'           => $fee,
            ]);
        }

        // student / alumni branch — unchanged
        $programs = Program::all();

        return view('student.enrollment.create', [
            'programs'      => $programs,
            'lockedProgram' => null,
            'application'   => null,
            'fee'           => $fee,
        ]);
    }
    // ── index() — NEW ────────────────────────────────────────────
    public function index()
    {
        $user = Auth::user();

        $enrollments = Enrollment::where('user_id', $user->id)
            ->with(['program', 'payment'])
            ->latest()
            ->paginate(10);

        // Resolve subject names for each enrollment's subject_ids
        $enrollments->each(function ($enrollment) {
            $enrollment->subjects = \App\Models\CourseSubject::whereIn('id', $enrollment->subject_ids ?? [])
                ->get(['id', 'subject_code', 'subject_name']);
        });

        return view('student.enrollment.index', compact('enrollments'));
    }
    // ── store() ───────────────────────────────────────────────────

    public function store(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'program_id'  => ['required', 'exists:programs,id'],
            'year_level'  => ['required', 'integer', 'min:1', 'max:5'],
            'semester' => ['required', 'integer', 'in:1,2,3'],
            'subject_ids' => ['required', 'array', 'min:1'],
            'subject_ids.*' => ['exists:course_subjects,id'],
        ]);

        // ── Authorization: new_applicant must enroll in approved program only
        if ($user->hasRole('new_applicant')) {
            $application = $user->applications()
                ->where('status', 'approved')
                ->latest()
                ->first();

            if (! $application || (int) $application->program_id !== (int) $validated['program_id']) {
                abort(403, 'You may only enroll in your approved program.');
            }
        }

        // ── Guard: no duplicate active enrollments
        $existingEnrollment = Enrollment::where('user_id', $user->id)
            ->whereIn('status', ['pending', 'approved'])
            ->first();

        if ($existingEnrollment) {
            return redirect()->route('portal.dashboard')
                ->with('error', 'You already have an active enrollment.');
        }

        $enrollment = Enrollment::create([
            'user_id'     => $user->id,
            'program_id'  => $validated['program_id'],
            'year_level'  => $validated['year_level'],
            'semester'    => $validated['semester'],
            'subject_ids' => $validated['subject_ids'],
            'status'      => 'pending',
            'is_paid'     => false,
        ]);

        // Notify all registrars of the new enrollment submission
        $registrars = \App\Models\User::role('registrar')->get();
        foreach ($registrars as $registrar) {
            \App\Services\NotificationService::send(
                $registrar,
                'New Enrollment Submitted',
                "{$user->name} submitted an enrollment for {$enrollment->program->name} (Year {$enrollment->year_level}) and is awaiting your review.",
                'info',
                route('registrar.enrollments.show', $enrollment)
            );
        }

        return redirect()->route('portal.enrollment.index')->with('success', 'Enrollment submitted successfully. Await Registrar approval.');
    }

    // ── submitGcash() — NEW ────────────────────────────────────────

    public function submitGcash(Request $request, Enrollment $enrollment)
    {
        // Ownership check
        if ($enrollment->user_id !== Auth::id()) {
            abort(403);
        }

        // Enrollment must be approved before payment is accepted
        if ($enrollment->status !== 'approved') {
            return back()->with('error', 'Your enrollment must be approved before submitting payment.');
        }

        // Already paid
        if ($enrollment->is_paid) {
            return back()->with('error', 'This enrollment has already been paid.');
        }

        // Block duplicate pending submission
        $pendingExists = Payment::where('enrollment_id', $enrollment->id)
            ->where('status', 'pending')
            ->exists();

        if ($pendingExists) {
            return back()->with('error', 'You already have a payment pending verification. Please wait for the Cashier to review it.');
        }

        $validated = $request->validate([
            'reference_number' => ['required', 'string', 'max:255'],
            'proof_of_payment' => [
                'required',
                'file',
                'mimes:jpg,jpeg,png,pdf',
                'max:5120', // 5 MB
            ],
        ]);

        // Store proof privately — same pattern as ApplicationDocument
        $path = $request->file('proof_of_payment')
            ->store('payment-proofs', 'local');

        $amount = Setting::get('enrollment_fee', 500);

        Payment::create([
            'enrollment_id'    => $enrollment->id,
            'payment_method'   => 'gcash',
            'reference_number' => $validated['reference_number'],
            'proof_of_payment' => $path,
            'status'           => 'pending',   // hardcoded — never trusted from client
            'processed_by'     => null,
            'verified_by'      => null,
            'verified_at'      => null,
            'amount'           => $amount,
            'receipt_no'       => null,        // assigned only at verification
            'paid_at'          => null,        // assigned only at verification
        ]);

    foreach (\App\Models\User::role('cashier')->get() as $cashier) {
        \App\Services\NotificationService::send(
            $cashier,
            'New GCash Payment Pending Verification',
            "A GCash payment has been submitted for Enrollment #{$enrollment->id} and is awaiting your review.",
            'info',
            route('cashier.payments.show', $enrollment)
        );
    }

        return back()->with('success', 'GCash payment proof submitted. The Cashier will verify your payment shortly.');
    }

    // ── viewProof() — NEW (student viewing their own proof) ────────

    public function viewProof(Payment $payment)
    {
        if ($payment->enrollment->user_id !== Auth::id()) {
            abort(403);
        }

        if (! $payment->proof_of_payment || ! Storage::disk('local')->exists($payment->proof_of_payment)) {
            abort(404);
        }

        return response()->file(
            Storage::disk('local')->path($payment->proof_of_payment)
        );
    }

    // ── showGcashQr() — NEW (shared institutional QR, auth-only) ──

    public function showGcashQr()
    {
        $path = Setting::get('gcash_qr_path');

        if (! $path || ! Storage::disk('local')->exists($path)) {
            abort(404, 'GCash QR code not yet configured. Please contact the Registrar.');
        }

        return response()->file(
            Storage::disk('local')->path($path)
        );
    }

    // ── getSubjects() — unchanged ──────────────────────────────────

    public function getSubjects(Request $request, Program $program)
    {
        $validated = $request->validate([
            'year_level' => ['required', 'integer', 'min:1', 'max:5'],
            'semester'   => ['required'],
        ]);

        $subjects = \App\Models\CourseSubject::where('program_id', $program->id)
            ->where('year_level', $validated['year_level'])
            ->where('semester', $validated['semester'])
            ->orderBy('subject_code')
            ->get(['id', 'subject_code', 'subject_name']);

        return response()->json($subjects);
    }

    // ── paymentInfo() — unchanged signature, view gains new data ──

    public function paymentInfo(Enrollment $enrollment)
    {
        if ($enrollment->user_id !== Auth::id()) {
            abort(403);
        }

        $enrollment->load('program', 'payment');

        $gcashNumber  = Setting::get('gcash_number');
        $gcashName    = Setting::get('gcash_name');
        $gcashQrReady = Setting::get('gcash_qr_path') && Storage::disk('local')->exists(Setting::get('gcash_qr_path'));
        $fee          = Setting::get('enrollment_fee', 500);

        // Latest payment (could be pending/rejected/verified)
        $latestPayment = Payment::where('enrollment_id', $enrollment->id)
            ->latest()
            ->first();

        return view('student.enrollment.payment-info', compact(
            'enrollment',
            'gcashNumber',
            'gcashName',
            'gcashQrReady',
            'fee',
            'latestPayment'
        ));
    }
}