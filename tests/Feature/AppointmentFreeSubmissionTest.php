<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\AppointmentSlot;
use App\Models\DocumentRequest;
use App\Models\DocumentWorkflowEvent;
use App\Models\OfficeClosure;
use App\Models\Setting;
use App\Models\UserNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\SeedsRoles;
use Tests\TestCase;

class AppointmentFreeSubmissionTest extends TestCase
{
    use RefreshDatabase, SeedsRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('dtc_testing', DB::selectOne('SELECT DATABASE() AS name')->name);
        $this->seedRoles();
        Storage::fake('local');
    }

    public function test_both_request_forms_use_the_original_visual_styles_without_slot_inputs(): void
    {
        Setting::set('gcash_qr_path', 'qr.png');
        Setting::set('gcash_name', 'DTC Cashier (test)');
        Setting::set('gcash_number', '09170000000');
        Storage::disk('local')->put('qr.png', 'qr');
        foreach (['student' => 'portal.appointments.create', 'alumni' => 'portal.documents.create'] as $role => $route) {
            $response = $this->actingAs($this->makeUserWithRole($role))->get(route($route))
                ->assertOk()
                ->assertSee('css/document-request-flow.css', false)
                ->assertSee('css/booking-create.css', false)
                ->assertSee('dr-document-choice', false)
                ->assertSee('dr-slip-title', false)
                ->assertSee('data-document-submission', false)
                ->assertSee('documents[0][payment_method]', false)
                ->assertDontSee('name="appointment_slot_id"', false);
            $directory = storage_path('framework/testing');
            if (! is_dir($directory)) {
                mkdir($directory, 0755, true);
            }
            file_put_contents($directory.'/document-picker-'.$role.'.html', $response->getContent());
        }
    }

    public function test_both_portals_submit_without_appointments_and_ignore_scheduling_and_owner_input(): void
    {
        $slot = AppointmentSlot::factory()->create(['max_bookings' => 1]);
        Appointment::factory()->create(['appointment_slot_id' => $slot->id, 'status' => 'confirmed']);
        OfficeClosure::create(['start_date' => today(), 'end_date' => today()->addDays(30), 'title' => 'Closed', 'type' => 'office_closure', 'is_active' => true, 'is_confirmed' => true]);
        foreach (['student', 'alumni'] as $role) {
            $owner = $this->makeUserWithRole($role);
            $route = $role === 'alumni' ? 'portal.documents.store' : 'portal.appointments.store';
            $this->actingAs($owner)->post(route($route), ['documents' => [['document_type' => 'diploma', 'copies' => 2]],
                'payment_method' => 'walk_in', 'appointment_slot_id' => 999999, 'user_id' => 999999])->assertSessionHasNoErrors();
            $document = DocumentRequest::where('user_id', $owner->id)->sole();
            $this->assertNull($document->appointment_id);
            $this->assertNull($document->payment_appointment_id);
            $this->assertNull($document->pending_claim_appointment_id);
            $this->assertSame('200.00', $document->fee);
            $this->get(route('portal.documents.index'))->assertOk()->assertSee('D-'.$document->id);
            $this->get(route('portal.appointments.index'))->assertOk()->assertDontSee('D-'.$document->id.'<', false);
        }
        $this->assertDatabaseCount('appointments', 1);
        $this->assertDatabaseCount('appointment_document_items', 0);
        $this->assertDatabaseCount('appointment_payments', 0);
    }

    public function test_multiple_gcash_documents_have_distinct_proofs_and_independent_verification(): void
    {
        Setting::set('gcash_qr_path', 'qr.png');
        Storage::disk('local')->put('qr.png', 'qr');
        $owner = $this->makeUserWithRole('student');
        $this->actingAs($owner)->post(route('portal.appointments.store'), ['documents' => [
            ['document_type' => 'transcript', 'copies' => 1, 'payment_method' => 'gcash_qr', 'reference_number' => 'FIRST', 'proof_of_payment' => UploadedFile::fake()->image('first.png')],
            ['document_type' => 'diploma', 'copies' => 1, 'payment_method' => 'gcash_qr', 'reference_number' => 'SECOND', 'proof_of_payment' => UploadedFile::fake()->image('second.png', 20, 20)],
        ]])->assertSessionHasNoErrors();
        [$first, $second] = DocumentRequest::orderBy('id')->get()->all();
        $this->assertSame('tor', $first->document_type);
        $this->assertNotSame($first->proof_of_payment, $second->proof_of_payment);
        $this->assertCount(1, $first->paymentProofs);
        $this->assertCount(1, $second->paymentProofs);
        $this->actingAs($this->makeUserWithRole('cashier'))->post(route('cashier.document-payments.verify-gcash', $first), ['amount_received' => 50, 'receipt_no' => 'ONLY-FIRST'])->assertSessionHasNoErrors();
        $this->assertTrue($first->fresh()->paymentSatisfied());
        $this->assertFalse($second->fresh()->paymentSatisfied());
        $this->assertSame('pending_verification', $second->fresh()->payment_status);
        $this->actingAs($owner)->get(route('portal.transactions.receipt', 'D-'.$first->id))->assertOk();
        $this->get(route('portal.transactions.receipt', 'D-'.$second->id))->assertNotFound();
        $originalProof = $second->proof_of_payment;
        $this->actingAs($this->makeUserWithRole('cashier'))->post(route('cashier.document-payments.reject', $second), ['rejection_reason' => 'Please correct proof'])->assertSessionHasNoErrors();
        $this->actingAs($owner)->post(route('portal.appointments.document-payment.gcash', $second), [
            'reference_number' => 'CORRECTED', 'proof_of_payment' => UploadedFile::fake()->image('corrected.png', 30, 30),
        ])->assertSessionHasNoErrors();
        Storage::disk('local')->assertExists($originalProof);
        $this->assertSame(['rejected', 'pending'], $second->paymentProofs()->orderBy('id')->pluck('status')->all());
        $this->assertSame('ONLY-FIRST', $first->fresh()->receipt_no);
    }

    public function test_one_combined_proof_cannot_fund_multiple_documents(): void
    {
        $this->actingAs($this->makeUserWithRole('alumni'))->post(route('portal.documents.store'), [
            'documents' => [['document_type' => 'tor', 'copies' => 1], ['document_type' => 'diploma', 'copies' => 1]],
            'payment_method' => 'gcash_qr', 'reference_number' => 'COMBINED', 'proof_of_payment' => UploadedFile::fake()->image('combined.png'),
        ])->assertSessionHasErrors();
        $this->assertDatabaseCount('document_requests', 0);
        $this->assertDatabaseCount('document_payment_proofs', 0);
    }

    public function test_identical_retry_creates_one_set_and_conflicting_key_is_rejected(): void
    {
        $owner = $this->makeUserWithRole('alumni');
        $body = ['operation_key' => (string) Str::uuid(), 'document_type' => 'tor', 'copies' => 1, 'payment_method' => 'walk_in'];
        $this->actingAs($owner)->post(route('portal.documents.store'), $body)->assertSessionHasNoErrors();
        $this->post(route('portal.documents.store'), $body)->assertSessionHasNoErrors();
        $this->post(route('portal.documents.store'), array_replace($body, ['copies' => 2]))->assertSessionHasErrors('operation_key');
        $this->assertDatabaseCount('document_requests', 1);
        $this->assertDatabaseCount('document_workflow_events', 1);
    }

    public function test_event_failure_rolls_back_documents_and_new_uploads(): void
    {
        $this->makeUserWithRole('admin');
        $this->makeUserWithRole('registrar');
        Setting::set('gcash_qr_path', 'qr.png');
        Storage::disk('local')->put('qr.png', 'qr');
        DocumentWorkflowEvent::creating(fn () => throw new \RuntimeException('Injected event failure'));
        try {
            $this->withoutExceptionHandling()->actingAs($this->makeUserWithRole('alumni'))->post(route('portal.documents.store'), [
                'document_type' => 'tor', 'copies' => 1, 'payment_method' => 'gcash_qr', 'reference_number' => 'FAIL', 'proof_of_payment' => UploadedFile::fake()->image('proof.png'),
            ]);
            $this->fail('Expected event failure');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Injected event failure', $exception->getMessage());
        } finally {
            DocumentWorkflowEvent::flushEventListeners();
            DocumentWorkflowEvent::clearBootedModels();
        }
        $this->assertDatabaseCount('document_requests', 0);
        $this->assertDatabaseCount('user_notifications', 0);
        $this->assertSame([], Storage::disk('local')->allFiles('document-request-proofs'));
    }

    public function test_new_document_submissions_notify_staff_once_for_both_portals(): void
    {
        $admin = $this->makeUserWithRole('admin');
        $admin->assignRole('registrar');
        $registrar = $this->makeUserWithRole('registrar');
        $cashier = $this->makeUserWithRole('cashier');
        Setting::set('document_fee_certification', '0');
        Setting::set('gcash_qr_path', 'qr.png');
        Storage::disk('local')->put('qr.png', 'qr');
        foreach (['student', 'alumni'] as $role) {
            $owner = $this->makeUserWithRole($role);
            $route = $role === 'student' ? 'portal.appointments.store' : 'portal.documents.store';
            $payload = ['operation_key' => (string) Str::uuid(), 'documents' => [
                ['document_type' => 'diploma', 'copies' => 2, 'payment_method' => 'walk_in'],
                ['document_type' => 'certification', 'copies' => 1],
                ['document_type' => 'tor', 'copies' => 1, 'payment_method' => 'gcash_qr',
                    'reference_number' => 'NOTICE-'.$owner->id,
                    'proof_of_payment' => UploadedFile::fake()->image('proof-'.$owner->id.'.png')],
            ]];
            $this->actingAs($owner)->post(route($route), $payload)->assertSessionHasNoErrors();
            $records = DocumentRequest::where('user_id', $owner->id)->get();
            $this->assertCount(3, $records);
            foreach ($records as $record) {
                $notices = UserNotification::where('event_key', 'document-submitted:'.$record->id)->get();
                $this->assertEqualsCanonicalizing([$admin->id, $registrar->id], $notices->pluck('user_id')->all());
                foreach ($notices as $notice) {
                    $this->assertSame('New Document Request', $notice->title);
                    $this->assertStringContainsString($record->document_label, $notice->message);
                    $this->assertStringContainsString($owner->name, $notice->message);
                    $this->assertSame(route('registrar.documents.index', ['claimant_id' => $owner->id]), $notice->link);
                    $this->assertNull($notice->read_at);
                }
            }
            $count = UserNotification::count();
            $this->post(route($route), $payload)->assertSessionHasNoErrors();
            $this->assertSame($count, UserNotification::count());
            $this->assertDatabaseMissing('user_notifications', ['user_id' => $owner->id]);
        }
        $this->assertDatabaseMissing('user_notifications', ['user_id' => $cashier->id]);
        $this->actingAs($admin)->get(route('registrar.documents.index'))->assertOk();
    }

    public function test_standalone_cash_collections_reconcile_history_receipts_and_exports_for_both_roles(): void
    {
        foreach (['student', 'alumni'] as $role) {
            $owner = $this->makeUserWithRole($role);
            $cashier = $this->makeUserWithRole('cashier');
            $this->actingAs($owner)->post(route($role === 'student' ? 'portal.appointments.store' : 'portal.documents.store'), [
                'documents' => [['document_type' => 'diploma', 'copies' => 1], ['document_type' => 'certification', 'copies' => 1]], 'payment_method' => 'walk_in',
            ])->assertSessionHasNoErrors();
            $documents = DocumentRequest::where('user_id', $owner->id)->orderBy('id')->get();
            $this->actingAs($cashier)->get(route('cashier.document-payments.index'))->assertOk();
            foreach ($documents as $document) {
                $body = ['amount_received' => $document->fee, 'receipt_no' => 'SEPARATE-'.$document->id];
                $this->post(route('cashier.document-payments.verify-walk-in', $document), $body)->assertSessionHasNoErrors();
                $this->post(route('cashier.document-payments.verify-walk-in', $document), $body)->assertSessionHasErrors('payment');
            }
            $history = app(\App\Services\TransactionHistory::class);
            $this->assertSame(13000, $history->summary($owner->id, $role, [])['verified_cents']);
            $this->assertSame(2, $history->query($owner->id, $role)->count());
            $report = app(\App\Services\Reports\PaymentReportService::class)->query(
                \App\Services\Reports\PaymentReportFilters::fromArray(['period' => 'today']))->whereIn('event_key', $documents->map(fn ($document) => 'D-'.$document->id))->get();
            $this->assertCount(2, $report);
            $this->assertSame(13000, (int) $report->sum('cents'));
            $this->actingAs($owner);
            foreach ($documents as $document) {
                $this->get(route('portal.transactions.receipt', 'D-'.$document->id))->assertOk();
            }
            $this->get(route('portal.transactions.export.pdf'))->assertOk();
            $this->get(route('portal.transactions.export.csv'))->assertOk();
            $this->actingAs($this->makeUserWithRole($role))->get(route('portal.payments.document', $documents->first()))->assertForbidden();
            $this->get(route('portal.transactions.receipt', 'D-'.$documents->first()->id))->assertNotFound();
        }
        $this->assertDatabaseCount('appointment_payments', 0);
    }

    public function test_sparse_document_uploads_and_duplicate_proofs_are_handled_safely(): void
    {
        Setting::set('gcash_qr_path', 'qr.png');
        Storage::disk('local')->put('qr.png', 'qr');
        $owner = $this->makeUserWithRole('alumni');
        $this->actingAs($owner)->post(route('portal.documents.store'), ['documents' => [3 => [
            'document_type' => 'true_copy', 'copies' => 1, 'payment_method' => 'gcash_qr', 'reference_number' => 'SPARSE', 'proof_of_payment' => UploadedFile::fake()->image('sparse.png'),
        ]]])->assertSessionHasNoErrors();
        $this->assertNotNull(DocumentRequest::sole()->proof_of_payment);
        $proof = UploadedFile::fake()->image('same.png');
        $this->post(route('portal.documents.store'), ['documents' => [
            ['document_type' => 'tor', 'copies' => 1, 'payment_method' => 'gcash_qr', 'reference_number' => 'ONE', 'proof_of_payment' => $proof],
            ['document_type' => 'diploma', 'copies' => 1, 'payment_method' => 'gcash_qr', 'reference_number' => 'TWO', 'proof_of_payment' => $proof],
        ]])->assertSessionHasErrors();
        $this->assertDatabaseCount('document_requests', 1);
        $this->assertDatabaseCount('document_payment_proofs', 1);
    }

    public function test_nested_proof_does_not_hide_changed_document_input_on_retry(): void
    {
        Setting::set('gcash_qr_path', 'qr.png');
        Storage::disk('local')->put('qr.png', 'qr');
        $body = ['operation_key' => (string) Str::uuid(), 'documents' => [
            ['document_type' => 'diploma', 'copies' => 1, 'payment_method' => 'gcash_qr', 'reference_number' => 'NESTED', 'proof_of_payment' => UploadedFile::fake()->image('nested.png')],
        ]];
        $this->actingAs($this->makeUserWithRole('student'))->post(route('portal.appointments.store'), $body)->assertSessionHasNoErrors();
        $this->post(route('portal.appointments.store'), $body)->assertSessionHasNoErrors();
        $body['documents'][0]['copies'] = 2;
        $this->post(route('portal.appointments.store'), $body)->assertSessionHasErrors('operation_key');
        $this->assertDatabaseCount('document_requests', 1);
        $this->assertDatabaseCount('document_payment_proofs', 1);
    }

    public function test_historical_submission_key_cannot_create_a_second_standalone_request(): void
    {
        $owner = $this->makeUserWithRole('student');
        $appointment = Appointment::factory()->create(['user_id' => $owner->id]);
        $key = (string) Str::uuid();
        \App\Models\AppointmentLifecycleEvent::create(['appointment_id' => $appointment->id, 'actor_id' => $owner->id,
            'operation_key' => $key, 'request_fingerprint' => hash('sha256', 'old'), 'action' => 'submission.booked',
            'previous_version' => null, 'resulting_version' => 1, 'document_item_ids' => [], 'before' => [], 'after' => [], 'outcome' => [], 'created_at' => now()]);
        $this->actingAs($owner)->post(route('portal.appointments.store'), ['operation_key' => $key,
            'document_type' => 'diploma', 'payment_method' => 'walk_in'])->assertSessionHasErrors('operation_key');
        $this->assertDatabaseCount('document_requests', 0);
        $this->assertDatabaseCount('document_workflow_events', 0);
        $this->assertDatabaseCount('appointment_lifecycle_events', 1);
    }

    public function test_new_standalone_requests_cannot_use_legacy_pickup_or_direct_release_yet(): void
    {
        $owner = $this->makeUserWithRole('alumni');
        Setting::set('document_fee_tor', '0');
        $this->actingAs($owner)->post(route('portal.documents.store'), ['document_type' => 'tor', 'copies' => 1])->assertSessionHasNoErrors();
        $document = DocumentRequest::sole();
        $document->update(['status' => 'ready']);
        $this->post(route('portal.documents.pickup-appointment', $document), ['appointment_slot_id' => AppointmentSlot::factory()->create()->id])->assertSessionHasErrors('appointment_slot_id');
        $this->actingAs($this->makeUserWithRole('registrar'))->post(route('registrar.documents.status', $document), ['status' => 'released'])->assertSessionHasErrors('status');
        $this->assertSame('ready', $document->fresh()->status);
        $this->assertNull($document->fresh()->released_at);
        $this->assertDatabaseCount('appointments', 0);
    }

    public function test_zero_fee_and_invalid_inputs_cannot_create_paid_records(): void
    {
        Setting::set('document_fee_diploma', '0');
        $owner = $this->makeUserWithRole('student');
        $this->actingAs($owner)->post(route('portal.appointments.store'), ['documents' => [['document_type' => 'diploma', 'copies' => 2]],
            'is_paid' => true, 'payment_status' => 'verified', 'fee' => 500])->assertSessionHasNoErrors();
        $document = DocumentRequest::sole();
        $this->assertTrue($document->paymentSatisfied());
        $this->assertFalse($document->is_paid);
        $this->assertNull($document->receipt_no);
        $this->assertNull($document->proof_of_payment);
        foreach ([0, 11, 'invalid'] as $copies) {
            $this->post(route('portal.appointments.store'), ['documents' => [['document_type' => 'diploma', 'copies' => $copies]]])->assertSessionHasErrors('documents.0.copies');
        }
        $this->assertDatabaseCount('document_requests', 1);
        foreach (['new_applicant', 'registrar', 'cashier', 'admin'] as $role) {
            $this->actingAs($this->makeUserWithRole($role))->post(route('portal.appointments.store'), [])->assertForbidden();
        }
    }
}
