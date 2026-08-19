<?php

namespace Tests\Feature;

use App\Models\Enrollment;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\SeedsRoles;
use Tests\TestCase;

class CashierPaymentFlowTest extends TestCase
{
    use RefreshDatabase, SeedsRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    /** @test */
    public function cashier_can_confirm_a_walk_in_payment_for_an_approved_unpaid_enrollment()
    {
        $cashier    = $this->makeUserWithRole('cashier');
        $enrollment = Enrollment::factory()->create(['status' => 'approved', 'is_paid' => false]);

        $response = $this->actingAs($cashier)
            ->post(route('cashier.payments.store', $enrollment));

        $response->assertRedirect(route('cashier.payments.receipt', $enrollment));

        $enrollment->refresh();
        $this->assertTrue($enrollment->is_paid);

        $this->assertDatabaseHas('payments', [
            'enrollment_id'  => $enrollment->id,
            'payment_method' => 'walk_in',
            'status'         => 'verified',
            'processed_by'   => $cashier->id,
        ]);
    }

    /** @test */
    public function walk_in_payment_cannot_be_recorded_twice_for_the_same_enrollment()
    {
        $cashier    = $this->makeUserWithRole('cashier');
        $enrollment = Enrollment::factory()->paid()->create();
        Payment::factory()->create(['enrollment_id' => $enrollment->id, 'processed_by' => $cashier->id]);

        $response = $this->actingAs($cashier)
            ->post(route('cashier.payments.store', $enrollment));

        $response->assertRedirect();
        $response->assertSessionHas('error');

        // Still only the one payment row — no duplicate was created.
        $this->assertSame(1, Payment::where('enrollment_id', $enrollment->id)->count());
    }

    /** @test */
    public function a_non_cashier_cannot_process_payments()
    {
        $student    = $this->makeUserWithRole('student');
        $enrollment = Enrollment::factory()->create(['status' => 'approved', 'is_paid' => false]);

        $this->actingAs($student)
            ->post(route('cashier.payments.store', $enrollment))
            ->assertForbidden();
    }

    /** @test */
    public function cashier_can_verify_a_pending_gcash_payment_and_it_marks_the_enrollment_paid()
    {
        $cashier    = $this->makeUserWithRole('cashier');
        $enrollment = Enrollment::factory()->create(['status' => 'approved', 'is_paid' => false]);
        $payment    = Payment::factory()->gcashPending()->create(['enrollment_id' => $enrollment->id]);

        $response = $this->actingAs($cashier)
            ->post(route('cashier.payments.verify', $payment));

        $response->assertRedirect();

        $payment->refresh();
        $enrollment->refresh();

        $this->assertSame('verified', $payment->status);
        $this->assertSame($cashier->id, $payment->processed_by);
        $this->assertSame($cashier->id, $payment->verified_by);
        $this->assertNotNull($payment->verified_at);
        $this->assertTrue($enrollment->is_paid);
    }

    /** @test */
    public function cashier_can_reject_a_pending_gcash_payment_with_a_reason()
    {
        $cashier    = $this->makeUserWithRole('cashier');
        $enrollment = Enrollment::factory()->create(['status' => 'approved', 'is_paid' => false]);
        $payment    = Payment::factory()->gcashPending()->create(['enrollment_id' => $enrollment->id]);

        $response = $this->actingAs($cashier)
            ->post(route('cashier.payments.reject', $payment), [
                'remarks' => 'Reference number does not match our GCash records.',
            ]);

        $response->assertRedirect();

        $payment->refresh();
        $enrollment->refresh();

        $this->assertSame('rejected', $payment->status);
        $this->assertFalse($enrollment->is_paid);
    }

    /** @test */
    public function rejecting_a_gcash_payment_requires_a_reason()
    {
        $cashier    = $this->makeUserWithRole('cashier');
        $enrollment = Enrollment::factory()->create(['status' => 'approved', 'is_paid' => false]);
        $payment    = Payment::factory()->gcashPending()->create(['enrollment_id' => $enrollment->id]);

        $this->actingAs($cashier)
            ->post(route('cashier.payments.reject', $payment), ['remarks' => ''])
            ->assertSessionHasErrors('remarks');

        $this->assertSame('pending', $payment->fresh()->status);
    }

    /** @test */
    public function receipt_page_shows_the_verified_payment_even_when_an_older_rejected_one_exists()
    {
        // Regression test for the Enrollment::payment() unordered-relation
        // bug: an enrollment can have multiple Payment rows (a rejected
        // GCash attempt, then a walk-in). The receipt must always reflect
        // the actual verified one, not an arbitrary row.
        $cashier    = $this->makeUserWithRole('cashier');
        $enrollment = Enrollment::factory()->paid()->create();

        $rejected = Payment::factory()->rejected()->create([
            'enrollment_id' => $enrollment->id,
            'created_at'    => now()->subDay(),
        ]);
        $verified = Payment::factory()->create([
            'enrollment_id' => $enrollment->id,
            'processed_by'  => $cashier->id,
            'receipt_no'    => 'DTC-VERIFIED1',
        ]);

        $response = $this->actingAs($cashier)
            ->get(route('cashier.payments.receipt', $enrollment));

        $response->assertOk();
        $response->assertSee('DTC-VERIFIED1');
        $response->assertDontSee($rejected->receipt_no);
    }

    /** @test */
    public function receipt_ajax_request_gets_a_real_404_json_error_when_no_verified_payment_exists()
    {
        // Regression test for the redirect-swallowed-by-AJAX bug: an
        // XHR request for a receipt with no verified payment must get a
        // real error status + message, not a silent 302 the JS can't see.
        $cashier    = $this->makeUserWithRole('cashier');
        $enrollment = Enrollment::factory()->create(['status' => 'approved', 'is_paid' => false]);

        $response = $this->actingAs($cashier)
            ->getJson(route('cashier.payments.receipt', $enrollment));

        $response->assertStatus(404);
        $response->assertJson(['message' => 'No verified payment found for this enrollment.']);
    }

    /** @test */
    public function receipt_non_ajax_request_redirects_with_an_error_when_no_verified_payment_exists()
    {
        $cashier    = $this->makeUserWithRole('cashier');
        $enrollment = Enrollment::factory()->create(['status' => 'approved', 'is_paid' => false]);

        $response = $this->actingAs($cashier)
            ->get(route('cashier.payments.receipt', $enrollment));

        $response->assertRedirect(route('cashier.payments.show', $enrollment));
        $response->assertSessionHas('error');
    }
}
