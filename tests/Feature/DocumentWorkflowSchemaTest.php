<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\DocumentRequest;
use App\Models\DocumentWorkflowEvent;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DocumentWorkflowSchemaTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('dtc_testing', DB::selectOne('SELECT DATABASE() AS name')->name);
        $this->artisan('migrate:fresh')->assertExitCode(0);
        $this->beforeApplicationDestroyed(function () {
            \Illuminate\Foundation\Testing\RefreshDatabaseState::$migrated = false;
        });
    }

    private function event(array $attributes = []): DocumentWorkflowEvent
    {
        $actor = Appointment::factory()->create()->user;

        return DocumentWorkflowEvent::create(array_merge([
            'actor_id' => $actor->id, 'operation_key' => (string) \Illuminate\Support\Str::uuid(),
            'request_fingerprint' => hash('sha256', 'fixture'), 'action' => 'schema.fixture',
            'document_request_ids' => [], 'before' => [], 'after' => [], 'outcome' => ['ok' => true],
            'created_at' => now(),
        ], $attributes));
    }

    public function test_migration_preserves_historical_records_and_reservations(): void
    {
        $migration = require database_path('migrations/2026_11_25_000001_add_document_workflow_integrity.php');
        $migration->down();
        $appointment = Appointment::factory()->create();
        $claim = Appointment::factory()->create(['user_id' => $appointment->user_id, 'visit_type' => 'claiming']);
        $document = DocumentRequest::create(['user_id' => $appointment->user_id, 'appointment_id' => $appointment->id,
            'document_type' => 'tor', 'status' => 'ready', 'fee' => 50, 'is_paid' => true,
            'payment_status' => 'verified', 'amount_received' => 50, 'receipt_no' => 'PRESERVED']);
        $document->pendingClaimAppointment()->associate($claim);
        $document->save();
        $claim->documentItems()->create(['document_request_id' => $document->id]);
        $released = DocumentRequest::create(['user_id' => $appointment->user_id, 'document_type' => 'tor', 'status' => 'released']);
        $claim->documentItems()->create(['document_request_id' => $released->id, 'released_at' => now(), 'released_by' => $appointment->user_id]);
        $payment = \App\Models\AppointmentPayment::factory()->create(['appointment_id' => $appointment->id,
            'is_document_bundle' => true, 'status' => 'verified', 'amount' => 50, 'receipt_no' => 'PRESERVED']);
        \App\Models\DocumentPaymentProof::create(['appointment_payment_id' => $payment->id,
            'path' => 'historical-proof.pdf', 'status' => 'verified', 'submitted_at' => now()]);
        \App\Models\AppointmentLifecycleEvent::create(['appointment_id' => $claim->id,
            'actor_id' => $appointment->user_id, 'operation_key' => (string) \Illuminate\Support\Str::uuid(),
            'request_fingerprint' => hash('sha256', 'historical'), 'action' => 'claim.booked',
            'previous_version' => null, 'resulting_version' => 1, 'document_item_ids' => [],
            'before' => [], 'after' => [], 'outcome' => [], 'created_at' => now()]);
        \App\Models\AuditLog::create(['user_id' => $appointment->user_id, 'action' => 'historical.fixture',
            'description' => 'Preserve historical audit evidence.']);
        $tables = ['appointments', 'document_requests', 'appointment_document_items', 'appointment_payments',
            'document_payment_proofs', 'appointment_lifecycle_events', 'audit_logs'];
        $before = [];
        foreach ($tables as $table) {
            $before[$table] = DB::table($table)->orderBy('id')->get()->map(fn ($row) => (array) $row)->all();
        }
        $migration->up();
        foreach ($tables as $table) {
            $after = DB::table($table)->orderBy('id')->get()->map(fn ($row) => (array) $row)->all();
            foreach ($before[$table] as $i => $row) {
                $this->assertSame($row, array_intersect_key($after[$i], $row));
            }
            $this->assertCount(count($before[$table]), $after);
        }
        foreach ([$document, $released] as $record) {
            $this->assertNull($record->fresh()->released_at);
            $this->assertNull($record->fresh()->released_by);
        }
        $this->assertDatabaseCount('document_workflow_events', 0);
        $this->assertSame($claim->id, $document->fresh()->pending_claim_appointment_id);
        $this->assertSame($appointment->id, $document->fresh()->payment_appointment_id);
    }

    public function test_release_metadata_relationship_and_event_casts(): void
    {
        $event = $this->event();
        $document = DocumentRequest::create(['user_id' => $event->actor_id, 'document_type' => 'tor', 'fee' => 0]);
        $this->assertTrue($document->paymentSatisfied());
        $document->released_at = now();
        $document->released_by = $event->actor_id;
        $document->save();
        $this->assertInstanceOf(\Carbon\Carbon::class, $document->fresh()->released_at);
        $this->assertSame($event->actor_id, $document->fresh()->releaser->id);
        $this->assertSame(['ok' => true], $event->fresh()->outcome);
        $this->assertSame($event->actor_id, $event->actor->id);
        $this->assertFalse($document->isFillable('released_by'));
        $this->assertFalse($document->isFillable('released_at'));
    }

    public function test_database_rejects_duplicate_operation_keys(): void
    {
        $event = $this->event();
        $this->expectException(QueryException::class);
        $this->event(['operation_key' => $event->operation_key]);
    }

    public function test_database_rejects_unknown_event_actor(): void
    {
        $this->expectException(QueryException::class);
        $this->event(['actor_id' => 999999999]);
    }

    public function test_database_rejects_unknown_releasing_staff(): void
    {
        $event = $this->event();
        $document = DocumentRequest::create(['user_id' => $event->actor_id, 'document_type' => 'tor']);
        $this->expectException(QueryException::class);
        DB::table('document_requests')->where('id', $document->id)->update(['released_by' => 999999999]);
    }

    public function test_events_cannot_be_updated_or_deleted_through_models_or_sql(): void
    {
        $event = $this->event();
        foreach (['model_update', 'model_delete', 'sql_update', 'sql_delete'] as $operation) {
            try {
                match ($operation) {
                    'model_update' => $event->update(['action' => 'changed']),
                    'model_delete' => $event->delete(),
                    'sql_update' => DB::table('document_workflow_events')->where('id', $event->id)->update(['action' => 'changed']),
                    'sql_delete' => DB::table('document_workflow_events')->where('id', $event->id)->delete(),
                };
                $this->fail('Append-only enforcement failed.');
            } catch (\LogicException|QueryException $exception) {
                $this->assertStringContainsString('append-only', $exception->getMessage());
            }
        }
        $this->assertSame('schema.fixture', $event->fresh()->action);
    }

    public function test_event_and_release_metadata_roll_back_together(): void
    {
        $appointment = Appointment::factory()->create();
        $document = DocumentRequest::create(['user_id' => $appointment->user_id, 'document_type' => 'tor']);
        try {
            DB::transaction(function () use ($document, $appointment) {
                $document->released_at = now();
                $document->released_by = $appointment->user_id;
                $document->save();
                $this->event(['document_request_ids' => [$document->id]]);
                throw new \RuntimeException('Injected failure');
            });
        } catch (\RuntimeException $exception) {
            $this->assertSame('Injected failure', $exception->getMessage());
        }
        $this->assertNull($document->fresh()->released_at);
        $this->assertNull($document->fresh()->released_by);
        $this->assertDatabaseCount('document_workflow_events', 0);
    }

    public function test_rollback_refuses_to_remove_recorded_history(): void
    {
        $this->event();
        $migration = require database_path('migrations/2026_11_25_000001_add_document_workflow_integrity.php');
        $this->expectException(\RuntimeException::class);
        $migration->down();
    }

    public function test_recorded_release_alone_prevents_schema_rollback(): void
    {
        $appointment = Appointment::factory()->create();
        $document = DocumentRequest::create(['user_id' => $appointment->user_id, 'document_type' => 'tor']);
        $document->released_at = now();
        $document->save();
        $migration = require database_path('migrations/2026_11_25_000001_add_document_workflow_integrity.php');
        $this->expectException(\RuntimeException::class);
        $migration->down();
    }

    public function test_event_actor_cannot_be_deleted(): void
    {
        $event = $this->event();
        $this->expectException(QueryException::class);
        DB::table('users')->where('id', $event->actor_id)->delete();
    }

    public function test_releasing_staff_cannot_be_deleted(): void
    {
        $owner = Appointment::factory()->create()->user;
        $staff = \App\Models\User::factory()->create();
        $document = DocumentRequest::create(['user_id' => $owner->id, 'document_type' => 'tor']);
        $document->released_by = $staff->id;
        $document->save();
        $this->expectException(QueryException::class);
        DB::table('users')->where('id', $staff->id)->delete();
    }
}
