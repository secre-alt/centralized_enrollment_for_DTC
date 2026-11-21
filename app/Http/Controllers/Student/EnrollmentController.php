<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use App\Models\EnrollmentReplacementRequest;
use App\Models\Payment;
use App\Models\Program;
use App\Models\Setting;
use App\Services\EnrollmentSubjectReplacementService;
use App\Services\NotificationService;
use App\Support\PrivateFilePreview;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

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

            // An earlier term's approved enrollment must not block this term.
            $term = $this->configuredAcademicTerm();
            $existingEnrollment = Enrollment::where('user_id', $user->id)
                ->whereIn('status', ['pending', 'approved'])
                ->when($term, fn ($query) => $query
                    ->where('school_year', $term['school_year'])
                    ->where('semester', $term['semester']))
                ->first();

            if ($existingEnrollment) {
                return redirect()->route('portal.dashboard')
                    ->with('error', 'You already have an active enrollment.');
            }

            return view('student.enrollment.create', [
                'lockedProgram' => $application->program,
                'application' => $application,
                'programs' => null,   // signals the Blade to render locked UI
                'fee' => $fee,
            ]);
        }

        // Existing institutional program is authoritative for ordinary enrollment.
        $officialProgramId = $user->studentProfile?->program_id;
        $officialProgram = $officialProgramId ? Program::find($officialProgramId) : null;
        $programs = Program::all();

        return view('student.enrollment.create', [
            'programs' => $programs,
            'lockedProgram' => $officialProgram,
            'application' => null,
            'fee' => $fee,
        ]);
    }

    // ── index() — NEW ────────────────────────────────────────────
    public function index(Request $request)
    {
        $user = Auth::user();
        $request->validate(['payment' => ['nullable', 'integer', 'min:1']]);
        $linkedPaymentEnrollment = $request->filled('payment')
            ? Enrollment::where('user_id', $user->id)->whereKey($request->integer('payment'))->first()
            : null;
        if ($linkedPaymentEnrollment) {
            return redirect()->route('portal.enrollment.payment-info', $linkedPaymentEnrollment);
        }

        $enrollments = Enrollment::where('user_id', $user->id)
            ->with([
                'program',
                'payment',
                'subjectOfferings.classSchedule.courseSubject',
                'replacementRequests.items.originalSubject',
                'replacementRequests.items.selectedSchedule.courseSubject.program',
            ])
            ->latest()
            ->paginate(\App\Support\TablePagination::perPage(10))->appends($request->except('payment'));

        // Resolve subject names for each enrollment's subject_ids
        $enrollments->each(function ($enrollment) {
            $enrollment->subjects = \App\Models\CourseSubject::whereIn('id', $enrollment->subject_ids ?? [])
                ->get(['id', 'subject_code', 'subject_name']);
            $enrollment->has_outstanding_term_payment = Payment::outstandingForTerm($enrollment)->exists();
            $refundStatuses = Payment::where('enrollment_id', $enrollment->id)
                ->where('status', 'verified')
                ->pluck('refund_status');
            $enrollment->refund_fully_confirmed = $refundStatuses->isNotEmpty()
                && $refundStatuses->every(fn ($status) => $status === 'refunded');
        });

        return view('student.enrollment.index', compact('enrollments', 'linkedPaymentEnrollment'));
    }
    // ── store() ───────────────────────────────────────────────────

    public function store(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'program_id' => ['required', 'exists:programs,id'],
            'year_level' => ['required', 'integer', 'min:1', 'max:5'],
            'semester' => ['required', 'integer', 'in:1,2,3'],
            'is_irregular' => ['nullable', 'boolean'],
            'subject_ids' => ['required', 'array', 'min:1'],
            'subject_ids.*' => ['exists:course_subjects,id'],
        ]);

        $isIrregular = $request->boolean('is_irregular');
        $term = $this->configuredAcademicTerm();
        if (! $term) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'semester' => 'The current academic year and semester are not configured. Please contact the Registrar.',
            ]);
        }

        // ── Reject duplicate subject submissions outright.
        if (count($validated['subject_ids']) !== count(array_unique($validated['subject_ids']))) {
            abort(422, 'Duplicate subjects were submitted.');
        }

        // ── Authorization: subjects must belong to the selected program.
        // This is the authoritative check — it applies whether the student
        // used the regular (locked year/semester) picker or the irregular
        // (all-subjects, grouped) picker, since the frontend selection is
        // never trusted on its own.
        $validSubjectCount = \App\Models\CourseSubject::where('program_id', $validated['program_id'])
            ->whereIn('id', $validated['subject_ids'])
            ->count();

        if ($validSubjectCount !== count($validated['subject_ids'])) {
            abort(422, 'One or more selected subjects do not belong to the selected program.');
        }

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
        $enrollment = DB::transaction(function () use ($user, $validated, $isIrregular, $term) {
            \App\Models\User::whereKey($user->id)->lockForUpdate()->firstOrFail();

            $officialProfile = $user->studentProfile()->lockForUpdate()->first();
            if (! $user->hasRole('new_applicant') && $officialProfile?->program_id
                && (int) $officialProfile->program_id !== (int) $validated['program_id']) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'program_id' => 'Enroll in your recorded degree program. Contact the Registrar to approve and record a program change first.',
                ]);
            }

            $existingEnrollment = Enrollment::where('user_id', $user->id)
                ->where('school_year', $term['school_year'])
                ->where('semester', $term['semester'])
                ->whereIn('status', ['pending', 'approved'])
                ->lockForUpdate()
                ->first();

            if ($existingEnrollment) {
                return null;
            }

            if ((int) $validated['semester'] !== (int) $term['semester']) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'semester' => 'The academic term has changed. Refresh the enrollment form and select subjects again.',
                ]);
            }

            if (! $isIrregular) {
                $termSubjectCount = \App\Models\CourseSubject::where('program_id', $validated['program_id'])
                    ->where('year_level', $validated['year_level'])
                    ->where('semester', $term['semester'])
                    ->whereIn('id', $validated['subject_ids'])
                    ->count();
                if ($termSubjectCount !== count($validated['subject_ids'])) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'subject_ids' => 'Regular subjects must match your selected year and semester. Choose irregular subjects for a mixed load.',
                    ]);
                }
            }

            $created = Enrollment::create([
                'user_id' => $user->id,
                'program_id' => $validated['program_id'],
                'year_level' => $validated['year_level'],
                'semester' => $term['semester'],
                'school_year' => $term['school_year'],
                'is_irregular' => $isIrregular,
                'subject_ids' => $validated['subject_ids'],
                'status' => 'pending',
                'is_paid' => false,
                'requires_section_assignment' => true,
            ]);
            app(\App\Services\EnrollmentSectionAssignmentService::class)->assignPublishedGroup($created);

            return $created;
        }, 3);

        if (! $enrollment) {
            return redirect()->route('portal.dashboard')
                ->with('error', 'You already have an active enrollment for the current academic term.');
        }

        if ($enrollment->requires_section_assignment) {
            foreach (\App\Models\User::role(['registrar', 'admin'])->get() as $reviewer) {
                \App\Services\NotificationService::sendForEvent($reviewer, 'enrollment-sections-needed:'.$enrollment->id,
                    'Enrollment Needs Sections', $user->name.' needs section assignment before payment for enrollment #'.$enrollment->id.'.',
                    'info', route('registrar.enrollments.show', $enrollment));
            }
            \App\Services\NotificationService::sendUserUpdateSafely($user, 'enrollment-submitted:'.$enrollment->id,
                'Sections Needed', 'Your enrollment was received. The Registrar must assign your complete subject load before payment. Do not pay yet.',
                'info', route('portal.enrollment.index'), 'View enrollment status');

            return redirect()->route('portal.enrollment.index')->with('success', 'Enrollment received. Wait for Registrar section assignment before paying.');
        }

        // Payment is the first review step; notify Cashiers when the request is submitted.
        foreach (\App\Models\User::role(['cashier', 'admin'])->get() as $cashier) {
            \App\Services\NotificationService::send(
                $cashier,
                'Enrollment Awaiting Payment',
                "{$user->name} submitted an enrollment for {$enrollment->program->name} (Year {$enrollment->year_level}) and is ready for payment.",
                'info',
                route('cashier.payments.show', $enrollment)
            );
        }

        \App\Services\NotificationService::sendUserUpdateSafely(
            $user,
            'enrollment-submitted:'.$enrollment->id,
            'Enrollment Submitted',
            "We received your enrollment for {$enrollment->program->name}. Pay the enrollment fee at the Cashier or submit GCash proof for verification before Registrar review.",
            'info',
            route('portal.enrollment.index'),
            'View enrollment status'
        );

        return redirect()->route('portal.enrollment.index')->with('success', 'Enrollment submitted. Please pay the fee; Registrar review begins after the Cashier verifies payment.');
    }

    /** Resolve the active term from Registrar-managed academic settings. */
    private function configuredAcademicTerm(): ?array
    {
        $schoolYear = trim((string) Setting::get('current_school_year', ''));
        $semester = [
            '1st' => 1,
            '2nd' => 2,
            'Summer' => 3,
        ][(string) Setting::get('current_semester', '')] ?? null;

        if ($schoolYear === '' || $semester === null) {
            return null;
        }

        return ['school_year' => $schoolYear, 'semester' => $semester];
    }

    public function showReplacement(
        Enrollment $enrollment,
        EnrollmentSubjectReplacementService $replacementService
    ) {
        abort_unless((int) $enrollment->user_id === (int) Auth::id(), 403);
        $enrollment->load([
            'program',
            'payment',
            'replacementRequests.items.originalSubject',
            'replacementRequests.items.originalSchedule',
            'replacementRequests.items.selectedSchedule.courseSubject.program',
            'replacementRequests.items.selectedSchedule.hostingProgram',
        ]);
        $replacementRequest = $enrollment->replacementRequests
            ->where('status', 'open')
            ->sortByDesc('id')
            ->first();

        abort_unless(
            $enrollment->status === 'pending'
                && $enrollment->is_paid
                && $enrollment->payments()->where('status', 'verified')->exists()
                && $replacementRequest,
            404
        );

        $replacementOptions = collect();
        $capacityReconciliationRequired = [];
        foreach ($replacementRequest->items as $item) {
            $replacementOptions[$item->id] = $replacementService->eligibleOfferings($enrollment, $item);
            $capacityReconciliationRequired[$item->id] = $replacementService
                ->requiresCapacityReconciliation($enrollment, $item);
        }
        $retainedIrregular = (bool) ($replacementRequest->baseline_is_irregular ?? $enrollment->is_irregular)
            || $replacementService->enrollmentRequiresIrregular($enrollment, $replacementRequest->items->pluck('original_subject_id')->all());

        return view('student.enrollment.revise', compact(
            'enrollment',
            'replacementRequest',
            'replacementOptions',
            'capacityReconciliationRequired',
            'retainedIrregular'
        ));
    }

    public function submitReplacement(
        Request $request,
        Enrollment $enrollment,
        EnrollmentSubjectReplacementService $replacementService
    ) {
        abort_unless((int) $enrollment->user_id === (int) Auth::id(), 403);
        $validated = $request->validate([
            'replacements' => ['required', 'array', 'min:1'],
            'replacements.*' => ['required', 'integer', 'distinct', 'exists:class_schedules,id'],
            'confirm_revision' => ['required', 'accepted'],
        ]);

        $case = DB::transaction(function () use ($enrollment, $validated, $replacementService) {
            \App\Models\User::whereKey(Auth::id())->lockForUpdate()->firstOrFail();
            $lockedEnrollment = Enrollment::whereKey($enrollment->id)->lockForUpdate()->firstOrFail();
            if ((int) $lockedEnrollment->user_id !== (int) Auth::id()
                || $lockedEnrollment->status !== 'pending'
                || ! $lockedEnrollment->is_paid
                || ! $lockedEnrollment->payments()->where('status', 'verified')->exists()) {
                abort(404);
            }

            $replacementRequest = EnrollmentReplacementRequest::where('enrollment_id', $lockedEnrollment->id)
                ->where('status', 'open')
                ->lockForUpdate()
                ->with('items.originalSubject')
                ->latest('id')
                ->firstOrFail();

            $items = $replacementRequest->items->keyBy('id');
            $submitted = collect($validated['replacements'])->mapWithKeys(
                fn ($scheduleId, $itemId) => [(int) $itemId => (int) $scheduleId]
            );
            if ($submitted->keys()->sort()->values()->all() !== $items->keys()->map(fn ($id) => (int) $id)->sort()->values()->all()) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'replacements' => 'Choose one eligible section for each subject the Registrar identified.',
                ]);
            }

            $lockedSchedules = \App\Models\ClassSchedule::whereIn('id', $submitted->values())
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');
            $selectedSchedules = [];
            $selectedSchedulesByItem = [];
            $targetSubjectIds = [];
            foreach ($items as $item) {
                $schedule = $lockedSchedules->get($submitted[$item->id]);
                if (! $schedule) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'replacements' => 'One of the selected class sections is no longer available.',
                    ]);
                }

                $replacementService->assertOfferingEligible(
                    $lockedEnrollment,
                    $item,
                    $schedule
                );
                $selectedSchedules[] = $schedule;
                $selectedSchedulesByItem[$item->id] = $schedule;
                $targetSubjectIds[] = (int) $schedule->course_subject_id;
            }

            $unaffectedSubjectIds = collect($lockedEnrollment->subject_ids ?? [])
                ->map(fn ($id) => (int) $id)
                ->diff($items->pluck('original_subject_id')->map(fn ($id) => (int) $id));
            $unaffectedAssignments = $lockedEnrollment->subjectOfferings()
                ->whereIn('curriculum_subject_id', $unaffectedSubjectIds)
                ->with('classSchedule')
                ->get()->keyBy('curriculum_subject_id');
            $finalSubjectIds = $unaffectedSubjectIds->map(fn ($id) => (int) ($unaffectedAssignments->get($id)?->classSchedule?->course_subject_id ?? $id))
                ->merge($targetSubjectIds)->all();
            if (count($finalSubjectIds) !== count(array_unique($finalSubjectIds))) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'replacements' => 'The revised load cannot contain the same class subject more than once.',
                ]);
            }

            $replacementService->assertNoScheduleConflicts(
                $lockedEnrollment,
                $selectedSchedules,
                $items->pluck('original_subject_id')->all()
            );

            foreach ($items as $item) {
                $schedule = $selectedSchedulesByItem[$item->id];
                $equivalency = $replacementService->equivalencyFor(
                    $item->originalSubject,
                    $schedule->courseSubject
                );

                $item->update([
                    'selected_schedule_id' => $schedule->id,
                    'equivalency_id' => $equivalency?->id,
                ]);
                $lockedEnrollment->subjectOfferings()->updateOrCreate(
                    ['curriculum_subject_id' => $item->original_subject_id],
                    ['class_schedule_id' => $schedule->id]
                );
            }

            $baselineIsIrregular = $replacementRequest->baseline_is_irregular
                ?? (bool) $lockedEnrollment->is_irregular;
            $lockedEnrollment->update([
                'is_irregular' => (bool) $baselineIsIrregular
                    || $replacementService->enrollmentRequiresIrregular($lockedEnrollment),
            ]);

            $replacementRequest->update([
                'status' => 'submitted',
                'submitted_at' => now(),
            ]);

            return $replacementRequest->fresh(['enrollment.user', 'items.originalSubject']);
        }, 3);

        foreach (\App\Models\User::role(['registrar', 'admin'])->get() as $registrar) {
            NotificationService::sendForEvent(
                $registrar,
                'enrollment-revision-submitted:'.$case->id,
                'Revised Enrollment Awaiting Review',
                $case->enrollment->user->name.' resubmitted enrollment #'.$case->enrollment_id.' for Registrar review.',
                'info',
                route('registrar.enrollments.show', $case->enrollment)
            );
        }

        NotificationService::sendUserUpdateSafely(
            $case->enrollment->user,
            'enrollment-revision-submitted:'.$case->id,
            'Revised Enrollment Submitted',
            'Your revised subject selections were submitted to the Registrar. Your verified payment remains on the original enrollment.',
            'info',
            route('portal.enrollment.index'),
            'View enrollment status'
        );

        return redirect()->route('portal.enrollment.index')
            ->with('success', 'Revised enrollment submitted for Registrar review. No new enrollment or payment was created.');
    }

    public function requestEnrollmentRefund(Request $request, EnrollmentReplacementRequest $replacementRequest)
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
            'confirm_refund' => ['required', 'accepted'],
        ]);

        abort_unless(
            (int) $replacementRequest->enrollment()->value('user_id') === (int) Auth::id(),
            403
        );

        $case = DB::transaction(function () use ($replacementRequest, $validated) {
            $lockedEnrollment = Enrollment::whereKey($replacementRequest->enrollment_id)
                ->lockForUpdate()
                ->firstOrFail();
            $lockedCase = EnrollmentReplacementRequest::whereKey($replacementRequest->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ((int) $lockedEnrollment->user_id !== (int) Auth::id()
                || $lockedEnrollment->status !== 'pending'
                || ! $lockedEnrollment->is_paid
                || $lockedCase->status !== 'open') {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'reason' => 'This enrollment is no longer eligible for a refund request.',
                ]);
            }

            $payments = Payment::where('enrollment_id', $lockedEnrollment->id)
                ->where('status', 'verified')
                ->lockForUpdate()
                ->get();
            if ($payments->isEmpty() || $payments->contains(fn ($payment) => $payment->refund_status !== 'not_required')) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'reason' => 'The verified payment is missing or already has a refund request.',
                ]);
            }

            foreach ($payments as $payment) {
                $payment->update(['refund_status' => 'pending']);
            }

            $lockedEnrollment->update([
                'status' => 'rejected',
                'remarks' => 'Student requested a refund: '.trim($validated['reason']),
            ]);
            $lockedCase->update([
                'status' => 'refund_requested',
                'student_reason' => trim($validated['reason']),
            ]);

            return $lockedCase->fresh(['enrollment.user']);
        });

        foreach (\App\Models\User::role('cashier')->get() as $cashier) {
            NotificationService::sendForEvent(
                $cashier,
                'enrollment-refund-requested:'.$case->id,
                'Enrollment Refund Requested',
                $case->enrollment->user->name.' requested refund follow-up for enrollment #'.$case->enrollment_id.'. Verify the original payment before recording reconciliation.',
                'warning',
                route('cashier.payments.show', $case->enrollment)
            );
        }

        NotificationService::sendUserUpdateSafely(
            $case->enrollment->user,
            'enrollment-refund-requested:'.$case->id,
            'Refund Request Sent',
            'Your refund request was sent to the Cashier. The original verified payment remains in your history and is pending reconciliation.',
            'info',
            route('portal.enrollment.index'),
            'View enrollment status'
        );

        return redirect()->route('portal.enrollment.index')
            ->with('success', 'Refund request sent to the Cashier. It is not marked complete until the Cashier records the actual refund.');
    }

    // ── submitGcash() — NEW ────────────────────────────────────────

    public function submitGcash(Request $request, Enrollment $enrollment)
    {
        // Ownership check
        if ($enrollment->user_id !== Auth::id()) {
            abort(403);
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

        // Lock the enrollment so GCash and walk-in submissions serialize against
        // the same authoritative payment state. A rejected attempt may be retried.
        $proofPath = null;
        try {
            $result = DB::transaction(function () use ($enrollment, $request, $validated, &$proofPath) {
                \App\Models\User::whereKey(Auth::id())->lockForUpdate()->firstOrFail();
                $lockedEnrollment = Enrollment::whereKey($enrollment->id)->lockForUpdate()->firstOrFail();

                if (! in_array($lockedEnrollment->status, ['pending', 'approved'], true)) {
                    return 'not_payable';
                }
                if ($lockedEnrollment->requires_section_assignment) {
                    return 'sections_needed';
                }
                if ($lockedEnrollment->is_paid) {
                    return 'paid';
                }
                if (Payment::unresolvedRefundsForTerm($lockedEnrollment)->exists()) {
                    return 'refund_unresolved';
                }
                if (Payment::outstandingForTerm($lockedEnrollment)->exists()) {
                    return 'prior_payment';
                }

                $attempts = Payment::where('enrollment_id', $lockedEnrollment->id)
                    ->lockForUpdate()
                    ->get();
                if ($attempts->contains('status', 'verified')) {
                    return 'verified';
                }
                if ($attempts->contains('status', 'pending')) {
                    return 'pending';
                }

                $proofPath = $request->file('proof_of_payment')->store('payment-proofs', 'local');
                $payment = Payment::create([
                    'enrollment_id' => $lockedEnrollment->id,
                    'payment_method' => 'gcash',
                    'reference_number' => $validated['reference_number'],
                    'proof_of_payment' => $proofPath,
                    'status' => 'pending',
                    'processed_by' => null,
                    'verified_by' => null,
                    'verified_at' => null,
                    'amount' => Setting::get('enrollment_fee', 500),
                    'receipt_no' => null,
                    'paid_at' => null,
                ]);

                foreach (\App\Models\User::role('cashier')->get() as $cashier) {
                    \App\Services\NotificationService::sendForEvent(
                        $cashier,
                        'enrollment-gcash-payment-pending:'.$payment->id,
                        'New GCash Payment Pending Verification',
                        "A GCash payment has been submitted for Enrollment #{$lockedEnrollment->id} and is awaiting your review.",
                        'info',
                        route('cashier.payments.show', $lockedEnrollment)
                    );
                }

                return $payment;
            });
        } catch (\Throwable $exception) {
            if ($proofPath) {
                Storage::disk('local')->delete($proofPath);
            }
            throw $exception;
        }

        if (is_string($result)) {
            $message = [
                'not_payable' => 'This enrollment is not eligible for payment.',
                'sections_needed' => 'Wait for Registrar section assignment before paying. No payment proof was saved.',
                'paid' => 'This enrollment has already been paid.',
                'refund_unresolved' => 'A previous verified payment for this academic term has not been refunded. Contact the Cashier; no new payment can be made until the refund is confirmed.',
                'prior_payment' => 'Another payment for this academic term is already pending or verified. Contact the Cashier to resolve it before paying again.',
                'verified' => 'A verified payment already exists for this enrollment. Contact the Cashier if the records do not match.',
                'pending' => 'You already have a payment pending verification. Please wait for the Cashier to review it.',
            ][$result];

            return back()->with('error', $message);
        }

        \App\Services\NotificationService::sendUserUpdateSafely(
            Auth::user(),
            'enrollment-payment-proof-submitted:'.$result->id,
            'GCash Proof Received',
            'We received your GCash payment proof. The Cashier will review it shortly.',
            'info',
            route('portal.enrollment.payment-info', $enrollment),
            'View payment status'
        );

        return redirect()->route('portal.enrollment.payment-info', $enrollment)->with('success', 'GCash proof submitted.');
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

        return PrivateFilePreview::respond(
            request(),
            Storage::disk('local')->path($payment->proof_of_payment),
            'Payment proof',
            route('portal.enrollment.payment-info', $payment->enrollment),
            basename($payment->proof_of_payment)
        );
    }

    // ── showGcashQr() — NEW (shared institutional QR, auth-only) ──

    public function showGcashQr(Request $request)
    {
        $path = Setting::get('gcash_qr_path');

        if (! $path || ! Storage::disk('local')->exists($path)) {
            abort(404, 'GCash QR code not yet configured. Please contact the Registrar.');
        }

        $absolutePath = Storage::disk('local')->path($path);

        if ($request->boolean('download')) {
            $extension = pathinfo($absolutePath, PATHINFO_EXTENSION) ?: 'png';

            return response()->download($absolutePath, 'DTC-GCash-QR.'.$extension);
        }

        return response()->file($absolutePath);
    }

    // ── getSubjects() — regular mode unchanged; new grouped "all" mode ──

    public function getSubjects(Request $request, Program $program)
    {
        // Irregular mode: return every subject in this program, grouped by
        // year_level + semester, so the student can pick across groups in
        // a single enrollment. Program scoping still applies — a student
        // can never see or select another program's subjects.
        if ($request->query('mode') === 'all') {
            $subjects = \App\Models\CourseSubject::where('program_id', $program->id)
                ->orderBy('year_level')
                ->orderBy('semester')
                ->orderBy('subject_code')
                ->get(['id', 'subject_code', 'subject_name', 'units', 'year_level', 'semester']);

            $grouped = $subjects->groupBy(function ($s) {
                return $s->year_level.'|'.$s->semester;
            })->map(function ($group, $key) {
                [$year, $sem] = explode('|', $key);

                return [
                    'year_level' => (int) $year,
                    'semester' => (int) $sem,
                    'subjects' => $group->values(),
                ];
            })->values();

            return response()->json($grouped);
        }

        // ── Regular mode — unchanged ────────────────────────────────────
        $validated = $request->validate([
            'year_level' => ['required', 'integer', 'min:1', 'max:5'],
            'semester' => ['required'],
        ]);

        $subjects = \App\Models\CourseSubject::where('program_id', $program->id)
            ->where('year_level', $validated['year_level'])
            ->where('semester', $validated['semester'])
            ->orderBy('subject_code')
            ->get(['id', 'subject_code', 'subject_name']);

        return response()->json($subjects);
    }

    // ── paymentInfo() — unchanged signature, view gains new data ──

    public function paymentInfo(Request $request, Enrollment $enrollment)
    {
        if ($enrollment->user_id !== Auth::id()) {
            abort(403);
        }

        $enrollment->load('program', 'payment');

        $gcashNumber = Setting::get('gcash_number');
        $gcashName = Setting::get('gcash_name');
        $gcashQrReady = Setting::get('gcash_qr_path') && Storage::disk('local')->exists(Setting::get('gcash_qr_path'));
        $fee = Setting::get('enrollment_fee', 500);

        // Latest payment (could be pending/rejected/verified)
        $latestPayment = Payment::where('enrollment_id', $enrollment->id)
            ->latest()
            ->first();
        $verifiedPayments = Payment::where('enrollment_id', $enrollment->id)
            ->where('status', 'verified')
            ->get(['refund_status']);
        $refundFullyConfirmed = $verifiedPayments->isNotEmpty()
            && $verifiedPayments->every(fn ($verifiedPayment) => $verifiedPayment->refund_status === 'refunded');
        $outstandingTermPayments = Payment::outstandingForTerm($enrollment)
            ->with('enrollment')
            ->latest()
            ->get();

        return view('student.enrollment.payment', compact(
            'enrollment',
            'gcashNumber',
            'gcashName',
            'gcashQrReady',
            'fee',
            'latestPayment',
            'outstandingTermPayments',
            'refundFullyConfirmed'
        ));
    }
}
