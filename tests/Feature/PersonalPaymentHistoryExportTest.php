<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\AppointmentPayment;
use App\Models\DocumentRequest;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Services\PersonalPaymentHistoryExport;
use App\Services\TransactionHistory;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\SeedsRoles;
use Tests\TestCase;

class PersonalPaymentHistoryExportTest extends TestCase
{
    use RefreshDatabase, SeedsRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    private function payment($user, array $attributes = []): Payment
    {
        return Payment::factory()->create(array_merge(['enrollment_id' => Enrollment::factory()->create(['user_id' => $user->id])->id, 'verified_at' => now()], $attributes));
    }

    private function document($user, array $attributes = []): DocumentRequest
    {
        return DocumentRequest::create(array_merge(['user_id' => $user->id, 'document_type' => 'tor', 'fee' => '50.00', 'amount_received' => '50.00', 'is_paid' => true, 'payment_status' => 'verified', 'verified_at' => now(), 'receipt_no' => 'DOC-OR'], $attributes));
    }

    private function csv(array $filters = []): array
    {
        $content = $this->get(route('portal.transactions.export.csv', $filters))->assertOk()->streamedContent();
        $this->assertSame("\xEF\xBB\xBF", substr($content, 0, 3));
        $stream = fopen('php://temp', 'w+');
        fwrite($stream, substr($content, 3));
        rewind($stream);
        $header = fgetcsv($stream);
        $rows = [];
        while (($values = fgetcsv($stream)) !== false) {
            $rows[] = array_combine(array_keys(PersonalPaymentHistoryExport::COLUMNS), $values);
        }
        fclose($stream);
        $this->assertSame(array_values(PersonalPaymentHistoryExport::COLUMNS), $header);

        return $rows;
    }

    public function test_exports_require_authenticated_student_or_alumni(): void
    {
        foreach (['pdf', 'csv'] as $type) {
            $this->get(route('portal.transactions.export.'.$type))->assertRedirect(route('login'));
        }
        foreach (['admin', 'cashier', 'registrar', 'new_applicant'] as $role) {
            $this->actingAs($this->makeUserWithRole($role));
            foreach (['pdf', 'csv'] as $type) {
                $this->get(route('portal.transactions.export.'.$type))->assertForbidden();
            }
        }
    }

    public function test_both_roles_export_only_owned_categories_even_with_injected_user_ids_and_secondary_staff_role(): void
    {
        foreach (['student', 'alumni'] as $role) {
            $owner = $this->makeUserWithRole($role);
            $owner->assignRole('admin');
            $foreign = $this->makeUserWithRole('student');
            $payment = $this->payment($owner);
            $document = $this->document($owner);
            $appointment = Appointment::factory()->create(['user_id' => $owner->id]);
            $ap = AppointmentPayment::factory()->create(['appointment_id' => $appointment->id, 'amount' => '20.00']);
            $this->payment($foreign);
            $this->document($foreign);
            $this->actingAs($owner);
            $keys = array_column($this->csv(['user_id' => $foreign->id, 'role' => 'admin']), 'key');
            $expected = ['D-'.$document->id, 'A-'.$ap->id];
            if ($role === 'student') {
                $expected[] = 'E-'.$payment->id;
            }
            sort($keys);
            sort($expected);
            $this->assertSame($expected, $keys);
        }
    }

    public function test_exports_share_every_filter_with_screen_and_include_all_pages(): void
    {
        $owner = $this->makeUserWithRole('student');
        $this->actingAs($owner);
        $first = $this->payment($owner, ['receipt_no' => 'MATCH-1', 'created_at' => '2026-11-05 10:00:00']);
        $this->payment($owner, ['receipt_no' => 'MATCH-2', 'created_at' => '2026-11-06 10:00:00']);
        $this->payment($owner, ['receipt_no' => 'MATCH-3', 'created_at' => '2026-11-07 10:00:00']);
        $this->payment($owner, ['receipt_no' => 'MATCH-GCASH', 'payment_method' => 'gcash']);
        $this->payment($owner, ['receipt_no' => 'MATCH-PENDING', 'status' => 'pending']);
        $this->document($owner);
        $filters = ['search' => 'MATCH', 'category' => 'enrollment', 'method' => 'cash', 'status' => 'verified', 'from' => '2026-11-05', 'to' => '2026-11-07', 'per_page' => 1, 'page' => 2];
        $page = $this->get(route('portal.transactions.index', $filters))->assertOk();
        $this->assertCount(1, $page->viewData('transactions'));
        $rows = $this->csv($filters);
        $this->assertCount(3, $rows);
        $expected = app(TransactionHistory::class)->query($owner->id, 'student', $filters)->pluck('transaction_key')->all();
        $this->assertSame($expected, array_column($rows, 'key'));
        $pdf = \Mockery::mock(\Barryvdh\DomPDF\PDF::class);
        Pdf::shouldReceive('loadView')->once()->with('transactions.history-pdf', \Mockery::on(function ($data) use ($expected, $owner) {
            $this->assertSame($expected, $data['transactions']->pluck('transaction_key')->all());
            $this->assertSame($owner->name, $data['holder']);
            $this->assertSame(150000, $data['summary']['verified_cents']);
            $this->assertSame('2026-11-05', $data['filters']['from']);
            $this->assertStringContainsString('PHT', $data['generatedAt']);

            return true;
        }))->andReturn($pdf);
        $pdf->shouldReceive('setPaper')->with('a4', 'landscape')->andReturnSelf();
        $pdf->shouldReceive('download')->andReturn(response('PDF', 200, ['Content-Type' => 'application/pdf']));
        $this->get(route('portal.transactions.export.pdf', $filters))->assertOk();
    }

    public function test_manila_recorded_date_boundaries_include_final_second_and_support_open_ranges(): void
    {
        $owner = $this->makeUserWithRole('student');
        $this->actingAs($owner);
        foreach (['2026-11-04 23:59:59', '2026-11-05 00:00:00', '2026-11-05 23:59:59', '2026-11-06 00:00:00'] as $date) {
            $this->payment($owner, ['created_at' => $date]);
        }
        $this->assertCount(2, $this->csv(['from' => '2026-11-05', 'to' => '2026-11-05']));
        $this->assertCount(3, $this->csv(['to' => '2026-11-05']));
        $this->assertCount(3, $this->csv(['from' => '2026-11-05']));
        config(['app.timezone' => 'UTC']);
        $utcRows = $this->csv(['from' => '2026-11-05', 'to' => '2026-11-05']);
        $this->assertCount(2, $utcRows);
        $this->assertSame(['2026-11-05 08:00:00', '2026-11-05 07:59:59'], array_column($utcRows, 'date'));
    }

    public function test_bundle_mirrors_are_excluded_but_separate_legacy_fees_remain(): void
    {
        $owner = $this->makeUserWithRole('alumni');
        $this->actingAs($owner);
        $appointment = Appointment::factory()->create(['user_id' => $owner->id]);
        AppointmentPayment::factory()->verified()->create(['appointment_id' => $appointment->id, 'is_document_bundle' => true, 'amount' => '70.00']);
        $this->document($owner, ['appointment_id' => $appointment->id]);
        $this->document($owner, ['appointment_id' => $appointment->id, 'fee' => '20.00', 'amount_received' => '20.00']);
        $legacy = Appointment::factory()->create(['user_id' => $owner->id]);
        AppointmentPayment::factory()->verified()->create(['appointment_id' => $legacy->id, 'amount' => '50.00', 'reference_number' => 'LEGACY']);
        $this->document($owner, ['appointment_id' => $legacy->id, 'payment_method' => 'gcash_qr', 'reference_number' => 'LEGACY']);
        $this->document($owner, ['appointment_id' => $legacy->id, 'receipt_no' => 'SEPARATE']);
        $rows = $this->csv();
        $this->assertCount(3, $rows);
        $this->assertSame(17000, array_sum(array_map(fn ($row) => (int) str_replace('.', '', $row['collected']), $rows)));
    }

    public function test_csv_safety_and_non_verified_amounts_never_become_collections(): void
    {
        $owner = $this->makeUserWithRole('student');
        $this->actingAs($owner);
        $this->payment($owner, ['receipt_no' => " \t=SUM(1,2)\"Ñ\"", 'amount' => '0.10', 'refund_status' => 'refunded', 'refund_amount' => '0.10', 'refunded_at' => now(), 'refund_reference' => '+REF']);
        $this->payment($owner, ['status' => 'pending']);
        $this->payment($owner, ['status' => 'rejected']);
        $this->document($owner, ['payment_status' => 'unpaid', 'is_paid' => false]);
        $rows = $this->csv();
        foreach ($rows as $row) {
            if ($row['status'] === 'Verified') {
                $this->assertSame("' \t=SUM(1,2)\"Ñ\"", $row['reference']);
                $this->assertSame('0.10', $row['collected']);
                $this->assertSame('0.10', $row['refund_amount']);
                $this->assertSame("'+REF", $row['refund_reference']);
            } else {
                $this->assertSame('0.00', $row['collected']);
                $this->assertSame('—', $row['verified']);
            }
        }
        $this->assertCount(4, $rows);
    }

    public function test_real_pdf_generation_and_empty_exports_do_not_change_receipt_eligibility(): void
    {
        foreach (['student', 'alumni'] as $role) {
            $owner = $this->makeUserWithRole($role);
            $this->actingAs($owner);
            $document = $this->document($owner, ['receipt_no' => null]);
            $response = $this->get(route('portal.transactions.export.pdf'))->assertOk()->assertHeader('Content-Type', 'application/pdf');
            $this->assertStringStartsWith('%PDF-', $response->getContent());
            $this->get(route('portal.transactions.receipt', 'D-'.$document->id))->assertNotFound();
            $this->assertCount(0, $this->csv(['search' => 'no-matching-history']));
            $this->get(route('portal.transactions.export.pdf', ['search' => 'no-matching-history']))->assertOk();
        }
    }

    public function test_filters_are_validated_on_both_exports(): void
    {
        $this->actingAs($this->makeUserWithRole('alumni'));
        foreach (['pdf', 'csv'] as $type) {
            foreach ([['category' => 'enrollment'], ['status' => 'invalid'], ['method' => 'invalid'], ['search' => ['invalid']], ['from' => '2026-02-30'], ['from' => '2026-11-10', 'to' => '2026-11-01']] as $filters) {
                $this->getJson(route('portal.transactions.export.'.$type, $filters))->assertUnprocessable();
            }
        }
    }

    public function test_csv_includes_more_than_one_batch_without_duplicates(): void
    {
        $owner = $this->makeUserWithRole('student');
        $this->actingAs($owner);
        $payment = $this->payment($owner);
        Payment::factory()->count(501)->create(['enrollment_id' => $payment->enrollment_id]);
        $rows = $this->csv(['per_page' => 1, 'page' => 10]);
        $this->assertCount(502, $rows);
        $this->assertCount(502, array_unique(array_column($rows, 'key')));
    }

    public function test_pdf_limit_redirects_without_silently_truncating_history(): void
    {
        $this->actingAs($this->makeUserWithRole('student'));
        $query = \Mockery::mock(\Illuminate\Database\Query\Builder::class);
        $query->shouldReceive('reorder')->andReturnSelf();
        $query->shouldReceive('count')->andReturn(2001);
        $history = \Mockery::mock(TransactionHistory::class);
        $history->shouldReceive('query')->once()->andReturn($query);
        $this->app->instance(TransactionHistory::class, $history);
        Pdf::shouldReceive('loadView')->never();
        $this->get(route('portal.transactions.export.pdf', ['status' => 'pending']))
            ->assertRedirect(route('portal.transactions.index', ['status' => 'pending']))->assertSessionHas('history_export_notice');
    }
}
