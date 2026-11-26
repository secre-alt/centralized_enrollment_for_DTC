<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\AppointmentPayment;
use App\Models\AppointmentSlot;
use App\Models\DocumentRequest;
use App\Models\DocumentPaymentProof;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\SeedsRoles;
use Tests\TestCase;

class ClaimingAppointmentTest extends TestCase
{
    use \Tests\SeedsHistoricalClaimingAppointments;
    use RefreshDatabase, SeedsRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    private function document($owner, array $changes = []): DocumentRequest
    {
        return DocumentRequest::create(array_merge(['user_id' => $owner->id, 'document_type' => 'tor',
            'copies' => 1, 'status' => 'ready', 'fee' => 0, 'is_paid' => false, 'payment_status' => 'unpaid'], $changes));
    }

    public function test_both_roles_can_claim_documents_from_different_origins_without_financial_changes(): void
    {
        foreach (['student', 'alumni'] as $role) {
            $owner = $this->makeUserWithRole($role);
            $documents = collect();
            foreach ([1, 2] as $number) {
                $origin = Appointment::factory()->create(['user_id' => $owner->id, 'visit_type' => 'submission']);
                AppointmentPayment::factory()->verified()->create(['appointment_id' => $origin->id,
                    'amount' => 50, 'receipt_no' => $role.'-OR-'.$number, 'is_document_bundle' => true]);
                $documents->push($this->document($owner, ['appointment_id' => $origin->id, 'fee' => 50,
                    'is_paid' => true, 'payment_status' => 'verified', 'receipt_no' => $role.'-OR-'.$number]));
            }
            $slot = AppointmentSlot::factory()->create();
            $before = $documents->map->getAttributes();
            $this->actingAs($owner)->historicalClaiming(['appointment_slot_id' => $slot->id,
                'document_ids' => $documents->pluck('id')->all(), 'user_id' => 999, 'visit_type' => 'submission'])->assertSessionHasNoErrors()->assertRedirect();
            $claim = Appointment::where('user_id', $owner->id)->where('visit_type', 'claiming')->sole();
            $this->assertCount(2, $claim->documentItems);
            $this->assertSame('pending', $claim->status);
            $this->assertSame(0, $claim->payments()->count());
            foreach ($documents as $index => $document) {
                $document->refresh();
                $this->assertSame($claim->id, $document->pending_claim_appointment_id);
                foreach (['appointment_id', 'payment_appointment_id', 'receipt_no', 'fee', 'is_paid', 'payment_status', 'status'] as $field) {
                    $this->assertEquals($before[$index][$field], $document->getAttributes()[$field]);
                }
            }
            $this->get('/portal/claiming-appointments')->assertOk()->assertSee('Claiming appointment #'.$claim->id)->assertSee('Transcript of Records');
        }
        $this->assertDatabaseCount('appointment_payments', 4);
    }

    public function test_invalid_multi_document_selection_rolls_back_everything(): void
    {
        $owner = $this->makeUserWithRole('student');
        $other = $this->makeUserWithRole('student');
        foreach ([['status' => 'processing'], ['fee' => 50], ['status' => 'released'], ['status' => 'cancelled'],
            ['user_id' => $other->id], ['is_paid' => false, 'payment_status' => 'verified', 'fee' => 50]] as $changes) {
            $valid = $this->document($owner);
            $invalid = $this->document($owner, $changes);
            $slot = AppointmentSlot::factory()->create();
            $this->actingAs($owner)->historicalClaiming(['appointment_slot_id' => $slot->id,
                'document_ids' => [$valid->id, $invalid->id]])->assertSessionHasErrors('document_ids');
            $this->assertNull($valid->fresh()->pending_claim_appointment_id);
            $this->assertNull($invalid->fresh()->pending_claim_appointment_id);
        }
        $this->assertDatabaseCount('appointments', 0);
        $this->assertDatabaseCount('appointment_document_items', 0);
    }

    public function test_zero_fee_document_and_already_reserved_document(): void
    {
        $owner = $this->makeUserWithRole('alumni');
        $document = $this->document($owner);
        $this->actingAs($owner)->historicalClaiming(['appointment_slot_id' => AppointmentSlot::factory()->create()->id,
            'document_ids' => [$document->id]])->assertSessionHasNoErrors();
        $reservation = $document->fresh()->pending_claim_appointment_id;
        $valid = $this->document($owner);
        $this->historicalClaiming(['appointment_slot_id' => AppointmentSlot::factory()->create()->id,
            'document_ids' => [$document->id, $valid->id]])->assertSessionHasErrors('document_ids');
        $this->assertSame($reservation, $document->fresh()->pending_claim_appointment_id);
        $this->assertNull($valid->fresh()->pending_claim_appointment_id);
        $this->assertDatabaseCount('appointments', 1);
    }

    public function test_closed_full_and_invalid_slots_and_duplicate_ids_are_rejected(): void
    {
        $owner = $this->makeUserWithRole('student');
        $document = $this->document($owner);
        $closed = AppointmentSlot::factory()->create(['status' => 'closed']);
        $full = AppointmentSlot::factory()->create(['max_bookings' => 1]);
        Appointment::factory()->create(['appointment_slot_id' => $full->id, 'status' => 'pending']);
        foreach ([$closed->id, $full->id, 999999] as $slot) {
            $this->actingAs($owner)->historicalClaiming(['appointment_slot_id' => $slot,
                'document_ids' => [$document->id]])->assertSessionHasErrors('appointment_slot_id');
        }
        $this->historicalClaiming(['appointment_slot_id' => $closed->id,
            'document_ids' => [$document->id, $document->id]])->assertSessionHasErrors('document_ids.0');
        $this->assertNull($document->fresh()->pending_claim_appointment_id);
    }

    public function test_non_portal_roles_cannot_book_or_view_claims(): void
    {
        foreach (['registrar', 'cashier', 'admin', 'new_applicant'] as $role) {
            $this->actingAs($this->makeUserWithRole($role))->get('/portal/claiming-appointments')->assertForbidden();
            $this->post('/portal/claiming-appointments', [])->assertForbidden();
        }
    }

    public function test_claiming_cannot_enter_legacy_edit_cancellation_confirmation_or_payment_paths(): void
    {
        $owner = $this->makeUserWithRole('alumni');
        $document = $this->document($owner);
        $this->actingAs($owner)->historicalClaiming(['appointment_slot_id' => AppointmentSlot::factory()->create()->id,
            'document_ids' => [$document->id]])->assertSessionHasNoErrors();
        $claim = Appointment::where('visit_type', 'claiming')->sole();
        $this->get(route('portal.appointments.edit', $claim))->assertStatus(422);
        $this->put(route('portal.appointments.update', $claim), [])->assertStatus(422);
        $this->post(route('portal.appointments.cancel', $claim))->assertStatus(422);
        $this->post(route('portal.appointments.payment.gcash', $claim), [])->assertStatus(422);
        $this->get(route('portal.payments.appointment', $claim))->assertRedirect(route('portal.claiming-appointments.index'));
        $this->post(route('portal.documents.pickup-appointment', $document),
            ['appointment_slot_id' => AppointmentSlot::factory()->create()->id])->assertSessionHasErrors('appointment_slot_id');
        $this->actingAs($this->makeUserWithRole('registrar'))
            ->post(route('registrar.appointments.confirm', $claim))->assertStatus(422);
        $this->assertFalse($claim->canBeConfirmed());
        $this->assertDatabaseCount('document_requests', 1);
        $this->assertDatabaseCount('appointment_payments', 0);
        $this->assertSame($claim->id, $document->fresh()->pending_claim_appointment_id);
        $this->assertSame('ready', $document->fresh()->status);
    }

    public function test_paid_bundle_receipts_and_gcash_history_remain_single_collections(): void
    {
        $owner = $this->makeUserWithRole('student');
        $origin = Appointment::factory()->create(['user_id' => $owner->id, 'visit_type' => 'submission']);
        $payment = AppointmentPayment::factory()->verified()->create(['appointment_id' => $origin->id,
            'payment_method' => 'gcash', 'is_document_bundle' => true, 'amount' => 50, 'receipt_no' => 'CLAIM-OR', 'proof_of_payment' => 'retained.png']);
        $proof = DocumentPaymentProof::create(['appointment_payment_id' => $payment->id, 'path' => 'retained.png',
            'status' => 'verified', 'submitted_at' => now(), 'reviewed_at' => now(), 'reviewed_by' => $owner->id]);
        $document = $this->document($owner, ['appointment_id' => $origin->id, 'is_paid' => true, 'payment_status' => 'verified', 'fee' => 50]);
        $originalPayment = $payment->fresh()->getAttributes();
        $originalProof = $proof->fresh()->getAttributes();
        $this->actingAs($owner)->historicalClaiming(['appointment_slot_id' => AppointmentSlot::factory()->create()->id,
            'document_ids' => [$document->id]])->assertSessionHasNoErrors();
        $this->assertEquals($originalPayment, $payment->fresh()->getAttributes());
        $this->assertEquals($originalProof, $proof->fresh()->getAttributes());
        $this->get(route('portal.transactions.receipt', 'A-'.$payment->id))->assertOk();
        $this->get(route('portal.transactions.receipt', 'D-'.$document->id))->assertNotFound();
        $this->assertSame(1, app(\App\Services\TransactionHistory::class)->query($owner->id, 'student')->count());
        $this->assertSame(5000, app(\App\Services\TransactionHistory::class)->summary($owner->id, 'student', [])['verified_cents']);
    }

    public function test_claim_lists_are_scoped_to_the_authenticated_owner(): void
    {
        $owner = $this->makeUserWithRole('student');
        $other = $this->makeUserWithRole('alumni');
        $private = $this->document($other);
        $mine = $this->document($owner);
        $this->actingAs($other)->historicalClaiming(['appointment_slot_id' => AppointmentSlot::factory()->create()->id,
            'document_ids' => [$private->id]])->assertSessionHasNoErrors();
        $this->actingAs($owner)->get('/portal/claiming-appointments')->assertOk()
            ->assertViewHas('appointments', fn ($appointments) => $appointments->isEmpty())
            ->assertDontSee('Book a claiming appointment');
    }
}
