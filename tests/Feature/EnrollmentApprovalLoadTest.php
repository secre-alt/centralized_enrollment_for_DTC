<?php

namespace Tests\Feature;

use App\Models\ClassSchedule;
use App\Models\CourseSubject;
use App\Models\CourseSubjectEquivalency;
use App\Models\Enrollment;
use App\Models\EnrollmentSubjectOffering;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\SeedsRoles;
use Tests\TestCase;

class EnrollmentApprovalLoadTest extends TestCase
{
    use RefreshDatabase, SeedsRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->actingAs($this->makeUserWithRole('registrar'));
    }

    private function load(): array
    {
        $subject = CourseSubject::factory()->create(['classification' => 'unclassified']);
        $enrollment = Enrollment::factory()->pending()->create([
            'user_id' => $this->makeUserWithRole('student')->id, 'program_id' => $subject->program_id,
            'subject_ids' => [$subject->id], 'school_year' => '2026-2027', 'semester' => 1, 'is_paid' => true,
        ]);
        $payment = Payment::factory()->create(['enrollment_id' => $enrollment->id]);
        $section = ClassSchedule::create([
            'course_subject_id' => $subject->id, 'section_code' => 'A', 'school_year' => '2026-2027',
            'semester' => 1, 'capacity' => 1, 'is_active' => true, 'day_pattern' => 'MWF',
            'time_start' => '08:00:00', 'time_end' => '09:00:00',
        ]);

        return [$enrollment, $subject, $section, $payment];
    }

    public function test_same_subject_approval_needs_no_classification_or_equivalency_and_preserves_payment(): void
    {
        [$enrollment, $subject, $section, $payment] = $this->load();
        $before = $payment->fresh()->getRawOriginal();
        $this->get(route('registrar.enrollments.show', $enrollment))->assertOk()
            ->assertSee('Academic equivalencies')->assertSee('Optional')->assertDontSee('Classify '.$subject->subject_code.' in subject management');
        $this->post(route('registrar.enrollments.approve', $enrollment), ['section_choices' => [$subject->id => $section->id]])
            ->assertSessionHasNoErrors()->assertRedirect(route('registrar.enrollments.index'));
        $this->assertSame('approved', $enrollment->fresh()->status);
        $this->assertFalse($enrollment->fresh()->is_irregular);
        $this->assertSame($before, $payment->fresh()->getRawOriginal());
        $this->assertSame(1, $enrollment->payments()->count());
        $this->assertDatabaseHas('enrollment_subject_offerings', ['enrollment_id' => $enrollment->id, 'class_schedule_id' => $section->id]);
        $this->post(route('registrar.enrollments.approve', $enrollment))->assertSessionHasErrors('enrollment');
    }

    public function test_missing_section_and_unknown_active_assignments_fail_without_creating_assignments(): void
    {
        [$enrollment, $subject, $section] = $this->load();
        $this->post(route('registrar.enrollments.approve', $enrollment))->assertSessionHasErrors('enrollment');
        Enrollment::factory()->pending()->create(['program_id' => $subject->program_id, 'subject_ids' => [$subject->id], 'school_year' => $enrollment->school_year, 'semester' => 1]);
        $this->post(route('registrar.enrollments.approve', $enrollment), ['section_choices' => [$subject->id => $section->id]])->assertSessionHasErrors('replacements');
        $this->assertSame('pending', $enrollment->fresh()->status);
        $this->assertSame(0, $enrollment->subjectOfferings()->count());
    }

    public function test_invalid_term_activity_capacity_and_schedule_are_rechecked_at_approval(): void
    {
        [$enrollment, $subject, $section] = $this->load();
        $original = $section->getAttributes();
        foreach ([['is_active' => false], ['school_year' => '2025-2026'], ['semester' => 2], ['capacity' => null], ['day_pattern' => 'UNKNOWN'], ['time_end' => '07:00:00']] as $invalid) {
            $section->setRawAttributes($original);
            $section->fill($invalid)->save();
            $this->post(route('registrar.enrollments.approve', $enrollment), ['section_choices' => [$subject->id => $section->id]])->assertSessionHasErrors();
            $this->assertSame('pending', $enrollment->fresh()->status);
            $this->assertSame(0, $enrollment->subjectOfferings()->count());
        }
    }

    public function test_full_section_conflicting_complete_load_and_assignment_rewrite_roll_back(): void
    {
        [$enrollment, $subject, $section] = $this->load();
        $other = Enrollment::factory()->create(['program_id' => $subject->program_id, 'subject_ids' => [$subject->id], 'school_year' => $enrollment->school_year, 'semester' => 1]);
        $other->subjectOfferings()->create(['curriculum_subject_id' => $subject->id, 'class_schedule_id' => $section->id]);
        $this->post(route('registrar.enrollments.approve', $enrollment), ['section_choices' => [$subject->id => $section->id]])->assertSessionHasErrors('replacements');
        $other->update(['status' => 'rejected']);
        $second = CourseSubject::factory()->create(['program_id' => $subject->program_id]);
        $secondSection = $section->replicate();
        $secondSection->fill(['course_subject_id' => $second->id, 'section_code' => 'B'])->save();
        $enrollment->update(['subject_ids' => [$subject->id, $second->id]]);
        $this->post(route('registrar.enrollments.approve', $enrollment), ['section_choices' => [$subject->id => $section->id, $second->id => $secondSection->id]])->assertSessionHasErrors('replacements');
        $this->assertSame(0, $enrollment->subjectOfferings()->count());
        $enrollment->update(['subject_ids' => [$subject->id]]);
        $enrollment->subjectOfferings()->create(['curriculum_subject_id' => $subject->id, 'class_schedule_id' => $section->id]);
        $this->post(route('registrar.enrollments.approve', $enrollment), ['section_choices' => [$subject->id => $secondSection->id]])->assertSessionHasErrors('enrollment');
        $this->assertSame($section->id, $enrollment->subjectOfferings()->first()->class_schedule_id);
    }

    public function test_equivalency_targets_match_units_are_directed_and_apply_recorded_load_treatment(): void
    {
        [$enrollment, $subject, $section] = $this->load();
        $subject->update(['classification' => 'major']);
        $target = CourseSubject::factory()->create(['units' => 4]);
        $url = route('registrar.subjects.equivalencies.store', $subject);
        $payload = ['equivalent_subject_id' => $target->id, 'requires_irregular' => '1', 'approval_reason' => 'Official academic approval.'];
        $this->post($url, $payload)->assertSessionHasErrors('equivalent_subject_id');
        $this->assertSame(0, CourseSubjectEquivalency::count());
        $target->update(['units' => $subject->units]);
        $this->post($url, $payload)->assertSessionHasNoErrors();
        $this->post($url, $payload)->assertSessionHasErrors('equivalent_subject_id');
        $this->assertSame(1, CourseSubjectEquivalency::count());
        $this->assertDatabaseMissing('course_subject_equivalencies', ['source_subject_id' => $target->id, 'equivalent_subject_id' => $subject->id]);
        $this->get(route('registrar.enrollments.show', $enrollment))->assertOk()->assertDontSee('<option value="'.$target->id.'">', false);
        $section->update(['course_subject_id' => $target->id]);
        $enrollment->subjectOfferings()->create(['curriculum_subject_id' => $subject->id, 'class_schedule_id' => $section->id]);
        $this->post(route('registrar.enrollments.approve', $enrollment))->assertSessionHasNoErrors();
        $this->assertTrue($enrollment->fresh()->is_irregular);
        $this->assertSame($subject->program_id, $enrollment->fresh()->program_id);
    }

    public function test_latest_payment_and_role_checks_cannot_be_bypassed(): void
    {
        [$enrollment, $subject, $section] = $this->load();
        Payment::factory()->gcashPending()->create(['enrollment_id' => $enrollment->id]);
        $this->post(route('registrar.enrollments.approve', $enrollment), ['section_choices' => [$subject->id => $section->id]])->assertSessionHasErrors('enrollment');
        $this->actingAs($enrollment->user)->post(route('registrar.enrollments.approve', $enrollment))->assertForbidden();
        $this->post(route('registrar.subjects.equivalencies.store', $subject), [])->assertForbidden();
    }
}
