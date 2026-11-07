<?php

namespace Tests\Feature;

use App\Models\Program;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\SeedsRoles;
use Tests\TestCase;

class PublicApplicationValidationTest extends TestCase
{
    use \Tests\AdmissionTestPayload;
    use RefreshDatabase, SeedsRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        config(['services.application_submission.per_ip_per_hour' => 1000]);
        Mail::fake();
        Storage::fake('local');
    }

    private function submit(array $changes = [])
    {
        $program = Program::first() ?? Program::factory()->create();
        $this->get(route('public.application.create'))->assertOk();
        return $this->from(route('public.application.create'))->post(route('public.application.store'), $this->admissionPayload(array_replace([
            'submission_token' => session('public_application_submission_token'),
            'academic_status' => 'new_student', 'program_id' => $program->id,
            'first_name' => 'José', 'last_name' => "O’Neill-Peña", 'gender' => 'male',
            'birthdate' => '2004-02-29', 'email' => 'validation@example.test', 'ack_enrollment' => 'on',
        ], $changes)));
    }

    public function test_invalid_supplied_values_are_rejected_without_creating_an_application(): void
    {
        $cases = [
            ['first_name', '1234'], ['last_name', '<script>bad</script>'], ['middle_name', 'Name42'],
            ['father_name', '1234'], ['mother_name', '1234'], ['guardian_name', '1234'], ['spouse_name', '1234'],
            ['birthdate', '2025-02-29'], ['birthdate', '02/03/2005'], ['birthdate', '2004-02-29T12:00:00Z'],
            ['birthdate', today()->addDay()->toDateString()], ['birthdate', ['2005-01-01']],
            ['phone', 'call me'], ['phone', '0912345678'], ['phone', '+63912345678'], ['phone', '09+171234567'],
            ['parent_contact', '123'], ['guardian_contact', ['09171234567']],
            ['email', 'not-an-email'], ['first_name', ['Juan']], ['lrn', '12345678901'], ['lrn', '12345678901x'],
            ['postal_code', '06004'], ['postal_code', 'abcd'], ['postal_code', '0000'],
            ['school_year_completed', '2025-2024'], ['school_year_completed', '2024-2026'],
            ['school_year_completed', 'last year'], ['region_code', ['0700000000']], ['province_code', ['0701200000']],
        ];
        foreach ($cases as [$field, $value]) {
            $this->submit([$field => $value] + ($field === 'spouse_name' ? ['marital_status' => 'married'] : []))->assertSessionHasErrors($field);
            $this->assertDatabaseCount('applications', 0);
            $this->get(route('public.application.create'))->assertOk();
        }
    }

    public function test_school_year_validation_uses_the_configured_academic_year(): void
    {
        Setting::set('current_school_year', '2026-2027');

        foreach (['1800-1801', '2025-2026', '2026-2027'] as $index => $value) {
            $this->submit([
                'email' => 'school-valid-' . $index . '@example.test',
                'school_year_completed' => $value,
            ])->assertSessionHasNoErrors()->assertRedirect(route('public.application.success'));
        }

        $this->submit(['school_year_completed' => '2027-2028'])
            ->assertSessionHasErrors('school_year_completed');
        $this->assertSame(
            'Enter a current or previous school year.',
            session('errors')->first('school_year_completed')
        );
    }

    public function test_school_year_validation_reports_format_and_consecutive_year_errors(): void
    {
        Setting::set('current_school_year', '2026-2027');

        foreach ([
            ['2026', 'Enter a school year in YYYY-YYYY format.'],
            ['2026–2027', 'Enter a school year in YYYY-YYYY format.'],
            ['2024-2026', 'The ending year must be one year after the starting year.'],
        ] as $index => [$value, $expectedMessage]) {
            $this->submit([
                'email' => 'school-invalid-' . $index . '@example.test',
                'school_year_completed' => $value,
            ])->assertSessionHasErrors('school_year_completed');
            $this->assertSame($expectedMessage, session('errors')->first('school_year_completed'));
            $this->get(route('public.application.create'))->assertOk();
        }
    }

    public function test_school_year_fallback_uses_the_calendar_year_as_the_academic_start_year(): void
    {
        Setting::set('current_school_year', 'invalid');
        $year = today()->year;

        $this->submit([
            'email' => 'school-fallback-current@example.test',
            'school_year_completed' => $year . '-' . ($year + 1),
        ])->assertSessionHasNoErrors()->assertRedirect(route('public.application.success'));

        $this->submit([
            'email' => 'school-fallback-future@example.test',
            'school_year_completed' => ($year + 1) . '-' . ($year + 2),
        ])->assertSessionHasErrors('school_year_completed');
        $this->assertSame(
            'Enter a current or previous school year.',
            session('errors')->first('school_year_completed')
        );
    }

    public function test_school_year_is_required_for_returnees_with_a_specific_message(): void
    {
        $this->submit([
            'academic_status' => 'returnee',
            'email' => 'returnee-missing-year@example.test',
            'school_year_completed' => '',
        ])->assertSessionHasErrors('school_year_completed');

        $this->assertSame(
            'Please enter your last school year attended.',
            session('errors')->first('school_year_completed')
        );
    }

    public function test_configured_current_school_year_is_valid_for_each_applicant_type(): void
    {
        Setting::set('current_school_year', '2026-2027');

        foreach (['new_student', 'transferee', 'shiftee', 'returnee', 'cross_enrollee'] as $index => $type) {
            $this->submit([
                'academic_status' => $type,
                'email' => 'school-type-' . $index . '@example.test',
                'school_year_completed' => '2026-2027',
            ])->assertSessionHasNoErrors()->assertRedirect(route('public.application.success'));
        }
    }

    public function test_academic_settings_reject_malformed_or_nonconsecutive_current_years(): void
    {
        $admin = $this->makeUserWithRole('admin');
        foreach (['2026', '2026–2027', '2026-2028'] as $value) {
            $this->actingAs($admin)
                ->put(route('admin.settings.academic.update'), [
                    'current_school_year' => $value,
                    'current_semester' => '1st',
                ])
                ->assertSessionHasErrors('current_school_year');
        }
    }

    public function test_formatted_mobile_landline_and_international_contacts_are_accepted(): void
    {
        foreach (['09171234567', '+639171234567', '+63 917-123-4567', '(02) 8123-4567', '032-123-4567', '+63 (2) 8123-4567', '+1 (202) 555-0123'] as $index => $number) {
            $email = 'phone-' . $index . '@example.test';
            $this->submit(['email' => $email, 'phone' => $number, 'parent_contact' => $number, 'guardian_contact' => $number])
                ->assertSessionHasNoErrors()->assertRedirect(route('public.application.success'));
            $this->assertDatabaseHas('applications', ['email' => $email, 'phone' => $number, 'parent_contact' => $number, 'guardian_contact' => $number]);
        }
    }

    public function test_leap_day_unicode_names_lrn_and_country_specific_postal_codes_are_preserved(): void
    {
        $this->submit(['lrn' => '001234567890', 'postal_code' => '0604', 'school_year_completed' => '2024-2025'])
            ->assertSessionHasNoErrors()->assertRedirect(route('public.application.success'));
        $this->assertDatabaseHas('applications', ['first_name' => 'José', 'birthdate' => '2004-02-29', 'lrn' => '001234567890', 'postal_code' => '0604', 'school_year_completed' => '2024-2025']);
        $this->submit(['email' => 'international@example.test', 'country' => 'United Kingdom', 'postal_code' => 'SW1A 1AA'])
            ->assertSessionHasNoErrors()->assertRedirect(route('public.application.success'));
        $this->submit(['email' => 'bad-postal@example.test', 'country' => 'United Kingdom', 'postal_code' => '<invalid>'])
            ->assertSessionHasErrors('postal_code');
        foreach (['SW1A--1AA', 'SW1A  1AA', 'SW1A-', '-SW1A'] as $index => $postal) {
            $this->submit(['email' => 'separator-' . $index . '@example.test', 'country' => 'United Kingdom', 'postal_code' => $postal])->assertSessionHasErrors('postal_code');
        }
    }

    public function test_inapplicable_values_are_excluded_and_nationality_is_not_assumed(): void
    {
        $this->submit(['email' => 'stale@example.test', 'marital_status' => 'single', 'spouse_name' => ['invalid stale value'], 'disability' => '', 'pwd_id' => ['invalid stale value'], 'nationality' => ''])
            ->assertRedirect(route('public.application.success'));
        $this->assertDatabaseHas('applications', ['email' => 'stale@example.test', 'spouse_name' => null, 'pwd_id' => null, 'nationality' => null]);
        $this->submit(['email' => 'applicable@example.test', 'marital_status' => 'married', 'spouse_name' => 'Test Spouse', 'disability' => 'Visual impairment', 'pwd_id' => 'PWD-123'])
            ->assertRedirect(route('public.application.success'));
        $this->assertDatabaseHas('applications', ['email' => 'applicable@example.test', 'spouse_name' => 'Test Spouse', 'pwd_id' => 'PWD-123']);
        $this->submit(['email' => 'voluntary@example.test', 'disability' => 'Visual impairment', 'pwd_id' => ''])
            ->assertRedirect(route('public.application.success'));
    }

    public function test_optional_values_remain_optional(): void
    {
        $this->submit(['phone' => '', 'guardian_contact' => '', 'parent_contact' => '', 'lrn' => '', 'postal_code' => '', 'school_year_completed' => ''])
            ->assertSessionHasNoErrors()->assertRedirect(route('public.application.success'));
    }

    public function test_unsafe_oversize_empty_or_unknown_uploads_are_rejected(): void
    {
        foreach ([
            UploadedFile::fake()->create('document.exe', 20, 'application/pdf'),
            UploadedFile::fake()->create('document.txt', 20, 'text/plain'),
            UploadedFile::fake()->create('document.pdf', 5121, 'application/pdf'),
            UploadedFile::fake()->create('document.pdf', 0, 'application/pdf'),
        ] as $file) {
            $this->submit(['documents' => ['form_138' => $file]])->assertSessionHasErrors('documents.form_138');
            $this->assertDatabaseCount('applications', 0);
        }
        $this->submit(['documents' => ['unexpected' => UploadedFile::fake()->create('extra.pdf', 20, 'application/pdf')]])
            ->assertSessionHasErrors('documents');
        $this->submit(['documents' => ['form_138' => UploadedFile::fake()->create('scan.pdf', 20, 'application/pdf')]])
            ->assertSessionHasNoErrors()->assertRedirect(route('public.application.success'));
    }
}
