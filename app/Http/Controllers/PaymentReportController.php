<?php

namespace App\Http\Controllers;

use App\Http\Requests\PaymentReportRequest;
use App\Services\Reports\PaymentReportFilters;
use App\Services\Reports\PaymentReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;

class PaymentReportController extends Controller
{
    public function index(PaymentReportRequest $request, PaymentReportService $reports)
    {
        $filters = $request->filters();
        $data = DB::transaction(fn () => [
            'filters' => $filters, 'summary' => $reports->summary($filters), 'chart' => $reports->chart($filters),
            'transactions' => $reports->ordered($filters)->paginate(25)->appends($filters->query()),
        ]);

        return response()->view('reports.payments.index', $data)->header('Cache-Control', 'private, no-store');
    }

    public function pdf(PaymentReportRequest $request, PaymentReportService $reports)
    {
        $filters = $request->filters();
        [$summary, $transactions] = DB::transaction(function () use ($filters, $reports) {
            $summary = $reports->summary($filters);

            return [$summary, $summary['event_count'] <= PaymentReportService::PDF_LIMIT
                ? $reports->ordered($filters)->limit(PaymentReportService::PDF_LIMIT + 1)->get() : collect()];
        });
        if ($summary['event_count'] > PaymentReportService::PDF_LIMIT) {
            return redirect()->route('reports.payments.index', $filters->query())->with('report_notice',
                'PDF reports support up to '.PaymentReportService::PDF_LIMIT.' rows. Narrow the date range or export CSV for the complete dataset.');
        }
        // Dompdf builds the complete document in memory. Limit materialization; CSV is the unbounded export.
        if ($transactions->count() > PaymentReportService::PDF_LIMIT) {
            return redirect()->route('reports.payments.index', $filters->query())->with('report_notice', 'The report grew while generating. Narrow the date range or use CSV.');
        }

        return Pdf::loadView('admin.reports.payment', [
            'filters' => $filters, 'summary' => $summary, 'transactions' => $transactions,
            'generatedAt' => now(PaymentReportFilters::TIMEZONE)->format('M j, Y g:i A').' PHT',
        ])->setPaper('a4', 'landscape')->download($this->filename($filters, 'pdf'))
            ->header('Cache-Control', 'private, no-store');
    }

    public function csv(PaymentReportRequest $request, PaymentReportService $reports)
    {
        $filters = $request->filters();

        return response()->streamDownload(function () use ($filters, $reports) {
            $stream = fopen('php://output', 'w');
            fwrite($stream, "\xEF\xBB\xBF"); // UTF-8 Excel support, including the peso sign and Filipino names.
            fputcsv($stream, ['Event ID', 'Event', 'Event date (Asia/Manila)', 'Verification date (Asia/Manila)', 'Receipt/reference', 'Payer', 'Payment type', 'Payment method', 'Amount (PHP; refunds negative)', 'Processing staff'], ',', '"', '');
            // lazy() retrieves bounded pages instead of PDO buffering the entire result set.
            DB::transaction(function () use ($reports, $filters, $stream) {
                foreach ($reports->ordered($filters)->lazy(500) as $row) {
                    $text = [
                        $row->event_key, ucfirst($row->event), $reports::date($row->occurred_at), $reports::date($row->verified_at),
                        $row->reference, $row->payer, ucfirst($row->type), $row->method === 'cash' ? 'Cash' : ($row->method === 'gcash' ? 'GCash' : $row->method),
                    ];
                    $values = array_map([$reports, 'csvText'], $text);
                    // Amount is generated solely from integer centavos, never user-provided spreadsheet content.
                    $values[] = $reports::decimal(($row->event === 'refund' ? -1 : 1) * (int) $row->cents);
                    $values[] = $reports::csvText($row->staff);
                    fputcsv($stream, $values, ',', '"', '');
                }
            });
            fclose($stream);
        }, $this->filename($filters, 'csv'), ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    private function filename(PaymentReportFilters $filters, string $extension): string
    {
        return 'DTC_Payment_Report_'.$filters->from->format('Ymd').'_'.$filters->until->subDay()->format('Ymd').'.'.$extension;
    }
}
