<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Program;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\SeedsRoles;
use Tests\TestCase;

class RegistrarApplicationDuplicateReviewTest extends TestCase
{
    use RefreshDatabase, SeedsRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    public function test_possible_match_requires_same_normalized_name_birthdate_and_contact_and_is_staff_review_only(): void
    {
        $registrar = $this->makeUserWithRole('registrar');
        $program = Program::factory()->create();
        $application = $this->makeApplication($program, 'APP-DUP-A', [
            'first_name' => '  Jane ', 'middle_name' => 'Marie', 'last_name' => 'Doe',
            'birthdate' => '2006-03-04', 'email' => 'jane@example.test', 'phone' => '(555) 123-4567',
        ]);
        $match = $this->makeApplication($program, 'APP-DUP-B', [
            'first_name' => 'jane', 'middle_name' => 'marie', 'last_name' => 'DOE',
            'birthdate' => '2006-03-04', 'email' => 'JANE@example.test', 'phone' => '5551234567',
        ]);
        $this->makeApplication($program, 'APP-DUP-NAME-ONLY', [
            'first_name' => 'Jane', 'middle_name' => 'Marie', 'last_name' => 'Doe',
            'birthdate' => '2006-03-04', 'email' => 'different@example.test', 'phone' => '5559990000',
        ]);
        $this->makeApplication($program, 'APP-DUP-DATE-DIFF', [
            'first_name' => 'Jane', 'middle_name' => 'Marie', 'last_name' => 'Doe',
            'birthdate' => '2006-03-05', 'email' => 'jane@example.test', 'phone' => '5551234567',
        ]);

        $this->actingAs($registrar)
            ->get(route('registrar.applications.index'))
            ->assertOk()
            ->assertSee('Possible duplicate')
            ->assertSee('name="duplicate_review"', false)
            ->assertSee('Needs review');

        $this->get(route('registrar.applications.index', ['duplicate_review' => 'pending']))
            ->assertOk()
            ->assertSee($application->reference_no)
            ->assertSee($match->reference_no)
            ->assertDontSee('APP-DUP-NAME-ONLY')
            ->assertDontSee('APP-DUP-DATE-DIFF');

        $this->get(route('registrar.applications.show', $application))
            ->assertOk()
            ->assertSee($match->reference_no)
            ->assertSee('Same normalized name, Same birthdate, Same email, Same phone')
            ->assertDontSee('APP-DUP-NAME-ONLY')
            ->assertDontSee('APP-DUP-DATE-DIFF');

        $this->post(route('registrar.applications.duplicate-review', $application), ['outcome' => 'confirmed_duplicate'])
            ->assertRedirect(route('registrar.applications.show', $application))
            ->assertSessionHas('success');

        $this->assertSame('submitted', $application->fresh()->status);
        $this->assertSame('submitted', $match->fresh()->status);
        $this->assertDatabaseHas('applications', [
            'id' => $application->id,
            'duplicate_review_outcome' => 'confirmed_duplicate',
            'duplicate_reviewed_by' => $registrar->id,
        ]);
        $this->assertNotNull($application->fresh()->duplicate_reviewed_at);

        $this->get(route('registrar.applications.index', ['duplicate_review' => 'reviewed']))
            ->assertOk()
            ->assertSee($application->reference_no)
            ->assertDontSee($match->reference_no);
        $this->get(route('registrar.applications.index', ['duplicate_review' => 'pending']))
            ->assertOk()
            ->assertSee($match->reference_no)
            ->assertDontSee($application->reference_no);
    }

    public function test_registrar_can_mark_pair_not_duplicate_and_other_roles_cannot_review_it(): void
    {
        $program = Program::factory()->create();
        $application = $this->makeApplication($program, 'APP-DUP-C', [
            'first_name' => 'Alex', 'last_name' => 'Smith', 'birthdate' => '2005-01-01', 'email' => 'same@example.test',
        ]);
        $this->makeApplication($program, 'APP-DUP-D', [
            'first_name' => 'Alex', 'last_name' => 'Smith', 'birthdate' => '2005-01-01', 'email' => 'same@example.test',
        ]);

        $this->actingAs($this->makeUserWithRole('cashier'))
            ->post(route('registrar.applications.duplicate-review', $application), ['outcome' => 'confirmed_duplicate'])
            ->assertForbidden();

        $registrar = $this->makeUserWithRole('registrar');
        $this->actingAs($registrar)
            ->post(route('registrar.applications.duplicate-review', $application), ['outcome' => 'not_duplicate'])
            ->assertRedirect(route('registrar.applications.show', $application));

        $this->assertDatabaseHas('applications', [
            'id' => $application->id,
            'duplicate_review_outcome' => 'not_duplicate',
            'duplicate_reviewed_by' => $registrar->id,
        ]);
        $this->assertSame('submitted', $application->fresh()->status);
        $this->actingAs($registrar)
            ->get(route('registrar.applications.index', ['duplicate_review' => 'reviewed']))
            ->assertOk()
            ->assertSee($application->reference_no);
    }

    private function makeApplication(Program $program, string $reference, array $attributes = []): Application
    {
        return Application::create(array_merge([
            'reference_no' => $reference,
            'program_id' => $program->id,
            'academic_status' => 'new_student',
            'first_name' => 'Test',
            'last_name' => 'Applicant',
            'gender' => 'female',
            'birthdate' => '2005-01-01',
            'email' => strtolower($reference).'@example.test',
            'status' => 'submitted',
        ], $attributes));
    }
}
