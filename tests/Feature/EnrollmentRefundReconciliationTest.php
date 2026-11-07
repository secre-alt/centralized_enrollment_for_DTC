<?php

namespace Tests\Feature;

use App\Models\CourseSubject;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\Program;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\SeedsRoles;
use Tests\TestCase;

class EnrollmentRefundReconciliationTest extends TestCase
{
    use RefreshDatabase, SeedsRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        Setting::set('current_school_year', '2026-2027');
        Setting::set('current_semester', '1st');
        Storage::fake('local');
    }

    private function rejectedPaidEnrollment($student): array
    {
        $program = Program::factory()->create();
        $enrollment = Enrollment::factory()->create([
            'user_id' => $student->id,
            'program_id' => $program->id,
            'status' => 'rejected',
            'is_paid' => true,
            'school_year' => '2026-2027',
            'semester' => 1,
        ]);
        $payment = Payment::factory()->create([
            'enrollment_id' => $enrollment->id,
            'status' => 'verified',
            'amount' => 500,
            'refund_status' => 'pending',
        ]);

        return [$program, $enrollment, $payment];
    }

    private function submitFreshEnrollment($student, Program $program): Enrollment
    {
        $subject = CourseSubject::factory()->for($program)->yearSem(1, 1)->create();
        $this->actingAs($student)
            ->post(route('portal.enrollment.store'), [
                'program_id' => $program->id,
                'year_level' => 1,
                'semester' => 1,
                'is_irregular' => '0',
                'subject_ids' => [$subject->id],
            ])
            ->assertRedirect(route('portal.enrollment.index'))
            ->assertSessionHas('success');

        return Enrollment::where('user_id', $student->id)->latest('id')->firstOrFail();
    }

    public function test_rejected_verified_payment_is_preserved_and_marked_for_refund_follow_up(): void
    {
        $student = $this->makeUserWithRole('student');
        $cashier = $this->makeUserWithRole('cashier');
        $registrar = $this->makeUserWithRole('registrar');
        $enrollment = Enrollment::factory()->pending()->create([
            'user_id' => $student->id,
            'is_paid' => false,
            'school_year' => '2026-2027',
            'semester' => 1,
        ]);

        $this->actingAs($cashier)->post(route('cashier.payments.store', $enrollment));
        $payment = Payment::where('enrollment_id', $enrollment->id)->firstOrFail();
        $this->actingAs($registrar)->post(route('registrar.enrollments.reject', $enrollment), [
            'remarks' => 'Correct the selected subjects.',
        ])->assertRedirect(route('registrar.enrollments.index'));

        $this->assertSame('verified', $payment->fresh()->status);
        $this->assertSame('pending', $payment->fresh()->refund_status);
        $this->assertNull($payment->fresh()->refunded_at);
        $this->assertSame('rejected', $enrollment->fresh()->status);
    }

    public function test_resubmission_is_allowed_but_new_payment_is_blocked_until_full_refund_is_confirmed(): void
    {
        $student = $this->makeUserWithRole('student');
        [$program, $oldEnrollment, $oldPayment] = $this->rejectedPaidEnrollment($student);
        $newEnrollment = $this->submitFreshEnrollment($student, $program);

        $this->actingAs($student)
            ->get(route('portal.enrollment.payment-info', $newEnrollment))
            ->assertOk()
            ->assertSee('Payment is blocked pending reconciliation.')
            ->assertDontSee('name="proof_of_payment"', false);

        $cashier = $this->makeUserWithRole('cashier');
        $this->actingAs($cashier)
            ->post(route('cashier.payments.store', $newEnrollment))
            ->assertSessionHasErrors('payment');

        $this->actingAs($student)
            ->post(route('portal.enrollment.payment.gcash', $newEnrollment), [
                'reference_number' => 'GCASH-RETRY-1',
                'proof_of_payment' => UploadedFile::fake()->image('proof.jpg'),
            ])
            ->assertSessionHas('error');

        $this->assertSame(0, Payment::where('enrollment_id', $newEnrollment->id)->count());
        $this->assertSame('verified', $oldPayment->fresh()->status);
        $this->assertSame('pending', $oldPayment->fresh()->refund_status);
        $this->assertSame('rejected', $oldEnrollment->fresh()->status);
    }

    public function test_only_cashier_confirmed_full_refund_unlocks_new_payment_and_is_audited(): void
    {
        $student = $this->makeUserWithRole('student');
        $cashier = $this->makeUserWithRole('cashier');
        [$program, $oldEnrollment, $payment] = $this->rejectedPaidEnrollment($student);

        $this->actingAs($cashier)
            ->post(route('cashier.payments.confirm-refund', $payment), [
                'refund_amount' => '499.99',
                'refund_reference' => 'EXT-REFUND-123',
            ])
            ->assertSessionHasErrors('refund_amount');
        $this->assertSame('pending', $payment->fresh()->refund_status);
        $this->assertNull($payment->fresh()->refunded_at);

        $this->post(route('cashier.payments.confirm-refund', $payment), [
            'refund_amount' => '500.00',
            'refund_reference' => 'EXT-REFUND-123',
        ])->assertRedirect(route('cashier.payments.show', $oldEnrollment));

        $payment->refresh();
        $this->assertSame('verified', $payment->status);
        $this->assertSame('refunded', $payment->refund_status);
        $this->assertSame('500.00', $payment->refund_amount);
        $this->assertSame('EXT-REFUND-123', $payment->refund_reference);
        $this->assertSame($cashier->id, $payment->refunded_by);
        $this->assertNotNull($payment->refunded_at);

        $newEnrollment = $this->submitFreshEnrollment($student, $program);
        $this->actingAs($cashier)->post(route('cashier.payments.store', $newEnrollment))
            ->assertRedirect(route('cashier.payments.index'));
        $this->assertSame(2, Payment::whereHas('enrollment', fn ($query) => $query
            ->where('user_id', $student->id)
            ->where('school_year', '2026-2027')
            ->where('semester', 1))->count());

        $this->get(route('cashier.transactions.show', 'E-'.$payment->id))
            ->assertOk()
            ->assertSee('Full refund confirmed')
            ->assertSee('EXT-REFUND-123')
            ->assertSee('500.00');

        $this->actingAs($cashier)->get(route('cashier.dashboard'))
            ->assertOk()
            ->assertViewHas('totalRevenue', fn ($total) => (float) $total === 500.0)
            ->assertViewHas('todayCollection', fn ($total) => (float) $total === 500.0);
        $admin = $this->makeUserWithRole('admin');
        $this->actingAs($admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertViewHas('totalRevenue', fn ($total) => (float) $total === 500.0);
        $this->get(route('admin.reports.payment'))->assertOk();
    }

    public function test_competing_gcash_submissions_for_the_same_term_cannot_both_create_payment_attempts(): void
    {
        $student = $this->makeUserWithRole('student');
        $program = Program::factory()->create();
        $first = Enrollment::factory()->pending()->create([
            'user_id' => $student->id,
            'program_id' => $program->id,
            'school_year' => '2026-2027',
            'semester' => 1,
        ]);
        $second = Enrollment::factory()->pending()->create([
            'user_id' => $student->id,
            'program_id' => $program->id,
            'school_year' => '2026-2027',
            'semester' => 1,
        ]);

        $this->actingAs($student)->post(route('portal.enrollment.payment.gcash', $first), [
            'reference_number' => 'GCASH-FIRST',
            'proof_of_payment' => UploadedFile::fake()->image('first.jpg'),
        ])->assertSessionHas('success');
        $this->post(route('portal.enrollment.payment.gcash', $second), [
            'reference_number' => 'GCASH-SECOND',
            'proof_of_payment' => UploadedFile::fake()->image('second.jpg'),
        ])->assertSessionHas('error');

        $this->assertSame(1, Payment::whereHas('enrollment', fn ($query) => $query->where('user_id', $student->id))->count());
        $this->assertSame('pending', Payment::where('enrollment_id', $first->id)->firstOrFail()->status);
        $this->assertSame('pending', $second->fresh()->status);
    }

    public function test_cashier_can_find_rejected_paid_enrollment_and_refund_follow_up_in_filtered_queue(): void
    {
        $student = $this->makeUserWithRole('student');
        $cashier = $this->makeUserWithRole('cashier');
        [, $enrollment] = $this->rejectedPaidEnrollment($student);

        $this->actingAs($cashier)
            ->get(route('cashier.payments.index', ['filter' => 'refund_follow_up']))
            ->assertOk()
            ->assertViewHas('enrollments', fn ($rows) => $rows->total() === 1)
            ->assertSee('Refund follow-up')
            ->assertSee(route('cashier.payments.show', $enrollment), false);
        $this->get(route('cashier.payments.show', $enrollment))
            ->assertOk()
            ->assertSee('Refund pending confirmation')
            ->assertSee('name="refund_reference"', false);

        $this->post(route('cashier.payments.confirm-refund', $enrollment->payments()->first()), [
            'refund_amount' => 500,
            'refund_reference' => 'EXT-REFUND-456',
        ])->assertRedirect(route('cashier.payments.show', $enrollment));

        $this->get(route('cashier.payments.index', ['filter' => 'refunded']))
            ->assertViewHas('enrollments', fn ($rows) => $rows->total() === 1)
            ->assertSee('Refunded');
        $this->get(route('cashier.payments.receipt', $enrollment))
            ->assertRedirect(route('cashier.payments.index', ['receipt' => $enrollment->id]));
    }

    public function test_students_cannot_access_cashier_payment_or_refund_confirmation_routes(): void
    {
        $owner = $this->makeUserWithRole('student');
        $attacker = $this->makeUserWithRole('student');
        $cashier = $this->makeUserWithRole('cashier');
        [, $enrollment, $payment] = $this->rejectedPaidEnrollment($owner);

        $this->actingAs($attacker)
            ->get(route('cashier.payments.show', $enrollment))
            ->assertForbidden();
        $this->post(route('cashier.payments.confirm-refund', $payment), [
            'refund_amount' => 500,
            'refund_reference' => 'UNAUTHORIZED',
        ])->assertForbidden();
        $this->get(route('portal.enrollment.payment-info', $enrollment))
            ->assertForbidden();

        $this->actingAs($cashier)
            ->post(route('cashier.payments.confirm-refund', $payment), [
                'refund_amount' => 500,
                'refund_reference' => 'CASHIER-ONLY',
            ])->assertRedirect();
        $this->assertSame('refunded', $payment->fresh()->refund_status);
    }
}
