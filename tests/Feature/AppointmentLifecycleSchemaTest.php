<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\AppointmentDocumentItem;
use App\Models\DocumentRequest;
use App\Models\OfficeClosure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AppointmentLifecycleSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_schema_is_additive_and_legacy_appointments_remain_unclassified(): void
    {
        $this->assertSame('dtc_testing', DB::selectOne('SELECT DATABASE() AS name')->name);
        $this->assertTrue(Schema::hasColumn('appointments', 'visit_type'));
        $appointment = Appointment::factory()->create()->fresh();
        $this->assertSame('legacy', $appointment->visit_type);
        $this->assertNull($appointment->completed_at);
        $this->assertNull($appointment->completed_by);
        $this->assertSame(0, $appointment->documentItems()->count());
        $this->assertSame(0, $appointment->disruptions()->count());
    }

    public function test_document_can_have_multiple_visit_items_without_changing_payment_origin(): void
    {
        $origin = Appointment::factory()->create();
        $claim = Appointment::factory()->create(['user_id' => $origin->user_id, 'visit_type' => 'claiming']);
        $document = DocumentRequest::create(['user_id' => $origin->user_id, 'appointment_id' => $origin->id,
            'document_type' => 'tor', 'fee' => 0, 'status' => 'ready']);
        $origin->documentItems()->create(['document_request_id' => $document->id]);
        $item = $claim->documentItems()->create(['document_request_id' => $document->id,
            'released_by' => $origin->user_id, 'released_at' => now()]);
        $document->pendingClaimAppointment()->associate($claim);
        $document->save();
        $this->assertSame($claim->id, $document->fresh()->pendingClaimAppointment->id);
        $this->assertSame($origin->id, $document->fresh()->payment_appointment_id);
        $this->assertSame($origin->id, $document->fresh()->appointment_id);
        $this->assertCount(2, $document->appointmentItems);
        $this->assertSame($document->id, $item->documentRequest->id);
        $this->assertSame($claim->id, $item->appointment->id);
        $this->assertSame($origin->user_id, $item->releaser->id);
        $this->assertInstanceOf(\Carbon\Carbon::class, $item->released_at);
        $this->assertSame('ready', $document->fresh()->status);
    }

    public function test_database_rejects_duplicate_visit_items(): void
    {
        $appointment = Appointment::factory()->create();
        $document = DocumentRequest::create(['user_id' => $appointment->user_id, 'document_type' => 'tor']);
        $appointment->documentItems()->create(['document_request_id' => $document->id]);
        $this->expectException(QueryException::class);
        $appointment->documentItems()->create(['document_request_id' => $document->id]);
    }

    public function test_closure_disruption_relationships_and_notification_defaults(): void
    {
        $appointment = Appointment::factory()->create();
        $closure = OfficeClosure::create(['start_date' => '2026-12-01', 'end_date' => '2026-12-01',
            'title' => 'Office closed', 'type' => 'office_closure']);
        $disruption = $appointment->disruptions()->create(['office_closure_id' => $closure->id,
            'notification_key' => 'appointment-closure:'.$closure->id.':'.$appointment->id])->fresh();
        $this->assertSame('open', $disruption->status);
        $this->assertSame('pending', $disruption->notification_status);
        $this->assertSame(0, $disruption->notification_attempts);
        $this->assertSame($appointment->id, $disruption->appointment->id);
        $this->assertSame($closure->id, $disruption->officeClosure->id);
        $this->assertSame($disruption->id, $closure->appointmentDisruptions->sole()->id);
        $this->expectException(QueryException::class);
        $appointment->disruptions()->create(['office_closure_id' => $closure->id,
            'notification_key' => 'different-key']);
    }

    public function test_reservation_and_item_insert_roll_back_together(): void
    {
        $appointment = Appointment::factory()->create();
        $document = DocumentRequest::create(['user_id' => $appointment->user_id, 'document_type' => 'tor']);
        try {
            DB::transaction(function () use ($appointment, $document) {
                $document->pendingClaimAppointment()->associate($appointment);
                $document->save();
                AppointmentDocumentItem::create(['appointment_id' => $appointment->id, 'document_request_id' => $document->id]);
                throw new \RuntimeException('Rollback fixture');
            });
        } catch (\RuntimeException $exception) {
            $this->assertSame('Rollback fixture', $exception->getMessage());
        }
        $this->assertNull($document->fresh()->pending_claim_appointment_id);
        $this->assertDatabaseCount('appointment_document_items', 0);
    }

    public function test_database_prevents_deleting_appointment_with_reserved_document(): void
    {
        $appointment = Appointment::factory()->create();
        $document = DocumentRequest::create(['user_id' => $appointment->user_id, 'document_type' => 'tor']);
        $document->pendingClaimAppointment()->associate($appointment);
        $document->save();
        $this->expectException(QueryException::class);
        DB::table('appointments')->where('id', $appointment->id)->delete();
    }

    public function test_database_prevents_deleting_document_visit_history(): void
    {
        $appointment = Appointment::factory()->create();
        $document = DocumentRequest::create(['user_id' => $appointment->user_id, 'document_type' => 'tor']);
        $appointment->documentItems()->create(['document_request_id' => $document->id]);
        $this->expectException(QueryException::class);
        DB::table('document_requests')->where('id', $document->id)->delete();
    }

    public function test_standalone_documents_and_completion_metadata_have_independent_relationships(): void
    {
        $appointment = Appointment::factory()->create();
        $document = DocumentRequest::create(['user_id' => $appointment->user_id,
            'document_type' => 'tor', 'status' => 'ready', 'fee' => 0]);
        $this->assertNull($document->fresh()->appointment_id);
        $this->assertNull($document->fresh()->payment_appointment_id);
        $this->assertNull($document->fresh()->pendingClaimAppointment);
        $this->assertTrue($document->paymentSatisfied());
        $appointment->update(['completed_at' => now(), 'completed_by' => $appointment->user_id]);
        $this->assertInstanceOf(\Carbon\Carbon::class, $appointment->fresh()->completed_at);
        $this->assertSame($appointment->user_id, $appointment->completer->id);
    }
}
