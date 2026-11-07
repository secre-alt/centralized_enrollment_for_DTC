<?php

namespace Tests\Feature;

use App\Models\Program;
use App\Support\AdmissionValidation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;
use Tests\SeedsRoles;
use Tests\AdmissionTestPayload;

class AdmissionRequirementsTest extends TestCase
{
    use RefreshDatabase, SeedsRoles, AdmissionTestPayload;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        Mail::fake();
        config(['services.application_submission.per_ip_per_hour' => 1000]);
    }

    private function submit(array $overrides = [])
    {
        $this->get(route('public.application.create'))->assertOk();
        $payload = $this->admissionPayload(array_merge([
            'academic_status' => 'new_student', 'program_id' => Program::factory()->create()->id,
            'first_name' => 'Test', 'last_name' => 'Applicant', 'gender' => 'female',
            'birthdate' => '2005-01-01', 'email' => uniqid('requirements') . '@example.test',
            'submission_token' => session('public_application_submission_token'), 'ack_enrollment' => 'on',
        ], $overrides));
        return $this->from(route('public.application.create'))->post(route('public.application.store'), $payload);
    }

    public function test_address_minimums_support_manual_independent_and_international_addresses(): void
    {
        foreach (['country', 'current_address', 'city'] as $field) {
            $this->submit([$field => ''])->assertSessionHasErrors($field);
        }
        $this->submit(['region' => '', 'province' => '', 'barangay' => '', 'postal_code' => '', 'phone' => ''])
            ->assertRedirect(route('public.application.success'));
        $this->submit(['city' => '', 'region_code' => '0700000000', 'province_code' => '', 'city_code' => '0730600000'])
            ->assertRedirect(route('public.application.success'));
        $this->submit(['country' => 'Japan', 'city' => '', 'international_location' => ''])->assertSessionHasErrors('international_location');
        $this->submit(['country' => 'Japan', 'city' => '', 'region' => '', 'province' => '', 'barangay' => '', 'international_location' => 'Tokyo'])
            ->assertRedirect(route('public.application.success'));
    }

    public function test_education_minimums_are_enforced_for_every_applicant_type(): void
    {
        foreach (AdmissionValidation::REQUIRED_EDUCATION as $type => $fields) {
            foreach ($fields as $field) {
                $this->submit(['academic_status' => $type, $field => ''])->assertSessionHasErrors($field);
            }
            $this->submit(['academic_status' => $type])->assertRedirect(route('public.application.success'));
        }
    }
}
