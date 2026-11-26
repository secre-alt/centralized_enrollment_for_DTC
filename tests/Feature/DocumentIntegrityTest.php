<?php

namespace Tests\Feature;

use App\Models\{Appointment, AppointmentPayment, AppointmentSlot, DocumentRequest};
use App\Services\{TransactionHistory};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Storage};
use Tests\SeedsRoles;
use Tests\TestCase;

class DocumentIntegrityTest extends TestCase
{
    use RefreshDatabase, SeedsRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('dtc_testing', DB::selectOne('SELECT DATABASE() AS name')->name);
        $this->seedRoles();
        Storage::fake('local');
    }

    public function test_cancelled_bundle_reconciliation_and_rebooking_preserve_one_collection(): void
    {
        $owner = $this->makeUserWithRole('alumni');
        $cashier = $this->makeUserWithRole('cashier');
        $appointment = Appointment::factory()->create(['user_id' => $owner->id, 'status' => 'cancelled']);
        Storage::disk('local')->put('proof.pdf', 'proof');
        $payment = AppointmentPayment::factory()->create(['appointment_id' => $appointment->id,
            'is_document_bundle' => true, 'payment_method' => 'gcash', 'status' => 'pending',
            'amount' => '50.00', 'proof_of_payment' => 'proof.pdf']);
        $document = DocumentRequest::create(['user_id' => $owner->id, 'appointment_id' => $appointment->id,
            'document_type' => 'tor', 'status' => 'ready', 'fee' => 50,
            'payment_method' => 'gcash_qr', 'payment_status' => 'pending_verification']);
        // Exercise an untouched legacy row, without a migration backfill.
        DB::table('document_requests')->where('id', $document->id)->update(['payment_appointment_id' => null]);
        $this->actingAs($cashier)->post(route('cashier.document-payments.bundles.verify', $payment),
            ['amount_received' => 50, 'receipt_no' => 'OR-ORIGIN'])->assertSessionHasNoErrors();
        $slot = AppointmentSlot::factory()->create(['date' => now()->addDays(2)->toDateString(), 'status' => 'open']);
        $this->actingAs($owner)->post(route('portal.documents.pickup-appointment', $document),
            ['appointment_slot_id' => $slot->id])->assertSessionHasErrors('appointment_slot_id');
        // Explicit fixture for a pre-retirement rebooking: financial identity must still reconcile.
        $historicalRebooking = Appointment::factory()->create(['user_id' => $owner->id, 'appointment_slot_id' => $slot->id]);
        $document->refresh()->update(['appointment_id' => $historicalRebooking->id]);
        $document->refresh();
        $this->assertNotSame($appointment->id, $document->appointment_id);
        $this->assertEquals($appointment->id, $document->getRawOriginal('payment_appointment_id'));
        $this->assertTrue($document->usesBundledPayment());
        $this->assertCount(1, $appointment->paymentDocuments()->get());
        $this->get(route('portal.payments.document', $document))->assertRedirect(route('portal.payments.appointment', $appointment));
        $this->get(route('portal.transactions.receipt', 'D-'.$document->id))->assertNotFound();
        $this->get(route('portal.transactions.receipt', 'A-'.$payment->id))->assertOk();
        $history = app(TransactionHistory::class);
        $this->assertSame(5000, $history->summary($owner->id, 'alumni', [])['verified_cents']);
        $this->assertSame(1, $history->query($owner->id, 'alumni')->count());
        $this->get(route('portal.transactions.export.pdf'))->assertOk();
        $this->get(route('portal.transactions.export.csv'))->assertOk();
        $this->actingAs($cashier)->get(route('reports.payments.index'))->assertOk();
        $report = app(\App\Services\Reports\PaymentReportService::class)->query(
            \App\Services\Reports\PaymentReportFilters::fromArray(['period' => 'today']))->get();
        $this->assertCount(1, $report);
        $this->assertSame(5000, (int) $report->sum('cents'));
        $this->get(route('cashier.document-payments.show', $document))->assertOk()->assertViewHas('bundlePayment', fn ($bundle) => $bundle->id === $payment->id);
        $this->post(route('cashier.document-payments.verify-gcash', $document), ['amount_received' => 50])->assertSessionHasErrors('payment');
        $this->assertSame('50.00', $payment->fresh()->amount);
        $this->assertSame('verified', $payment->fresh()->status);
        $this->assertSame(1, AppointmentPayment::count());
    }

    public function test_origin_cannot_be_changed_and_failed_rebooking_preserves_it(): void
    {
        $owner = $this->makeUserWithRole('alumni');
        $appointment = Appointment::factory()->create(['user_id' => $owner->id, 'status' => 'cancelled']);
        $document = DocumentRequest::create(['user_id' => $owner->id, 'appointment_id' => $appointment->id,
            'document_type' => 'tor', 'status' => 'ready', 'fee' => 0]);
        $slot = AppointmentSlot::factory()->create(['date' => now()->addDays(2)->toDateString(), 'status' => 'closed']);
        $this->actingAs($owner)->post(route('portal.documents.pickup-appointment', $document), ['appointment_slot_id' => $slot->id])->assertSessionHasErrors();
        $this->assertEquals($appointment->id, $document->fresh()->appointment_id);
        $this->expectException(\LogicException::class);
        $document->payment_appointment_id = null;
        $document->save();
    }
}

