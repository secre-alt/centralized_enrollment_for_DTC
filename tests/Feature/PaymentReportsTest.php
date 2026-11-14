<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\AppointmentPayment;
use App\Models\DocumentRequest;
use App\Models\Payment;
use App\Services\Reports\PaymentReportFilters;
use App\Services\Reports\PaymentReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\SeedsRoles;
use Tests\TestCase;

class PaymentReportsTest extends TestCase
{
    use RefreshDatabase, SeedsRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-10 12:00:00', 'Asia/Manila'));
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    private function payment(array $attributes = []): Payment
    {
        return Payment::factory()->create(array_merge(['verified_at' => '2026-11-05 12:00:00', 'amount' => '500.00'], $attributes));
    }

    private function report(array $filters = []): array
    {
        return app(PaymentReportService::class)->summary(PaymentReportFilters::fromArray($filters));
    }

    private function document(array $attributes = []): DocumentRequest
    {
        return DocumentRequest::create(array_merge([
            'user_id' => $this->makeUserWithRole('student')->id, 'document_type' => 'tor', 'fee' => '50.00',
            'amount_received' => '50.00', 'payment_status' => 'verified', 'is_paid' => true,
            'verified_at' => '2026-11-05 12:00:00', 'payment_method' => 'walk_in',
        ], $attributes));
    }

    public function test_default_and_all_presets_resolve_inclusive_manila_calendar_dates(): void
    {
        foreach ([
            'today' => ['2026-11-10', '2026-11-10'], 'last_7_days' => ['2026-11-04', '2026-11-10'],
            'last_30_days' => ['2026-10-12', '2026-11-10'], 'this_month' => ['2026-11-01', '2026-11-30'],
            'last_month' => ['2026-10-01', '2026-10-31'], 'this_year' => ['2026-01-01', '2026-12-31'],
        ] as $period => [$from, $to]) {
            $filters = PaymentReportFilters::fromArray(['period' => $period]);
            $this->assertSame($from, $filters->query()['from']);
            $this->assertSame($to, $filters->query()['to']);
        }
        $this->assertSame('this_month', PaymentReportFilters::fromArray([])->period);
    }

    public function test_collections_require_verified_status_timestamp_and_positive_amount(): void
    {
        $this->payment(['amount' => '0.10', 'created_at' => '2026-10-01']);
        $this->payment(['amount' => '0.20']);
        $this->payment(['status' => 'pending']);
        $this->payment(['status' => 'rejected']);
        $this->payment(['amount' => '0.00']);
        $this->payment(['verified_at' => null]);
        $this->payment(['verified_at' => '2026-10-31 23:59:59']);
        $summary = $this->report();
        $this->assertSame(30, $summary['gross']);
        $this->assertSame(2, $summary['verified_count']);
        $this->assertSame(1, $summary['undated_count']);
    }

    public function test_custom_boundaries_use_manila_midnight_and_exclusive_next_day(): void
    {
        foreach (['2026-11-03 23:59:59', '2026-11-04 00:00:00', '2026-11-10 23:59:59', '2026-11-11 00:00:00'] as $date) {
            $this->payment(['verified_at' => $date, 'amount' => '1.00']);
        }
        $this->assertSame(200, $this->report(['period' => 'custom', 'from' => '2026-11-04', 'to' => '2026-11-10'])['gross']);
    }

    public function test_manila_boundaries_are_converted_when_storage_timezone_is_utc(): void
    {
        config(['app.timezone' => 'UTC']);
        foreach (['2026-11-03 15:59:59', '2026-11-03 16:00:00', '2026-11-04 15:59:59', '2026-11-04 16:00:00'] as $date) {
            $this->payment(['verified_at' => $date, 'amount' => '1.00']);
        }
        $filters = PaymentReportFilters::fromArray(['period' => 'custom', 'from' => '2026-11-04', 'to' => '2026-11-04']);
        $this->assertSame(200, app(PaymentReportService::class)->summary($filters)['gross']);
        $this->assertSame(200, app(PaymentReportService::class)->chart($filters)['points'][0]['gross']);
        $this->assertSame('2026-11-04 00:00:00', PaymentReportService::date('2026-11-03 16:00:00'));
    }

    public function test_confirmed_refunds_belong_to_their_own_period_and_do_not_remove_original_collection_count(): void
    {
        $this->payment(['verified_at' => '2026-10-15 10:00:00', 'refund_status' => 'refunded', 'refund_amount' => '500.00', 'refunded_at' => '2026-11-05 10:00:00']);
        $this->payment(['refund_status' => 'pending', 'refund_amount' => '500.00', 'refunded_at' => '2026-11-05 10:00:00']);
        $summary = $this->report();
        $this->assertSame(50000, $summary['gross']);
        $this->assertSame(50000, $summary['refunds']);
        $this->assertSame(0, $summary['net']);
        $this->assertSame(1, $summary['verified_count']);
        $october = $this->report(['period' => 'last_month']);
        $this->assertSame(50000, $october['gross']);
        $this->assertSame(0, $october['refunds']);
        $this->assertSame(1, $october['verified_count']);
    }

    public function test_bundle_is_counted_once_and_cancelled_booking_does_not_erase_collection(): void
    {
        $appointment = Appointment::factory()->create(['status' => 'cancelled']);
        AppointmentPayment::factory()->verified()->create(['appointment_id' => $appointment->id, 'is_document_bundle' => true, 'amount' => '70.00', 'verified_at' => '2026-11-05 10:00:00']);
        $this->document(['appointment_id' => $appointment->id, 'fee' => '50.00']);
        $this->document(['appointment_id' => $appointment->id, 'fee' => '20.00', 'amount_received' => '20.00']);
        $this->document(['fee' => '0.00', 'amount_received' => '0.00']);
        $this->assertSame(7000, $this->report(['type' => 'documents'])['gross']);
        $this->assertSame(1, $this->report()['verified_count']);
    }

    public function test_legacy_matching_payment_is_deduplicated_but_separate_processing_fee_is_preserved(): void
    {
        $appointment = Appointment::factory()->create();
        AppointmentPayment::factory()->verified()->create(['appointment_id' => $appointment->id, 'amount' => '50.00', 'reference_number' => 'MATCH', 'verified_at' => '2026-11-05 10:00:00']);
        $this->document(['appointment_id' => $appointment->id, 'payment_method' => 'gcash_qr', 'reference_number' => 'MATCH']);
        $this->document(['appointment_id' => $appointment->id, 'receipt_no' => 'SEPARATE']);
        $this->assertSame(10000, $this->report()['gross']);
        $this->assertSame(2, $this->report()['verified_count']);
    }

    public function test_type_and_method_filters_normalize_cash_and_gcash_variants(): void
    {
        $this->payment();
        $this->document(['payment_method' => 'gcash_qr']);
        $this->assertSame(50000, $this->report(['type' => 'enrollment', 'method' => 'cash'])['gross']);
        $this->assertSame(5000, $this->report(['type' => 'documents', 'method' => 'gcash'])['gross']);
        $this->assertSame(0, $this->report(['type' => 'enrollment', 'method' => 'gcash'])['gross']);
    }

    public function test_chart_zero_fills_daily_or_monthly_buckets_and_matches_summary(): void
    {
        $this->payment();
        $service = app(PaymentReportService::class);
        $filters = PaymentReportFilters::fromArray([]);
        $chart = $service->chart($filters);
        $this->assertSame('Daily', $chart['interval']);
        $this->assertCount(30, $chart['points']);
        $this->assertSame($service->summary($filters)['gross'], array_sum(array_column($chart['points'], 'gross')));
        $year = $service->chart(PaymentReportFilters::fromArray(['period' => 'this_year']));
        $this->assertSame('Monthly', $year['interval']);
        $this->assertCount(12, $year['points']);
    }

    public function test_page_and_exports_require_admin_or_cashier_on_the_server(): void
    {
        foreach (['index', 'pdf', 'csv'] as $action) {
            $this->get(route('reports.payments.'.$action))->assertRedirect(route('login'));
        }
        foreach (['student', 'alumni', 'new_applicant', 'registrar'] as $role) {
            $this->actingAs($this->makeUserWithRole($role));
            foreach (['index', 'pdf', 'csv'] as $action) {
                $this->get(route('reports.payments.'.$action))->assertForbidden();
            }
        }
        foreach (['admin', 'cashier'] as $role) {
            $this->actingAs($this->makeUserWithRole($role));
            app()->forgetInstance(\JeroenNoten\LaravelAdminLte\AdminLte::class);
            $this->get(route('reports.payments.index'))->assertOk()->assertSee('Payment Reports')->assertSee('Asia/Manila');
            $this->get(route('reports.payments.csv'))->assertOk();
            $this->get(route('reports.payments.pdf'))->assertOk()->assertHeader('content-type', 'application/pdf');
        }
    }

    public function test_invalid_filters_are_rejected_for_page_and_both_exports(): void
    {
        $this->actingAs($this->makeUserWithRole('cashier'));
        foreach (['index', 'pdf', 'csv'] as $action) {
            foreach ([
                [['period' => 'unknown'], 'period'], [['type' => 'invalid'], 'type'], [['method' => 'gcash_qr'], 'method'],
                [['period' => 'custom'], 'from'], [['period' => 'custom', 'from' => '2026-02-30', 'to' => '2026-03-01'], 'from'],
                [['period' => 'custom', 'from' => '2026-11-10', 'to' => '2026-11-01'], 'to'],
                [['period' => ['today']], 'period'], [['page' => -1], 'page'],
            ] as [$input, $field]) {
                $this->getJson(route('reports.payments.'.$action, $input))->assertUnprocessable()->assertJsonValidationErrors($field);
            }
        }
    }

    public function test_page_csv_and_pdf_use_the_same_filtered_events_and_totals(): void
    {
        $this->actingAs($this->makeUserWithRole('cashier'));
        $this->payment(['amount' => '10.10', 'receipt_no' => 'INCLUDED']);
        $this->payment(['amount' => '20.20', 'receipt_no' => 'REFUNDED', 'refund_status' => 'refunded', 'refund_amount' => '20.20', 'refund_reference' => 'REF-1', 'refunded_at' => '2026-11-07 11:00:00']);
        $this->payment(['amount' => '99.00', 'payment_method' => 'gcash']);
        $this->document();
        $params = ['period' => 'custom', 'from' => '2026-11-01', 'to' => '2026-11-10', 'type' => 'enrollment', 'method' => 'cash'];
        $page = $this->get(route('reports.payments.index', $params))->assertOk();
        $summary = $page->viewData('summary');
        $this->assertSame(3030, $summary['gross']);
        $this->assertSame(2020, $summary['refunds']);
        $this->assertSame(1010, $summary['net']);
        $keys = $page->viewData('transactions')->pluck('event_key')->all();
        $csv = $this->get(route('reports.payments.csv', $params))->assertOk()->streamedContent();
        $stream = fopen('php://temp', 'w+');
        fwrite($stream, $csv);
        rewind($stream);
        fgetcsv($stream);
        $csvKeys = [];
        $net = 0;
        while (($row = fgetcsv($stream)) !== false) {
            $csvKeys[] = $row[0];
            $net += (int) str_replace('.', '', $row[8]);
        }
        fclose($stream);
        $this->assertSame($keys, $csvKeys);
        $this->assertSame($summary['net'], $net);
        $pdf = \Mockery::mock(\Barryvdh\DomPDF\PDF::class);
        Pdf::shouldReceive('loadView')->once()->with('admin.reports.payment', \Mockery::on(function ($data) use ($keys, $summary) {
            $this->assertSame($summary, $data['summary']);
            $this->assertSame($keys, $data['transactions']->pluck('event_key')->all());
            $this->assertStringContainsString('PHT', $data['generatedAt']);

            return true;
        }))->andReturn($pdf);
        $pdf->shouldReceive('setPaper')->with('a4', 'landscape')->andReturnSelf();
        $pdf->shouldReceive('download')->andReturn(response('PDF', 200, ['Content-Type' => 'application/pdf']));
        $this->get(route('reports.payments.pdf', $params))->assertOk();
    }

    public function test_csv_escapes_untrusted_cells_and_preserves_decimal_amounts(): void
    {
        $cashier = $this->makeUserWithRole('cashier', ['name' => '+SUM(1,2)']);
        $this->actingAs($cashier);
        $payment = $this->payment(['amount' => '0.10', 'receipt_no' => '=1+1', 'verified_by' => $cashier->id]);
        $payment->enrollment->user->update(['name' => "  @SUM(1,2)\n\"Quoted\""]);
        $csv = $this->get(route('reports.payments.csv'))->streamedContent();
        $stream = fopen('php://temp', 'w+');
        fwrite($stream, $csv);
        rewind($stream);
        fgetcsv($stream);
        $row = fgetcsv($stream);
        fclose($stream);
        $this->assertSame("'=1+1", $row[4]);
        $this->assertSame("'  @SUM(1,2)\n\"Quoted\"", $row[5]);
        $this->assertSame('0.10', $row[8]);
        $this->assertSame("'+SUM(1,2)", $row[9]);
        $this->assertSame("'\t=1+1", PaymentReportService::csvText("\t=1+1"));
    }

    public function test_pagination_keeps_filters_and_csv_includes_every_page(): void
    {
        $this->actingAs($this->makeUserWithRole('admin'));
        Payment::factory()->count(27)->create(['verified_at' => '2026-11-05 12:00:00']);
        $page = $this->get(route('reports.payments.index', ['period' => 'last_7_days', 'type' => 'enrollment', 'method' => 'cash']))->assertOk();
        $this->assertCount(25, $page->viewData('transactions'));
        $page->assertSee('period=last_7_days')->assertSee('type=enrollment');
        $csv = $this->get(route('reports.payments.csv', ['period' => 'last_7_days']))->streamedContent();
        $this->assertCount(29, explode("\n", $csv));
    }

    public function test_old_admin_payment_report_link_opens_filtered_dashboard(): void
    {
        $this->actingAs($this->makeUserWithRole('admin'));
        $filters = PaymentReportFilters::fromArray(['period' => 'last_7_days']);
        $this->get(route('admin.reports.payment', ['period' => 'last_7_days']))->assertRedirect(route('reports.payments.index', $filters->query()));
    }

    public function test_refund_only_period_can_have_negative_net_without_verified_transactions(): void
    {
        $this->payment(['verified_at' => '2026-10-01 12:00:00', 'refund_status' => 'refunded', 'refund_amount' => '500.00', 'refunded_at' => '2026-11-10 12:00:00']);
        $summary = $this->report();
        $this->assertSame(0, $summary['gross']);
        $this->assertSame(-50000, $summary['net']);
        $this->assertSame(0, $summary['verified_count']);
        $this->assertSame(1, $summary['event_count']);
    }

    public function test_pending_rejected_free_and_undated_document_payments_do_not_enter_collections(): void
    {
        $this->document(['payment_status' => 'pending_verification']);
        $this->document(['payment_status' => 'rejected']);
        $this->document(['is_paid' => false]);
        $this->document(['fee' => '0.00', 'amount_received' => '50.00']);
        $this->document(['verified_at' => null]);
        AppointmentPayment::factory()->create(['amount' => '50.00']);
        AppointmentPayment::factory()->rejected()->create(['amount' => '50.00']);
        AppointmentPayment::factory()->verified()->create(['amount' => '0.00']);
        $this->assertSame(0, $this->report()['gross']);
        $this->assertSame(1, $this->report()['undated_count']);
    }

    public function test_csv_streams_across_multiple_batches_without_missing_or_duplicate_events(): void
    {
        $this->actingAs($this->makeUserWithRole('cashier'));
        $first = $this->payment(['amount' => '0.10']);
        Payment::factory()->count(502)->create(['enrollment_id' => $first->enrollment_id, 'amount' => '0.10', 'verified_at' => '2026-11-05 12:00:00']);
        $csv = $this->get(route('reports.payments.csv'))->assertOk()->streamedContent();
        $stream = fopen('php://temp', 'w+');
        fwrite($stream, $csv);
        rewind($stream);
        fgetcsv($stream);
        $keys = [];
        while (($row = fgetcsv($stream)) !== false) {
            $keys[] = $row[0];
        }
        fclose($stream);
        $this->assertCount(503, $keys);
        $this->assertCount(503, array_unique($keys));
        $this->assertSame(5030, $this->report()['gross']);
    }

    public function test_large_pdf_reports_redirect_to_filters_without_loading_dompdf(): void
    {
        $this->actingAs($this->makeUserWithRole('cashier'));
        $service = \Mockery::mock(PaymentReportService::class)->makePartial();
        $service->shouldReceive('summary')->once()->andReturn(['event_count' => PaymentReportService::PDF_LIMIT + 1]);
        $service->shouldNotReceive('ordered');
        $this->app->instance(PaymentReportService::class, $service);
        Pdf::shouldReceive('loadView')->never();
        $filters = PaymentReportFilters::fromArray(['period' => 'last_7_days']);
        $this->get(route('reports.payments.pdf', ['period' => 'last_7_days']))
            ->assertRedirect(route('reports.payments.index', $filters->query()))->assertSessionHas('report_notice');
    }
}
