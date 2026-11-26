<?php

namespace Tests\Feature;

use App\Mail\WorkflowUpdate;
use App\Models\Appointment;
use App\Models\DocumentRequest;
use App\Models\DocumentWorkflowEvent;
use App\Models\Setting;
use App\Models\UserNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\SeedsRoles;
use Tests\TestCase;

class DocumentReadyNotificationTest extends TestCase
{
    use SeedsRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('dtc_testing', DB::selectOne('SELECT DATABASE() AS name')->name);
        $this->artisan('migrate:fresh')->assertExitCode(0);
        $this->beforeApplicationDestroyed(function () {
            if (DB::transactionLevel()) {
                DB::rollBack();
            }
            \Illuminate\Foundation\Testing\RefreshDatabaseState::$migrated = false;
        });
        $this->seedRoles();
        Mail::fake();
    }

    private function document(string $role = 'student', array $attributes = []): DocumentRequest
    {
        return DocumentRequest::create(array_merge(['user_id' => $this->makeUserWithRole($role)->id,
            'document_type' => 'tor', 'copies' => 1, 'status' => 'processing', 'fee' => 50,
            'payment_method' => 'walk_in', 'payment_status' => 'unpaid'], $attributes));
    }

    public function test_both_owners_receive_one_durable_ready_notice_and_tracked_email(): void
    {
        Setting::set('registrar_hours', 'Published office hours');
        Setting::set('registrar_email', 'registrar@example.test');
        $registrar = $this->makeUserWithRole('registrar');
        foreach (['student', 'alumni'] as $role) {
            $document = $this->document($role);
            $body = ['status' => 'ready', 'operation_key' => (string) Str::uuid(), 'remarks' => 'Staff-only fixture note'];
            $this->actingAs($registrar)->post(route('registrar.documents.status', $document), $body)->assertSessionHasNoErrors();
            $event = DocumentWorkflowEvent::where('action', 'document.ready')->where('operation_key', $body['operation_key'])->sole();
            $this->assertSame([$document->id], $event->document_request_ids);
            $this->assertSame($registrar->id, $event->actor_id);
            $this->assertSame('processing', $event->before['status']);
            $this->assertSame('ready', $event->after['status']);
            $notice = UserNotification::where('user_id', $document->user_id)->where('event_key', 'document-ready:'.$document->id)->sole();
            $this->assertSame('queued', $notice->email_status);
            $this->assertSame(route('portal.documents.index'), $notice->link);
            $this->assertStringContainsString('Published office hours', $notice->message);
            $this->assertStringNotContainsString('Staff-only fixture note', $notice->message);
            $notice->markAsRead();
            $this->post(route('registrar.documents.status', $document), $body)->assertSessionHasNoErrors();
            $this->assertNotNull($notice->fresh()->read_at);
            $this->post(route('registrar.documents.status', $document), array_replace($body, ['remarks' => 'Different']))->assertSessionHasErrors('operation_key');
            $this->assertSame(1, UserNotification::where('user_id', $document->user_id)->where('event_key', 'document-ready:'.$document->id)->count());
            $notice->delete();
            $this->post(route('registrar.documents.status', $document), $body)->assertSessionHasNoErrors();
            $this->assertSame(0, UserNotification::where('user_id', $document->user_id)->count());
        }
        Mail::assertQueued(WorkflowUpdate::class, 2);
        Mail::assertQueued(\App\Mail\DocumentRequestUpdate::class, 0);
    }

    public function test_email_waits_for_outer_commit_and_rollback_removes_all_ready_writes(): void
    {
        $document = $this->document();
        $body = ['status' => 'ready', 'operation_key' => (string) Str::uuid()];
        $this->actingAs($this->makeUserWithRole('registrar'));
        DB::beginTransaction();
        $this->post(route('registrar.documents.status', $document), $body)->assertSessionHasNoErrors();
        Mail::assertNothingQueued();
        DB::rollBack();
        $this->assertSame('processing', $document->fresh()->status);
        $this->assertDatabaseCount('document_workflow_events', 0);
        $this->assertDatabaseCount('user_notifications', 0);
        DB::beginTransaction();
        $this->post(route('registrar.documents.status', $document), $body)->assertSessionHasNoErrors();
        Mail::assertNothingQueued();
        DB::commit();
        Mail::assertQueued(WorkflowUpdate::class, 1);
    }

    public function test_notification_failure_rolls_back_and_same_command_can_retry(): void
    {
        $document = $this->document();
        $body = ['status' => 'ready', 'operation_key' => (string) Str::uuid()];
        UserNotification::creating(fn () => throw new \RuntimeException('Notice persistence failed'));
        try {
            $this->withoutExceptionHandling()->actingAs($this->makeUserWithRole('registrar'))->post(route('registrar.documents.status', $document), $body);
            $this->fail('Expected persistence failure');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Notice persistence failed', $exception->getMessage());
        } finally {
            UserNotification::flushEventListeners();
            UserNotification::clearBootedModels();
        }
        $this->assertSame('processing', $document->fresh()->status);
        $this->assertDatabaseCount('document_workflow_events', 0);
        $this->assertDatabaseCount('audit_logs', 0);
        Mail::assertNothingQueued();
        $this->post(route('registrar.documents.status', $document), $body)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('document_workflow_events', 1);
    }

    public function test_enqueue_failure_preserves_ready_and_existing_retry_command_is_idempotent(): void
    {
        $document = $this->document('alumni');
        Mail::shouldReceive('to')->once()->andThrow(new \RuntimeException('Queue unavailable'));
        $body = ['status' => 'ready', 'operation_key' => (string) Str::uuid()];
        $this->actingAs($this->makeUserWithRole('registrar'))->post(route('registrar.documents.status', $document), $body)->assertSessionHasNoErrors();
        $notice = UserNotification::where('user_id', $document->user_id)->sole();
        $this->assertSame('ready', $document->fresh()->status);
        $this->assertSame('enqueue_failed', $notice->email_status);
        Mail::fake();
        $this->artisan('workflow-emails:retry', ['notification' => $notice->id])->assertExitCode(0);
        $this->artisan('workflow-emails:retry', ['notification' => $notice->id])->assertExitCode(1);
        $this->post(route('registrar.documents.status', $document), $body)->assertSessionHasNoErrors();
        Mail::assertQueued(WorkflowUpdate::class, 1);
        $this->assertDatabaseCount('document_workflow_events', 1);
    }

    public function test_missing_invalid_email_is_safe_and_can_retry_after_correction(): void
    {
        foreach (['', 'invalid-email'] as $email) {
            $document = $this->document();
            $document->user->update(['email' => $email]);
            $this->actingAs($this->makeUserWithRole('registrar'))->post(route('registrar.documents.status', $document), ['status' => 'ready', 'operation_key' => (string) Str::uuid()])->assertSessionHasNoErrors();
            $notice = UserNotification::where('user_id', $document->user_id)->sole();
            $this->assertSame('invalid_recipient', $notice->email_status);
            $this->assertSame('ready', $document->fresh()->status);
            Mail::assertNothingQueued();
            $document->user->update(['email' => 'corrected-'.$document->id.'@example.test']);
            $this->artisan('workflow-emails:retry', ['notification' => $notice->id])->assertExitCode(0);
            Mail::assertQueued(WorkflowUpdate::class, 1);
            Mail::fake();
        }
    }

    public function test_new_claiming_instructions_zero_fee_and_payment_states_and_legacy_history(): void
    {
        $registrar = $this->makeUserWithRole('registrar');
        foreach ([['fee' => 0], ['payment_status' => 'pending_verification', 'payment_method' => 'gcash_qr'], ['is_paid' => true, 'payment_status' => 'verified']] as $attributes) {
            $document = $this->document('alumni', $attributes);
            $appointment = Appointment::factory()->create(['user_id' => $document->user_id, 'visit_type' => 'submission']);
            $document->appointment()->associate($appointment);
            $document->save();
            $appointment->documentItems()->create(['document_request_id' => $document->id]);
            $origin = $document->payment_appointment_id;
            $claim = Appointment::factory()->create(['user_id' => $document->user_id, 'visit_type' => 'claiming']);
            $document->pending_claim_appointment_id = $claim->id;
            $document->save();
            $claim->documentItems()->create(['document_request_id' => $document->id]);
            $this->actingAs($registrar)->post(route('registrar.documents.status', $document), ['status' => 'ready', 'operation_key' => (string) Str::uuid()])->assertSessionHasNoErrors();
            $notice = UserNotification::where('user_id', $document->user_id)->sole();
            $this->assertStringContainsString('No claiming appointment is required', $notice->message);
            $this->assertStringContainsString('Bring your claim stub if one was issued and your receipt if payment was required', $notice->message);
            $this->assertSame($origin, $document->fresh()->payment_appointment_id);
            $this->assertNull($document->fresh()->released_at);
            $this->assertSame($claim->id, $document->fresh()->pending_claim_appointment_id);
            $this->assertSame(2, $document->appointmentItems()->count());
            $this->assertStringContainsString($document->paymentNotRequired() ? 'No Payment Required' : ($document->paymentSatisfied() ? 'payment is verified' : 'Cashier is still verifying'), $notice->message);
        }
    }

    public function test_event_write_failure_rolls_back_status_notices_and_audit(): void
    {
        $document = $this->document();
        DocumentWorkflowEvent::creating(fn () => throw new \RuntimeException('Ledger unavailable'));
        try {
            $this->withoutExceptionHandling()->actingAs($this->makeUserWithRole('registrar'))->post(route('registrar.documents.status', $document), ['status' => 'ready']);
            $this->fail('Expected ledger failure');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Ledger unavailable', $exception->getMessage());
        } finally {
            DocumentWorkflowEvent::flushEventListeners();
            DocumentWorkflowEvent::clearBootedModels();
        }
        $this->assertSame('processing', $document->fresh()->status);
        $this->assertDatabaseCount('user_notifications', 0);
        $this->assertDatabaseCount('audit_logs', 0);
        Mail::assertNothingQueued();
    }

    public function test_unrelated_updates_and_historical_ready_rows_do_not_create_ready_events(): void
    {
        $document = $this->document('student', ['status' => 'submitted']);
        $this->actingAs($this->makeUserWithRole('registrar'))->post(route('registrar.documents.status', $document), ['status' => 'processing'])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('document_workflow_events', 0);
        $document->update(['status' => 'ready']);
        $this->post(route('registrar.documents.status', $document), ['status' => 'ready'])->assertSessionHasErrors('status');
        $this->assertDatabaseCount('document_workflow_events', 0);
        $this->call('POST', route('registrar.documents.status', $document), ['status' => 'ready'])->assertSessionHasErrors('operation_key');
        Mail::assertQueued(WorkflowUpdate::class, 0);
    }

    public function test_other_roles_cannot_mark_ready_and_other_owners_cannot_read_notice(): void
    {
        $document = $this->document();
        foreach (['student', 'alumni', 'cashier', 'new_applicant'] as $role) {
            $this->actingAs($this->makeUserWithRole($role))->post(route('registrar.documents.status', $document), ['status' => 'ready', 'operation_key' => (string) Str::uuid()])->assertForbidden();
        }
        $this->actingAs($this->makeUserWithRole('registrar'))->post(route('registrar.documents.status', $document), ['status' => 'ready', 'operation_key' => (string) Str::uuid()])->assertSessionHasNoErrors();
        $notice = UserNotification::where('user_id', $document->user_id)->sole();
        $this->actingAs($this->makeUserWithRole('student'))->get(route('notifications.index'))->assertOk()->assertDontSee('Transcript of Records');
        $this->assertNull($notice->fresh()->read_at);
    }
}
