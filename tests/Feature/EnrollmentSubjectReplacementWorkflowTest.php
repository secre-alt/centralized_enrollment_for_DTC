<?php

namespace Tests\Feature;

use App\Models\ClassSchedule;
use App\Models\CourseSubject;
use App\Models\CourseSubjectEquivalency;
use App\Models\Enrollment;
use App\Models\EnrollmentReplacementRequest;
use App\Models\EnrollmentSubjectOffering;
use App\Models\Payment;
use App\Models\Program;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\SeedsRoles;
use Tests\TestCase;

class EnrollmentSubjectReplacementWorkflowTest extends TestCase
{
    use RefreshDatabase, SeedsRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        Setting::set('current_school_year', '2026-2027');
        Setting::set('current_semester', '1st');
    }

    public function test_workflow_tests_use_the_isolated_testing_database(): void
    {
        $this->assertSame('testing', app()->environment());
        $this->assertSame('dtc_testing', DB::selectOne('SELECT DATABASE() AS db_name')->db_name);
    }

    private function paidEnrollment($student, Program $program, CourseSubject $subject): array
    {
        $enrollment = Enrollment::factory()->pending()->create([
            'user_id' => $student->id,
            'program_id' => $program->id,
            'school_year' => '2026-2027',
            'semester' => 1,
            'subject_ids' => [$subject->id],
            'is_paid' => true,
        ]);
        $payment = Payment::factory()->create([
            'enrollment_id' => $enrollment->id,
            'status' => 'verified',
            'refund_status' => 'not_required',
            'amount' => 500,
        ]);

        return [$enrollment, $payment];
    }

    private function section(CourseSubject $subject, string $code, int $capacity = 10, array $overrides = []): ClassSchedule
    {
        return ClassSchedule::create(array_merge([
            'course_subject_id' => $subject->id,
            'section_code' => $code,
            'school_year' => '2026-2027',
            'semester' => 1,
            'capacity' => $capacity,
            'is_active' => true,
            'day_pattern' => 'MWF',
            'time_start' => '08:00:00',
            'time_end' => '09:00:00',
            'room' => 'A101',
            'instructor_name' => 'Instructor',
        ], $overrides));
    }

    private function returnForReplacement($registrar, Enrollment $enrollment, CourseSubject $subject, ?ClassSchedule $unavailable = null): EnrollmentReplacementRequest
    {
        $response = $this->actingAs($registrar)->post(
            route('registrar.enrollments.subject-replacement', $enrollment),
            [
                'affected_subject_ids' => [$subject->id],
                'unavailable_schedule_ids' => $unavailable
                    ? [$subject->id => $unavailable->id]
                    : [],
                'reason' => 'The selected class section is full.',
            ]
        );
        $response->assertRedirect(route('registrar.enrollments.show', $enrollment));

        return EnrollmentReplacementRequest::where('enrollment_id', $enrollment->id)->latest('id')->firstOrFail();
    }

    public function test_same_subject_section_revision_preserves_payment_program_and_regular_status(): void
    {
        $student = $this->makeUserWithRole('student');
        $registrar = $this->makeUserWithRole('registrar');
        $program = Program::factory()->create();
        $subject = CourseSubject::factory()->for($program)->create(['classification' => 'major']);
        [$enrollment, $payment] = $this->paidEnrollment($student, $program, $subject);
        $fullSection = $this->section($subject, 'A', 1);
        $replacementSection = $this->section($subject, 'B', 2, ['day_pattern' => 'TTH']);
        EnrollmentSubjectOffering::create([
            'enrollment_id' => $enrollment->id,
            'curriculum_subject_id' => $subject->id,
            'class_schedule_id' => $fullSection->id,
        ]);

        $case = $this->returnForReplacement($registrar, $enrollment, $subject, $fullSection);
        $this->assertSame('pending', $enrollment->fresh()->status);
        $this->assertSame('verified', $payment->fresh()->status);
        $this->assertSame('not_required', $payment->fresh()->refund_status);

        $this->actingAs($student)
            ->get(route('portal.enrollment.revise', $enrollment))
            ->assertOk()
            ->assertSee('Subject Replacement Required')
            ->assertSee('Registrar marked Section A unavailable.')
            ->assertSee('Section B');

        $this->post(route('portal.enrollment.revise.submit', $enrollment), [
            'confirm_revision' => '1',
            'replacements' => [$case->items->first()->id => $replacementSection->id],
        ])->assertRedirect(route('portal.enrollment.index'));

        $this->assertSame('submitted', $case->fresh()->status);
        $this->assertSame('pending', $enrollment->fresh()->status);
        $this->assertSame($program->id, $enrollment->fresh()->program_id);
        $this->assertFalse($enrollment->fresh()->is_irregular);
        $this->assertSame([$subject->id], $enrollment->fresh()->subject_ids);
        $this->assertSame('verified', $payment->fresh()->status);
        $this->assertSame(1, Payment::where('enrollment_id', $enrollment->id)->count());
        $this->assertSame(1, Enrollment::where('user_id', $student->id)->count());
        $this->assertDatabaseHas('enrollment_subject_offerings', [
            'enrollment_id' => $enrollment->id,
            'curriculum_subject_id' => $subject->id,
            'class_schedule_id' => $replacementSection->id,
        ]);

        $this->actingAs($registrar)
            ->post(route('registrar.enrollments.approve', $enrollment))
            ->assertRedirect(route('registrar.enrollments.index'));
        $this->assertSame('approved', $enrollment->fresh()->status);
        $this->assertSame('approved', $case->fresh()->status);
        $this->assertSame('verified', $payment->fresh()->status);
    }

    public function test_approved_major_equivalency_keeps_degree_program_and_uses_approved_irregular_policy(): void
    {
        $student = $this->makeUserWithRole('student');
        $registrar = $this->makeUserWithRole('registrar');
        $program = Program::factory()->create();
        $hostingProgram = Program::factory()->create();
        $major = CourseSubject::factory()->for($program)->create(['classification' => 'major']);
        $equivalent = CourseSubject::factory()->for($hostingProgram)->create(['classification' => 'major']);
        [$enrollment, $payment] = $this->paidEnrollment($student, $program, $major);
        $offering = $this->section($equivalent, 'CROSS-1');
        CourseSubjectEquivalency::create([
            'source_subject_id' => $major->id,
            'equivalent_subject_id' => $equivalent->id,
            'approved_by' => $registrar->id,
            'approval_reason' => 'Approved cross-program minor equivalency.',
            'requires_irregular' => true,
            'approved_at' => now(),
        ]);

        $case = $this->returnForReplacement($registrar, $enrollment, $major);
        $this->actingAs($student)->post(route('portal.enrollment.revise.submit', $enrollment), [
            'confirm_revision' => '1',
            'replacements' => [$case->items->first()->id => $offering->id],
        ])->assertRedirect(route('portal.enrollment.index'));

        $this->assertSame($program->id, $enrollment->fresh()->program_id);
        $this->assertTrue($enrollment->fresh()->is_irregular);
        $this->assertSame('verified', $payment->fresh()->status);
        $this->assertSame($offering->id, $case->fresh()->items->first()->selected_schedule_id);
    }

    public function test_same_minor_subject_hosted_by_another_program_can_remain_regular_when_policy_says_so(): void
    {
        $student = $this->makeUserWithRole('student');
        $registrar = $this->makeUserWithRole('registrar');
        $program = Program::factory()->create();
        $hostProgram = Program::factory()->create();
        $minor = CourseSubject::factory()->for($program)->create(['classification' => 'minor']);
        [$enrollment] = $this->paidEnrollment($student, $program, $minor);
        $original = $this->section($minor, 'HOME', 1);
        $crossProgramSection = $this->section($minor, 'SHARED', 10, [
            'hosting_program_id' => $hostProgram->id,
            'requires_irregular' => false,
            'day_pattern' => 'TTH',
        ]);
        $occupant = Enrollment::factory()->pending()->create([
            'program_id' => $program->id,
            'school_year' => '2026-2027',
            'semester' => 1,
            'subject_ids' => [$minor->id],
        ]);
        EnrollmentSubjectOffering::create([
            'enrollment_id' => $enrollment->id,
            'curriculum_subject_id' => $minor->id,
            'class_schedule_id' => $original->id,
        ]);
        EnrollmentSubjectOffering::create([
            'enrollment_id' => $occupant->id,
            'curriculum_subject_id' => $minor->id,
            'class_schedule_id' => $original->id,
        ]);
        $case = $this->returnForReplacement($registrar, $enrollment, $minor, $original);

        $this->actingAs($student)
            ->get(route('portal.enrollment.revise', $enrollment))
            ->assertOk()
            ->assertSee($hostProgram->name)
            ->assertSee('Section SHARED');

        $this->post(route('portal.enrollment.revise.submit', $enrollment), [
            'confirm_revision' => '1',
            'replacements' => [$case->items->first()->id => $crossProgramSection->id],
        ])->assertRedirect(route('portal.enrollment.index'));

        $this->assertSame($program->id, $enrollment->fresh()->program_id);
        $this->assertFalse($enrollment->fresh()->is_irregular);
        $this->assertSame($minor->id, $case->fresh()->items->first()->original_subject_id);
        $this->assertSame($crossProgramSection->id, $case->fresh()->items->first()->selected_schedule_id);
    }

    public function test_irregular_status_returns_to_its_baseline_after_a_later_regular_revision(): void
    {
        $student = $this->makeUserWithRole('student');
        $registrar = $this->makeUserWithRole('registrar');
        $program = Program::factory()->create();
        $hostProgram = Program::factory()->create();
        $minor = CourseSubject::factory()->for($program)->create(['classification' => 'minor']);
        [$enrollment] = $this->paidEnrollment($student, $program, $minor);
        $crossProgramSection = $this->section($minor, 'CROSS-IRREGULAR', 5, [
            'hosting_program_id' => $hostProgram->id,
            'requires_irregular' => true,
        ]);
        $homeSection = $this->section($minor, 'HOME-REGULAR', 5, [
            'day_pattern' => 'TTH',
        ]);

        $firstCase = $this->returnForReplacement($registrar, $enrollment, $minor);
        $this->actingAs($student)->post(route('portal.enrollment.revise.submit', $enrollment), [
            'confirm_revision' => '1',
            'replacements' => [$firstCase->items->first()->id => $crossProgramSection->id],
        ])->assertRedirect(route('portal.enrollment.index'));
        $this->assertTrue($enrollment->fresh()->is_irregular);

        $secondCase = $this->returnForReplacement($registrar, $enrollment, $minor, $crossProgramSection);
        $this->assertFalse($secondCase->baseline_is_irregular);
        $this->actingAs($student)->post(route('portal.enrollment.revise.submit', $enrollment), [
            'confirm_revision' => '1',
            'replacements' => [$secondCase->items->first()->id => $homeSection->id],
        ])->assertRedirect(route('portal.enrollment.index'));

        $this->assertFalse($enrollment->fresh()->is_irregular);
        $this->assertSame($program->id, $enrollment->fresh()->program_id);
    }

    public function test_revision_requires_explicit_confirmation_before_any_assignment_changes(): void
    {
        $student = $this->makeUserWithRole('student');
        $registrar = $this->makeUserWithRole('registrar');
        $program = Program::factory()->create();
        $subject = CourseSubject::factory()->for($program)->create(['classification' => 'minor']);
        [$enrollment, $payment] = $this->paidEnrollment($student, $program, $subject);
        $offering = $this->section($subject, 'OPEN', 10);
        $case = $this->returnForReplacement($registrar, $enrollment, $subject);
        $this->actingAs($student)->get(route('portal.enrollment.revise', $enrollment))->assertOk()
            ->assertSee('Review your revised load')->assertSee('confirm_revision')->assertSee('enrollment-revision.js');
        foreach ([null, '0'] as $confirmation) {
            $this->post(route('portal.enrollment.revise.submit', $enrollment), [
                'replacements' => [$case->items->first()->id => $offering->id],
                'confirm_revision' => $confirmation,
            ])->assertSessionHasErrors('confirm_revision');
        }
        $this->assertSame('open', $case->fresh()->status);
        $this->assertSame(0, $enrollment->subjectOfferings()->count());
        $this->assertSame('verified', $payment->fresh()->status);
    }

    public function test_equivalent_replacement_cannot_duplicate_an_unaffected_subject(): void
    {
        $student = $this->makeUserWithRole('student');
        $registrar = $this->makeUserWithRole('registrar');
        $program = Program::factory()->create();
        $subject = CourseSubject::factory()->for($program)->create(['classification' => 'minor']);
        $other = CourseSubject::factory()->for($program)->create(['units' => $subject->units]);
        [$enrollment] = $this->paidEnrollment($student, $program, $subject);
        $enrollment->update(['subject_ids' => [$subject->id, $other->id]]);
        CourseSubjectEquivalency::create([
            'source_subject_id' => $subject->id, 'equivalent_subject_id' => $other->id,
            'approved_by' => $registrar->id, 'approved_at' => now(), 'requires_irregular' => false,
            'approval_reason' => 'Approved directed equivalency.',
        ]);
        $offering = $this->section($other, 'DUPLICATE', 10);
        $case = $this->returnForReplacement($registrar, $enrollment, $subject);
        $this->actingAs($student)->post(route('portal.enrollment.revise.submit', $enrollment), [
            'confirm_revision' => '1', 'replacements' => [$case->items->first()->id => $offering->id],
        ])->assertSessionHasErrors('replacements');
        $this->assertSame('open', $case->fresh()->status);
        $this->assertSame(0, $enrollment->subjectOfferings()->count());
    }

    public function test_multiple_affected_subjects_are_resubmitted_atomically_without_another_payment(): void
    {
        $student = $this->makeUserWithRole('student');
        $registrar = $this->makeUserWithRole('registrar');
        $program = Program::factory()->create();
        $first = CourseSubject::factory()->for($program)->create(['classification' => 'minor']);
        $second = CourseSubject::factory()->for($program)->create(['classification' => 'major']);
        [$enrollment, $payment] = $this->paidEnrollment($student, $program, $first);
        $enrollment->update(['subject_ids' => [$first->id, $second->id]]);
        $firstSection = $this->section($first, 'FIRST', 10);
        $secondSection = $this->section($second, 'SECOND', 10, ['day_pattern' => 'TTH', 'is_active' => false]);
        $this->actingAs($registrar)->post(route('registrar.enrollments.subject-replacement', $enrollment), [
            'affected_subject_ids' => [$first->id, $second->id], 'reason' => 'Both original sections are unavailable.',
        ])->assertSessionHasNoErrors();
        $case = EnrollmentReplacementRequest::where('enrollment_id', $enrollment->id)->latest('id')->firstOrFail();
        $choices = $case->items->mapWithKeys(fn ($item) => [$item->id => $item->original_subject_id === $first->id ? $firstSection->id : $secondSection->id])->all();
        $payload = ['confirm_revision' => '1', 'replacements' => $choices];
        $this->actingAs($student)->post(route('portal.enrollment.revise.submit', $enrollment), $payload)
            ->assertSessionHasErrors('replacements');
        $this->assertSame(0, $enrollment->subjectOfferings()->count());
        $this->assertSame(0, $case->items()->whereNotNull('selected_schedule_id')->count());
        $this->assertSame('open', $case->fresh()->status);
        $secondSection->update(['is_active' => true]);
        $this->post(route('portal.enrollment.revise.submit', $enrollment), $payload)
            ->assertSessionHasNoErrors()->assertRedirect(route('portal.enrollment.index'));
        $this->assertSame(2, $enrollment->subjectOfferings()->count());
        $this->assertSame('submitted', $case->fresh()->status);
        $this->assertSame('verified', $payment->fresh()->status);
        $this->assertSame(1, $enrollment->payments()->count());
        $this->assertSame(1, Enrollment::where('user_id', $student->id)->count());
    }

    public function test_direct_submission_cannot_reselect_the_section_marked_unavailable(): void
    {
        $student = $this->makeUserWithRole('student');
        $registrar = $this->makeUserWithRole('registrar');
        $program = Program::factory()->create();
        $subject = CourseSubject::factory()->for($program)->create(['classification' => 'minor']);
        [$enrollment, $payment] = $this->paidEnrollment($student, $program, $subject);
        $unavailable = $this->section($subject, 'UNAVAILABLE', 10);
        $case = $this->returnForReplacement($registrar, $enrollment, $subject, $unavailable);
        $this->actingAs($student)->post(route('portal.enrollment.revise.submit', $enrollment), [
            'confirm_revision' => '1',
            'replacements' => [$case->items->first()->id => $unavailable->id],
        ])->assertSessionHasErrors('replacements');
        $this->assertSame('open', $case->fresh()->status);
        $this->assertNull($case->items->first()->fresh()->selected_schedule_id);
        $this->assertSame(0, $enrollment->subjectOfferings()->count());
        $this->assertSame('verified', $payment->fresh()->status);
    }

    public function test_revising_another_subject_preserves_irregular_treatment_from_the_final_load(): void
    {
        foreach (['offering', 'equivalency', 'baseline'] as $policy) {
            $student = $this->makeUserWithRole('student');
            $registrar = $this->makeUserWithRole('registrar');
            $program = Program::factory()->create();
            $host = Program::factory()->create();
            $first = CourseSubject::factory()->for($program)->create(['classification' => 'minor']);
            $second = CourseSubject::factory()->for($program)->create(['classification' => 'major']);
            [$enrollment, $payment] = $this->paidEnrollment($student, $program, $first);
            $enrollment->update(['subject_ids' => [$first->id, $second->id], 'is_irregular' => $policy === 'baseline']);
            $offeredSubject = $first;
            if ($policy === 'equivalency') {
                $offeredSubject = CourseSubject::factory()->for($host)->create(['units' => $first->units]);
                CourseSubjectEquivalency::create([
                    'source_subject_id' => $first->id, 'equivalent_subject_id' => $offeredSubject->id,
                    'approved_by' => $registrar->id, 'approved_at' => now(),
                    'approval_reason' => 'Approved equivalent with irregular load treatment.', 'requires_irregular' => true,
                ]);
            }
            $firstSection = $this->section($offeredSubject, 'FIRST', 10, $policy === 'offering'
                ? ['hosting_program_id' => $host->id, 'requires_irregular' => true] : []);
            $home = $this->section($second, 'HOME', 10, ['day_pattern' => 'TTH']);
            $case = $this->returnForReplacement($registrar, $enrollment, $first);
            $this->actingAs($student)->post(route('portal.enrollment.revise.submit', $enrollment), [
                'confirm_revision' => '1',
                'replacements' => [$case->items->first()->id => $firstSection->id],
            ])->assertSessionHasNoErrors()->assertRedirect(route('portal.enrollment.index'));
            $this->assertTrue($enrollment->fresh()->is_irregular);
            $case = $this->returnForReplacement($registrar, $enrollment, $second);
            $this->actingAs($student)->post(route('portal.enrollment.revise.submit', $enrollment), [
                'confirm_revision' => '1',
                'replacements' => [$case->items->first()->id => $home->id],
            ])->assertSessionHasNoErrors()->assertRedirect(route('portal.enrollment.index'));
            $this->assertTrue($enrollment->fresh()->is_irregular);
            $this->assertSame($firstSection->id, $enrollment->subjectOfferings()->where('curriculum_subject_id', $first->id)->value('class_schedule_id'));
            $this->assertSame($program->id, $enrollment->fresh()->program_id);
            $this->assertSame('verified', $payment->fresh()->status);
            $this->assertSame('not_required', $payment->fresh()->refund_status);
        }
    }

    public function test_unassigned_enrollments_from_another_term_do_not_reduce_current_section_capacity(): void
    {
        $student = $this->makeUserWithRole('student');
        $registrar = $this->makeUserWithRole('registrar');
        $program = Program::factory()->create();
        $subject = CourseSubject::factory()->for($program)->create(['classification' => 'minor']);
        [$enrollment] = $this->paidEnrollment($student, $program, $subject);
        $section = $this->section($subject, 'CURRENT', 2);

        Enrollment::factory()->create([
            'program_id' => $program->id,
            'school_year' => '2025-2026',
            'semester' => 1,
            'subject_ids' => [$subject->id],
            'status' => 'approved',
        ]);

        $case = $this->returnForReplacement($registrar, $enrollment, $subject);
        $this->actingAs($student)
            ->get(route('portal.enrollment.revise', $enrollment))
            ->assertOk()
            ->assertSee('Section CURRENT')
            ->assertSee('2 seat(s) available');

        $this->assertSame(2, app(\App\Services\EnrollmentSubjectReplacementService::class)
            ->remainingSeats($section, $enrollment));
        $this->assertFalse(app(\App\Services\EnrollmentSubjectReplacementService::class)
            ->requiresCapacityReconciliation($enrollment, $case->items->first()));
    }

    public function test_unknown_active_section_assignments_are_reconciled_without_counting_them_against_every_section(): void
    {
        $student = $this->makeUserWithRole('student');
        $otherStudent = $this->makeUserWithRole('student');
        $registrar = $this->makeUserWithRole('registrar');
        $program = Program::factory()->create();
        $subject = CourseSubject::factory()->for($program)->create(['classification' => 'minor']);
        [$enrollment] = $this->paidEnrollment($student, $program, $subject);
        $section = $this->section($subject, 'RECONCILE', 5);
        Enrollment::factory()->pending()->create([
            'user_id' => $otherStudent->id,
            'program_id' => $program->id,
            'school_year' => '2026-2027',
            'semester' => 1,
            'subject_ids' => [$subject->id],
        ]);

        $case = $this->returnForReplacement($registrar, $enrollment, $subject);
        $this->actingAs($student)
            ->get(route('portal.enrollment.revise', $enrollment))
            ->assertOk()
            ->assertSee('must reconcile those records')
            ->assertDontSee('Section RECONCILE');

        $this->assertSame(5, app(\App\Services\EnrollmentSubjectReplacementService::class)
            ->remainingSeats($section, $enrollment));
        $this->assertTrue(app(\App\Services\EnrollmentSubjectReplacementService::class)
            ->requiresCapacityReconciliation($enrollment, $case->items->first()));

        $this->from(route('portal.enrollment.revise', $enrollment))
            ->actingAs($student)
            ->post(route('portal.enrollment.revise.submit', $enrollment), [
                'confirm_revision' => '1',
                'replacements' => [$case->items->first()->id => $section->id],
            ])
            ->assertSessionHasErrors([
                'replacements' => 'Section capacity cannot be confirmed until the Registrar reconciles active enrollments without a recorded section assignment.',
            ]);
    }

    public function test_section_capacity_cannot_be_reduced_while_active_assignments_are_unknown(): void
    {
        $admin = $this->makeUserWithRole('admin');
        $program = Program::factory()->create();
        $subject = CourseSubject::factory()->for($program)->create(['classification' => 'minor']);
        $section = $this->section($subject, 'LOCKED', 5);
        Enrollment::factory()->pending()->create([
            'program_id' => $program->id,
            'school_year' => '2026-2027',
            'semester' => 1,
            'subject_ids' => [$subject->id],
        ]);

        $this->actingAs($admin)
            ->from(route('admin.programs.index', ['program' => $program->id]))
            ->post(route('admin.programs.subjects.schedule.store', $subject), [
                'section_code' => 'LOCKED',
                'hosting_program_id' => $program->id,
                'capacity' => 4,
                'is_active' => 1,
                'day_pattern' => 'MWF',
                'time_start' => '08:00',
                'time_end' => '09:00',
                'room' => 'A101',
                'instructor_name' => 'Instructor',
            ])
            ->assertSessionHasErrors('capacity');

        $this->assertSame(5, $section->fresh()->capacity);
    }

    public function test_approved_enrollment_and_payment_cannot_be_changed_by_replacement_actions(): void
    {
        $student = $this->makeUserWithRole('student');
        $program = Program::factory()->create();
        $subject = CourseSubject::factory()->for($program)->create();
        $enrollment = Enrollment::factory()->create([
            'user_id' => $student->id,
            'program_id' => $program->id,
            'school_year' => '2026-2027',
            'semester' => 1,
            'subject_ids' => [$subject->id],
            'is_paid' => true,
            'status' => 'approved',
        ]);
        $payment = Payment::factory()->create([
            'enrollment_id' => $enrollment->id,
            'status' => 'verified',
            'refund_status' => 'not_required',
        ]);
        $request = EnrollmentReplacementRequest::create([
            'enrollment_id' => $enrollment->id,
            'requested_by' => $student->id,
            'status' => 'open',
            'reason' => 'Stale request used to verify approval guards.',
        ]);
        $item = $request->items()->create(['original_subject_id' => $subject->id]);
        $section = $this->section($subject, 'APPROVED');

        $this->actingAs($student)
            ->get(route('portal.enrollment.revise', $enrollment))
            ->assertNotFound();
        $this->post(route('portal.enrollment.revise.submit', $enrollment), [
            'confirm_revision' => '1',
            'replacements' => [$item->id => $section->id],
        ])->assertNotFound();

        $this->assertSame('approved', $enrollment->fresh()->status);
        $this->assertSame([$subject->id], $enrollment->fresh()->subject_ids);
        $this->assertSame('verified', $payment->fresh()->status);
        $this->assertDatabaseMissing('enrollment_subject_offerings', [
            'enrollment_id' => $enrollment->id,
            'curriculum_subject_id' => $subject->id,
            'class_schedule_id' => $section->id,
        ]);
    }

    public function test_unapproved_cross_program_subject_is_rejected_server_side(): void
    {
        $student = $this->makeUserWithRole('student');
        $registrar = $this->makeUserWithRole('registrar');
        $program = Program::factory()->create();
        $otherProgram = Program::factory()->create();
        $major = CourseSubject::factory()->for($program)->create(['classification' => 'major']);
        $unapproved = CourseSubject::factory()->for($otherProgram)->create(['classification' => 'major']);
        [$enrollment, $payment] = $this->paidEnrollment($student, $program, $major);
        $offering = $this->section($unapproved, 'OTHER-1');
        $case = $this->returnForReplacement($registrar, $enrollment, $major);

        $this->actingAs($student)->from(route('portal.enrollment.revise', $enrollment))
            ->post(route('portal.enrollment.revise.submit', $enrollment), [
                'confirm_revision' => '1',
                'replacements' => [$case->items->first()->id => $offering->id],
            ])
            ->assertSessionHasErrors('replacements');

        $this->assertSame('open', $case->fresh()->status);
        $this->assertSame('verified', $payment->fresh()->status);
        $this->assertSame(0, EnrollmentSubjectOffering::where('enrollment_id', $enrollment->id)->count());
    }

    public function test_student_refund_request_stays_pending_until_cashier_records_actual_refund(): void
    {
        $student = $this->makeUserWithRole('student');
        $registrar = $this->makeUserWithRole('registrar');
        $cashier = $this->makeUserWithRole('cashier');
        $program = Program::factory()->create();
        $subject = CourseSubject::factory()->for($program)->create();
        [$enrollment, $payment] = $this->paidEnrollment($student, $program, $subject);
        $case = $this->returnForReplacement($registrar, $enrollment, $subject);

        $this->actingAs($student)->post(route('portal.enrollment.replacement.refund', $case), [
            'reason' => 'I cannot continue this term.',
            'confirm_refund' => '1',
        ])->assertRedirect(route('portal.enrollment.index'));

        $this->assertSame('rejected', $enrollment->fresh()->status);
        $this->assertSame('pending', $payment->fresh()->refund_status);
        $this->assertSame('refund_requested', $case->fresh()->status);

        $this->actingAs($cashier)->post(route('cashier.payments.confirm-refund', $payment), [
            'refund_amount' => '500.00',
            'refund_reference' => 'MANUAL-REFUND-1',
        ])->assertRedirect(route('cashier.payments.show', $enrollment));

        $this->assertSame('refunded', $payment->fresh()->refund_status);
        $this->assertSame('refunded', $case->fresh()->status);
        $this->assertSame('verified', $payment->fresh()->status);

        $this->from(route('cashier.payments.show', $enrollment))
            ->post(route('cashier.payments.confirm-refund', $payment), [
                'refund_amount' => '500.00',
                'refund_reference' => 'MANUAL-REFUND-1',
            ])
            ->assertSessionHasErrors('refund_reference');
        $this->assertSame(1, Payment::where('enrollment_id', $enrollment->id)
            ->where('refund_status', 'refunded')
            ->count());
    }

    public function test_non_owner_cannot_open_another_students_replacement_page(): void
    {
        $student = $this->makeUserWithRole('student');
        $otherStudent = $this->makeUserWithRole('student');
        $registrar = $this->makeUserWithRole('registrar');
        $program = Program::factory()->create();
        $subject = CourseSubject::factory()->for($program)->create();
        [$enrollment] = $this->paidEnrollment($student, $program, $subject);
        $this->section($subject, 'OPEN-1');
        $this->returnForReplacement($registrar, $enrollment, $subject);

        $this->actingAs($otherStudent)
            ->get(route('portal.enrollment.revise', $enrollment))
            ->assertForbidden();
    }

    public function test_inactive_or_full_offerings_are_not_offered_or_accepted(): void
    {
        $student = $this->makeUserWithRole('student');
        $registrar = $this->makeUserWithRole('registrar');
        $program = Program::factory()->create();
        $subject = CourseSubject::factory()->for($program)->create();
        [$enrollment] = $this->paidEnrollment($student, $program, $subject);
        $full = $this->section($subject, 'FULL', 1);
        $inactive = $this->section($subject, 'CLOSED', 3, ['is_active' => false]);
        $occupant = Enrollment::factory()->pending()->create([
            'program_id' => $program->id,
            'school_year' => '2026-2027',
            'semester' => 1,
            'subject_ids' => [$subject->id],
        ]);
        EnrollmentSubjectOffering::create([
            'enrollment_id' => $occupant->id,
            'curriculum_subject_id' => $subject->id,
            'class_schedule_id' => $full->id,
        ]);
        $case = $this->returnForReplacement($registrar, $enrollment, $subject);

        $this->actingAs($student)
            ->get(route('portal.enrollment.revise', $enrollment))
            ->assertOk()
            ->assertDontSee('FULL')
            ->assertDontSee('CLOSED');

        foreach ([$full, $inactive] as $offering) {
            $this->from(route('portal.enrollment.revise', $enrollment))
                ->post(route('portal.enrollment.revise.submit', $enrollment), [
                    'confirm_revision' => '1',
                    'replacements' => [$case->items->first()->id => $offering->id],
                ])
                ->assertSessionHasErrors('replacements');
        }
    }

    public function test_two_students_cannot_claim_the_same_final_section_seat(): void
    {
        $registrar = $this->makeUserWithRole('registrar');
        $program = Program::factory()->create();
        $subject = CourseSubject::factory()->for($program)->create(['classification' => 'minor']);
        $finalSeat = $this->section($subject, 'LAST', 1);
        $firstStudent = $this->makeUserWithRole('student');
        [$firstEnrollment] = $this->paidEnrollment($firstStudent, $program, $subject);
        $firstCase = $this->returnForReplacement($registrar, $firstEnrollment, $subject);

        $this->actingAs($firstStudent)->post(route('portal.enrollment.revise.submit', $firstEnrollment), [
            'confirm_revision' => '1',
            'replacements' => [$firstCase->items->first()->id => $finalSeat->id],
        ])->assertRedirect(route('portal.enrollment.index'));

        $secondStudent = $this->makeUserWithRole('student');
        [$secondEnrollment] = $this->paidEnrollment($secondStudent, $program, $subject);
        $secondCase = $this->returnForReplacement($registrar, $secondEnrollment, $subject);
        $this->actingAs($secondStudent)
            ->from(route('portal.enrollment.revise', $secondEnrollment))
            ->post(route('portal.enrollment.revise.submit', $secondEnrollment), [
                'confirm_revision' => '1',
                'replacements' => [$secondCase->items->first()->id => $finalSeat->id],
            ])
            ->assertSessionHasErrors('replacements');

        $this->assertSame(1, EnrollmentSubjectOffering::where('class_schedule_id', $finalSeat->id)->count());
    }

    public function test_replacement_section_cannot_conflict_with_an_unaffected_subject(): void
    {
        $student = $this->makeUserWithRole('student');
        $registrar = $this->makeUserWithRole('registrar');
        $program = Program::factory()->create();
        $affectedSubject = CourseSubject::factory()->for($program)->create(['classification' => 'minor']);
        $unaffectedSubject = CourseSubject::factory()->for($program)->create(['classification' => 'major']);
        $fullSection = $this->section($affectedSubject, 'FULL', 1);
        $replacement = $this->section($affectedSubject, 'CONFLICT', 2);
        $unaffectedSection = $this->section($unaffectedSubject, 'OTHER', 20);
        $enrollment = Enrollment::factory()->pending()->create([
            'user_id' => $student->id,
            'program_id' => $program->id,
            'school_year' => '2026-2027',
            'semester' => 1,
            'subject_ids' => [$affectedSubject->id, $unaffectedSubject->id],
            'is_paid' => true,
        ]);
        Payment::factory()->create([
            'enrollment_id' => $enrollment->id,
            'status' => 'verified',
            'refund_status' => 'not_required',
        ]);
        EnrollmentSubjectOffering::create([
            'enrollment_id' => $enrollment->id,
            'curriculum_subject_id' => $affectedSubject->id,
            'class_schedule_id' => $fullSection->id,
        ]);
        EnrollmentSubjectOffering::create([
            'enrollment_id' => $enrollment->id,
            'curriculum_subject_id' => $unaffectedSubject->id,
            'class_schedule_id' => $unaffectedSection->id,
        ]);
        $case = $this->returnForReplacement($registrar, $enrollment, $affectedSubject, $fullSection);

        $this->actingAs($student)
            ->from(route('portal.enrollment.revise', $enrollment))
            ->post(route('portal.enrollment.revise.submit', $enrollment), [
                'confirm_revision' => '1',
                'replacements' => [$case->items->first()->id => $replacement->id],
            ])
            ->assertSessionHasErrors('replacements');

        $this->assertSame($fullSection->id, $enrollment->subjectOfferings()
            ->where('curriculum_subject_id', $affectedSubject->id)
            ->value('class_schedule_id'));
    }
}
