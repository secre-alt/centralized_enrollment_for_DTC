<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\AppointmentPayment;
use App\Models\DocumentRequest;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Services\TransactionHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\SeedsRoles;
use Tests\TestCase;

class PortalPaymentHistoryEnhancementsTest extends TestCase
{
    use RefreshDatabase, SeedsRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    private function enrollmentPayment($user, array $attributes = []): Payment
    {
        return Payment::factory()->create(array_merge([
            'enrollment_id' => Enrollment::factory()->create(['user_id' => $user->id])->id,
            'verified_at' => now(),
        ], $attributes));
    }

    private function document($user, array $attributes = []): DocumentRequest
    {
        return DocumentRequest::create(array_merge([
            'user_id' => $user->id, 'document_type' => 'tor', 'fee' => '50.00', 'amount_received' => '50.00',
            'is_paid' => true, 'payment_status' => 'verified', 'verified_at' => now(), 'receipt_no' => 'DOC-OR-1',
        ], $attributes));
    }

    public function test_summary_is_exact_owned_and_independent_of_status_tabs(): void
    {
        $student = $this->makeUserWithRole('student');
        $this->enrollmentPayment($student, ['amount' => '0.10', 'refund_status' => 'refunded', 'refund_amount' => '0.10', 'refunded_at' => now()]);
        $this->enrollmentPayment($student, ['amount' => '0.20']);
        $this->enrollmentPayment($student, ['status' => 'pending']);
        $this->enrollmentPayment($student, ['status' => 'rejected']);
        $this->enrollmentPayment($student, ['refund_status' => 'pending', 'amount' => '1.00']);
        $this->enrollmentPayment($this->makeUserWithRole('student'));
        $this->document($student, ['fee' => '0.00', 'amount_received' => '0.00']);
        $response = $this->actingAs($student)->get(route('portal.transactions.index', ['status' => 'pending']))->assertOk();
        $this->assertSame(['verified_cents' => 130, 'pending_count' => 1, 'refund_cents' => 10], $response->viewData('summary'));
        $this->assertSame(6, (int) $response->viewData('statusCounts')->sum());
        $this->assertSame(4, (int) $response->viewData('statusCounts')['verified']);
        $this->assertSame(1, (int) $response->viewData('statusCounts')['pending']);
        $response->assertSee('Verified payments')->assertSee('Confirmed refunds');
    }

    public function test_bundle_and_children_appear_once_and_cannot_issue_duplicate_receipts(): void
    {
        foreach (['student', 'alumni'] as $role) {
            $owner = $this->makeUserWithRole($role);
            $appointment = Appointment::factory()->create(['user_id' => $owner->id, 'document_type' => 'tor']);
            $bundle = AppointmentPayment::factory()->verified()->create(['appointment_id' => $appointment->id, 'is_document_bundle' => true, 'amount' => '70.00', 'receipt_no' => 'BUNDLE-OR']);
            $child = $this->document($owner, ['appointment_id' => $appointment->id]);
            $this->document($owner, ['appointment_id' => $appointment->id, 'document_type' => 'diploma', 'fee' => '20.00', 'amount_received' => '20.00']);
            $response = $this->actingAs($owner)->get(route('portal.transactions.index'))->assertOk();
            $this->assertSame(1, $response->viewData('transactions')->total());
            $this->assertSame(7000, $response->viewData('summary')['verified_cents']);
            $description = 'Document bundle — Appointment #'.$appointment->id;
            $this->assertSame($description, $response->viewData('transactions')->first()->related);
            $response->assertSee($description);
            $record = app(TransactionHistory::class)->find($owner->id, $role, 'A-'.$bundle->id);
            $this->assertStringContainsString($description, view('transactions.receipt', ['transaction' => $record, 'amountCents' => 7000])->render());
            $this->get(route('portal.transactions.show', 'D-'.$child->id))->assertNotFound();
            $this->get(route('portal.transactions.receipt', 'D-'.$child->id))->assertNotFound();
            $this->get(route('portal.transactions.show', 'A-'.$bundle->id))->assertOk()->assertSee('Download official receipt')->assertSee($description);
            $this->get(route('portal.transactions.receipt', 'A-'.$bundle->id))->assertOk()->assertHeader('content-type', 'application/pdf');
        }
    }

    public function test_pending_bundles_use_payment_status_tabs_without_changing_legacy_payments(): void
    {
        foreach (['student', 'alumni'] as $role) {
            $owner = $this->makeUserWithRole($role);
            $keys = ['unpaid' => [], 'pending_verification' => [], 'pending' => []];
            foreach (['walk_in' => 'unpaid', 'cash' => 'unpaid', 'gcash' => 'pending_verification'] as $method => $status) {
                $appointment = Appointment::factory()->create(['user_id' => $owner->id]);
                $payment = AppointmentPayment::factory()->create([
                    'appointment_id' => $appointment->id, 'is_document_bundle' => true,
                    'payment_method' => $method, 'status' => 'pending', 'amount' => '50.00',
                ]);
                $keys[$status][] = 'A-'.$payment->id;
                $this->document($owner, [
                    'appointment_id' => $appointment->id, 'is_paid' => false,
                    'payment_status' => $status, 'payment_method' => $method === 'gcash' ? 'gcash_qr' : 'walk_in',
                ]);
                $this->actingAs($owner)->get(route('portal.transactions.show', 'A-'.$payment->id))
                    ->assertOk()->assertViewHas('transaction', fn ($record) => $record->status === $status)
                    ->assertDontSee('Download official receipt');
                $this->assertSame('pending', $payment->fresh()->status);
            }
            $legacy = AppointmentPayment::factory()->create([
                'appointment_id' => Appointment::factory()->create(['user_id' => $owner->id])->id,
                'is_document_bundle' => false, 'payment_method' => 'gcash', 'status' => 'pending',
            ]);
            $keys['pending'][] = 'A-'.$legacy->id;
            foreach ($keys as $status => $expected) {
                $response = $this->get(route('portal.transactions.index', ['status' => $status]))->assertOk();
                $this->assertEqualsCanonicalizing($expected, $response->viewData('transactions')->pluck('transaction_key')->all());
                $this->assertSame(4, $response->viewData('summary')['pending_count']);
            }
            $this->assertSame('Appointment #'.$legacy->appointment_id.' — '.$legacy->appointment->document_type,
                app(TransactionHistory::class)->find($owner->id, $role, 'A-'.$legacy->id)->related);
        }
    }

    public function test_legacy_mirrors_are_suppressed_but_separate_charges_remain(): void
    {
        $owner = $this->makeUserWithRole('alumni');
        $appointment = Appointment::factory()->create(['user_id' => $owner->id]);
        AppointmentPayment::factory()->verified()->create(['appointment_id' => $appointment->id, 'amount' => '50.00', 'reference_number' => 'SAME-REF']);
        $this->document($owner, ['appointment_id' => $appointment->id, 'payment_method' => 'gcash_qr', 'reference_number' => 'SAME-REF']);
        $separate = $this->document($owner, ['appointment_id' => $appointment->id, 'receipt_no' => 'SEPARATE']);
        $response = $this->actingAs($owner)->get(route('portal.transactions.index'))->assertOk();
        $this->assertSame(2, $response->viewData('transactions')->total());
        $this->assertSame(10000, $response->viewData('summary')['verified_cents']);
        $this->get(route('portal.transactions.show', 'D-'.$separate->id))->assertOk();
    }

    public function test_receipts_enforce_ownership_for_every_source_even_with_secondary_staff_role(): void
    {
        $owner = $this->makeUserWithRole('student');
        $other = $this->makeUserWithRole('student');
        $other->assignRole('cashier');
        $payment = $this->enrollmentPayment($owner);
        $document = $this->document($owner);
        $appointment = Appointment::factory()->create(['user_id' => $owner->id]);
        $appointmentPayment = AppointmentPayment::factory()->verified()->create(['appointment_id' => $appointment->id, 'amount' => '10.00', 'receipt_no' => 'AP-OR']);
        foreach (['E-'.$payment->id, 'D-'.$document->id, 'A-'.$appointmentPayment->id] as $key) {
            $this->actingAs($owner)->get(route('portal.transactions.receipt', $key))->assertOk()->assertHeader('content-type', 'application/pdf');
            $this->actingAs($other)->get(route('portal.transactions.show', $key))->assertNotFound();
            $this->get(route('portal.transactions.receipt', $key))->assertNotFound();
        }
    }

    public function test_receipt_download_rejects_ineligible_payments_and_does_not_invent_receipt_numbers(): void
    {
        $owner = $this->makeUserWithRole('student');
        $this->actingAs($owner);
        foreach ([['status' => 'pending'], ['status' => 'rejected'], ['receipt_no' => null], ['verified_at' => null], ['amount' => '0.00'], ['amount' => '-0.10']] as $attributes) {
            $payment = $this->enrollmentPayment($owner, $attributes);
            $this->get(route('portal.transactions.show', 'E-'.$payment->id))->assertOk()->assertDontSee('Download official receipt');
            $this->get(route('portal.transactions.receipt', 'E-'.$payment->id))->assertNotFound();
        }
        $document = $this->document($owner, ['amount_received' => null]);
        $this->get(route('portal.transactions.receipt', 'D-'.$document->id))->assertNotFound();
    }

    public function test_refunded_receipt_discloses_refund_and_preserves_original_payment(): void
    {
        $owner = $this->makeUserWithRole('student');
        $payment = $this->enrollmentPayment($owner, ['refund_status' => 'refunded', 'refund_amount' => '500.00', 'refund_reference' => 'REFUND-ABC', 'refunded_at' => now()]);
        $record = app(TransactionHistory::class)->find($owner->id, 'student', 'E-'.$payment->id);
        $html = view('transactions.receipt', ['transaction' => $record, 'amountCents' => 50000])->render();
        $this->assertStringContainsString('Full refund confirmed', $html);
        $this->assertStringContainsString('REFUND-ABC', $html);
        $this->assertStringContainsString('original collection', $html);
        $this->actingAs($owner)->get(route('portal.transactions.show', 'E-'.$payment->id))->assertOk()->assertSee('Full refund confirmed')->assertSee('REFUND-ABC');
    }

    public function test_filters_keep_existing_search_tabs_and_role_categories_and_normalize_methods(): void
    {
        $alumni = $this->makeUserWithRole('alumni');
        $this->document($alumni, ['payment_method' => 'gcash_qr'])->forceFill(['created_at' => '2026-11-05 10:00:00'])->save();
        $this->document($alumni, ['receipt_no' => 'OTHER'])->forceFill(['created_at' => '2026-10-05 10:00:00'])->save();
        $params = ['from' => '2026-11-05', 'to' => '2026-11-05', 'category' => 'document', 'method' => 'gcash', 'search' => 'DOC-OR-1'];
        $response = $this->actingAs($alumni)->get(route('portal.transactions.index', $params))->assertOk();
        $this->assertSame(1, $response->viewData('transactions')->total());
        $this->assertSame(5000, $response->viewData('summary')['verified_cents']);
        $this->assertSame(['document', 'appointment'], $response->viewData('categories'));
        $response->assertSee('Filter by status')->assertSee('Filters')->assertSee('Search transactions');
        $this->getJson(route('portal.transactions.index', ['category' => 'enrollment']))->assertUnprocessable();
        $this->getJson(route('portal.transactions.index', ['from' => '2026-11-10', 'to' => '2026-11-01']))->assertUnprocessable();
        $this->get(route('portal.transactions.index', ['to' => '2026-11-05']))->assertOk();
    }

    public function test_guest_and_staff_only_accounts_cannot_use_portal_receipt_endpoint(): void
    {
        $this->get(route('portal.transactions.receipt', 'E-1'))->assertRedirect(route('login'));
        foreach (['registrar', 'cashier', 'admin'] as $role) {
            $this->actingAs($this->makeUserWithRole($role))->get(route('portal.transactions.receipt', 'E-1'))->assertForbidden();
        }
    }

    public function test_pending_bundle_counts_once_and_paid_cancelled_documents_remain_in_history(): void
    {
        $owner = $this->makeUserWithRole('alumni');
        $appointment = Appointment::factory()->create(['user_id' => $owner->id]);
        AppointmentPayment::factory()->create(['appointment_id' => $appointment->id, 'is_document_bundle' => true, 'amount' => '50.00']);
        $child = $this->document($owner, ['appointment_id' => $appointment->id, 'payment_status' => 'unpaid', 'is_paid' => false]);
        $cancelled = $this->document($owner, ['status' => 'cancelled']);
        $free = $this->document($owner, ['fee' => '0.00', 'amount_received' => '0.00']);
        $this->actingAs($owner);
        $response = $this->get(route('portal.transactions.index'))->assertOk();
        $this->assertSame(1, $response->viewData('summary')['pending_count']);
        $this->assertSame(5000, $response->viewData('summary')['verified_cents']);
        $this->get(route('portal.transactions.show', 'D-'.$cancelled->id))->assertOk();
        $this->get(route('portal.transactions.receipt', 'D-'.$cancelled->id))->assertOk();
        $this->get(route('portal.transactions.receipt', 'D-'.$free->id))->assertNotFound();
        $this->get(route('portal.transactions.show', 'D-'.$child->id))->assertNotFound();
    }
}
