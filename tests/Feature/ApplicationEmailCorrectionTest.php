<?php

namespace Tests\Feature;

use App\Mail\ApplicationEmailChangeVerification;
use App\Mail\ApplicationSubmitted;
use App\Models\Application;
use App\Models\ApplicationEmailChange;
use App\Models\Program;
use App\Models\UserNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Tests\SeedsRoles;
use Tests\TestCase;

class ApplicationEmailCorrectionTest extends TestCase
{
    use RefreshDatabase, SeedsRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        Mail::fake();
    }

    public function test_applicant_must_verify_new_email_before_application_is_resubmitted(): void
    {
        $this->makeUserWithRole('registrar');
        $application = $this->makeApplication();
        $statusLink = $this->statusLink($application);

        $this->get($statusLink)
            ->assertOk()
            ->assertSee('Update your application email')
            ->assertSee('name="new_email"', false)
            ->assertSee('Other requested corrections must be arranged with the Registrar.');

        $this->post($this->correctionUrl($application), ['new_email' => 'changed@example.test'])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame('original@example.test', $application->fresh()->email);
        $this->assertSame('revision_required', $application->fresh()->status);
        $change = ApplicationEmailChange::firstOrFail();
        $this->assertSame('Registrar asked for an email correction.', $change->previous_remarks);
        $this->assertNotNull($change->previous_reviewed_at);
        Mail::assertSent(ApplicationEmailChangeVerification::class, function ($mail) use ($change) {
            return $mail->hasTo('changed@example.test') && $mail->emailChange->is($change);
        });
        Mail::assertNotSent(ApplicationSubmitted::class);

        $token = $this->tokenFromEmail();
        $confirmPath = route('public.application.status.email-change.confirm', [$change, $token], false);
        $this->get($confirmPath)->assertOk()->assertSee('Verify your new email');
        $this->assertSame('revision_required', $application->fresh()->status);

        $completePath = route('public.application.status.email-change.complete', [$change, $token], false);
        $this->post($completePath)
            ->assertRedirect(route('public.application.status.form'))
            ->assertSessionHas('success');

        $application->refresh();
        $this->assertSame('changed@example.test', $application->email);
        $this->assertSame('submitted', $application->status);
        $this->assertSame('Registrar asked for an email correction.', $application->remarks);
        $this->assertNotNull($change->previous_reviewed_by);
        $this->assertNotNull($change->fresh()->verified_at);
        Mail::assertSent(ApplicationSubmitted::class, fn ($mail) => $mail->hasTo('changed@example.test'));
        $this->assertDatabaseHas('user_notifications', ['title' => 'Application Resubmitted']);
    }

    public function test_manual_lookup_cannot_request_email_change_and_expired_verification_cannot_resubmit(): void
    {
        $application = $this->makeApplication();
        $this->post(route('public.application.status.email-correction', ['application' => $application->id, 'email_hash' => $this->emailHash($application)]), [
            'new_email' => 'changed@example.test',
        ])->assertForbidden();

        $token = bin2hex(random_bytes(32));
        $change = $application->emailChanges()->create([
            'old_email' => $application->email,
            'new_email' => 'changed@example.test',
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->subMinute(),
        ]);

        $this->post(route('public.application.status.email-change.complete', [$change, $token], false))
            ->assertRedirect(route('public.application.status.form'))
            ->assertSessionHas('error');

        $this->assertSame('original@example.test', $application->fresh()->email);
        $this->assertSame('revision_required', $application->fresh()->status);
        Mail::assertNothingSent();
    }

    private function makeApplication(): Application
    {
        $reviewer = $this->makeUserWithRole('registrar');

        return Application::create([
            'reference_no' => 'APP-2026-EMAIL1',
            'program_id' => Program::factory()->create()->id,
            'academic_status' => 'new_student',
            'first_name' => 'Test',
            'last_name' => 'Applicant',
            'gender' => 'female',
            'birthdate' => '2005-01-01',
            'email' => 'original@example.test',
            'status' => 'revision_required',
            'remarks' => 'Registrar asked for an email correction.',
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now()->subDay(),
        ]);
    }

    private function statusLink(Application $application): string
    {
        return URL::temporarySignedRoute('public.application.status.email', now()->addHour(), [
            'application' => $application->id,
            'email_hash' => $this->emailHash($application),
        ]);
    }

    private function correctionUrl(Application $application): string
    {
        return URL::temporarySignedRoute('public.application.status.email-correction', now()->addHour(), [
            'application' => $application->id,
            'email_hash' => $this->emailHash($application),
        ]);
    }

    private function emailHash(Application $application): string
    {
        return hash_hmac('sha256', strtolower(trim($application->email)), (string) config('app.key'));
    }

    private function tokenFromEmail(): string
    {
        $mail = Mail::sent(ApplicationEmailChangeVerification::class)->last();
        preg_match('~/status/email-change/\d+/([a-f0-9]{64})$~', $mail->confirmationUrl, $matches);

        return $matches[1];
    }
}
