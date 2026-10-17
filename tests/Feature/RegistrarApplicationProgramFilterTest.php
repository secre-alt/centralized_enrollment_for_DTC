<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Program;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\SeedsRoles;
use Tests\TestCase;

class RegistrarApplicationProgramFilterTest extends TestCase
{
    use RefreshDatabase, SeedsRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    public function test_registrar_can_filter_applications_by_program(): void
    {
        $registrar = $this->makeUserWithRole('registrar');
        $program = Program::factory()->create(['name' => 'Applied Computing']);
        $otherProgram = Program::factory()->create(['name' => 'Business Management']);
        $matching = $this->makeApplication($program, ['reference_no' => 'APP-PROGRAM-MATCH']);
        $other = $this->makeApplication($otherProgram, ['reference_no' => 'APP-PROGRAM-OTHER']);

        $this->actingAs($registrar)
            ->get(route('registrar.applications.index', ['program_id' => $program->id]))
            ->assertOk()
            ->assertSee($matching->reference_no)
            ->assertSee('Applied Computing')
            ->assertDontSee($other->reference_no)
            ->assertSee('value="' . $program->id . '" selected', false);
    }

    public function test_program_filter_combines_with_keyword_type_and_status_filters(): void
    {
        $registrar = $this->makeUserWithRole('registrar');
        $program = Program::factory()->create(['name' => 'Information Systems']);
        $otherProgram = Program::factory()->create(['name' => 'Office Administration']);

        $matching = $this->makeApplication($program, [
            'reference_no' => 'APP-COMBINED-YES',
            'first_name' => 'Jordan',
            'academic_status' => 'transferee',
            'status' => 'under_review',
        ]);
        $this->makeApplication($program, [
            'reference_no' => 'APP-COMBINED-WRONG-STATUS',
            'first_name' => 'Jordan',
            'academic_status' => 'transferee',
            'status' => 'submitted',
        ]);
        $this->makeApplication($program, [
            'reference_no' => 'APP-COMBINED-WRONG-KEYWORD',
            'first_name' => 'Taylor',
            'academic_status' => 'transferee',
            'status' => 'under_review',
        ]);
        $this->makeApplication($otherProgram, [
            'reference_no' => 'APP-COMBINED-WRONG-PROGRAM',
            'first_name' => 'Jordan',
            'academic_status' => 'transferee',
            'status' => 'under_review',
        ]);

        $this->actingAs($registrar)
            ->get(route('registrar.applications.index', [
                'search' => 'Jordan',
                'type' => 'transferee',
                'status' => 'under_review',
                'program_id' => $program->id,
            ]))
            ->assertOk()
            ->assertSee($matching->reference_no)
            ->assertDontSee('APP-COMBINED-WRONG-STATUS')
            ->assertDontSee('APP-COMBINED-WRONG-KEYWORD')
            ->assertDontSee('APP-COMBINED-WRONG-PROGRAM');
    }

    public function test_program_filter_preserves_keyword_search_for_zero(): void
    {
        $registrar = $this->makeUserWithRole('registrar');
        $program = Program::factory()->create();
        $matching = $this->makeApplication($program, [
            'reference_no' => 'APP-ZERO-KEYWORD',
            'first_name' => 'Student 0',
        ]);
        $other = $this->makeApplication($program, [
            'reference_no' => 'APP-NO-MATCH',
            'first_name' => 'Student One',
        ]);

        $this->actingAs($registrar)
            ->get(route('registrar.applications.index', [
                'search' => '0',
                'program_id' => $program->id,
            ]))
            ->assertOk()
            ->assertSee($matching->reference_no)
            ->assertDontSee($other->reference_no);
    }

    public function test_program_filter_is_preserved_across_server_pagination(): void
    {
        $registrar = $this->makeUserWithRole('registrar');
        $program = Program::factory()->create();
        $otherProgram = Program::factory()->create();

        for ($index = 1; $index <= 12; $index++) {
            $application = $this->makeApplication($program, [
                'reference_no' => sprintf('APP-PAGE-%02d', $index),
            ]);
            $application->created_at = now()->addSeconds($index);
            $application->save();
        }

        $other = $this->makeApplication($otherProgram, ['reference_no' => 'APP-PAGE-OTHER']);

        $this->actingAs($registrar)
            ->get(route('registrar.applications.index', ['program_id' => $program->id, 'page' => 2]))
            ->assertOk()
            ->assertSee('12 total')
            ->assertSee('APP-PAGE-02')
            ->assertDontSee($other->reference_no)
            ->assertSee('program_id=' . $program->id);
    }

    public function test_empty_program_results_show_the_existing_empty_state(): void
    {
        $registrar = $this->makeUserWithRole('registrar');
        $program = Program::factory()->create();

        $this->actingAs($registrar)
            ->get(route('registrar.applications.index', ['program_id' => $program->id]))
            ->assertOk()
            ->assertSee('0 total')
            ->assertSee('No applications match your filters.');
    }

    public function test_invalid_program_filter_is_rejected(): void
    {
        $registrar = $this->makeUserWithRole('registrar');

        $this->actingAs($registrar)
            ->get(route('registrar.applications.index', ['program_id' => 'not-a-program']))
            ->assertSessionHasErrors('program_id');
    }

    public function test_applications_filter_remains_restricted_to_registrar_and_admin_roles(): void
    {
        $this->get(route('registrar.applications.index'))
            ->assertRedirect(route('login'));

        $student = $this->makeUserWithRole('student');
        $this->actingAs($student)
            ->get(route('registrar.applications.index'))
            ->assertForbidden();
    }

    private function makeApplication(Program $program, array $attributes = []): Application
    {
        static $sequence = 0;
        $sequence++;

        return Application::create(array_merge([
            'reference_no' => 'APP-FILTER-' . $sequence,
            'program_id' => $program->id,
            'academic_status' => 'new_student',
            'first_name' => 'Applicant',
            'last_name' => 'Example',
            'gender' => 'female',
            'birthdate' => '2005-01-01',
            'email' => 'applicant-' . $sequence . '@example.test',
            'status' => 'submitted',
        ], $attributes));
    }
}
