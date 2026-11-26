<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\AppointmentLifecycleEvent;
use App\Models\AppointmentSlot;
use App\Models\DocumentRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\SeedsRoles;
use Tests\TestCase;

class AppointmentLifecycleIntegrityTest extends TestCase
{
    use \Tests\SeedsHistoricalClaimingAppointments;
    use RefreshDatabase, SeedsRoles, \Tests\SeedsHistoricalDocumentAppointments;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    public function test_schema_defaults_leave_history_unclassified(): void
    {
        $appointment = Appointment::factory()->create();
        $document = DocumentRequest::create(['user_id' => $appointment->user_id, 'document_type' => 'tor', 'fee' => 0]);
        $item = $appointment->documentItems()->create(['document_request_id' => $document->id])->fresh();
        $this->assertSame(0, $appointment->fresh()->lifecycle_version);
        $this->assertNull($appointment->fresh()->previous_appointment_id);
        foreach (['resolution', 'resolved_at', 'resolved_by'] as $field) {
            $this->assertNull($item->$field);
        }
        $this->assertTrue(Schema::hasTable('appointment_lifecycle_events'));
        $this->assertDatabaseCount('appointment_lifecycle_events', 0);
        try {
            DB::table('appointment_document_items')->where('id', $item->id)->update(['resolved_by' => 999999999]);
            $this->fail('Unknown resolution staff must be rejected.');
        } catch (\Illuminate\Database\QueryException $exception) {
            $this->assertSame('23000', $exception->getCode());
        }
    }

    public function test_previous_appointment_is_unique_and_deletion_is_restricted(): void
    {
        $previous = Appointment::factory()->create();
        $next = Appointment::factory()->create();
        DB::table('appointments')->where('id', $next->id)->update(['previous_appointment_id' => $previous->id]);
        try {
            $previous->delete();
            $this->fail('Referenced history must not be deleted.');
        } catch (\Illuminate\Database\QueryException $exception) {
            $this->assertSame('23000', $exception->getCode());
        }
        $this->expectException(\Illuminate\Database\QueryException::class);
        $other = Appointment::factory()->create();
        DB::table('appointments')->where('id', $other->id)->update(['previous_appointment_id' => $previous->id]);
    }

    public function test_claim_booking_and_release_are_versioned_and_identical_retries_replay(): void
    {
        $owner = $this->makeUserWithRole('student');
        $document = DocumentRequest::create(['user_id' => $owner->id, 'document_type' => 'tor', 'fee' => 0, 'status' => 'ready']);
        $payload = ['operation_key' => (string) Str::uuid(), 'appointment_slot_id' => AppointmentSlot::factory()->create()->id,
            'document_ids' => [$document->id]];
        $this->actingAs($owner)->historicalClaiming($payload)->assertRedirect()->assertSessionHasNoErrors();
        $claim = Appointment::where('visit_type', 'claiming')->sole();
        $this->assertSame(1, $claim->lifecycle_version);
        $this->historicalClaiming($payload)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseCount('appointments', 1);
        $this->assertDatabaseCount('appointment_lifecycle_events', 1);
        $booked = AppointmentLifecycleEvent::sole();
        $this->assertNull($booked->before['documents'][0]['pending_claim_appointment_id']);
        $this->assertSame($claim->id, $booked->after['documents'][0]['pending_claim_appointment_id']);
        $payload['appointment_slot_id'] = AppointmentSlot::factory()->create()->id;
        $this->historicalClaiming($payload)->assertSessionHasErrors('operation_key');
        session()->forget('errors');
        $registrar = $this->makeUserWithRole('registrar');
        $release = ['operation_key' => (string) Str::uuid(), 'lifecycle_version' => 1,
            'document_ids' => [$document->id], 'handover_confirmed' => 1];
        $this->actingAs($registrar)->post('/registrar/claiming-appointments/'.$claim->id.'/release', $release)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(2, $claim->fresh()->lifecycle_version);
        $this->post('/registrar/claiming-appointments/'.$claim->id.'/release', $release)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseCount('appointment_lifecycle_events', 2);
        $event = AppointmentLifecycleEvent::where('action', 'claim.released')->sole();
        $this->assertSame(1, $event->previous_version);
        $this->assertSame(2, $event->resulting_version);
        $this->assertSame([$claim->documentItems()->sole()->id], $event->document_item_ids);
        $this->assertSame($registrar->id, $event->actor_id);
    }

    public function test_stale_and_missing_metadata_cannot_release_or_write_audit(): void
    {
        $owner = $this->makeUserWithRole('alumni');
        $claim = Appointment::factory()->create(['user_id' => $owner->id, 'visit_type' => 'claiming']);
        $document = DocumentRequest::create(['user_id' => $owner->id, 'document_type' => 'tor', 'fee' => 0, 'status' => 'ready']);
        $document->pending_claim_appointment_id = $claim->id;
        $document->save();
        $claim->documentItems()->create(['document_request_id' => $document->id]);
        $this->actingAs($this->makeUserWithRole('registrar'));
        $url = '/registrar/claiming-appointments/'.$claim->id.'/release';
        $payload = ['document_ids' => [$document->id], 'handover_confirmed' => 1];
        $this->call('POST', $url, $payload)->assertSessionHasErrors('operation_key');
        $this->call('POST', $url, $payload + ['operation_key' => (string) Str::uuid()])->assertSessionHasErrors('lifecycle_version');
        $this->post($url, $payload + ['operation_key' => (string) Str::uuid(), 'lifecycle_version' => 9])->assertSessionHasErrors('lifecycle_version');
        $this->assertDatabaseCount('appointment_lifecycle_events', 0);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'document_request.claim_released']);
        $this->assertSame('ready', $document->fresh()->status);
    }

    public function test_edit_confirmation_and_supported_cancellation_increment_once_and_reject_stale_commands(): void
    {
        $owner = $this->makeUserWithRole('student');
        $appointment = Appointment::factory()->create(['user_id' => $owner->id, 'document_type' => 'transcript']);
        $document = DocumentRequest::create(['user_id' => $owner->id, 'appointment_id' => $appointment->id, 'document_type' => 'tor', 'fee' => 0, 'status' => 'submitted']);
        $edit = ['operation_key' => (string) Str::uuid(), 'lifecycle_version' => 0,
            'appointment_slot_id' => AppointmentSlot::factory()->create()->id, 'document_type' => 'transcript', 'purpose' => 'Updated purpose'];
        $this->actingAs($owner)->put(route('portal.appointments.update', $appointment), $edit)->assertSessionHasNoErrors();
        $this->assertSame(1, $appointment->fresh()->lifecycle_version);
        $this->put(route('portal.appointments.update', $appointment), $edit)->assertSessionHasNoErrors();
        $this->put(route('portal.appointments.update', $appointment), array_replace($edit, ['operation_key' => (string) Str::uuid()]))->assertSessionHasErrors('lifecycle_version');
        $registrar = $this->makeUserWithRole('registrar');
        $confirm = ['operation_key' => (string) Str::uuid(), 'lifecycle_version' => 1];
        $this->actingAs($registrar)->post(route('registrar.appointments.confirm', $appointment), $confirm)->assertSessionHasNoErrors();
        $this->assertSame(2, $appointment->fresh()->lifecycle_version);
        $cancel = ['operation_key' => (string) Str::uuid(), 'lifecycle_version' => 2, 'remarks' => 'Existing supported cancellation'];
        $this->post(route('registrar.appointments.cancel', $appointment), $cancel)->assertSessionHasNoErrors();
        $this->post(route('registrar.appointments.cancel', $appointment), $cancel)->assertSessionHasNoErrors();
        $this->assertSame(3, $appointment->fresh()->lifecycle_version);
        $this->assertSame('cancelled', $document->fresh()->status);
        $this->assertSame([1, 2, 3], $appointment->lifecycleEvents()->orderBy('id')->pluck('resulting_version')->all());
    }

    public function test_legacy_pickup_is_idempotent_and_preserves_standalone_financial_fields(): void
    {
        $owner = $this->makeUserWithRole('alumni');
        $document = DocumentRequest::create(['user_id' => $owner->id, 'document_type' => 'tor', 'fee' => 50,
            'status' => 'ready', 'is_paid' => true, 'payment_status' => 'verified', 'amount_received' => 50, 'receipt_no' => 'LEGACY-INTEGRITY']);
        $payload = ['operation_key' => (string) Str::uuid(), 'appointment_slot_id' => AppointmentSlot::factory()->create()->id];
        $url = route('portal.documents.pickup-appointment', $document);
        $this->actingAs($owner)->post($url, $payload)->assertSessionHasErrors('appointment_slot_id');
        $this->post($url, $payload)->assertSessionHasErrors('appointment_slot_id');
        $this->assertDatabaseCount('appointments', 0);
        $this->assertDatabaseCount('appointment_lifecycle_events', 0);
        $this->assertSame('LEGACY-INTEGRITY', $document->fresh()->receipt_no);
        $this->assertSame('50.00', $document->fresh()->amount_received);
        $this->assertTrue($document->fresh()->paymentSatisfied());
        $this->assertDatabaseCount('appointment_document_items', 0);
    }

    public function test_event_failure_rolls_back_booking_and_retry_can_complete(): void
    {
        $owner = $this->makeUserWithRole('student');
        $document = DocumentRequest::create(['user_id' => $owner->id, 'document_type' => 'tor', 'fee' => 0, 'status' => 'ready']);
        $payload = ['operation_key' => (string) Str::uuid(), 'appointment_slot_id' => AppointmentSlot::factory()->create()->id, 'document_ids' => [$document->id]];
        $this->actingAs($owner)->withoutExceptionHandling();
        AppointmentLifecycleEvent::created(fn () => throw new \RuntimeException('Injected lifecycle event failure'));
        try {
            try {
                $this->historicalClaiming($payload);
                $this->fail('An event failure must abort the transaction.');
            } catch (\RuntimeException $exception) {
                $this->assertSame('Injected lifecycle event failure', $exception->getMessage());
            }
        } finally {
            \Illuminate\Support\Facades\Event::forget('eloquent.created: '.AppointmentLifecycleEvent::class);
            $this->withExceptionHandling();
        }
        $this->assertDatabaseCount('appointments', 0);
        $this->assertDatabaseCount('appointment_document_items', 0);
        $this->assertDatabaseCount('appointment_lifecycle_events', 0);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'appointment.claiming_booked']);
        $this->assertNull($document->fresh()->pending_claim_appointment_id);
        $this->historicalClaiming($payload)->assertSessionHasNoErrors();
        $this->assertSame(1, Appointment::sole()->lifecycle_version);
    }

    public function test_lifecycle_events_cannot_be_rewritten_through_model_or_sql(): void
    {
        $owner = $this->makeUserWithRole('alumni');
        $this->actingAs($owner)->historicalSubmission( ['appointment_slot_id' => AppointmentSlot::factory()->create()->id,
            'document_type' => 'tor', 'copies' => 1, 'payment_method' => 'walk_in'], false)->assertSessionHasNoErrors();
        $event = AppointmentLifecycleEvent::sole();
        try {
            $event->update(['reason' => 'Rewrite']);
            $this->fail('Model events must be append-only.');
        } catch (\LogicException $exception) {
            $this->assertStringContainsString('append-only', $exception->getMessage());
        }
        foreach (['update', 'delete'] as $action) {
            try {
                $query = DB::table('appointment_lifecycle_events')->where('id', $event->id);
                $action === 'update' ? $query->update(['reason' => 'Rewrite']) : $query->delete();
                $this->fail('SQL events must be append-only.');
            } catch (\Illuminate\Database\QueryException $exception) {
                $this->assertSame('45000', $exception->getCode());
            }
        }
        $this->assertNull($event->fresh()->reason);
        try {
            (require database_path('migrations/2026_11_24_000001_add_appointment_lifecycle_integrity.php'))->down();
            $this->fail('Populated lifecycle history must block schema rollback.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('Lifecycle integrity data exists', $exception->getMessage());
        }
        $this->assertDatabaseCount('appointment_lifecycle_events', 1);
    }

    public function test_version_write_failure_rolls_back_handover_items_and_audits(): void
    {
        $owner = $this->makeUserWithRole('alumni');
        $claim = Appointment::factory()->create(['user_id' => $owner->id, 'visit_type' => 'claiming']);
        $document = DocumentRequest::create(['user_id' => $owner->id, 'document_type' => 'tor', 'fee' => 0, 'status' => 'ready']);
        $document->pending_claim_appointment_id = $claim->id;
        $document->save();
        $item = $claim->documentItems()->create(['document_request_id' => $document->id]);
        $this->actingAs($this->makeUserWithRole('registrar'))->withoutExceptionHandling();
        DB::listen(function ($query) {
            if (str_starts_with($query->sql, 'update `appointments` set `lifecycle_version`')) {
                throw new \RuntimeException('Injected version write failure');
            }
        });
        try {
            try {
                $this->post('/registrar/claiming-appointments/'.$claim->id.'/release', ['document_ids' => [$document->id], 'handover_confirmed' => 1]);
                $this->fail('A version write failure must abort handover.');
            } catch (\RuntimeException $exception) {
                $this->assertSame('Injected version write failure', $exception->getMessage());
            }
        } finally {
            \Illuminate\Support\Facades\Event::forget(\Illuminate\Database\Events\QueryExecuted::class);
            $this->withExceptionHandling();
        }
        $this->assertSame(0, $claim->fresh()->lifecycle_version);
        $this->assertSame('pending', $claim->fresh()->status);
        $this->assertSame('ready', $document->fresh()->status);
        $this->assertSame($claim->id, $document->fresh()->pending_claim_appointment_id);
        $this->assertNull($item->fresh()->released_at);
        $this->assertDatabaseCount('appointment_lifecycle_events', 0);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'document_request.claim_released']);
    }

    public function test_legacy_walk_in_release_preserves_original_appointment_without_fabricating_items(): void
    {
        $owner = $this->makeUserWithRole('alumni');
        $appointment = Appointment::factory()->create(['user_id' => $owner->id, 'status' => 'confirmed']);
        $document = DocumentRequest::create(['user_id' => $owner->id, 'appointment_id' => $appointment->id,
            'document_type' => 'tor', 'fee' => 0, 'status' => 'ready']);
        $this->actingAs($this->makeUserWithRole('registrar'));
        $payload = ['operation_key' => (string) Str::uuid(), 'claimant_id' => $owner->id, 'document_ids' => [$document->id], 'handover_confirmed' => '1'];
        $this->post(route('registrar.documents.walk-in-release'), $payload)->assertSessionHasNoErrors();
        $this->post(route('registrar.documents.walk-in-release'), $payload)->assertSessionHasNoErrors();
        $this->assertSame(0, $appointment->fresh()->lifecycle_version);
        $this->assertSame('released', $document->fresh()->status);
        $this->assertNotNull($document->fresh()->released_at);
        $this->assertSame('confirmed', $appointment->fresh()->status);
        $this->assertDatabaseCount('appointment_document_items', 0);
        $this->assertDatabaseCount('appointment_lifecycle_events', 0);
        $this->assertDatabaseCount('document_workflow_events', 1);
    }

    public function test_malformed_upload_cannot_crash_command_fingerprinting(): void
    {
        $this->actingAs($this->makeUserWithRole('student'))->post(route('portal.appointments.store'), [
            'appointment_slot_id' => AppointmentSlot::factory()->create()->id,
            'documents' => [['document_type' => 'diploma', 'copies' => 1]], 'payment_method' => 'walk_in',
            'proof_of_payment' => [\Illuminate\Http\UploadedFile::fake()->image('nested.png')],
        ], ['Accept' => 'application/json'])->assertUnprocessable();
        $this->assertDatabaseCount('appointment_lifecycle_events', 0);
        $this->assertDatabaseCount('appointments', 0);
    }

    public function test_operation_fingerprint_distinguishes_null_from_false(): void
    {
        $this->actingAs($this->makeUserWithRole('student'));
        $payload = ['operation_key' => (string) Str::uuid(), 'appointment_slot_id' => AppointmentSlot::factory()->create()->id,
            'documents' => [['document_type' => 'diploma', 'copies' => 1]], 'payment_method' => 'walk_in', 'purpose' => null];
        $this->post(route('portal.appointments.store'), $payload)->assertSessionHasNoErrors();
        $payload['purpose'] = false;
        $this->post(route('portal.appointments.store'), $payload)->assertSessionHasErrors('operation_key');
        $this->assertDatabaseCount('document_workflow_events', 1);
        $this->assertDatabaseCount('appointments', 0);
    }

    public function test_legacy_pickup_cannot_replace_a_new_submission_relationship(): void
    {
        $owner = $this->makeUserWithRole('alumni');
        $submission = Appointment::factory()->create(['user_id' => $owner->id, 'visit_type' => 'submission', 'status' => 'cancelled']);
        $document = DocumentRequest::create(['user_id' => $owner->id, 'appointment_id' => $submission->id,
            'document_type' => 'tor', 'status' => 'ready', 'fee' => 0]);
        $submission->documentItems()->create(['document_request_id' => $document->id]);
        $this->actingAs($owner)->post(route('portal.documents.pickup-appointment', $document), [
            'appointment_slot_id' => AppointmentSlot::factory()->create()->id,
        ])->assertSessionHasErrors('appointment_slot_id');
        $this->assertSame($submission->id, $document->fresh()->appointment_id);
        $this->assertSame($submission->id, $document->fresh()->payment_appointment_id);
        $this->assertDatabaseCount('appointments', 1);
        $this->assertDatabaseCount('appointment_lifecycle_events', 0);
    }

    public function test_an_input_hash_cannot_masquerade_as_the_original_uploaded_file_on_replay(): void
    {
        $file = \Illuminate\Http\UploadedFile::fake()->image('proof.png');
        $payload = ['operation_key' => (string) Str::uuid(), 'appointment_slot_id' => AppointmentSlot::factory()->create()->id,
            'documents' => [['document_type' => 'diploma', 'copies' => 1]], 'payment_method' => 'walk_in', 'proof_of_payment' => $file];
        $this->actingAs($this->makeUserWithRole('student'))->post(route('portal.appointments.store'), $payload)->assertSessionHasNoErrors();
        $payload['proof_of_payment'] = ['sha256' => hash_file('sha256', $file->getRealPath())];
        $this->post(route('portal.appointments.store'), $payload)->assertSessionHasErrors('operation_key');
        $this->assertDatabaseCount('document_workflow_events', 1);
    }
}
