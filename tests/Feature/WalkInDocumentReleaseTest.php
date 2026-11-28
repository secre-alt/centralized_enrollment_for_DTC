<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\DocumentRequest;
use App\Models\DocumentWorkflowEvent;
use App\Models\UserNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\SeedsRoles;
use Tests\TestCase;

class WalkInDocumentReleaseTest extends TestCase
{
    use SeedsRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('dtc_testing', DB::selectOne('SELECT DATABASE() AS name')->name);
        $this->artisan('migrate:fresh')->assertExitCode(0);
        $this->beforeApplicationDestroyed(fn () => \Illuminate\Foundation\Testing\RefreshDatabaseState::$migrated = false);
        $this->seedRoles();
        Mail::fake();
    }

    private function document($owner, array $attributes = []): DocumentRequest
    {
        return DocumentRequest::create($attributes + ['user_id' => $owner->id, 'document_type' => 'tor', 'copies' => 1, 'fee' => 0, 'status' => 'ready']);
    }

    private function handover($owner, array $ids, array $overrides = [])
    {
        return $this->post('/registrar/documents/walk-in-release', $overrides + ['claimant_id' => $owner->id,
            'document_ids' => $ids, 'handover_confirmed' => '1', 'operation_key' => (string) Str::uuid()]);
    }

    public function test_ready_cards_offer_release_only_to_registrars_when_requirements_are_satisfied(): void
    {
        $owner = $this->makeUserWithRole('student');
        $this->document($owner, ['fee' => 50, 'payment_status' => 'verified', 'is_paid' => true]);
        $this->document($owner, ['fee' => 50, 'payment_status' => 'unpaid']);
        $this->actingAs($this->makeUserWithRole('registrar'))
            ->get(route('registrar.documents.index'))->assertOk()
            ->assertSee('Release documents')->assertSee('Awaiting payment verification')
            ->assertSee('data-target="#releaseModal'.$owner->id.'"', false);
        $this->actingAs($this->makeUserWithRole('admin'))
            ->get(route('registrar.documents.index'))->assertOk()
            ->assertSee('Release documents')->assertDontSee('Registrar records release');
    }

    public function test_paid_student_request_changes_to_released_only_after_confirmed_handover(): void
    {
        $student = $this->makeUserWithRole('student');
        $registrar = $this->makeUserWithRole('admin');
        $document = $this->document($student, ['fee' => 20, 'payment_status' => 'verified', 'is_paid' => true]);
        $this->actingAs($student)->get(route('portal.documents.index'))->assertOk()
            ->assertSee('Document progress: Ready')->assertSee('Ready for pickup');
        $this->actingAs($registrar)->get(route('registrar.documents.index', ['claimant_id' => $student->id]))
            ->assertOk()->assertSee('data-release-form', false);
        $this->assertSame('ready', $document->fresh()->status);
        $this->handover($student, [$document->id])->assertRedirect()->assertSessionHasNoErrors();
        $document->refresh();
        $this->assertSame('released', $document->status);
        $this->assertSame($registrar->id, $document->released_by);
        $this->assertNotNull($document->released_at);
        $this->assertSame('verified', $document->payment_status);
        $this->assertTrue($document->is_paid);
        $this->actingAs($student)->get(route('portal.documents.index'))->assertOk()
            ->assertSee('Document progress: Released')->assertSee('Physically released')
            ->assertSee('data-group="released"', false)->assertDontSee('data-group="ready"', false);
    }

    public function test_student_and_alumni_can_collect_exact_documents_without_an_appointment(): void
    {
        $registrar = $this->makeUserWithRole('registrar');
        $this->actingAs($registrar);
        foreach (['student', 'alumni'] as $role) {
            $owner = $this->makeUserWithRole($role);
            $documents = collect(range(1, 3))->map(fn () => $this->document($owner));
            $ids = $documents->take(2)->pluck('id')->all();
            $key = (string) Str::uuid();
            $this->handover($owner, $ids, ['operation_key' => $key])->assertRedirect()->assertSessionHasNoErrors();
            foreach ($documents->take(2) as $document) {
                $this->assertSame('released', $document->fresh()->status);
                $this->assertSame($registrar->id, $document->fresh()->released_by);
                $this->assertNotNull($document->fresh()->released_at);
                $this->assertNull($document->fresh()->appointment_id);
                $this->assertNull($document->fresh()->payment_appointment_id);
            }
            $this->assertSame('ready', $documents->last()->fresh()->status);
            $this->assertNull($documents->last()->fresh()->released_at);
            $event = DocumentWorkflowEvent::where('operation_key', $key)->sole();
            $this->assertSame($ids, $event->document_request_ids);
            $this->assertSame($registrar->id, $event->actor_id);
            $this->assertSame(2, UserNotification::where('user_id', $owner->id)->count());
            $this->handover($owner, $ids, ['operation_key' => $key])->assertSessionHasNoErrors();
            $this->assertSame(2, UserNotification::where('user_id', $owner->id)->count());
            $this->handover($owner, [$ids[0]], ['operation_key' => $key])->assertSessionHasErrors('operation_key');
            $this->handover($owner, $ids)->assertSessionHasErrors('document_ids');
        }
        $this->assertDatabaseCount('appointments', 0);
        $this->assertDatabaseCount('appointment_payments', 0);
        Mail::assertQueued(\App\Mail\WorkflowUpdate::class, 4);
    }

    public function test_active_historical_reservations_are_reconciled_only_for_collected_items(): void
    {
        $owner = $this->makeUserWithRole('alumni');
        $submission = Appointment::factory()->create(['user_id' => $owner->id, 'visit_type' => 'submission']);
        $claim = Appointment::factory()->create(['user_id' => $owner->id, 'visit_type' => 'claiming']);
        $documents = collect(range(1, 2))->map(function () use ($owner, $submission, $claim) {
            $document = $this->document($owner, ['appointment_id' => $submission->id]);
            $document->pending_claim_appointment_id = $claim->id;
            $document->save();
            $claim->documentItems()->create(['document_request_id' => $document->id]);

            return $document;
        });
        $this->actingAs($this->makeUserWithRole('registrar'));
        foreach ($documents as $index => $document) {
            $this->handover($owner, [$document->id])->assertSessionHasNoErrors();
            $this->assertNull($document->fresh()->pending_claim_appointment_id);
            $this->assertSame($submission->id, $document->fresh()->appointment_id);
            $this->assertSame($submission->id, $document->fresh()->payment_appointment_id);
            $this->assertNotNull($claim->documentItems()->where('document_request_id', $document->id)->sole()->released_at);
            $this->assertSame($index === 0 ? 'pending' : 'completed', $claim->fresh()->status);
            $this->assertSame($index + 1, $claim->fresh()->lifecycle_version);
            if ($index === 0) {
                $this->assertSame($claim->id, $documents[1]->fresh()->pending_claim_appointment_id);
            }
        }
        $this->assertDatabaseCount('appointment_lifecycle_events', 2);
        $this->assertDatabaseCount('appointment_document_items', 2);
    }

    public function test_invalid_selected_document_rolls_back_every_document_and_reservation(): void
    {
        $owner = $this->makeUserWithRole('student');
        $valid = $this->document($owner);
        $this->actingAs($this->makeUserWithRole('registrar'));
        foreach ([['status' => 'processing'], ['status' => 'cancelled'], ['status' => 'released'], ['fee' => 50, 'payment_status' => 'pending_verification'], ['user_id' => $this->makeUserWithRole('alumni')->id]] as $attributes) {
            $invalid = $this->document($owner, $attributes);
            $this->handover($owner, [$valid->id, $invalid->id])->assertSessionHasErrors('document_ids');
            $this->assertSame('ready', $valid->fresh()->status);
        }
        $this->assertDatabaseCount('document_workflow_events', 0);
        $this->assertDatabaseCount('user_notifications', 0);
    }

    public function test_closed_or_conflicting_historical_reservations_require_review(): void
    {
        $owner = $this->makeUserWithRole('alumni');
        $this->actingAs($this->makeUserWithRole('registrar'));
        foreach (['cancelled', 'completed', 'pending'] as $status) {
            $claim = Appointment::factory()->create(['user_id' => $owner->id, 'visit_type' => 'claiming', 'status' => $status]);
            $document = $this->document($owner);
            $document->pending_claim_appointment_id = $claim->id;
            $document->save();
            if ($status !== 'pending') {
                $claim->documentItems()->create(['document_request_id' => $document->id]);
            }
            $this->handover($owner, [$document->id])->assertSessionHasErrors('document_ids');
            $this->assertSame($claim->id, $document->fresh()->pending_claim_appointment_id);
            $this->assertSame('ready', $document->fresh()->status);
            $this->assertSame($status, $claim->fresh()->status);
        }
    }

    public function test_confirmation_and_registrar_role_are_required(): void
    {
        $owner = $this->makeUserWithRole('student');
        $document = $this->document($owner);
        foreach (['student', 'alumni', 'cashier'] as $role) {
            $this->actingAs($this->makeUserWithRole($role));
            $this->handover($owner, [$document->id])->assertForbidden();
        }
        $this->actingAs($this->makeUserWithRole('registrar'));
        $this->handover($owner, [$document->id], ['handover_confirmed' => '0'])->assertSessionHasErrors('handover_confirmed');
        $this->handover($owner, [$document->id], ['operation_key' => null])->assertSessionHasErrors('operation_key');
        $this->assertSame('ready', $document->fresh()->status);
    }

    public function test_new_claim_bookings_are_retired_but_owner_history_remains(): void
    {
        $owner = $this->makeUserWithRole('alumni');
        $document = $this->document($owner);
        $slot = \App\Models\AppointmentSlot::factory()->create();
        $body = ['document_ids' => [$document->id], 'appointment_slot_id' => $slot->id, 'operation_key' => (string) Str::uuid()];
        $this->actingAs($owner)->post(route('portal.claiming-appointments.store'), $body)->assertSessionHasErrors('appointment_slot_id');
        $this->post(route('portal.documents.pickup-appointment', $document), $body)->assertSessionHasErrors('appointment_slot_id');
        $this->get(route('portal.claiming-appointments.index'))->assertOk()->assertDontSee('Book a claiming appointment');
        $this->assertDatabaseCount('appointments', 0);
        $this->assertNull($document->fresh()->pending_claim_appointment_id);
    }

    public function test_event_failure_rolls_back_the_entire_handover(): void
    {
        $owner = $this->makeUserWithRole('student');
        $document = $this->document($owner);
        $this->actingAs($this->makeUserWithRole('registrar'));
        DocumentWorkflowEvent::creating(fn () => throw new \RuntimeException('Injected ledger failure'));
        try {
            $this->withoutExceptionHandling();
            $this->handover($owner, [$document->id]);
            $this->fail('Expected ledger failure');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Injected ledger failure', $exception->getMessage());
        } finally {
            DocumentWorkflowEvent::flushEventListeners();
            DocumentWorkflowEvent::clearBootedModels();
        }
        $this->assertSame('ready', $document->fresh()->status);
        $this->assertNull($document->fresh()->released_by);
        $this->assertDatabaseCount('user_notifications', 0);
        $this->assertDatabaseCount('audit_logs', 0);
        Mail::assertNothingQueued();
    }

    public function test_owner_history_ready_notice_and_release_email_retry_are_preserved(): void
    {
        $owner = $this->makeUserWithRole('student');
        $document = $this->document($owner);
        $this->document($owner, ['fee' => 50, 'payment_status' => 'pending_verification']);
        $ready = UserNotification::create(['user_id' => $owner->id, 'event_key' => 'document-ready:'.$document->id,
            'title' => 'Document Request Update', 'message' => 'Previously prepared', 'type' => 'info']);
        $ready->markAsRead();
        $key = (string) Str::uuid();
        $this->actingAs($this->makeUserWithRole('registrar'));
        $this->document($owner);
        $page = $this->get(route('registrar.documents.index', ['claimant_id' => $owner->id]))->assertOk()->assertSee('data-release-form', false)->assertSee('handover_confirmed', false);
        $directory = storage_path('framework/testing');
        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }
        file_put_contents($directory.'/walk-in-release-form.html', $page->getContent());
        $mailFake = Mail::getFacadeRoot();
        Mail::shouldReceive('to')->once()->andThrow(new \RuntimeException('Queue unavailable'));
        $this->handover($owner, [$document->id], ['operation_key' => $key])->assertSessionHasNoErrors();
        $notice = UserNotification::where('event_key', 'document-released:'.$document->id)->sole();
        $this->assertSame('enqueue_failed', $notice->email_status);
        $this->assertNotNull($ready->fresh()->read_at);
        $this->assertSame('released', $document->fresh()->status);
        Mail::swap($mailFake);
        $this->handover($owner, [$document->id], ['operation_key' => $key])->assertSessionHasNoErrors();
        Mail::assertQueued(\App\Mail\WorkflowUpdate::class, 1);
        $this->assertSame(2, UserNotification::where('user_id', $owner->id)->count());
        $this->actingAs($owner)->get(route('portal.documents.index'))->assertOk()->assertSee('Physically released')->assertSee('Released');
        $this->actingAs($this->makeUserWithRole('alumni'))->get(route('portal.documents.index'))->assertOk()->assertDontSee('D-'.$document->id);
    }

    public function test_partial_walk_in_collection_preserves_bundles_standalone_receipts_and_reports(): void
    {
        $owner = $this->makeUserWithRole('student');
        $cashier = $this->makeUserWithRole('cashier');
        $documents = collect();
        $payments = collect();
        $proofs = collect();
        foreach ([1, 2] as $number) {
            $origin = Appointment::factory()->create(['user_id' => $owner->id, 'visit_type' => 'submission']);
            $payment = \App\Models\AppointmentPayment::factory()->verified()->create(['appointment_id' => $origin->id,
                'is_document_bundle' => true, 'amount' => 50, 'receipt_no' => 'WALK-IN-BUNDLE-'.$number]);
            $payments->push($payment);
            $proofs->push(\App\Models\DocumentPaymentProof::create(['appointment_payment_id' => $payment->id,
                'path' => 'preserved-'.$number.'.png', 'status' => 'verified', 'submitted_at' => now(), 'reviewed_at' => now(), 'reviewed_by' => $cashier->id]));
            $documents->push($this->document($owner, ['appointment_id' => $origin->id, 'fee' => 50, 'is_paid' => true,
                'payment_status' => 'verified', 'amount_received' => 50, 'receipt_no' => $payment->receipt_no, 'verified_at' => now(), 'verified_by' => $cashier->id]));
        }
        $standalone = $this->document($owner, ['fee' => 30, 'is_paid' => true, 'payment_status' => 'verified', 'amount_received' => 30,
            'receipt_no' => 'WALK-IN-STANDALONE', 'verified_at' => now(), 'verified_by' => $cashier->id]);
        $paymentBefore = $payments->map(fn ($payment) => $payment->fresh()->getAttributes())->all();
        $proofBefore = $proofs->map(fn ($proof) => $proof->fresh()->getAttributes())->all();
        $history = app(\App\Services\TransactionHistory::class);
        $report = app(\App\Services\Reports\PaymentReportService::class);
        $filters = \App\Services\Reports\PaymentReportFilters::fromArray(['period' => 'today']);
        $reportBefore = $report->query($filters)->get()->toArray();
        $this->assertSame(13000, $history->summary($owner->id, 'student', [])['verified_cents']);
        $this->assertSame(3, $history->query($owner->id, 'student')->count());
        $this->actingAs($this->makeUserWithRole('registrar'));
        $this->handover($owner, [$documents[0]->id, $standalone->id])->assertSessionHasNoErrors();
        $this->assertSame('ready', $documents[1]->fresh()->status);
        $this->assertSame($paymentBefore, $payments->map(fn ($payment) => $payment->fresh()->getAttributes())->all());
        $this->assertSame($proofBefore, $proofs->map(fn ($proof) => $proof->fresh()->getAttributes())->all());
        $this->assertEquals($reportBefore, $report->query($filters)->get()->toArray());
        $this->assertSame(13000, $history->summary($owner->id, 'student', [])['verified_cents']);
        $this->assertSame(3, $history->query($owner->id, 'student')->count());
        $this->actingAs($owner)->get(route('portal.transactions.receipt', 'A-'.$payments[0]->id))->assertOk();
        $this->get(route('portal.transactions.receipt', 'D-'.$documents[0]->id))->assertNotFound();
        $this->get(route('portal.transactions.receipt', 'D-'.$standalone->id))->assertOk();
        $this->get(route('portal.transactions.export.pdf'))->assertOk();
        $this->get(route('portal.transactions.export.csv'))->assertOk();
    }

    public function test_manila_notification_formatting_does_not_change_stored_release_timestamps(): void
    {
        $timezone = date_default_timezone_get();
        try {
            config(['app.timezone' => 'UTC']);
            date_default_timezone_set('UTC');
            $this->travelTo(\Carbon\Carbon::parse('2026-11-24 00:00:00', 'UTC'));
            $owner = $this->makeUserWithRole('alumni');
            $first = $this->document($owner);
            $second = $this->document($owner);
            $this->actingAs($this->makeUserWithRole('registrar'));
            $this->handover($owner, [$first->id, $second->id])->assertSessionHasNoErrors();
            $this->assertSame($first->fresh()->getRawOriginal('released_at'), $second->fresh()->getRawOriginal('released_at'));
            $this->assertSame('2026-11-24 00:00:00', $first->fresh()->getRawOriginal('released_at'));
            $this->assertStringContainsString('8:00 AM', UserNotification::where('user_id', $owner->id)->firstOrFail()->message);
        } finally {
            $this->travelBack();
            date_default_timezone_set($timezone);
        }
    }

    public function test_pre_transition_completed_handover_command_replays_without_backfilling_history(): void
    {
        $owner = $this->makeUserWithRole('alumni');
        $registrar = $this->makeUserWithRole('registrar');
        $claim = Appointment::factory()->create(['user_id' => $owner->id, 'visit_type' => 'claiming', 'status' => 'completed']);
        $document = $this->document($owner, ['status' => 'released']);
        $url = '/registrar/claiming-appointments/'.$claim->id.'/release';
        $body = ['document_ids' => [$document->id], 'handover_confirmed' => 1, 'lifecycle_version' => 0, 'operation_key' => (string) Str::uuid()];
        $request = \Illuminate\Http\Request::create($url, 'POST', $body);
        \App\Models\AppointmentLifecycleEvent::create(['appointment_id' => $claim->id, 'actor_id' => $registrar->id,
            'operation_key' => $body['operation_key'], 'request_fingerprint' => app(\App\Services\AppointmentLifecycle::class)->fingerprint($request, 'claim.released'),
            'action' => 'claim.released', 'previous_version' => 0, 'resulting_version' => 1,
            'document_item_ids' => [], 'before' => [], 'after' => [], 'outcome' => [], 'created_at' => now()]);
        $this->actingAs($registrar)->post($url, $body)->assertSessionHasNoErrors()->assertRedirect();
        $this->assertNull($document->fresh()->released_at);
        $this->assertSame('released', $document->fresh()->status);
        $this->assertDatabaseCount('appointment_lifecycle_events', 1);
        $this->assertDatabaseCount('document_workflow_events', 0);
        $this->assertDatabaseCount('user_notifications', 0);
    }
}
