<?php

namespace App\Services;

use App\Models\ClassSchedule;
use App\Models\CourseSubject;
use App\Models\CourseSubjectEquivalency;
use App\Models\Enrollment;
use App\Models\EnrollmentReplacementItem;
use App\Models\EnrollmentSubjectOffering;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

class EnrollmentSubjectReplacementService
{
    /** Called inside the enrollment approval transaction; never guesses a section. */
    public function validateApprovalLoad(Enrollment $enrollment, array $sectionChoices = []): void
    {
        $ids = array_map('intval', $enrollment->subject_ids ?? []);
        if (! $ids || count($ids) !== count(array_unique($ids))) {
            throw ValidationException::withMessages(['enrollment' => 'Review the load: subjects are missing or duplicated.']);
        }
        $subjects = CourseSubject::whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        if ($subjects->count() !== count($ids) || $subjects->contains(fn ($subject) => (int) $subject->program_id !== (int) $enrollment->program_id)) {
            throw ValidationException::withMessages(['enrollment' => 'The selected curriculum subjects do not match this enrollment program.']);
        }
        if (array_diff(array_map('intval', array_keys($sectionChoices)), $ids)) {
            throw ValidationException::withMessages(['enrollment' => 'A section choice does not belong to this enrollment load.']);
        }
        $assignments = $enrollment->subjectOfferings()->lockForUpdate()->get()->keyBy('curriculum_subject_id');
        if ($assignments->keys()->diff($ids)->isNotEmpty()) {
            throw ValidationException::withMessages(['enrollment' => 'Reconcile section assignments that are outside the selected load.']);
        }
        $choices = [];
        foreach ($ids as $id) {
            $recorded = $assignments->get($id)?->class_schedule_id;
            if ($recorded && isset($sectionChoices[$id]) && (int) $sectionChoices[$id] !== (int) $recorded) {
                throw ValidationException::withMessages(['enrollment' => 'Use subject replacement to change an existing section assignment.']);
            }
            $choices[$id] = $recorded ?: ($sectionChoices[$id] ?? null);
            if (! $choices[$id]) {
                throw ValidationException::withMessages(['enrollment' => 'Record a section for '.$subjects[$id]->subject_code.' before approval. Availability cannot be confirmed without it.']);
            }
        }
        $schedules = ClassSchedule::whereIn('id', array_values($choices))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        $unavailable = EnrollmentReplacementItem::whereHas('replacementRequest', fn ($query) => $query->where('enrollment_id', $enrollment->id))
            ->whereNotNull('original_schedule_id')->get();
        $load = [];
        $targetSubjects = [];
        foreach ($choices as $id => $scheduleId) {
            $schedule = $schedules->get($scheduleId);
            if (! $schedule) {
                throw ValidationException::withMessages(['enrollment' => 'A recorded section no longer exists. Reconcile the load before approval.']);
            }
            $item = new EnrollmentReplacementItem(['original_subject_id' => $id]);
            $item->setRelation('originalSubject', $subjects[$id]);
            $item->original_schedule_id = $unavailable->first(fn ($row) => (int) $row->original_subject_id === $id && (int) $row->original_schedule_id === (int) $scheduleId)?->original_schedule_id;
            $this->assertOfferingEligible($enrollment, $item, $schedule);
            if ((string) $schedule->time_start >= (string) $schedule->time_end) {
                throw ValidationException::withMessages(['enrollment' => 'A section has invalid class times. Correct its schedule before approval.']);
            }
            $load[] = $schedule;
            $targetSubjects[] = (int) $schedule->course_subject_id;
        }
        if (count($targetSubjects) !== count(array_unique($targetSubjects))) {
            throw ValidationException::withMessages(['enrollment' => 'Two curriculum selections map to the same academic subject. Review the load.']);
        }
        $this->assertNoScheduleConflicts($enrollment, $load, $ids);
        foreach ($choices as $id => $scheduleId) {
            if (! $assignments->has($id)) {
                $enrollment->subjectOfferings()->create(['curriculum_subject_id' => $id, 'class_schedule_id' => $scheduleId]);
            }
        }
        $enrollment->is_irregular = (bool) $enrollment->is_irregular || $this->enrollmentRequiresIrregular($enrollment);
    }

    public function eligibleOfferings(Enrollment $enrollment, EnrollmentReplacementItem $item): Collection
    {
        $term = ClassSchedule::query()
            ->where('school_year', $enrollment->school_year)
            ->where('semester', $enrollment->semester)
            ->where('is_active', true)
            ->whereNotNull('section_code')
            ->whereNotNull('capacity')
            ->where('capacity', '>', 0)
            ->with(['courseSubject.program', 'hostingProgram'])
            ->orderBy('section_code')
            ->get();

        return $term->filter(function (ClassSchedule $schedule) use ($enrollment, $item) {
            $sameSubject = (int) $item->original_subject_id === (int) $schedule->course_subject_id;
            $equivalency = $this->equivalencyFor($item->originalSubject, $schedule->courseSubject);
            $hasEquivalentUnits = $equivalency
                && (float) $item->originalSubject->units === (float) $schedule->courseSubject->units;

            $remainingSeats = $this->remainingSeats($schedule, $enrollment);
            $schedule->setAttribute('remaining_seats', $remainingSeats);
            $schedule->setAttribute('replacement_requires_irregular', $this->requiresIrregular([$schedule], [$equivalency]));
            $schedule->setRelation('replacementEquivalency', $equivalency?->load('approver'));
            $hostingProgramId = (int) ($schedule->hosting_program_id ?: $schedule->courseSubject->program_id);
            $hasConfiguredLoadTreatment = $hostingProgramId === (int) $schedule->courseSubject->program_id
                || $schedule->requires_irregular !== null;

            return ($sameSubject || $hasEquivalentUnits)
                && (int) $schedule->id !== (int) $item->original_schedule_id
                && $hasConfiguredLoadTreatment
                && ! $this->hasUnassignedActiveEnrollmentsForSchedule($schedule, $enrollment)
                && $remainingSeats > 0;
        })->values();
    }

    public function requiresCapacityReconciliation(
        Enrollment $enrollment,
        EnrollmentReplacementItem $item
    ): bool {
        $candidateSubjectIds = CourseSubjectEquivalency::query()
            ->where('source_subject_id', $item->original_subject_id)
            ->pluck('equivalent_subject_id')
            ->push((int) $item->original_subject_id)
            ->unique()
            ->all();

        $candidateSchedules = ClassSchedule::query()
            ->where('school_year', $enrollment->school_year)
            ->where('semester', $enrollment->semester)
            ->where('is_active', true)
            ->whereNotNull('section_code')
            ->whereNotNull('capacity')
            ->where('capacity', '>', 0)
            ->whereIn('course_subject_id', $candidateSubjectIds)
            ->with('courseSubject')
            ->get();

        foreach ($candidateSchedules as $schedule) {
            $equivalency = $this->equivalencyFor($item->originalSubject, $schedule->courseSubject);
            if (((int) $schedule->course_subject_id === (int) $item->original_subject_id
                    || ($equivalency
                        && (float) $item->originalSubject->units === (float) $schedule->courseSubject->units))
                && $this->hasUnassignedActiveEnrollmentsForSchedule($schedule, $enrollment)) {
                return true;
            }
        }

        return false;
    }

    public function hasUnassignedActiveEnrollmentsForSchedule(
        ClassSchedule $schedule,
        ?Enrollment $excludingEnrollment = null
    ): bool {
        $curriculumSubjectIds = CourseSubjectEquivalency::query()
            ->where('equivalent_subject_id', $schedule->course_subject_id)
            ->pluck('source_subject_id')
            ->push((int) $schedule->course_subject_id)
            ->unique()
            ->all();

        $query = Enrollment::query()
            ->where('school_year', $schedule->school_year)
            ->where('semester', $schedule->semester)
            ->whereIn('status', ['pending', 'approved'])
            ->when($excludingEnrollment, fn ($query) => $query->where('id', '!=', $excludingEnrollment->id))
            ->where(function ($query) use ($curriculumSubjectIds) {
                foreach ($curriculumSubjectIds as $subjectId) {
                    $query->orWhere(fn ($query) => $query
                        ->whereJsonContains('subject_ids', (int) $subjectId)
                        ->whereDoesntHave('subjectOfferings', fn ($offerings) => $offerings
                            ->where('curriculum_subject_id', $subjectId)));
                }
            });

        return $query->exists();
    }

    public function equivalencyFor(CourseSubject $original, CourseSubject $replacement): ?CourseSubjectEquivalency
    {
        if ((int) $original->id === (int) $replacement->id) {
            return null;
        }

        return CourseSubjectEquivalency::query()
            ->where('source_subject_id', $original->id)
            ->where('equivalent_subject_id', $replacement->id)
            ->first();
    }

    public function assertOfferingEligible(
        Enrollment $enrollment,
        EnrollmentReplacementItem $item,
        ClassSchedule $schedule
    ): ?CourseSubjectEquivalency {
        if ((int) $schedule->id === (int) $item->original_schedule_id) {
            throw ValidationException::withMessages([
                'replacements' => 'The Registrar marked this section unavailable. Choose another eligible section.',
            ]);
        }

        $schedule->loadMissing('courseSubject', 'hostingProgram');
        $original = $item->originalSubject;
        $replacement = $schedule->courseSubject;
        $equivalency = $this->equivalencyFor($original, $replacement);

        if ((int) $original->program_id !== (int) $enrollment->program_id
            || ((int) $original->id !== (int) $replacement->id
                && (! in_array($original->classification, ['major', 'minor', 'general_education'], true)
                    || ! $equivalency
                    || (float) $original->units !== (float) $replacement->units))) {
            throw ValidationException::withMessages([
                'replacements' => 'The selected section is not the same subject and has no approved academic equivalency for this enrollment.',
            ]);
        }

        if ($equivalency && $equivalency->requires_irregular === null) {
            throw ValidationException::withMessages([
                'replacements' => 'The academic load treatment for this equivalency has not been configured.',
            ]);
        }

        if (! $schedule->is_active
            || ! $schedule->section_code
            || ! $schedule->capacity
            || (int) $schedule->semester !== (int) $enrollment->semester
            || $schedule->school_year !== $enrollment->school_year) {
            throw ValidationException::withMessages([
                'replacements' => 'The selected section is not open for this enrollment term.',
            ]);
        }

        $hostingProgramId = (int) ($schedule->hosting_program_id ?: $replacement->program_id);
        $isCrossProgramOffering = $hostingProgramId !== (int) $replacement->program_id;
        if ($isCrossProgramOffering && $schedule->requires_irregular === null) {
            throw ValidationException::withMessages([
                'replacements' => 'The Registrar has not configured whether this cross-program section is Regular or Irregular.',
            ]);
        }

        $this->assertCapacityAvailable($schedule, $enrollment);

        return $equivalency;
    }

    public function assertCapacityAvailable(ClassSchedule $schedule, Enrollment $enrollment): void
    {
        if ($this->hasUnassignedActiveEnrollmentsForSchedule($schedule, $enrollment)) {
            throw ValidationException::withMessages([
                'replacements' => 'Section capacity cannot be confirmed until the Registrar reconciles active enrollments without a recorded section assignment.',
            ]);
        }

        if ($this->remainingSeats($schedule, $enrollment) <= 0) {
            throw ValidationException::withMessages([
                'replacements' => 'The selected section has reached capacity. Choose another eligible section.',
            ]);
        }
    }

    public function remainingSeats(ClassSchedule $schedule, Enrollment $enrollment): int
    {
        if (! $schedule->capacity) {
            return 0;
        }

        $assignedSeats = EnrollmentSubjectOffering::query()
            ->where('class_schedule_id', $schedule->id)
            ->where('enrollment_id', '!=', $enrollment->id)
            ->whereHas('enrollment', fn ($query) => $query->whereIn('status', ['pending', 'approved']))
            // Submission holds the offering lock. Use a current read rather than a
            // repeatable-read snapshot created before another student committed.
            ->lockForUpdate()
            ->count();

        return max(0, (int) $schedule->capacity - $assignedSeats);
    }

    public function assertNoScheduleConflicts(Enrollment $enrollment, array $replacementSchedules, array $replacedSubjectIds): void
    {
        foreach ($replacementSchedules as $schedule) {
            if (! $this->scheduleDays($schedule->day_pattern)) {
                throw ValidationException::withMessages([
                    'replacements' => 'The selected section schedule cannot be validated. Contact the Registrar.',
                ]);
            }
        }

        $conflictCandidates = [];
        $remainingSubjectIds = collect($enrollment->subject_ids ?? [])
            ->map(fn ($id) => (int) $id)
            ->diff(array_map('intval', $replacedSubjectIds))
            ->values();

        foreach ($remainingSubjectIds as $subjectId) {
            $assigned = EnrollmentSubjectOffering::query()
                ->where('enrollment_id', $enrollment->id)
                ->where('curriculum_subject_id', $subjectId)
                ->with('classSchedule')
                ->first();

            if ($assigned) {
                $conflictCandidates[] = $assigned->classSchedule;

                continue;
            }

            $legacySchedules = ClassSchedule::query()
                ->where('course_subject_id', $subjectId)
                ->whereNull('section_code')
                ->get();
            foreach ($legacySchedules as $legacySchedule) {
                $conflictCandidates[] = $legacySchedule;
            }
        }

        foreach ($replacementSchedules as $replacementSchedule) {
            foreach ($conflictCandidates as $existingSchedule) {
                if ($existingSchedule && $this->schedulesOverlap($replacementSchedule, $existingSchedule)) {
                    throw ValidationException::withMessages([
                        'replacements' => 'The selected section conflicts with another subject in this enrollment.',
                    ]);
                }
            }
        }

        for ($index = 0; $index < count($replacementSchedules); $index++) {
            for ($otherIndex = $index + 1; $otherIndex < count($replacementSchedules); $otherIndex++) {
                if ($this->schedulesOverlap($replacementSchedules[$index], $replacementSchedules[$otherIndex])) {
                    throw ValidationException::withMessages([
                        'replacements' => 'The selected replacement sections conflict with each other.',
                    ]);
                }
            }
        }
    }

    public function enrollmentRequiresIrregular(Enrollment $enrollment, array $excludingSubjectIds = []): bool
    {
        $assignments = $enrollment->subjectOfferings()
            ->whereIn('curriculum_subject_id', $enrollment->subject_ids ?? [])
            ->whereNotIn('curriculum_subject_id', $excludingSubjectIds)
            ->with(['curriculumSubject', 'classSchedule.courseSubject'])
            ->get();

        foreach ($assignments as $assignment) {
            $schedule = $assignment->classSchedule;
            $equivalency = $this->equivalencyFor($assignment->curriculumSubject, $schedule->courseSubject);
            if ($this->requiresIrregular([$schedule], [$equivalency])) {
                return true;
            }
        }

        return false;
    }

    public function requiresIrregular(array $schedules, array $equivalencies): bool
    {
        foreach ($schedules as $index => $schedule) {
            $equivalency = $equivalencies[$index] ?? null;
            if ($equivalency && $equivalency->requires_irregular) {
                return true;
            }

            $hostingProgramId = (int) ($schedule->hosting_program_id ?: $schedule->courseSubject->program_id);
            if ($hostingProgramId !== (int) $schedule->courseSubject->program_id
                && $schedule->requires_irregular) {
                return true;
            }
        }

        return false;
    }

    private function schedulesOverlap(ClassSchedule $first, ClassSchedule $second): bool
    {
        $firstDays = $this->scheduleDays($first->day_pattern);
        $secondDays = $this->scheduleDays($second->day_pattern);

        if (! $firstDays || ! $secondDays) {
            throw ValidationException::withMessages([
                'replacements' => 'A selected class schedule cannot be validated. Contact the Registrar.',
            ]);
        }

        if (! array_intersect($firstDays, $secondDays)) {
            return false;
        }

        return (string) $first->time_start < (string) $second->time_end
            && (string) $second->time_start < (string) $first->time_end;
    }

    private function scheduleDays(string $pattern): array
    {
        $pattern = strtoupper(preg_replace('/[^A-Z]/', '', $pattern));
        $pattern = str_replace(
            ['MONDAY', 'TUESDAY', 'WEDNESDAY', 'THURSDAY', 'FRIDAY', 'SATURDAY', 'SUNDAY'],
            ['MO', 'TU', 'WE', 'TH', 'FR', 'SA', 'SU'],
            $pattern
        );

        preg_match_all('/MO|TU|WE|TH|FR|SA|SU|M|T|W|R|F/', $pattern, $matches);

        if (preg_replace('/MO|TU|WE|TH|FR|SA|SU|M|T|W|R|F/', '', $pattern) !== '') {
            return [];
        }

        return array_values(array_unique(array_map(
            fn ($day) => ['M' => 'MO', 'T' => 'TU', 'W' => 'WE', 'R' => 'TH', 'F' => 'FR'][$day] ?? $day,
            $matches[0]
        )));
    }
}
