<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\AppointmentPayment;
use App\Models\AuditLog;
use App\Models\DocumentPaymentProof;
use App\Models\DocumentRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\SeedsRoles;
use Tests\TestCase;

class ClaimingReleaseTest extends TestCase
{
    use RefreshDatabase, SeedsRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    private function claim(string $role = 'student', int $count = 3): Appointment
    {
        $owner = $this->makeUserWithRole($role);
        $claim = Appointment::factory()->create(['user_id' => $owner->id, 'visit_type' => 'claiming']);
        for ($i = 0; $i < $count; $i++) {
            $document = DocumentRequest::create(['user_id' => $owner->id, 'document_type' => 'tor',
                'status' => 'ready', 'fee' => 0]);
            $document->pending_claim_appointment_id = $claim->id;
            $document->save();
            $claim->documentItems()->create(['document_request_id' => $document->id]);
        }

        return $claim->load('documentItems.documentRequest');
    }

    private function release(Appointment $claim, array $ids, array $extra = [])
    {
        return $this->post('/registrar/claiming-appointments/'.$claim->id.'/release',
            array_merge(['document_ids' => $ids, 'handover_confirmed' => '1'], $extra));
    }

    public function test_partial_release_and_completion_are_exact_for_both_portal_roles(): void
    {
        $registrar = $this->makeUserWithRole('registrar');
        foreach (['student', 'alumni'] as $role) {
            $claim = $this->claim($role);
            $activeStatus = $role === 'alumni' ? 'confirmed' : 'pending';
            $claim->update(['status' => $activeStatus]);
            $ids = $claim->documentItems->pluck('document_request_id')->all();
            $this->actingAs($registrar)->get('/registrar/claiming-appointments')->assertOk()
                ->assertSee($claim->user->name)->assertSee('0 of 3 released');
            $this->release($claim, [$ids[0]])->assertSessionHasNoErrors()->assertRedirect();
            $first = $claim->documentItems[0]->fresh();
            $this->assertNotNull($first->released_at);
            $this->assertSame($registrar->id, $first->released_by);
            $this->assertSame('released', $first->documentRequest->status);
            $this->assertNull($first->documentRequest->pending_claim_appointment_id);
            $this->assertSame($activeStatus, $claim->fresh()->status);
            $this->assertNull($claim->fresh()->completed_at);
            foreach (array_slice($ids, 1) as $id) {
                $this->assertDatabaseHas('document_requests', ['id' => $id, 'status' => 'ready', 'pending_claim_appointment_id' => $claim->id]);
            }
            $original = $first->getAttributes();
            $this->release($claim, [$ids[0]])->assertSessionHasErrors('document_ids');
            $this->release($claim, array_slice($ids, 1))->assertSessionHasNoErrors();
            $this->assertEquals($original, $first->fresh()->getAttributes());
            $this->assertSame('completed', $claim->fresh()->status);
            $this->assertSame($registrar->id, $claim->fresh()->completed_by);
            $this->assertNotNull($claim->fresh()->completed_at);
            $this->actingAs($claim->user)->get('/portal/claiming-appointments')->assertOk()
                ->assertSee('3 of 3 released')->assertSee($registrar->name);
        }
        $this->assertSame(6, AuditLog::where('action', 'document_request.claim_released')->count());
    }

    public function test_invalid_selected_document_rolls_back_the_entire_handover(): void
    {
        $registrar = $this->makeUserWithRole('registrar');
        $other = $this->makeUserWithRole('alumni');
        $otherClaim = $this->claim();
        foreach ([['status' => 'processing'], ['status' => 'cancelled'], ['status' => 'released'],
            ['fee' => 50, 'is_paid' => false], ['user_id' => $other->id],
            ['pending_claim_appointment_id' => $otherClaim->id], ['pending_claim_appointment_id' => null]] as $changes) {
            $claim = $this->claim('student', 2);
            $ids = $claim->documentItems->pluck('document_request_id')->all();
            DocumentRequest::whereKey($ids[1])->update($changes);
            $this->actingAs($registrar);
            $this->release($claim, $ids)->assertSessionHasErrors('document_ids');
            $this->assertDatabaseHas('document_requests', ['id' => $ids[0], 'status' => 'ready', 'pending_claim_appointment_id' => $claim->id]);
            $this->assertSame(0, $claim->documentItems()->whereNotNull('released_at')->count());
            $this->assertSame('pending', $claim->fresh()->status);
        }
        $this->assertSame(0, AuditLog::where('action', 'document_request.claim_released')->count());
    }

    public function test_membership_confirmation_and_active_claim_are_required(): void
    {
        $claim = $this->claim('student', 1);
        $foreign = $this->claim('alumni', 1)->documentItems[0]->document_request_id;
        $id = $claim->documentItems[0]->document_request_id;
        $this->actingAs($this->makeUserWithRole('registrar'));
        $this->release($claim, [$foreign])->assertSessionHasErrors('document_ids');
        $this->release($claim, [$id, $id])->assertSessionHasErrors();
        $this->release($claim, [$id], ['handover_confirmed' => '0'])->assertSessionHasErrors('handover_confirmed');
        foreach ([['visit_type' => 'submission'], ['visit_type' => 'legacy'], ['visit_type' => 'claiming', 'status' => 'cancelled']] as $changes) {
            $claim->update($changes);
            $this->release($claim, [$id])->assertSessionHasErrors('appointment');
        }
        $this->assertDatabaseHas('document_requests', ['id' => $id, 'status' => 'ready']);
    }

    public function test_only_registrar_can_view_or_release(): void
    {
        $claim = $this->claim();
        foreach (['student', 'alumni', 'cashier', 'new_applicant'] as $role) {
            $this->actingAs($this->makeUserWithRole($role))->get('/registrar/claiming-appointments')->assertForbidden();
            $this->release($claim, [$claim->documentItems[0]->document_request_id])->assertForbidden();
        }
        $admin = $this->makeUserWithRole('admin');
        $this->actingAs($admin)->get('/registrar/claiming-appointments')->assertOk();
        $id = $claim->documentItems[0]->document_request_id;
        $this->release($claim, [$id])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('document_requests', ['id' => $id, 'status' => 'released', 'released_by' => $admin->id]);
    }

    public function test_legacy_release_remains_available_but_cannot_bypass_claims_or_new_submission_items(): void
    {
        $claim = $this->claim('alumni', 1);
        $reserved = $claim->documentItems[0]->documentRequest;
        $legacy = DocumentRequest::create(['user_id' => $claim->user_id, 'document_type' => 'tor', 'status' => 'ready', 'fee' => 0]);
        $submission = Appointment::factory()->create(['user_id' => $claim->user_id, 'visit_type' => 'submission']);
        $new = DocumentRequest::create(['user_id' => $claim->user_id, 'document_type' => 'tor', 'status' => 'ready', 'fee' => 0, 'appointment_id' => $submission->id]);
        $submission->documentItems()->create(['document_request_id' => $new->id]);
        $this->actingAs($this->makeUserWithRole('registrar'));
        foreach ([$reserved, $new] as $document) {
            $this->post(route('registrar.documents.status', $document), ['status' => 'released'])->assertSessionHasErrors('status');
            $this->assertSame('ready', $document->fresh()->status);
        }
        $this->post(route('registrar.documents.status', $legacy), ['status' => 'released'])->assertSessionHasErrors('status');
        $this->post(route('registrar.documents.walk-in-release'), ['claimant_id' => $claim->user_id, 'document_ids' => [$legacy->id],
            'handover_confirmed' => '1', 'operation_key' => (string) \Illuminate\Support\Str::uuid()])->assertSessionHasNoErrors();
        $this->assertSame('released', $legacy->fresh()->status);
    }

    public function test_financial_identity_proofs_and_receipts_survive_release(): void
    {
        $claim = $this->claim('alumni', 1);
        $document = $claim->documentItems[0]->documentRequest;
        $origin = Appointment::factory()->create(['user_id' => $claim->user_id, 'visit_type' => 'submission']);
        // A newly created paid document has its immutable origin captured at creation.
        $paid = DocumentRequest::create(['user_id' => $claim->user_id, 'appointment_id' => $origin->id,
            'document_type' => 'tor', 'status' => 'ready', 'fee' => 50, 'amount_received' => 50,
            'is_paid' => true, 'payment_status' => 'verified', 'receipt_no' => 'RELEASE-OR']);
        $paid->pending_claim_appointment_id = $claim->id;
        $paid->save();
        $claim->documentItems()->create(['document_request_id' => $paid->id]);
        $payment = AppointmentPayment::factory()->verified()->create(['appointment_id' => $origin->id,
            'is_document_bundle' => true, 'amount' => 50, 'receipt_no' => 'RELEASE-OR']);
        $proof = DocumentPaymentProof::create(['appointment_payment_id' => $payment->id, 'path' => 'retained.png', 'status' => 'verified', 'submitted_at' => now()]);
        $paymentBefore = $payment->fresh()->getAttributes();
        $proofBefore = $proof->fresh()->getAttributes();
        $this->actingAs($this->makeUserWithRole('registrar'));
        $this->release($claim, [$document->id, $paid->id])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame($origin->id, $paid->fresh()->payment_appointment_id);
        $this->assertSame($origin->id, $paid->fresh()->appointment_id);
        $this->assertEquals($paymentBefore, $payment->fresh()->getAttributes());
        $this->assertEquals($proofBefore, $proof->fresh()->getAttributes());
        $this->actingAs($claim->user)->get(route('portal.transactions.receipt', 'A-'.$payment->id))->assertOk();
        $this->get(route('portal.transactions.receipt', 'D-'.$paid->id))->assertNotFound();
        $this->assertSame(5000, app(\App\Services\TransactionHistory::class)->summary($claim->user_id, 'alumni', [])['verified_cents']);
    }

    public function test_audit_failure_rolls_back_release_and_allows_a_safe_retry(): void
    {
        $claim = $this->claim('student', 1);
        $registrar = $this->makeUserWithRole('registrar');
        $id = $claim->documentItems[0]->document_request_id;
        $this->actingAs($registrar);
        AuditLog::creating(function ($audit) {
            if ($audit->action === 'document_request.claim_released') {
                throw new \RuntimeException('Injected audit failure');
            }
        });
        try {
            $this->withoutExceptionHandling();
            try {
                $this->release($claim, [$id]);
                $this->fail('The injected audit failure must abort handover.');
            } catch (\RuntimeException $exception) {
                $this->assertSame('Injected audit failure', $exception->getMessage());
            }
        } finally {
            \Illuminate\Support\Facades\Event::forget('eloquent.creating: '.AuditLog::class);
            $this->withExceptionHandling();
        }
        $this->assertDatabaseHas('document_requests', ['id' => $id, 'status' => 'ready', 'pending_claim_appointment_id' => $claim->id]);
        $this->assertNull($claim->documentItems[0]->fresh()->released_at);
        $this->assertSame('pending', $claim->fresh()->status);
        $this->release($claim, [$id])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('completed', $claim->fresh()->status);
    }

    public function test_an_unresolved_sibling_does_not_complete_or_reverse_an_earlier_release(): void
    {
        $claim = $this->claim('alumni', 2);
        $ids = $claim->documentItems->pluck('document_request_id')->all();
        DocumentRequest::whereKey($ids[1])->update(['status' => 'cancelled']);
        $this->actingAs($this->makeUserWithRole('registrar'));
        $this->release($claim, [$ids[0]])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('pending', $claim->fresh()->status);
        $this->assertNull($claim->fresh()->completed_at);
        $this->assertDatabaseHas('document_requests', ['id' => $ids[1], 'status' => 'cancelled', 'pending_claim_appointment_id' => $claim->id]);
        $this->release($claim, $ids)->assertSessionHasErrors('document_ids');
        $this->assertDatabaseHas('document_requests', ['id' => $ids[0], 'status' => 'released', 'pending_claim_appointment_id' => null]);
        $this->assertSame(1, $claim->documentItems()->whereNotNull('released_at')->count());
    }
}
