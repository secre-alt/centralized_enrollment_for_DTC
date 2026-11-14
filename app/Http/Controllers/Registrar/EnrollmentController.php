<?php

namespace App\Http\Controllers\Registrar;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\Enrollment;
use App\Models\EnrollmentReplacementRequest;
use App\Models\Payment;
use App\Models\StudentProfile;
use App\Models\User;
use App\Services\NotificationService;
use App\Services\EnrollmentSubjectReplacementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EnrollmentController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'review' => ['nullable', 'integer'],
            'search' => ['nullable', 'string', 'max:200'],
            'program_id' => ['nullable', 'integer', 'exists:programs,id'],
            'school_year' => ['nullable', 'string', 'max:30'],
            'year_level' => ['nullable', 'integer', 'min:1'],
            'semester' => ['nullable', 'integer', 'min:1'],
            'classification' => ['nullable', 'in:regular,irregular'],
        ] + \App\Support\ListDateRange::rules($request));

        $query = Enrollment::with('user', 'program', 'payment')
            ->where('status', 'pending')
            ->where('is_paid', true)
            ->whereHas('payment', fn ($payment) => $payment->where('status', 'verified'));
        $search = trim($filters['search'] ?? '');
        if ($search !== '') {
            $query->where(function ($matches) use ($search) {
                $matches->whereHas('user', function ($user) use ($search) {
                    $user->where('name', 'like', '%' . $search . '%')
                        ->orWhere('email', 'like', '%' . $search . '%');
                })->orWhereHas('program', function ($program) use ($search) {
                    $program->where('name', 'like', '%' . $search . '%')
                        ->orWhere('code', 'like', '%' . $search . '%');
                });
            });
        }
        foreach (['program_id', 'school_year', 'year_level', 'semester'] as $field) {
            if (isset($filters[$field])) $query->where($field, $filters[$field]);
        }
        if (isset($filters['classification'])) {
            $query->where('is_irregular', $filters['classification'] === 'irregular');
        }
        \App\Support\ListDateRange::apply($query, $filters, 'enrollments.created_at');
        $enrollments = $query
            ->latest()
            ->paginate(\App\Support\TablePagination::perPage(10))->withQueryString();

        $reviewEnrollment = null;
        if (! empty($filters['review'])) {
            $reviewEnrollment = Enrollment::whereKey($filters['review'])
                ->where('status', 'pending')
                ->first();
        }

        $programs = \App\Models\Program::orderBy('name')->get(['id', 'name']);
        $academicFilters = [];
        foreach (['school_year', 'year_level', 'semester'] as $field) {
            $academicFilters[$field] = Enrollment::whereNotNull($field)->distinct()->orderBy($field)->pluck($field)->filter(fn ($value) => (string) $value !== '');
        }

        return view('registrar.enrollments.index', compact('enrollments', 'reviewEnrollment', 'programs', 'academicFilters'));
    }

    public function show(Enrollment $enrollment, EnrollmentSubjectReplacementService $replacementService)
    {
        $enrollment->load([
            'user',
            'program',
            'payment',
            'creditedSubjects.courseSubject',
            'replacementRequests.requester',
            'replacementRequests.items.originalSubject',
            'replacementRequests.items.originalSchedule.courseSubject.program',
            'replacementRequests.items.selectedSchedule.courseSubject.program',
            'replacementRequests.items.selectedSchedule.hostingProgram',
        ]);
        $subjects = \App\Models\CourseSubject::whereIn('id', $enrollment->subject_ids)
            ->with(['schedules.hostingProgram', 'equivalencies'])
            ->get();
        $programSubjects = \App\Models\CourseSubject::where('program_id', $enrollment->program_id)
            ->orderBy('year_level')->orderBy('semester')->orderBy('subject_code')
            ->get();
        $equivalentSubjects = \App\Models\CourseSubject::with('program')
            ->whereNotIn('id', $enrollment->subject_ids ?? [])
            ->orderBy('subject_code')
            ->get();
        $replacementRequest = $enrollment->replacementRequests
            ->where('status', 'open')
            ->sortByDesc('id')
            ->first();
        $replacementOptions = collect();
        if ($replacementRequest) {
            foreach ($replacementRequest->items as $item) {
                $replacementOptions[$item->id] = $replacementService->eligibleOfferings($enrollment, $item);
            }
        }

        return view('registrar.enrollments.show', compact(
            'enrollment',
            'subjects',
            'programSubjects',
            'equivalentSubjects',
            'replacementRequest',
            'replacementOptions'
        ));
    }

    /**
     * Registrar-side COR lookup for any enrollment (reached from search or
     * the enrollment show page), not just the ones pending review. Reuses
     * the same student-facing Blade views since the layout is identical —
     * only the enrollment/subjects data and the "back" link differ.
     */
    public function showCor(Enrollment $enrollment)
    {
        if ($enrollment->status !== 'approved' || ! $enrollment->is_paid) {
            return redirect()->route('registrar.enrollments.show', $enrollment)
                ->with('error', 'A Certificate of Registration is only available once enrollment is approved and paid.');
        }

        $enrollment->load('user', 'program');
        $subjects = \App\Models\CourseSubject::whereIn('id', $enrollment->subject_ids ?? [])
            ->with('schedules.hostingProgram')
            ->orderBy('subject_code')
            ->get();
        $offeringAssignments = $enrollment->subjectOfferings()
            ->with(['classSchedule.courseSubject.program', 'classSchedule.hostingProgram'])
            ->get()
            ->keyBy('curriculum_subject_id');
        $totalUnits = $subjects->sum('units');

        return view('student.cor.show', [
            'enrollment' => $enrollment,
            'subjects'   => $subjects,
            'offeringAssignments' => $offeringAssignments,
            'totalUnits' => $totalUnits,
            'backUrl'    => route('registrar.enrollments.show', $enrollment),
        ]);
    }

    public function downloadCor(Enrollment $enrollment)
    {
        if ($enrollment->status !== 'approved' || ! $enrollment->is_paid) {
            return redirect()->route('registrar.enrollments.show', $enrollment)
                ->with('error', 'A Certificate of Registration is only available once enrollment is approved and paid.');
        }

        $enrollment->load('user', 'program');
        $subjects = \App\Models\CourseSubject::whereIn('id', $enrollment->subject_ids ?? [])
            ->with('schedules.hostingProgram')
            ->orderBy('subject_code')
            ->get();
        $offeringAssignments = $enrollment->subjectOfferings()
            ->with(['classSchedule.courseSubject.program', 'classSchedule.hostingProgram'])
            ->get()
            ->keyBy('curriculum_subject_id');
        $totalUnits = $subjects->sum('units');

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('student.cor.pdf', compact('enrollment', 'subjects', 'totalUnits', 'offeringAssignments'));

        return $pdf->download('COR_' . str_replace(' ', '_', $enrollment->user->name) . '.pdf');
    }

    public function approve(Request $request, Enrollment $enrollment, EnrollmentSubjectReplacementService $replacementService)
    {
        $validated = $request->validate([
            'section_choices' => ['nullable', 'array'],
            'section_choices.*' => ['nullable', 'integer', 'min:1'],
        ]);
        $enrollment = DB::transaction(function () use ($enrollment, $validated, $replacementService) {
            $lockedEnrollment = Enrollment::whereKey($enrollment->id)->lockForUpdate()->firstOrFail();
            $payment = Payment::where('enrollment_id', $lockedEnrollment->id)
                ->latest('id')
                ->first();

            if ($lockedEnrollment->status !== 'pending' || ! $lockedEnrollment->is_paid || ! $payment || ! $payment->isVerified() || $payment->refund_status !== 'not_required') {
                throw ValidationException::withMessages([
                    'enrollment' => 'The Cashier must verify payment before the Registrar can review this enrollment.',
                ]);
            }

            $openReplacement = EnrollmentReplacementRequest::where('enrollment_id', $lockedEnrollment->id)
                ->where('status', 'open')
                ->exists();
            if ($openReplacement) {
                throw ValidationException::withMessages([
                    'enrollment' => 'The student must respond to the open subject replacement request before approval.',
                ]);
            }

            $replacementService->validateApprovalLoad($lockedEnrollment, $validated['section_choices'] ?? []);

            $user = $lockedEnrollment->user;
            if ($user->hasRole('new_applicant')) {
                $approvedApplicationExists = Application::where('user_id', $user->id)
                    ->where('program_id', $lockedEnrollment->program_id)
                    ->where('status', 'approved')
                    ->exists();

                if (! $approvedApplicationExists) {
                    throw ValidationException::withMessages([
                        'enrollment' => 'This applicant does not have an approved application for the enrollment program.',
                    ]);
                }

                $user->syncRoles(['student']);
                \App\Services\StudentWelcome::record($user);
            } elseif (! $user->hasAnyRole(['student', 'alumni', 'admin', 'registrar', 'cashier'])) {
                $user->assignRole('student');
            }

            $lockedEnrollment->update(['status' => 'approved']);
            $latestSubmittedRequest = EnrollmentReplacementRequest::where('enrollment_id', $lockedEnrollment->id)
                ->where('status', 'submitted')
                ->latest('id')
                ->first();
            $latestSubmittedRequest?->update(['status' => 'approved', 'resolved_at' => now()]);
            StudentProfile::createForEnrollmentIfMissing($user, $lockedEnrollment);

            return $lockedEnrollment->fresh(['user', 'program']);
        }, 3);

        // ── Notify Student ────────────────────────────────────────────
        NotificationService::sendUserUpdateSafely(
            $enrollment->user,
            'enrollment-approved:'.$enrollment->id,
            'Enrollment Approved',
            'Your enrollment has been approved by the Registrar after payment verification.',
            'success',
            route('portal.enrollment.index'),
            'View enrollment status'
        );

        // ── Notify Admin ──────────────────────────────────────────────
        $admins = User::role('admin')->get();
        foreach ($admins as $admin) {
            NotificationService::send(
                $admin,
                'Enrollment Approved',
                'Registrar approved ' . $enrollment->user->name . '\'s enrollment for ' .
                $enrollment->program->name . '.',
                'success',
                route('registrar.enrollments.index')
            );
        }

        return redirect()->route('registrar.enrollments.index')
            ->with('success', 'Enrollment approved. Student has been notified.');
    }

    public function reject(Request $request, Enrollment $enrollment)
    {
        $validated = $request->validate([
            'remarks' => ['required', 'string', 'max:500'],
        ]);

        $enrollment = DB::transaction(function () use ($enrollment, $validated) {
            $lockedEnrollment = Enrollment::whereKey($enrollment->id)->lockForUpdate()->firstOrFail();
            $hasVerifiedPayment = Payment::where('enrollment_id', $lockedEnrollment->id)
                ->where('status', 'verified')
                ->exists();

            if ($lockedEnrollment->status !== 'pending' || ! $lockedEnrollment->is_paid || ! $hasVerifiedPayment) {
                throw ValidationException::withMessages([
                    'enrollment' => 'Only enrollments with verified payment can be rejected by the Registrar.',
                ]);
            }

            if (EnrollmentReplacementRequest::where('enrollment_id', $lockedEnrollment->id)
                ->where('status', 'open')
                ->exists()) {
                throw ValidationException::withMessages([
                    'enrollment' => 'The student must choose replacement or request a refund before the Registrar can reject this enrollment.',
                ]);
            }

            $lockedEnrollment->update([
                'status'  => 'rejected',
                'remarks' => $validated['remarks'],
            ]);
            Payment::where('enrollment_id', $lockedEnrollment->id)
                ->where('status', 'verified')
                ->update(['refund_status' => 'pending']);
            $latestSubmittedRequest = EnrollmentReplacementRequest::where('enrollment_id', $lockedEnrollment->id)
                ->where('status', 'submitted')
                ->latest('id')
                ->first();
            $latestSubmittedRequest?->update([
                'status' => 'denied',
                'resolved_at' => now(),
            ]);

            return $lockedEnrollment->fresh(['user']);
        });

        // ── Notify Student ────────────────────────────────────────────
        NotificationService::sendUserUpdateSafely(
            $enrollment->user,
            'enrollment-rejected:'.$enrollment->id,
            'Enrollment Rejected',
            'Your enrollment was rejected after payment was verified. Contact the Cashier to arrange a refund; refunds are handled outside this system. Reason: ' . $validated['remarks'],
            'danger',
            route('portal.enrollment.index'),
            'Review enrollment'
        );

        // ── Notify Admin ──────────────────────────────────────────────
        $admins = User::role('admin')->get();
        foreach ($admins as $admin) {
            NotificationService::send(
                $admin,
                'Enrollment Rejected',
                'Registrar rejected ' . $enrollment->user->name . '\'s enrollment. Reason: ' . $validated['remarks'],
                'warning',
                route('registrar.enrollments.index')
            );
        }

        foreach (User::role('cashier')->get() as $cashier) {
            NotificationService::send(
                $cashier,
                'Refund Follow-up Needed',
                $enrollment->user->name . "'s paid enrollment was rejected by the Registrar. Contact the student to arrange the refund.",
                'warning',
                route('cashier.payments.show', $enrollment)
            );
        }

        return redirect()->route('registrar.enrollments.index')
            ->with('success', 'Enrollment rejected. Student and Cashier have been notified about refund follow-up.');
    }

    public function returnForSubjectReplacement(Request $request, Enrollment $enrollment)
    {
        $validated = $request->validate([
            'affected_subject_ids' => ['required', 'array', 'min:1'],
            'affected_subject_ids.*' => ['required', 'integer', 'distinct', 'exists:course_subjects,id'],
            'unavailable_schedule_ids' => ['nullable', 'array'],
            'unavailable_schedule_ids.*' => ['nullable', 'integer', 'exists:class_schedules,id'],
            'reason' => ['required', 'string', 'min:10', 'max:2000'],
        ]);

        $replacementRequest = DB::transaction(function () use ($enrollment, $validated) {
            $lockedEnrollment = Enrollment::whereKey($enrollment->id)->lockForUpdate()->firstOrFail();
            $verifiedPayment = Payment::where('enrollment_id', $lockedEnrollment->id)
                ->where('status', 'verified')
                ->lockForUpdate()
                ->exists();

            if ($lockedEnrollment->status !== 'pending' || ! $lockedEnrollment->is_paid || ! $verifiedPayment) {
                throw ValidationException::withMessages([
                    'enrollment' => 'Only a paid enrollment with verified payment can be returned for subject replacement.',
                ]);
            }

            if (EnrollmentReplacementRequest::where('enrollment_id', $lockedEnrollment->id)
                ->where('status', 'open')
                ->lockForUpdate()
                ->exists()) {
                throw ValidationException::withMessages([
                    'enrollment' => 'There is already an open subject replacement request for this enrollment.',
                ]);
            }

            $currentSubjectIds = collect($lockedEnrollment->subject_ids ?? [])->map(fn ($id) => (int) $id);
            $affectedIds = collect($validated['affected_subject_ids'])->map(fn ($id) => (int) $id);
            $subjectsBelongToProgram = \App\Models\CourseSubject::whereIn('id', $affectedIds)
                ->where('program_id', $lockedEnrollment->program_id)
                ->count();

            if ($currentSubjectIds->intersect($affectedIds)->count() !== $affectedIds->count()
                || $subjectsBelongToProgram !== $affectedIds->count()) {
                throw ValidationException::withMessages([
                    'affected_subject_ids' => 'Select only subjects in this enrollment and its official program.',
                ]);
            }

            $unavailableSchedules = $validated['unavailable_schedule_ids'] ?? [];
            foreach ($unavailableSchedules as $subjectId => $scheduleId) {
                if (! $scheduleId) {
                    continue;
                }

                $belongsToSubject = \App\Models\ClassSchedule::whereKey($scheduleId)
                    ->where('course_subject_id', $subjectId)
                    ->exists();
                if (! $affectedIds->contains((int) $subjectId) || ! $belongsToSubject) {
                    throw ValidationException::withMessages([
                        'unavailable_schedule_ids' => 'An unavailable offering must belong to one of the selected affected subjects.',
                    ]);
                }
            }

            $baselineIsIrregular = EnrollmentReplacementRequest::where('enrollment_id', $lockedEnrollment->id)
                ->orderBy('id')
                ->value('baseline_is_irregular');

            $replacementRequest = EnrollmentReplacementRequest::create([
                'enrollment_id' => $lockedEnrollment->id,
                'requested_by' => auth()->id(),
                'status' => 'open',
                'reason' => trim($validated['reason']),
                'baseline_is_irregular' => $baselineIsIrregular === null
                    ? (bool) $lockedEnrollment->is_irregular
                    : (bool) $baselineIsIrregular,
            ]);
            foreach ($affectedIds as $subjectId) {
                $scheduleId = $unavailableSchedules[$subjectId] ?? null;
                if (! $scheduleId) {
                    $scheduleId = \App\Models\EnrollmentSubjectOffering::where('enrollment_id', $lockedEnrollment->id)
                        ->where('curriculum_subject_id', $subjectId)
                        ->value('class_schedule_id');
                }

                $replacementRequest->items()->create([
                    'original_subject_id' => $subjectId,
                    'original_schedule_id' => $scheduleId,
                ]);
            }

            return $replacementRequest->load('items.originalSubject', 'enrollment.user', 'enrollment.program');
        });

        NotificationService::sendUserUpdateSafely(
            $replacementRequest->enrollment->user,
            'subject-replacement-required:'.$replacementRequest->id,
            'Subject Replacement Required',
            'The Registrar returned your paid enrollment for a subject decision. Your verified payment remains attached. Reason: '.$replacementRequest->reason,
            'warning',
            route('portal.enrollment.index'),
            'Review enrollment'
        );

        return redirect()->route('registrar.enrollments.show', $enrollment)
            ->with('success', 'Enrollment returned to the student for subject replacement. The verified payment was preserved.');
    }

    // ── Alumni Promotion ─────────────────────────────────────────────────────
    //
    // Only available when the enrollment is approved + paid and the user still
    // holds the 'student' role. Admin is allowed as well (route middleware
    // already covers registrar|admin).

    public function promoteToAlumni(Request $request, \App\Models\User $user)
    {
        // The target must currently be a student — guard against double-promotion
        // and against promoting cashiers/registrars/admins via this endpoint.
        if (! $user->hasRole('student')) {
            return redirect()->back()
                ->with('error', 'This action is only available for active students.');
        }

        $validated = $request->validate([
            'graduation_year' => ['required', 'integer', 'min:1990', 'max:' . (date('Y') + 1)],
        ]);

        // Update or create the StudentProfile row.
        // updateOrCreate so that existing profile rows (which always exist for
        // paid students) are updated in place rather than duplicated.
        $profile = $user->studentProfile()->updateOrCreate(
            ['user_id' => $user->id],
            ['graduation_year' => $validated['graduation_year'], 'academic_status' => 'alumni']
        );

        // Role swap — atomic, using Spatie's syncRoles to prevent dual-role state.
        $user->syncRoles(['alumni']);

        // Notify the newly promoted alumni.
        NotificationService::send(
            $user,
            'Promoted to Alumni',
            'Congratulations! You have been officially recorded as a DTC graduate (Batch ' .
                $validated['graduation_year'] . '). Your alumni portal is now active.',
            'success',
            route('portal.dashboard')
        );

        return redirect()->route('registrar.enrollments.index')
            ->with('success', $user->name . ' has been promoted to Alumni (Batch ' . $validated['graduation_year'] . ').');
    }

    // ── Credited Subjects (documentation only — see CreditedSubject model) ──
    //
    // Route middleware already restricts this whole controller to
    // registrar|admin, but ownership/consistency of the *data* still needs
    // explicit checks: a course_subject_id from the client is never trusted
    // to actually belong to the enrollment's program.

    public function storeCreditedSubject(Request $request, Enrollment $enrollment)
    {
        $validated = $request->validate([
            'course_subject_id'  => ['required', 'exists:course_subjects,id'],
            'equivalent_subject' => ['nullable', 'string', 'max:255'],
            'equivalent_school'  => ['nullable', 'string', 'max:255'],
            'credited_units'     => ['nullable', 'numeric', 'min:0', 'max:6'],
            'remarks'            => ['nullable', 'string', 'max:1000'],
        ]);

        // The credited subject must be a real subject in this enrollment's
        // own program — never trust that the client-submitted ID is scoped
        // correctly on its own.
        $belongsToProgram = \App\Models\CourseSubject::where('id', $validated['course_subject_id'])
            ->where('program_id', $enrollment->program_id)
            ->exists();

        if (! $belongsToProgram) {
            abort(422, 'That subject does not belong to this enrollment\'s program.');
        }

        \App\Models\CreditedSubject::updateOrCreate(
            [
                'enrollment_id'      => $enrollment->id,
                'course_subject_id'  => $validated['course_subject_id'],
            ],
            [
                'equivalent_subject' => $validated['equivalent_subject'] ?? null,
                'equivalent_school'  => $validated['equivalent_school'] ?? null,
                'credited_units'     => $validated['credited_units'] ?? null,
                'remarks'            => $validated['remarks'] ?? null,
                'approved_by'        => \Illuminate\Support\Facades\Auth::id(),
                'approved_at'        => now(),
            ]
        );

        return redirect()->route('registrar.enrollments.show', $enrollment)
            ->with('success', 'Credited subject saved.');
    }

    public function updateCreditedSubject(Request $request, \App\Models\CreditedSubject $creditedSubject)
    {
        $validated = $request->validate([
            'equivalent_subject' => ['nullable', 'string', 'max:255'],
            'equivalent_school'  => ['nullable', 'string', 'max:255'],
            'credited_units'     => ['nullable', 'numeric', 'min:0', 'max:6'],
            'remarks'            => ['nullable', 'string', 'max:1000'],
        ]);

        $creditedSubject->update(array_merge($validated, [
            'approved_by' => \Illuminate\Support\Facades\Auth::id(),
            'approved_at' => now(),
        ]));

        return redirect()->route('registrar.enrollments.show', $creditedSubject->enrollment_id)
            ->with('success', 'Credited subject updated.');
    }

    public function destroyCreditedSubject(\App\Models\CreditedSubject $creditedSubject)
    {
        $enrollmentId = $creditedSubject->enrollment_id;
        $creditedSubject->delete();

        return redirect()->route('registrar.enrollments.show', $enrollmentId)
            ->with('success', 'Credited subject removed.');
    }
}
