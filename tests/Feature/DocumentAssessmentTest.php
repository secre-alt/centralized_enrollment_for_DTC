<?php

namespace Tests\Feature;

use App\Models\DocumentRequest;
use App\Models\DocumentWorkflowEvent;
use App\Models\Setting;
use App\Models\UserNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\SeedsRoles;
use Tests\TestCase;

class DocumentAssessmentTest extends TestCase
{
    use RefreshDatabase, SeedsRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('dtc_testing', DB::selectOne('SELECT DATABASE() AS name')->name);
        $this->seedRoles();
    }

    private function submit(string $role = 'student'): DocumentRequest
    {
        $owner = $this->makeUserWithRole($role);
        $this->actingAs($owner)->post(route('portal.documents.store'), [
            'document_type' => 'tor', 'copies' => 2, 'payment_method' => 'walk_in',
            'assessment_status' => 'complete', 'assessment_required' => false, 'assessed_fee' => 0,
        ])->assertRedirect(route('portal.documents.index'))->assertSessionHasNoErrors();

        return DocumentRequest::where('user_id', $owner->id)->sole();
    }

    private function assessment(array $overrides = []): array
    {
        return $overrides + ['operation_key' => (string) Str::uuid(), 'assessment_version' => 0,
            'assessment_status' => 'complete', 'fee_confirmed' => '1'];
    }

    public function test_both_portals_require_registrar_assessment_and_preserve_the_saved_fee(): void
    {
        foreach (['student', 'alumni'] as $role) {
            Setting::set('document_fee_tor', '12.25');
            $document = $this->submit($role);
            $this->assertTrue($document->assessment_required);
            $this->assertSame('pending', $document->assessment_status);
            $this->assertSame('24.50', $document->fee);
            $this->assertNull($document->assessed_fee);
            $this->assertNull($document->appointment_id);
            $registrar = $this->makeUserWithRole('registrar');
            $this->actingAs($registrar)->get(route('registrar.documents.index'))->assertOk()
                ->assertSee('Assess request')->assertSee('Stored configured fee:')
                ->assertSee('assessment_version', false)->assertSee('two 2×2 photos');
            $this->actingAs($registrar)->post(route('registrar.documents.status', $document), ['status' => 'processing'])
                ->assertSessionHasErrors('assessment');
            $url = route('registrar.documents.assessment', $document);
            $this->post($url, $this->assessment(['assessment_status' => 'incomplete', 'fee_confirmed' => null]))
                ->assertSessionHasErrors('requirements_notes');
            $this->post($url, $this->assessment(['assessment_status' => 'incomplete', 'fee_confirmed' => null,
                'requirements_notes' => '<script>Clearance needed</script>']))->assertSessionHasNoErrors();
            $this->assertSame('incomplete', $document->fresh()->assessment_status);
            $this->actingAs($document->user)->get(route('portal.documents.index'))->assertOk()
                ->assertSee('&lt;script&gt;Clearance needed&lt;/script&gt;', false)->assertDontSee('<script>Clearance needed</script>', false);
            Setting::set('document_fee_tor', '99');
            $this->actingAs($registrar)->post($url, $this->assessment(['assessment_version' => 1, 'fee_confirmed' => null]))
                ->assertSessionHasErrors('fee_confirmed');
            $this->post($url, $this->assessment(['assessment_version' => 1]))->assertSessionHasNoErrors();
            $document->refresh();
            $this->assertTrue($document->assessmentSatisfied());
            $this->assertSame('24.50', $document->assessed_fee);
            $this->assertSame('24.50', $document->fee);
            $this->assertFalse($document->is_paid);
            $this->assertSame($registrar->id, $document->assessed_by);
            $this->post(route('registrar.documents.status', $document), ['status' => 'processing'])->assertSessionHasNoErrors();
            $this->assertSame('processing', $document->fresh()->status);
        }
        $this->assertDatabaseCount('appointments', 0);
        $this->assertDatabaseCount('appointment_payments', 0);
    }

    public function test_permissions_replays_stale_assessments_and_verified_payments(): void
    {
        $document = $this->submit();
        $document->forceFill(['is_paid' => true, 'payment_status' => 'verified', 'verified_at' => now(),
            'receipt_no' => 'KEEP-OR', 'amount_received' => $document->fee])->save();
        $before = $document->fresh()->only(['fee', 'is_paid', 'payment_status', 'verified_at', 'receipt_no', 'amount_received']);
        $url = route('registrar.documents.assessment', $document);
        foreach (['student', 'alumni', 'cashier', 'new_applicant'] as $role) {
            $this->actingAs($this->makeUserWithRole($role))->post($url, $this->assessment())->assertForbidden();
        }
        $admin = $this->makeUserWithRole('admin');
        $this->actingAs($admin);
        $payload = $this->assessment();
        $this->post($url, $payload)->assertSessionHasNoErrors();
        $count = UserNotification::count();
        $this->post($url, $payload)->assertSessionHasNoErrors();
        $this->assertSame($count, UserNotification::count());
        $this->assertSame(1, DocumentWorkflowEvent::where('action', 'document.assessed')->count());
        $this->post($url, array_merge($payload, ['requirements_notes' => 'Changed']))->assertSessionHasErrors('operation_key');
        $this->post($url, $this->assessment())->assertSessionHasErrors('assessment');
        $this->assertEquals($before, $document->fresh()->only(array_keys($before)));
        $this->post(route('registrar.documents.status', $document), ['status' => 'processing'])->assertSessionHasNoErrors();
        $this->post($url, $this->assessment(['assessment_version' => 1]))->assertSessionHasErrors('assessment');
    }

    public function test_assessment_failure_rolls_back_notes_actor_and_notification(): void
    {
        $document = $this->submit();
        $this->actingAs($this->makeUserWithRole('registrar'));
        $count = UserNotification::count();
        DocumentWorkflowEvent::creating(fn () => throw new \RuntimeException('Assessment failure'));
        try {
            $this->withoutExceptionHandling()->post(route('registrar.documents.assessment', $document), $this->assessment());
            $this->fail('Expected assessment failure');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Assessment failure', $exception->getMessage());
        } finally {
            DocumentWorkflowEvent::flushEventListeners();
            DocumentWorkflowEvent::clearBootedModels();
        }
        $this->assertSame('pending', $document->fresh()->assessment_status);
        $this->assertSame(0, $document->fresh()->assessment_version);
        $this->assertNull($document->fresh()->assessed_by);
        $this->assertSame($count, UserNotification::count());
    }

    public function test_legacy_requests_are_not_backfilled_or_repriced(): void
    {
        $document = DocumentRequest::create(['user_id' => $this->makeUserWithRole('student')->id,
            'document_type' => 'tor', 'copies' => 1, 'fee' => 75, 'status' => 'submitted']);
        $this->assertFalse($document->fresh()->assessment_required);
        $this->assertTrue($document->assessmentSatisfied());
        $this->actingAs($this->makeUserWithRole('registrar'))->post(route('registrar.documents.status', $document), ['status' => 'processing'])
            ->assertSessionHasNoErrors();
        $this->assertSame('75.00', $document->fresh()->fee);
    }

    public function test_unassessed_requests_cannot_bypass_ready_or_physical_release_and_free_requests_can_be_assessed(): void
    {
        Setting::set('document_fee_tor', '0');
        $document = $this->submit();
        $this->actingAs($this->makeUserWithRole('registrar'));
        $document->update(['status' => 'processing']);
        $this->post(route('registrar.documents.status', $document), ['status' => 'ready'])->assertSessionHasErrors('assessment');
        $document->update(['status' => 'ready']);
        $this->post(route('registrar.documents.walk-in-release'), ['operation_key' => (string) Str::uuid(),
            'claimant_id' => $document->user_id, 'document_ids' => [$document->id], 'handover_confirmed' => '1'])
            ->assertSessionHasErrors('document_ids');
        $document->update(['status' => 'submitted']);
        $this->post(route('registrar.documents.assessment', $document), $this->assessment())->assertSessionHasNoErrors();
        $this->assertSame('0.00', $document->fresh()->assessed_fee);
        $this->assertTrue($document->fresh()->paymentNotRequired());
        $this->post(route('registrar.documents.status', $document), ['status' => 'processing'])->assertSessionHasNoErrors();
    }
}
