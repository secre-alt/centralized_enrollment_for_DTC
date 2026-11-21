<?php

namespace Tests\Feature;

use App\Models\ClassSchedule;
use App\Models\CourseSubject;
use App\Models\CourseSubjectEquivalency;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\Program;
use App\Models\Setting;
use App\Models\StudentProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\SeedsRoles;
use Tests\TestCase;

class EnrollmentGapRegressionTest extends TestCase
{
    use RefreshDatabase, SeedsRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        Setting::set('current_school_year', '2026-2027');
        Setting::set('current_semester', '1st');
    }

    private function paid($student, $subjects)
    {
        $e = Enrollment::factory()->pending()->create(['user_id' => $student->id, 'program_id' => $subjects[0]->program_id, 'subject_ids' => array_map(fn ($s) => $s->id, $subjects), 'school_year' => '2026-2027', 'semester' => 1, 'is_paid' => true]);
        Payment::factory()->create(['enrollment_id' => $e->id]);

        return $e;
    }

    private function section($subject, $code, $day = 'MWF', $capacity = 40)
    {
        return ClassSchedule::create(['course_subject_id' => $subject->id, 'section_code' => $code, 'school_year' => '2026-2027', 'semester' => 1, 'capacity' => $capacity, 'is_active' => true, 'day_pattern' => $day, 'time_start' => '08:00:00', 'time_end' => '09:00:00']);
    }

    public function test_recovery_cannot_restore_a_second_active_enrollment_in_same_term()
    {
        $student = $this->makeUserWithRole('student');
        $s = CourseSubject::factory()->create();
        $old = $this->paid($student, [$s]);
        $old->update(['status' => 'rejected']);
        $old->payments()->update(['refund_status' => 'pending']);
        $fresh = Enrollment::factory()->pending()->create(['user_id' => $student->id, 'program_id' => $s->program_id, 'subject_ids' => [$s->id], 'school_year' => '2026-2027', 'semester' => 1]);
        $this->actingAs($this->makeUserWithRole('registrar'))->post(route('registrar.enrollments.subject-replacement', $old), ['affected_subject_ids' => [$s->id], 'reason' => 'Correct the rejection caused by full classes.', 'recover_rejection' => '1', 'confirm_no_refund' => '1'])->assertSessionHasErrors('enrollment');
        $this->assertSame('rejected', $old->fresh()->status);
        $this->assertSame('pending', $old->payments()->first()->refund_status);
        $this->assertSame(0, $old->replacementRequests()->count());
        $this->assertSame('pending', $fresh->fresh()->status);
    }

    public function test_two_students_can_reconcile_assignments_without_bypassing_approval_guard()
    {
        $s = CourseSubject::factory()->create();
        $first = $this->paid($this->makeUserWithRole('student'), [$s]);
        $second = $this->paid($this->makeUserWithRole('student'), [$s]);
        $section = $this->section($s, 'OPEN');
        $this->actingAs($this->makeUserWithRole('registrar'));
        $url = fn ($e) => route('registrar.enrollments.approve', $e);
        $choice = ['section_choices' => [$s->id => $section->id]];
        $this->post($url($first), $choice)->assertSessionHasErrors('replacements');
        $this->post($url($first), $choice + ['record_sections' => '1'])->assertSessionHasNoErrors();
        $this->assertSame('pending', $first->fresh()->status);
        $this->post($url($first))->assertSessionHasErrors('replacements');
        $this->post($url($second), $choice + ['record_sections' => '1'])->assertSessionHasNoErrors();
        foreach ([$first, $second] as $e) {
            $this->post($url($e))->assertSessionHasNoErrors();
            $this->assertSame('approved', $e->fresh()->status);
        } $this->assertDatabaseHas('audit_logs', ['action' => 'enrollment.sections_reconciled']);
    }

    public function test_reconciliation_cannot_overbook_or_bypass_roles()
    {
        $s = CourseSubject::factory()->create();
        $first = $this->paid($this->makeUserWithRole('student'), [$s]);
        $second = $this->paid($this->makeUserWithRole('student'), [$s]);
        $section = $this->section($s, 'LAST', 'MWF', 1);
        $payload = ['record_sections' => '1', 'section_choices' => [$s->id => $section->id]];
        $this->actingAs($this->makeUserWithRole('registrar'))->post(route('registrar.enrollments.approve', $first), $payload)->assertSessionHasNoErrors();
        $this->post(route('registrar.enrollments.approve', $second), $payload)->assertSessionHasErrors('section_choices');
        $this->assertSame(0, $second->subjectOfferings()->count());
        $this->actingAs($second->user)->post(route('registrar.enrollments.approve', $second), $payload)->assertForbidden();
    }

    public function test_returning_student_cannot_change_recorded_degree_through_enrollment()
    {
        $student = $this->makeUserWithRole('student');
        $official = Program::factory()->create();
        StudentProfile::create(['user_id' => $student->id, 'program_id' => $official->id, 'admission_type' => 'new_student']);
        $foreign = CourseSubject::factory()->create();
        $payload = ['program_id' => $foreign->program_id, 'year_level' => 1, 'semester' => 1, 'subject_ids' => [$foreign->id]];
        $this->actingAs($student)->post(route('portal.enrollment.store'), $payload)->assertSessionHasErrors('program_id');
        $this->assertSame(0, Enrollment::where('user_id', $student->id)->count());
        $home = CourseSubject::factory()->create(['program_id' => $official->id]);
        $this->post(route('portal.enrollment.store'), array_merge($payload, ['program_id' => $official->id, 'subject_ids' => [$home->id]]))->assertSessionHasNoErrors();
        $this->assertSame($official->id, $student->studentProfile->program_id);
    }

    public function test_paid_student_replaces_two_full_subjects_and_preserves_unaffected_load_and_receipt()
    {
        $student = $this->makeUserWithRole('student');
        $registrar = $this->makeUserWithRole('registrar');
        $program = Program::factory()->create();
        $major = CourseSubject::factory()->create(['program_id' => $program->id, 'classification' => 'major']);
        $ge = CourseSubject::factory()->create(['program_id' => $program->id, 'classification' => 'general_education']);
        $retained = CourseSubject::factory()->create(['program_id' => $program->id]);
        $equiv = CourseSubject::factory()->create(['units' => $ge->units]);
        $e = $this->paid($student, [$major, $ge, $retained]);
        $before = $e->payments()->first()->getRawOriginal();
        $a = $this->section($major, 'FULL-M', 'MWF');
        $b = $this->section($ge, 'FULL-G', 'TTH');
        $c = $this->section($retained, 'KEEP', 'SA');
        $ma = $this->section($major, 'NEW-M', 'MWF');
        $gb = $this->section($equiv, 'NEW-G', 'TTH');
        foreach ([[$major, $a], [$ge, $b], [$retained, $c]] as [$s,$off]) {
            $e->subjectOfferings()->create(['curriculum_subject_id' => $s->id, 'class_schedule_id' => $off->id]);
        }CourseSubjectEquivalency::create(['source_subject_id' => $ge->id, 'equivalent_subject_id' => $equiv->id, 'approved_by' => $registrar->id, 'approval_reason' => 'Official directed GE equivalency.', 'approved_at' => now(), 'requires_irregular' => true]);
        $this->actingAs($registrar)->post(route('registrar.enrollments.subject-replacement', $e), ['affected_subject_ids' => [$major->id, $ge->id], 'unavailable_schedule_ids' => [$major->id => $a->id, $ge->id => $b->id], 'reason' => 'Both selected sections have become unavailable.'])->assertSessionHasNoErrors();
        $case = $e->replacementRequests()->first();
        $items = $case->items->keyBy('original_subject_id');
        $this->actingAs($student)->get(route('portal.enrollment.revise', $e))->assertOk();
        $this->post(route('portal.enrollment.revise.submit', $e), ['confirm_revision' => '1', 'replacements' => [$items[$major->id]->id => $ma->id, $items[$ge->id]->id => $gb->id]])->assertSessionHasNoErrors();
        $this->assertSame('submitted', $case->fresh()->status);
        $this->assertSame($c->id, $e->subjectOfferings()->where('curriculum_subject_id', $retained->id)->value('class_schedule_id'));
        $this->assertSame([$major->id, $ge->id, $retained->id], $e->fresh()->subject_ids);
        $this->assertTrue($e->fresh()->is_irregular);
        $this->assertSame($program->id, $e->fresh()->program_id);
        $this->actingAs($registrar)->post(route('registrar.enrollments.approve', $e))->assertSessionHasNoErrors();
        $this->assertSame('approved', $e->fresh()->status);
        $this->assertSame('approved', $case->fresh()->status);
        $this->assertSame(1, $e->payments()->count());
        $this->assertSame($before, $e->payments()->first()->getRawOriginal());
    }
}
