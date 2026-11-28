<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\DocumentRequest;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\SeedsRoles;
use Tests\TestCase;

class DocumentRequestUiTest extends TestCase
{
    use RefreshDatabase, SeedsRoles;

    public function test_charter_guidance_and_next_actions_are_visible_to_both_requester_roles(): void
    {
        foreach (['student', 'alumni'] as $role) {
            $owner = $this->makeUserWithRole($role);
            $this->actingAs($owner)->get(route('portal.documents.create'))->assertOk()
                ->assertSee('Clearance for a first request')
                ->assertSee('two 2×2 photos')
                ->assertSee('Original and photocopy')
                ->assertSee('Submitting this form does not confirm');
            foreach (['unpaid', 'pending_verification', 'rejected', 'verified'] as $paymentStatus) {
                DocumentRequest::create(['user_id' => $owner->id, 'document_type' => 'tor', 'copies' => 1,
                    'fee' => 50, 'status' => 'ready', 'payment_status' => $paymentStatus,
                    'is_paid' => $paymentStatus === 'verified']);
            }
            foreach (['submitted', 'processing'] as $status) {
                DocumentRequest::create(['user_id' => $owner->id, 'document_type' => 'certification',
                    'copies' => 1, 'fee' => 0, 'status' => $status]);
            }
            $historyPage = $this->get(route('portal.documents.index'))->assertOk()
                ->assertSee('Document progress: Assessment')
                ->assertSee('dr-document-track', false)
                ->assertSee('Pay at the Cashier or review')
                ->assertSee('Wait for the Cashier to verify')
                ->assertSee('provide corrected payment details')
                ->assertSee('your claim stub if issued')
                ->assertSee('Await Registrar review')
                ->assertSee('applicable working-day lead time');
            $directory = storage_path('framework/testing');
            if (! is_dir($directory)) mkdir($directory, 0755, true);
            file_put_contents($directory.'/document-history-'.$role.'.html', $historyPage->getContent());
            $this->get(route('portal.appointments.index'))->assertOk()
                ->assertSee('No appointment records')
                ->assertSee('Claiming history')
                ->assertDontSee('Historical submission appointments appear below.');
            $this->get(route('portal.claiming-appointments.index'))->assertOk()
                ->assertSee('No claiming history')->assertSee('Back to appointments')
                ->assertDontSee('Historical records remain below.');
        }
    }

    public function test_staff_pages_explain_document_and_visit_responsibilities(): void
    {
        foreach (['registrar', 'admin'] as $role) {
            $this->actingAs($this->makeUserWithRole($role));
            $this->get(route('registrar.documents.index'))->assertOk()
                ->assertSee('Assess requirements and saved fees');
            $this->get(route('registrar.appointments.index'))->assertOk()
                ->assertSee('Scheduled visits')->assertSee('Open document requests');
        }
        foreach (['cashier', 'admin'] as $role) {
            $this->actingAs($this->makeUserWithRole($role))->get(route('cashier.document-payments.index'))->assertOk()
                ->assertSee('Document payment review')
                ->assertSee('Historical appointment bundles retain one payment and receipt.');
        }
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('dtc_testing', DB::selectOne('SELECT DATABASE() AS name')->name);
        $this->seedRoles();
    }

    public function test_both_roles_have_independent_navigation_and_owner_scoped_history(): void
    {
        foreach (['student', 'alumni'] as $role) {
            $owner = $this->makeUserWithRole($role);
            $other = $this->makeUserWithRole($role);
            $own = DocumentRequest::create(['user_id' => $owner->id, 'document_type' => 'diploma', 'copies' => 2,
                'fee' => 200, 'status' => 'ready', 'payment_status' => 'verified', 'is_paid' => true,
                'amount_received' => 200, 'verified_at' => now(), 'receipt_no' => 'UI-'.$owner->id,
                'purpose' => 'My visible request', 'remarks' => 'Staff-only confidential note']);
            $foreign = DocumentRequest::create(['user_id' => $other->id, 'document_type' => 'tor', 'copies' => 1,
                'fee' => 50, 'status' => 'submitted', 'purpose' => 'Foreign confidential purpose']);
            $this->actingAs($owner)->get(route('portal.documents.index'))->assertOk()
                ->assertSee('My visible request')->assertSee('Awaiting collection')
                ->assertSee(route('portal.transactions.receipt', 'D-'.$own->id), false)
                ->assertDontSee('Staff-only confidential note')->assertDontSee('Foreign confidential purpose');
            $menu = collect(config('adminlte.menu'));
            $documents = $menu->firstWhere('icon', 'folder-open');
            $this->assertSame(route('portal.documents.create'), $documents['submenu'][0]['url']);
            $this->assertSame(route('portal.documents.index'), $documents['submenu'][1]['url']);
            $this->assertSame(route('portal.appointments.index'), $menu->firstWhere('icon', 'calendar')['url']);
            $this->get(route('portal.appointments.index'))->assertOk();
            $owner->assignRole('cashier');
            $this->get(route('portal.payments.document', $foreign))->assertForbidden();
            $this->get(route('portal.documents.payment.proof', $foreign))->assertForbidden();
            $this->get(route('portal.transactions.receipt', 'D-'.$foreign->id))->assertNotFound();
        }
    }

    public function test_form_uses_configured_fees_and_canonical_submission_for_both_roles(): void
    {
        Setting::set('document_fee_tor', '12.25');
        Setting::set('document_fee_certification', '0');
        foreach (['student', 'alumni'] as $role) {
            $this->actingAs($this->makeUserWithRole($role));
            $page = $this->get(route('portal.documents.create'))->assertOk()
                ->assertSee('data-document-cents="1225"', false)->assertSee('data-document-summary', false)
                ->assertSee('What happens next')->assertSee('Free')->assertDontSee('name="appointment_slot_id"', false);
            if ($role === 'student') {
                $directory = storage_path('framework/testing');
                if (! is_dir($directory)) mkdir($directory, 0777, true);
                file_put_contents($directory.'/document-request-ui.html', $page->getContent());
            }
            $this->post(route('portal.documents.store'), ['documents' => [
                ['document_type' => 'tor', 'copies' => 2, 'payment_method' => 'walk_in'],
                ['document_type' => 'certification', 'copies' => 1],
            ], 'fee' => 1, 'is_paid' => true])->assertRedirect(route('portal.documents.index'))->assertSessionHasNoErrors();
            $records = DocumentRequest::where('user_id', auth()->id())->get();
            $this->assertSame('24.50', $records->firstWhere('document_type', 'tor')->fee);
            $this->assertFalse($records->firstWhere('document_type', 'tor')->is_paid);
            $this->assertTrue($records->firstWhere('document_type', 'certification')->paymentNotRequired());
            $this->post(route('portal.documents.store'), ['documents' => [
                ['document_type' => 'tor', 'copies' => 11, 'payment_method' => 'walk_in'],
            ]])->assertSessionHasErrors('documents.0.copies');
        }
        $this->assertDatabaseCount('appointments', 0);
    }

    public function test_partial_historical_collection_keeps_collected_and_ready_documents_visible(): void
    {
        $owner = $this->makeUserWithRole('student');
        $appointment = Appointment::factory()->create(['user_id' => $owner->id]);
        foreach (['released', 'ready'] as $status) {
            DocumentRequest::create(['user_id' => $owner->id, 'appointment_id' => $appointment->id,
                'document_type' => $status === 'released' ? 'diploma' : 'tor', 'copies' => 1,
                'fee' => 0, 'status' => $status]);
        }
        $this->actingAs($owner)->get(route('portal.documents.index'))->assertOk()
            ->assertSee('Partially released: 1')->assertSee('Collected')->assertSee('Awaiting collection');
    }

    public function test_non_portal_roles_and_applicants_cannot_access_document_module(): void
    {
        foreach (['new_applicant', 'registrar', 'cashier', 'admin'] as $role) {
            $this->actingAs($this->makeUserWithRole($role));
            foreach (['portal.documents.index', 'portal.documents.create', 'portal.appointments.index'] as $route) {
                $this->get(route($route))->assertForbidden();
            }
            $this->post(route('portal.documents.store'))->assertForbidden();
        }
    }

    public function test_sparse_selection_errors_stay_with_the_selected_document(): void
    {
        Storage::fake('local');
        Setting::set('gcash_qr_path', 'qr.png');
        Storage::disk('local')->put('qr.png', 'qr');
        $this->actingAs($this->makeUserWithRole('student'))
            ->from(route('portal.documents.create'))->post(route('portal.documents.store'), ['documents' => [
                1 => ['document_type' => 'diploma', 'copies' => 1, 'payment_method' => 'gcash_qr'],
            ]])->assertSessionHasErrors(['documents.1.reference_number', 'documents.1.proof_of_payment']);
        $this->get(route('portal.documents.create'))->assertOk()
            ->assertSee('id="reference-error-1" class="dr-field-error">A separate GCash reference is required.', false);
        $this->assertDatabaseCount('document_requests', 0);
    }
}
