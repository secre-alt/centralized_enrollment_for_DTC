<?php

namespace Tests\Feature;

use App\Models\Program;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\SeedsRoles;
use Tests\TestCase;

class PublicApplicationBirthdateTest extends TestCase
{
    use \Tests\AdmissionTestPayload;
    use RefreshDatabase, SeedsRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        Mail::fake();
    }

    public function test_age_boundaries_are_accepted(): void
    {
        $program = Program::factory()->create();

        foreach ([\App\Support\AdmissionValidation::latestBirthdate(), \App\Support\AdmissionValidation::earliestBirthdate()] as $index => $birthdate) {
            $payload = $this->validPayload($program, 'valid-birth-' . $index . '@example.test', $birthdate);
            $this->submitWithFreshToken($payload)->assertRedirect(route('public.application.success'));
            $this->assertDatabaseHas('applications', ['email' => $payload['email'], 'birthdate' => $birthdate]);
        }
    }

    public function test_underage_and_implausibly_old_birthdates_are_rejected(): void
    {
        $program = Program::factory()->create();
        $dates = [today()->toDateString(), today()->subYearsNoOverflow(15)->addDay()->toDateString(), today()->subYearsNoOverflow(101)->toDateString(), '1600-01-01', '1800-01-01'];
        foreach ($dates as $index => $date) {
            $payload = $this->validPayload($program, 'invalid-age-' . $index . '@example.test', $date);
            $this->submitWithFreshToken($payload)->assertSessionHasErrors('birthdate');
            $this->assertDatabaseMissing('applications', ['email' => $payload['email']]);
        }
    }

    public function test_future_birthdate_is_rejected_with_clear_error_and_not_persisted(): void
    {
        $program = Program::factory()->create();
        $payload = $this->validPayload($program, 'future-birth@example.test', today()->addDay()->toDateString());

        $this->submitWithFreshToken($payload)
            ->assertRedirect(route('public.application.create'))
            ->assertSessionHasErrors(['birthdate' => 'Enter a date of birth that is today or earlier.']);

        $this->assertDatabaseMissing('applications', ['email' => $payload['email']]);
    }

    public function test_malformed_birthdate_is_rejected_and_not_persisted(): void
    {
        $program = Program::factory()->create();
        $payload = $this->validPayload($program, 'malformed-birth@example.test', 'not-a-date');

        $this->submitWithFreshToken($payload)
            ->assertRedirect(route('public.application.create'))
            ->assertSessionHasErrors('birthdate');

        $this->assertDatabaseMissing('applications', ['email' => $payload['email']]);
    }

    private function submitWithFreshToken(array $payload)
    {
        $this->get(route('public.application.create'))->assertOk();
        $payload['submission_token'] = session('public_application_submission_token');

        return $this->from(route('public.application.create'))->post(route('public.application.store'), $this->admissionPayload($payload));
    }

    private function validPayload(Program $program, string $email, string $birthdate): array
    {
        return [
            'academic_status' => 'new_student',
            'program_id' => $program->id,
            'first_name' => 'Birth',
            'last_name' => 'Date',
            'gender' => 'female',
            'birthdate' => $birthdate,
            'email' => $email,
            'ack_enrollment' => 'on',
        ];
    }
}
