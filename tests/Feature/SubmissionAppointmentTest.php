<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\AppointmentSlot;
use App\Models\DocumentRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\SeedsRoles;
use Tests\TestCase;

class SubmissionAppointmentTest extends TestCase
{
    use RefreshDatabase, SeedsRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    public function test_both_portals_create_owned_standalone_requests_without_visit_items(): void
    {
        foreach (['student', 'alumni'] as $role) {
            $owner = $this->makeUserWithRole($role);
            $this->actingAs($owner)->post(route($role === 'alumni' ? 'portal.documents.store' : 'portal.appointments.store'), [
                'documents' => [['document_type' => 'diploma', 'copies' => 2], ['document_type' => 'certification', 'copies' => 1]],
                'payment_method' => 'walk_in', 'user_id' => 999999, 'pending_claim_appointment_id' => 999999,
            ])->assertSessionHasNoErrors();
            $documents = DocumentRequest::where('user_id', $owner->id)->get();
            $this->assertCount(2, $documents);
            foreach ($documents as $document) {
                $this->assertNull($document->appointment_id);
                $this->assertNull($document->payment_appointment_id);
                $this->assertNull($document->pending_claim_appointment_id);
            }
            $this->actingAs($this->makeUserWithRole('registrar'));
            $this->post(route('registrar.documents.status', $documents->first()), ['status' => 'processing'])->assertSessionHasErrors('assessment');
            $this->post(route('registrar.documents.assessment', $documents->first()), [
                'operation_key' => (string) \Illuminate\Support\Str::uuid(), 'assessment_version' => 0,
                'assessment_status' => 'complete', 'fee_confirmed' => '1',
            ])->assertSessionHasNoErrors();
            $this->post(route('registrar.documents.status', $documents->first()), ['status' => 'processing'])->assertSessionHasNoErrors();
        }
        $this->assertDatabaseCount('appointments', 0);
        $this->assertDatabaseCount('appointment_document_items', 0);
    }

    public function test_historical_standalone_alumni_request_remains_payable_processable_and_releasable(): void
    {
        $owner = $this->makeUserWithRole('alumni');
        $document = DocumentRequest::create(['user_id' => $owner->id, 'document_type' => 'tor',
            'copies' => 1, 'fee' => 50, 'status' => 'submitted', 'payment_method' => 'walk_in', 'payment_status' => 'unpaid']);
        $this->actingAs($owner)->get(route('portal.documents.index'))->assertOk();
        $this->get(route('portal.payments.document', $document))->assertOk();
        $this->actingAs($this->makeUserWithRole('cashier'))->post(route('cashier.document-payments.verify-walk-in', $document),
            ['amount_received' => 50, 'receipt_no' => 'LEGACY-SUBMISSION'])->assertSessionHasNoErrors();
        $this->actingAs($this->makeUserWithRole('registrar'));
        foreach (['processing', 'ready'] as $status) {
            $this->post(route('registrar.documents.status', $document), ['status' => $status])->assertSessionHasNoErrors();
        }
        $this->post(route('registrar.documents.status', $document), ['status' => 'released'])->assertSessionHasErrors('status');
        $this->post(route('registrar.documents.walk-in-release'), ['claimant_id' => $owner->id, 'document_ids' => [$document->id],
            'handover_confirmed' => '1', 'operation_key' => (string) \Illuminate\Support\Str::uuid()])->assertSessionHasNoErrors();
        $this->assertSame('released', $document->fresh()->status);
        $this->assertNotNull($document->fresh()->released_at);
        $this->assertSame(auth()->id(), $document->fresh()->released_by);
        $this->assertSame('LEGACY-SUBMISSION', $document->fresh()->receipt_no);
        $this->assertSame('verified', $document->fresh()->payment_status);
        $this->assertNull($document->fresh()->appointment_id);
        $this->assertNull($document->fresh()->payment_appointment_id);
        $this->assertDatabaseCount('appointments', 0);
        $this->assertDatabaseCount('appointment_document_items', 0);
    }

    public function test_both_portals_ignore_missing_invalid_and_full_slots(): void
    {
        foreach (['student', 'alumni'] as $role) {
            $owner = $this->makeUserWithRole($role);
            $slot = AppointmentSlot::factory()->create(['max_bookings' => 1]);
            Appointment::factory()->create(['appointment_slot_id' => $slot->id, 'status' => 'confirmed']);
            foreach ([null, 999999, $slot->id] as $slotId) {
                $this->actingAs($owner)->post(route($role === 'alumni' ? 'portal.documents.store' : 'portal.appointments.store'), [
                    'appointment_slot_id' => $slotId, 'documents' => [['document_type' => 'diploma', 'copies' => 1]], 'payment_method' => 'walk_in',
                ])->assertSessionHasNoErrors();
            }
        }
        $this->assertSame(6, DocumentRequest::count());
        $this->assertDatabaseCount('appointment_document_items', 0);
    }
}
