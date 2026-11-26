<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Enrollment;
use App\Models\Program;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\SeedsRoles;
use Tests\TestCase;

class EnrollmentFormRecoveryTest extends TestCase
{
    use RefreshDatabase, SeedsRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        Setting::set('current_school_year', '2026-2027');
        Setting::set('current_semester', '2nd');
    }

    public function test_form_uses_current_term_even_after_stale_input(): void
    {
        $student = $this->makeUserWithRole('student');
        $this->actingAs($student)->withSession(['_old_input' => ['semester' => 1]])
            ->get(route('portal.enrollment.create'))->assertOk()
            ->assertSee('2026-2027')->assertSee('2nd Semester')
            ->assertSee('name="semester" id="semester" value="2"', false)
            ->assertDontSee('<select name="semester"', false);
    }

    public function test_missing_term_disables_submission_without_guessing_a_semester(): void
    {
        Setting::set('current_semester', '');
        $this->actingAs($this->makeUserWithRole('student'))
            ->get(route('portal.enrollment.create'))->assertOk()
            ->assertSee('The current academic year and semester are not configured.')
            ->assertSee('type="submit" class="btn btn-primary flex-fill" disabled', false);
    }

    public function test_invalid_submission_returns_all_errors_and_subject_choices_to_the_form(): void
    {
        $student = $this->makeUserWithRole('student');
        $program = Program::factory()->create();
        $this->actingAs($student)->from(route('portal.enrollment.create'))
            ->post(route('portal.enrollment.store'), ['program_id' => $program->id,
                'year_level' => 9, 'semester' => 9, 'is_irregular' => 1, 'subject_ids' => [123]])
            ->assertRedirect(route('portal.enrollment.create'))
            ->assertSessionHasErrors(['year_level', 'semester']);
        $response = $this->get(route('portal.enrollment.create'))->assertOk()
            ->assertSee('data-selected-subjects="[123]"', false)
            ->assertSee('name="semester" id="semester" value="2"', false);
        $html = $response->getContent();
        $this->assertMatchesRegularExpression('/name="is_irregular" value="1"[^>]*checked/', $html);
        $this->assertGreaterThanOrEqual(3, substr_count($html, 'class="text-danger" role="alert"'));
    }

    public function test_applicant_overview_uses_the_same_next_action_for_section_hold_and_replacement(): void
    {
        $applicant = $this->makeUserWithRole('new_applicant');
        $program = Program::factory()->create();
        Application::create(['reference_no' => 'APP-RECOVERY', 'program_id' => $program->id,
            'user_id' => $applicant->id, 'first_name' => 'Test', 'last_name' => 'Applicant',
            'email' => $applicant->email, 'status' => 'approved']);
        $enrollment = Enrollment::factory()->pending()->create(['user_id' => $applicant->id,
            'program_id' => $program->id, 'school_year' => '2026-2027', 'semester' => 2,
            'requires_section_assignment' => true]);
        $response = $this->actingAs($applicant)->get(route('portal.dashboard'))->assertOk();
        $response->assertDontSee('Awaiting Payment');
        $response->assertSee('<strong>Wait for Section Assignment</strong>', false);

        $enrollment->update(['requires_section_assignment' => false, 'is_paid' => true]);
        $enrollment->replacementRequests()->create(['requested_by' => $applicant->id,
            'status' => 'open', 'reason' => 'Choose another section.']);
        $this->get(route('portal.dashboard'))->assertOk()
            ->assertSee('<strong>Subject Replacement Required</strong>', false)
            ->assertDontSee('Awaiting Registrar Review');
    }
}
