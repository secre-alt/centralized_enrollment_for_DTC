<?php

namespace Tests\Feature;

use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\UserNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\SeedsRoles;
use Tests\TestCase;

class EnrollmentCashierFirstFlowTest extends TestCase
{
    use RefreshDatabase, SeedsRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    public function test_payment_must_be_verified_before_registrar_approval_and_student_profile_creation(): void
    {
        $student = $this->makeUserWithRole('student');
        $cashier = $this->makeUserWithRole('cashier');
        $registrar = $this->makeUserWithRole('registrar');
        $enrollment = Enrollment::factory()->pending()->create(['user_id' => $student->id]);

        $this->actingAs($registrar)
            ->get(route('registrar.enrollments.index'))
            ->assertViewHas('enrollments', fn ($enrollments) => $enrollments->total() === 0);

        $this->post(route('registrar.enrollments.approve', $enrollment))
            ->assertSessionHasErrors('enrollment');
        $this->assertSame('pending', $enrollment->fresh()->status);
        $this->assertDatabaseMissing('student_profiles', ['user_id' => $student->id]);

        $this->actingAs($cashier)
            ->post(route('cashier.payments.store', $enrollment))
            ->assertRedirect(route('cashier.payments.index'));

        $this->assertSame('pending', $enrollment->fresh()->status);
        $this->assertDatabaseMissing('student_profiles', ['user_id' => $student->id]);

        $this->actingAs($registrar)
            ->get(route('registrar.enrollments.index'))
            ->assertViewHas('enrollments', fn ($enrollments) => $enrollments->total() === 1);
        $this->post(route('registrar.enrollments.approve', $enrollment))
            ->assertRedirect(route('registrar.enrollments.index'));

        $this->assertSame('approved', $enrollment->fresh()->status);
        $this->assertDatabaseHas('student_profiles', ['user_id' => $student->id]);
    }

    public function test_rejecting_a_paid_enrollment_preserves_payment_and_notifies_cashier_about_manual_refund(): void
    {
        $student = $this->makeUserWithRole('student');
        $cashier = $this->makeUserWithRole('cashier');
        $registrar = $this->makeUserWithRole('registrar');
        $enrollment = Enrollment::factory()->pending()->create(['user_id' => $student->id]);

        $this->actingAs($cashier)->post(route('cashier.payments.store', $enrollment));
        $this->actingAs($registrar)->post(route('registrar.enrollments.reject', $enrollment), [
            'remarks' => 'Please correct the selected subjects.',
        ])->assertRedirect(route('registrar.enrollments.index'));

        $this->assertSame('rejected', $enrollment->fresh()->status);
        $this->assertTrue($enrollment->fresh()->is_paid);
        $this->assertSame(1, Payment::where('enrollment_id', $enrollment->id)->where('status', 'verified')->count());
        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $cashier->id,
            'title' => 'Refund Follow-up Needed',
        ]);

        $studentNotice = UserNotification::where('user_id', $student->id)
            ->where('title', 'Enrollment Rejected')
            ->firstOrFail();
        $this->assertStringContainsString('refunds are handled outside this system', strtolower($studentNotice->message));
    }

    public function test_gcash_verification_sends_the_enrollment_to_registrar_review_without_approving_it(): void
    {
        $student = $this->makeUserWithRole('student');
        $cashier = $this->makeUserWithRole('cashier');
        $registrar = $this->makeUserWithRole('registrar');
        $enrollment = Enrollment::factory()->pending()->create(['user_id' => $student->id]);
        $payment = Payment::factory()->gcashPending()->create(['enrollment_id' => $enrollment->id]);

        $this->actingAs($cashier)
            ->post(route('cashier.payments.verify', $payment))
            ->assertRedirect(route('cashier.payments.index'));

        $this->assertSame('pending', $enrollment->fresh()->status);
        $this->assertTrue($enrollment->fresh()->is_paid);
        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $registrar->id,
            'title' => 'Enrollment Ready for Review',
            'link' => route('registrar.enrollments.index', ['review' => $enrollment->id]),
        ]);
    }
}
