<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Program;
use App\Models\CourseSubject;
use App\Models\CourseSubjectEquivalency;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProgramController extends Controller
{
    public function index(Request $request)
    {
        $programs = Program::withCount('subjects')
            ->orderBy('name')
            ->paginate(\App\Support\TablePagination::perPage(10), ['*'], 'page')->withQueryString();
        $hostingPrograms = Program::orderBy('name')->get();
        $allSubjects = CourseSubject::with('program')->orderBy('subject_code')->get();

        $selectedProgram = null;
        $subjects = collect();

        if ($request->filled('program')) {
            $selectedProgram = Program::find($request->query('program'));

            if ($selectedProgram) {
                $subjects = CourseSubject::where('program_id', $selectedProgram->id)
                    ->with('schedules')
                    ->orderBy('year_level')
                    ->orderBy('semester')
                    ->orderBy('subject_name')
                    ->paginate(\App\Support\TablePagination::perPage(10), ['*'], 'subjects_page')->withQueryString();
            }
        }

        // AJAX program-select: return only the Subjects card markup so the
        // page doesn't do a full reload (navbar/sidebar/programs table stay
        // untouched). Triggered by fetch() with X-Requested-With header.
        if ($request->ajax()) {
            return view('admin.programs.partials.subjects-card', compact('selectedProgram', 'subjects', 'allSubjects', 'hostingPrograms'));
        }

        return view('admin.programs.index', compact('programs', 'selectedProgram', 'subjects', 'allSubjects', 'hostingPrograms'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:20', 'unique:programs,code'],
        ]);

        Program::create($validated);

        return redirect()->route('admin.programs.index')
            ->with('success', 'Program added.');
    }

    public function update(Request $request, Program $program)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:20', 'unique:programs,code,' . $program->id],
        ]);

        $program->update($validated);

        return redirect()->route('admin.programs.index', $request->only('program'))
            ->with('success', 'Program updated.');
    }

    public function destroy(Program $program)
    {
        $program->delete();
        return redirect()->route('admin.programs.index')
            ->with('success', 'Program removed.');
    }

    public function subjects(Program $program)
    {
        $subjects = CourseSubject::where('program_id', $program->id)
            ->orderBy('year_level')
            ->orderBy('semester')
            ->get();
        $allSubjects = CourseSubject::with('program')->orderBy('subject_code')->get();
        $hostingPrograms = Program::orderBy('name')->get();

        return view('admin.programs.subjects', compact('program', 'subjects', 'allSubjects', 'hostingPrograms'));
    }

    public function storeSubject(Request $request, Program $program)
    {
        $validated = $request->validate([
            'subject_code' => ['required', 'string', 'max:20'],
            'subject_name' => ['required', 'string', 'max:255'],
            'classification' => ['required', 'in:major,minor,general_education'],
            'units'        => ['required', 'numeric', 'min:0.5', 'max:6'],
            'year_level'   => ['required', 'integer', 'min:1', 'max:4'],
            'semester'     => ['required', 'integer', 'in:1,2'],
        ]);

        CourseSubject::create(array_merge($validated, [
            'program_id' => $program->id,
        ]));

        return redirect()->route('admin.programs.index', ['program' => $program->id])
            ->with('success', 'Subject added.');
    }

    public function updateSubject(Request $request, CourseSubject $subject)
    {
        $validated = $request->validate([
            'subject_code' => ['required', 'string', 'max:20'],
            'subject_name' => ['required', 'string', 'max:255'],
            'classification' => ['required', 'in:major,minor,general_education'],
            'units'        => ['required', 'numeric', 'min:0.5', 'max:6'],
            'year_level'   => ['required', 'integer', 'min:1', 'max:4'],
            'semester'     => ['required', 'integer', 'in:1,2'],
        ]);

        $subject->update($validated);

        return redirect()->route('admin.programs.index', ['program' => $subject->program_id])
            ->with('success', 'Subject updated.');
    }

    public function destroySubject(CourseSubject $subject)
    {
        $programId = $subject->program_id;
        $subject->delete();

        return redirect()->route('admin.programs.index', ['program' => $programId])
            ->with('success', 'Subject removed.');
    }

    // ── SCHEDULE (per subject) ──────────────────────────────────────────────
    // Modeled as hasMany on CourseSubject, but the UI manages one schedule
    // per subject for now (updateOrCreate keeps it that way).

    public function storeSchedule(
        Request $request,
        CourseSubject $subject,
        \App\Services\EnrollmentSubjectReplacementService $replacementService
    )
    {
        $validated = $request->validate([
            'section_code'    => ['nullable', 'string', 'max:30'],
            'hosting_program_id' => ['nullable', 'integer', 'exists:programs,id'],
            'capacity'        => ['nullable', 'integer', 'min:1', 'max:1000'],
            'is_active'       => ['nullable', 'boolean'],
            'requires_irregular' => ['nullable', 'boolean'],
            'day_pattern'      => ['required', 'string', 'max:20'],
            'time_start'       => ['required', 'date_format:H:i'],
            'time_end'         => ['required', 'date_format:H:i', 'after:time_start'],
            'room'             => ['nullable', 'string', 'max:50'],
            'instructor_name'  => ['nullable', 'string', 'max:255'],
        ]);

        $scheduleData = [
            'day_pattern' => $validated['day_pattern'],
            'time_start' => $validated['time_start'],
            'time_end' => $validated['time_end'],
            'room' => $validated['room'] ?? 'TBA',
            'instructor_name' => $validated['instructor_name'] ?? null,
        ];

        if (! empty($validated['section_code'])) {
            if (empty($validated['capacity'])) {
                throw ValidationException::withMessages([
                    'capacity' => 'A section offering must have a defined seat capacity.',
                ]);
            }

            $schoolYear = trim((string) Setting::get('current_school_year', ''));
            $semester = [
                '1st' => 1,
                '2nd' => 2,
                'Summer' => 3,
            ][(string) Setting::get('current_semester', '')] ?? null;

            if ($schoolYear === '' || $semester === null) {
                throw ValidationException::withMessages([
                    'section_code' => 'Configure the current school year and semester before creating a section offering.',
                ]);
            }

            $hostingProgramId = (int) ($validated['hosting_program_id'] ?? $subject->program_id);
            if ($hostingProgramId !== (int) $subject->program_id
                && (! array_key_exists('requires_irregular', $validated)
                    || $validated['requires_irregular'] === null)) {
                throw ValidationException::withMessages([
                    'requires_irregular' => 'Choose whether this cross-program offering requires Irregular enrollment.',
                ]);
            }

            DB::transaction(function () use ($subject, $validated, $scheduleData, $schoolYear, $semester, $hostingProgramId, $replacementService) {
                $schedule = $subject->schedules()
                    ->where('section_code', trim($validated['section_code']))
                    ->where('school_year', $schoolYear)
                    ->where('semester', $semester)
                    ->lockForUpdate()
                    ->first();

                if ($schedule) {
                    $assignedSeats = $schedule->enrollmentSelections()
                        ->whereHas('enrollment', fn ($query) => $query->whereIn('status', ['pending', 'approved']))
                        ->count();
                    if ((int) $validated['capacity'] < $assignedSeats) {
                        throw ValidationException::withMessages([
                            'capacity' => 'Capacity cannot be reduced below the number of active assigned seats.',
                        ]);
                    }
                    if ((int) $validated['capacity'] < (int) $schedule->capacity
                        && $replacementService->hasUnassignedActiveEnrollmentsForSchedule($schedule)) {
                        throw ValidationException::withMessages([
                            'capacity' => 'Capacity cannot be reduced while active enrollments lack confirmed section assignments. Reconcile those enrollments first.',
                        ]);
                    }

                    $schedule->update(array_merge($scheduleData, [
                        'capacity' => $validated['capacity'],
                        'is_active' => (bool) ($validated['is_active'] ?? true),
                        'hosting_program_id' => $hostingProgramId,
                        'requires_irregular' => $hostingProgramId === (int) $subject->program_id
                            ? false
                            : (bool) $validated['requires_irregular'],
                    ]));

                    return;
                }

                $subject->schedules()->create(array_merge($scheduleData, [
                    'section_code' => trim($validated['section_code']),
                    'school_year' => $schoolYear,
                    'semester' => $semester,
                    'capacity' => $validated['capacity'],
                    'is_active' => (bool) ($validated['is_active'] ?? true),
                    'hosting_program_id' => $hostingProgramId,
                    'requires_irregular' => $hostingProgramId === (int) $subject->program_id
                        ? false
                        : (bool) $validated['requires_irregular'],
                ]));
            });
        } else {
            $schedule = $subject->schedules()->whereNull('section_code')->first();
            if ($schedule) {
                $schedule->update($scheduleData);
            } else {
                $subject->schedules()->create($scheduleData);
            }
        }

        return redirect()->route('admin.programs.index', ['program' => $subject->program_id])
            ->with('success', 'Schedule saved.');
    }

    public function destroySchedule(\App\Models\ClassSchedule $schedule)
    {
        if ($schedule->enrollmentSelections()->exists()) {
            throw ValidationException::withMessages([
                'schedule' => 'This section has enrollment history and cannot be deleted. Deactivate it instead.',
            ]);
        }

        $programId = $schedule->courseSubject->program_id;
        $schedule->delete();

        return redirect()->route('admin.programs.index', ['program' => $programId])
            ->with('success', 'Schedule removed.');
    }

    public function storeEquivalency(Request $request, CourseSubject $subject)
    {
        $validated = $request->validate([
            'equivalent_subject_id' => ['required', 'exists:course_subjects,id'],
            'approval_reason' => ['required', 'string', 'max:2000'],
            'requires_irregular' => ['required', 'boolean'],
            'return_enrollment_id' => ['nullable', 'integer', 'exists:enrollments,id'],
        ]);

        if ((int) $subject->id === (int) $validated['equivalent_subject_id']) {
            throw ValidationException::withMessages([
                'equivalent_subject_id' => 'A subject cannot be recorded as its own equivalency.',
            ]);
        }
        if (! in_array($subject->classification, ['major', 'minor', 'general_education'], true)) {
            throw ValidationException::withMessages([
                'equivalent_subject_id' => 'Classify the curriculum subject before approving an equivalency.',
            ]);
        }

        $target = CourseSubject::findOrFail($validated['equivalent_subject_id']);
        if ($subject->units === null || $target->units === null || (float) $subject->units !== (float) $target->units) {
            throw ValidationException::withMessages([
                'equivalent_subject_id' => 'Select a subject with matching units. Credit adjustments are not configured.',
            ]);
        }

        $returnEnrollment = null;
        if (! empty($validated['return_enrollment_id'])) {
            $returnEnrollment = \App\Models\Enrollment::whereKey($validated['return_enrollment_id'])
                ->where('program_id', $subject->program_id)
                ->whereJsonContains('subject_ids', $subject->id)
                ->first();

            if (! $returnEnrollment) {
                throw ValidationException::withMessages([
                    'return_enrollment_id' => 'The academic equivalency does not match the enrollment curriculum.',
                ]);
            }
        }

        if (CourseSubjectEquivalency::where('source_subject_id', $subject->id)
            ->where('equivalent_subject_id', $validated['equivalent_subject_id'])
            ->exists()) {
            throw ValidationException::withMessages([
                'equivalent_subject_id' => 'This directed equivalency is already recorded. Existing academic approvals are immutable.',
            ]);
        }

        CourseSubjectEquivalency::create([
            'source_subject_id' => $subject->id,
            'equivalent_subject_id' => $validated['equivalent_subject_id'],
            'approved_by' => Auth::id(),
            'approval_reason' => trim($validated['approval_reason']),
            'requires_irregular' => (bool) $validated['requires_irregular'],
            'approved_at' => now(),
        ]);

        if ($returnEnrollment) {
            return redirect()->route('registrar.enrollments.show', $returnEnrollment)
                ->with('success', 'Official subject equivalency recorded with its academic load treatment.');
        }

        return redirect()->route('admin.programs.index', ['program' => $subject->program_id])
            ->with('success', 'Official subject equivalency recorded with its academic load treatment.');
    }
}
