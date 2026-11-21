<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\ClassSchedule;
use App\Models\Enrollment;
use App\Models\TimetableDraft;
use Illuminate\Validation\ValidationException;

class EnrollmentSectionAssignmentService
{
    /** Called inside the new-enrollment transaction, with bounded outer retries. */
    public function assignPublishedGroup(Enrollment $enrollment): ?TimetableDraft
    {
        if ($enrollment->status !== 'pending' || $enrollment->is_paid || ! $enrollment->requires_section_assignment
            || $enrollment->payments()->exists() || $enrollment->replacementRequests()->exists()
            || $enrollment->subjectOfferings()->exists()) {
            throw ValidationException::withMessages(['enrollment' => 'Automatic group assignment applies only to a new unpaid enrollment without recorded sections.']);
        }

        $groups = TimetableDraft::where('program_id', $enrollment->program_id)
            ->where('year_level', $enrollment->year_level)->where('school_year', $enrollment->school_year)
            ->where('semester', $enrollment->semester)->where('status', 'published')->where('is_demo', false)
            ->orderBy('id')->get();
        $subjectIds = array_map('intval', $enrollment->subject_ids ?? []);
        $validator = app(EnrollmentSubjectReplacementService::class);
        foreach ($groups as $group) {
            $schedules = ClassSchedule::where('timetable_draft_id', $group->id)
                ->whereIn('course_subject_id', $subjectIds)->orderBy('id')->get();
            if (count($subjectIds) !== $schedules->count()
                || $schedules->pluck('course_subject_id')->unique()->count() !== count($subjectIds)) {
                continue;
            }
            $choices = $schedules->pluck('id', 'course_subject_id')->all();
            try {
                // Reuses complete-load validation and current seat counts AFTER
                // offering locks. This writes nothing until every check passes.
                $validator->validateApprovalLoad($enrollment, $choices);
            } catch (ValidationException $exception) {
                continue;
            }
            $enrollment->requires_section_assignment = false;
            $enrollment->save();
            AuditLog::create([
                'user_id' => $enrollment->user_id, 'action' => 'enrollment.sections_assigned',
                'description' => 'Assigned published group '.$group->group_code.' to enrollment #'.$enrollment->id.'.',
                'metadata' => ['enrollment_id' => $enrollment->id, 'timetable_draft_id' => $group->id, 'assignments' => $choices],
            ]);

            return $group;
        }

        return null;
    }
}
