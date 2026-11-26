<?php

namespace Tests\Feature;

use App\Models\ClassSchedule;
use App\Models\CourseSubject;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\TimetableDraft;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\SeedsRoles;
use Tests\TestCase;

class EnrollmentAutomaticSectionsTest extends TestCase
{
    use RefreshDatabase, SeedsRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        Setting::set('current_school_year', '2026-2027');
        Setting::set('current_semester', '1st');
        Setting::set('enrollment_fee', 500);
        Storage::fake('local');
    }

    private function subjects()
    {
        $first = CourseSubject::factory()->create(['year_level' => 1, 'semester' => 1]);
        $second = CourseSubject::factory()->create(['program_id' => $first->program_id, 'year_level' => 1, 'semester' => 1]);

        return collect([$first, $second]);
    }

    private function group($subjects, string $code = 'BSTM 1A', int $capacity = 30, array $attributes = []): array
    {
        $group = TimetableDraft::create($attributes + ['program_id' => $subjects->first()->program_id,
            'year_level' => 1, 'semester' => 1, 'school_year' => '2026-2027', 'group_code' => $code,
            'capacity' => $capacity, 'created_by' => $this->makeUserWithRole('admin')->id,
            'status' => 'published', 'configuration' => []]);
        $schedules = collect();
        foreach ($subjects as $index => $subject) {
            $schedules->push(ClassSchedule::create(['timetable_draft_id' => $group->id,
                'course_subject_id' => $subject->id, 'hosting_program_id' => $subject->program_id,
                'section_code' => $code, 'school_year' => $group->school_year, 'semester' => $group->semester,
                'capacity' => $capacity, 'is_active' => true, 'requires_irregular' => false, 'day_pattern' => 'MWF',
                'time_start' => sprintf('%02d:00', 8 + $index), 'time_end' => sprintf('%02d:00', 9 + $index), 'room' => '1T']));
        }

        return [$group, $schedules];
    }

    private function submit($student, $subjects, array $extra = []): Enrollment
    {
        $this->actingAs($student)->post(route('portal.enrollment.store'), $extra + [
            'program_id' => $subjects->first()->program_id, 'year_level' => 1, 'semester' => 1,
            'subject_ids' => $subjects->pluck('id')->all(),
        ])->assertSessionHasNoErrors()->assertRedirect(route('portal.enrollment.index'));

        return Enrollment::where('user_id', $student->id)->latest('id')->firstOrFail();
    }

    public function test_submission_assigns_complete_published_group_without_approval_or_payment(): void
    {
        $subjects = $this->subjects();
        [$group, $schedules] = $this->group($subjects);
        $student = $this->makeUserWithRole('student');
        $enrollment = $this->submit($student, $subjects);
        $this->assertFalse($enrollment->requires_section_assignment);
        $this->assertFalse($enrollment->is_paid);
        $this->assertFalse($enrollment->is_irregular);
        $this->assertSame('pending', $enrollment->status);
        $this->assertSame($subjects->pluck('id')->all(), $enrollment->subject_ids);
        $this->assertSame($schedules->pluck('id')->all(), $enrollment->subjectOfferings()->orderBy('curriculum_subject_id')->pluck('class_schedule_id')->all());
        $this->assertSame(0, Payment::count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'enrollment.sections_assigned', 'user_id' => $student->id]);
        $this->actingAs($student)->get(route('portal.enrollment.index'))->assertOk()->assertSee('Assigned sections and timetable')->assertSee($group->group_code);
        $this->actingAs($student)->post(route('portal.enrollment.store'), ['program_id' => $subjects->first()->program_id,
            'year_level' => 1, 'semester' => 1, 'subject_ids' => $subjects->pluck('id')->all()])->assertRedirect(route('portal.dashboard'));
        $this->assertSame(1, Enrollment::where('user_id', $student->id)->count());
        $this->assertSame(2, $enrollment->subjectOfferings()->count());
    }

    public function test_full_first_group_is_skipped_without_splitting_the_load(): void
    {
        $subjects = $this->subjects();
        [, $first] = $this->group($subjects, 'BSTM 1A', 1);
        [, $second] = $this->group($subjects, 'BSTM 1B', 1);
        $occupant = Enrollment::factory()->pending()->create(['program_id' => $subjects->first()->program_id,
            'school_year' => '2026-2027', 'semester' => 1, 'subject_ids' => [$subjects->first()->id]]);
        $occupant->subjectOfferings()->create(['curriculum_subject_id' => $subjects->first()->id, 'class_schedule_id' => $first->first()->id]);
        $enrollment = $this->submit($this->makeUserWithRole('student'), $subjects, ['is_irregular' => 1]);
        $this->assertFalse($enrollment->requires_section_assignment);
        $this->assertTrue($enrollment->is_irregular);
        $this->assertSame($second->pluck('id')->all(), $enrollment->subjectOfferings()->orderBy('curriculum_subject_id')->pluck('class_schedule_id')->all());
    }

    public function test_no_complete_group_leaves_hold_visible_to_student_and_registrar(): void
    {
        $subjects = $this->subjects();
        $this->group($subjects->take(1));
        $student = $this->makeUserWithRole('student');
        $enrollment = $this->submit($student, $subjects);
        $this->assertTrue($enrollment->requires_section_assignment);
        $this->assertSame(0, $enrollment->subjectOfferings()->count());
        $this->get(route('portal.enrollment.index'))->assertOk()->assertSee('Wait for Registrar section assignment')->assertDontSee('Pay tuition balance');
        $this->get(route('portal.enrollment.payment-info', $enrollment))->assertOk()->assertSee('Sections needed before payment')->assertDontSee('name="proof_of_payment"', false);
        $this->get(route('portal.dashboard'))->assertOk()->assertSee('Wait for Section Assignment')->assertDontSee('Proceed to Payment');
        $registrar = $this->makeUserWithRole('registrar');
        $this->actingAs($registrar)->get(route('registrar.enrollments.index'))->assertOk()->assertSee('Sections needed')
            ->assertViewHas('enrollments', fn ($rows) => $rows->contains('id', $enrollment->id));
        $this->get(route('registrar.enrollments.show', $enrollment))->assertOk()->assertSee('Assign sections')->assertSee('name="section_choices['.$subjects->first()->id.']"', false)
            ->assertSee('rg-enrollment-summary')->assertSee('rg-enrollment-subject-row')
            ->assertSee('Assign sections before payment.')
            ->assertDontSee('Registrar review is available after the Cashier verifies the enrollment fee.')
            ->assertSee('form="approve-enrollment-'.$enrollment->id.'"', false);
    }

    public function test_hold_blocks_payment_upload_cash_collection_and_crafted_gcash_verification(): void
    {
        $subjects = $this->subjects();
        $student = $this->makeUserWithRole('student');
        $enrollment = $this->submit($student, $subjects);
        $this->post(route('portal.enrollment.payment.gcash', $enrollment), ['reference_number' => 'AUTO-HOLD-001',
            'proof_of_payment' => UploadedFile::fake()->image('proof.png')])->assertSessionHas('error');
        $this->assertSame([], Storage::disk('local')->allFiles());
        $cashier = $this->makeUserWithRole('cashier');
        $this->actingAs($cashier)->post(route('cashier.payments.store', $enrollment))->assertSessionHasErrors('payment');
        $this->assertSame(0, Payment::count());
        $payment = Payment::factory()->gcashPending()->create(['enrollment_id' => $enrollment->id]);
        $this->post(route('cashier.payments.verify', $payment))->assertStatus(422);
        $this->assertSame('pending', $payment->fresh()->status);
        $this->assertFalse($enrollment->fresh()->is_paid);
    }

    public function test_registrar_can_assign_before_payment_then_cashier_and_final_review_continue(): void
    {
        $subjects = $this->subjects();
        $student = $this->makeUserWithRole('student');
        $enrollment = $this->submit($student, $subjects);
        [, $schedules] = $this->group($subjects);
        $choices = $schedules->pluck('id', 'course_subject_id')->all();
        $registrar = $this->makeUserWithRole('registrar');
        $this->actingAs($registrar)->post(route('registrar.enrollments.approve', $enrollment), ['section_choices' => $choices])->assertSessionHasErrors('enrollment');
        $this->post(route('registrar.enrollments.approve', $enrollment), ['record_sections' => 1, 'section_choices' => $choices])->assertSessionHasNoErrors();
        $this->assertFalse($enrollment->fresh()->requires_section_assignment);
        $this->assertFalse($enrollment->fresh()->is_paid);
        $this->assertSame('pending', $enrollment->fresh()->status);
        $this->assertDatabaseMissing('student_profiles', ['user_id' => $student->id]);
        $this->post(route('registrar.enrollments.approve', $enrollment), ['record_sections' => 1, 'section_choices' => $choices])->assertSessionHasErrors('enrollment');
        $this->actingAs($this->makeUserWithRole('cashier'))->post(route('cashier.payments.store', $enrollment))->assertSessionHasNoErrors();
        $paymentBefore = $enrollment->payment()->firstOrFail()->getRawOriginal();
        $this->actingAs($registrar)->post(route('registrar.enrollments.approve', $enrollment))->assertSessionHasNoErrors();
        $this->assertSame('approved', $enrollment->fresh()->status);
        $this->assertSame($paymentBefore, $enrollment->payment()->firstOrFail()->getRawOriginal());
    }

    public function test_legacy_unknown_occupancy_blocks_auto_assignment_but_other_new_holds_do_not(): void
    {
        $subjects = $this->subjects();
        $firstHold = $this->submit($this->makeUserWithRole('student'), $subjects);
        $this->group($subjects);
        $assigned = $this->submit($this->makeUserWithRole('student'), $subjects);
        $this->assertFalse($assigned->requires_section_assignment);
        $legacy = Enrollment::factory()->pending()->create(['program_id' => $subjects->first()->program_id,
            'school_year' => '2026-2027', 'semester' => 1, 'subject_ids' => $subjects->pluck('id')->all()]);
        $this->assertFalse($legacy->requires_section_assignment);
        $blocked = $this->submit($this->makeUserWithRole('student'), $subjects);
        $this->assertTrue($blocked->requires_section_assignment);
        $this->assertTrue($firstHold->fresh()->requires_section_assignment);
    }

    public function test_drafts_demo_and_wrong_term_groups_never_assign_students(): void
    {
        $subjects = $this->subjects();
        $this->group($subjects, 'DRAFT', 30, ['status' => 'draft']);
        $this->group($subjects, 'DEMO', 30, ['is_demo' => true]);
        $this->group($subjects, 'OLD', 30, ['school_year' => '2025-2026']);
        $enrollment = $this->submit($this->makeUserWithRole('student'), $subjects);
        $this->assertTrue($enrollment->requires_section_assignment);
        $this->assertSame(0, $enrollment->subjectOfferings()->count());
    }

    public function test_failed_manual_assignment_keeps_hold_and_rolls_back_all_choices(): void
    {
        $subjects = $this->subjects();
        $enrollment = $this->submit($this->makeUserWithRole('student'), $subjects);
        [, $schedules] = $this->group($subjects);
        $schedules->last()->update(['is_active' => false]);
        $this->actingAs($this->makeUserWithRole('registrar'))->post(route('registrar.enrollments.approve', $enrollment),
            ['record_sections' => 1, 'section_choices' => $schedules->pluck('id', 'course_subject_id')->all()])->assertSessionHasErrors();
        $this->assertTrue($enrollment->fresh()->requires_section_assignment);
        $this->assertSame(0, $enrollment->subjectOfferings()->count());
        $this->assertSame(0, Payment::count());
    }

    public function test_section_hold_does_not_hide_a_separate_pending_refund(): void
    {
        $subjects = $this->subjects();
        $student = $this->makeUserWithRole('student');
        $old = Enrollment::factory()->create(['user_id' => $student->id, 'program_id' => $subjects->first()->program_id,
            'school_year' => '2026-2027', 'semester' => 1, 'status' => 'rejected', 'is_paid' => true]);
        $payment = Payment::factory()->create(['enrollment_id' => $old->id, 'status' => 'verified', 'refund_status' => 'pending']);
        $before = $payment->fresh()->getRawOriginal();
        $held = $this->submit($student, $subjects);
        $this->get(route('portal.enrollment.payment-info', $held))->assertOk()->assertSee('Sections needed before payment')
            ->assertSee('Payment is blocked pending reconciliation.')->assertDontSee('name="proof_of_payment"', false);
        $this->assertSame($before, $payment->fresh()->getRawOriginal());
    }
}
