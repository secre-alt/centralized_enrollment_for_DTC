<?php

namespace Tests\Feature;

use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\CourseSubject;
use App\Models\Program;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\SeedsRoles;
use Tests\TestCase;

class StudentDashboardEnrollmentStatusTest extends TestCase
{
    use RefreshDatabase, SeedsRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    public function test_rejected_enrollment_displays_feedback_and_supported_resubmission_action(): void
    {
        $student = $this->makeUserWithRole('student');
        Enrollment::factory()->create([
            'user_id' => $student->id,
            'status' => 'rejected',
            'remarks' => 'Please correct your selected subjects.',
            'is_paid' => false,
        ]);

        $response = $this->actingAs($student)->get(route('portal.dashboard'));

        $response->assertOk()
            ->assertSee('Enrollment Status')
            ->assertSee('Please correct your selected subjects.')
            ->assertSee('Correct and Resubmit')
            ->assertSee(route('portal.enrollment.create'))
            ->assertSee('data-step-state="rejected"', false);

        $this->assertSame(1, substr_count($response->getContent(), 'data-step-state="done"'));
    }

    public function test_rejected_enrollment_without_feedback_has_neutral_recovery_guidance(): void
    {
        $student = $this->makeUserWithRole('student');
        Enrollment::factory()->create([
            'user_id' => $student->id,
            'status' => 'rejected',
            'remarks' => null,
            'is_paid' => false,
        ]);

        $this->actingAs($student)->get(route('portal.dashboard'))
            ->assertOk()
            ->assertSee('No additional feedback was provided.')
            ->assertSee('Review Enrollment History');
    }

    public function test_student_dashboard_only_displays_the_signed_in_students_enrollment_feedback(): void
    {
        $student = $this->makeUserWithRole('student');
        $otherStudent = $this->makeUserWithRole('student');
        Enrollment::factory()->create([
            'user_id' => $otherStudent->id,
            'status' => 'rejected',
            'remarks' => 'Private feedback belonging to another student.',
            'is_paid' => false,
        ]);

        $this->actingAs($student)->get(route('portal.dashboard'))
            ->assertOk()
            ->assertDontSee('Private feedback belonging to another student.')
            ->assertSee('Not Started');

        $this->actingAs($student)->get(route('portal.enrollment.index'))
            ->assertOk()
            ->assertDontSee('Private feedback belonging to another student.');
    }

    public function test_approved_unpaid_enrollment_is_not_presented_as_paid_or_enrolled(): void
    {
        $student = $this->makeUserWithRole('student');
        $program = Program::factory()->create();
        $subject = CourseSubject::factory()->for($program)->create();
        $enrollment = Enrollment::factory()->create([
            'user_id' => $student->id,
            'program_id' => $program->id,
            'subject_ids' => [$subject->id],
            'status' => 'approved',
            'is_paid' => false,
        ]);
        Payment::factory()->gcashPending()->create(['enrollment_id' => $enrollment->id]);

        $this->actingAs($student)->get(route('portal.dashboard'))
            ->assertOk()
            ->assertSee('Payment Verification')
            ->assertSee('Your payment proof is awaiting Cashier verification.')
            ->assertDontSee('All steps completed! You are officially enrolled.');
    }

    public function test_student_avatar_links_to_the_existing_profile_settings_page(): void
    {
        $student = $this->makeUserWithRole('student');

        $this->actingAs($student)->get(route('portal.dashboard'))
            ->assertOk()
            ->assertSee('href="' . route('portal.profile.show') . '"', false)
            ->assertSee('aria-label="Open profile settings"', false)
            ->assertSee('aria-label="Choose a new profile photo"', false)
            ->assertSee('data-dashboard-avatar-input', false)
            ->assertSee('action="' . route('portal.profile.update') . '"', false)
            ->assertSee('data-lucide="camera"', false);

        $this->actingAs($student)->get(route('portal.profile.show'))
            ->assertOk()
            ->assertSee('data-lucide="camera"', false)
            ->assertSee('aria-label="Change profile photo"', false);
    }

    public function test_dashboard_avatar_uses_the_saved_profile_photo_when_available(): void
    {
        $student = $this->makeUserWithRole('student', [
            'avatar' => 'avatars/john.jpg',
        ]);

        $this->actingAs($student)->get(route('portal.dashboard'))
            ->assertOk()
            ->assertSee('src="' . Storage::url($student->avatar) . '"', false)
            ->assertDontSee('>J</div>', false);
    }
}
