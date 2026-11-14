<?php

namespace App\Http\Controllers;

use App\Services\PersonalPaymentHistoryExport;
use App\Services\TransactionHistory;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class TransactionHistoryController extends Controller
{
    public function index(Request $request, TransactionHistory $history)
    {
        $role = $this->scopeRole($request);
        $categories = $role === 'alumni' ? ['document', 'appointment'] : ($role === 'new_applicant' ? ['enrollment'] : ['enrollment', 'document', 'appointment']);
        $filters = $this->filters($request, $role);
        $statusFilters = $filters;
        unset($statusFilters['status']);
        $statusCounts = $history->query($request->user()->id, $role, $statusFilters)
            ->reorder()->select('status')->selectRaw('COUNT(*) as total')->groupBy('status')->pluck('total', 'status');
        $transactions = $history->query($request->user()->id, $role, $filters)->paginate($filters['per_page'] ?? 15)->withQueryString();
        $summary = in_array($role, ['student', 'alumni'], true) ? $history->summary($request->user()->id, $role, $filters) : null;

        return response()->view('transactions.index', compact('transactions', 'role', 'summary', 'categories', 'filters', 'statusCounts'))->header('Cache-Control', 'private, no-store');
    }

    private function filters(Request $request, string $role): array
    {
        $categories = $role === 'alumni' ? ['document', 'appointment'] : ($role === 'new_applicant' ? ['enrollment'] : ['enrollment', 'document', 'appointment']);

        return $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'staff' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', Rule::in($categories)],
            'method' => ['nullable', 'in:cash,walk_in,gcash,gcash_qr'],
            'status' => ['nullable', 'in:pending,pending_verification,unpaid,verified,rejected'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => array_merge(['nullable', 'date_format:Y-m-d'], $request->filled('from') ? ['after_or_equal:from'] : []),
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);
    }

    public function exportPdf(Request $request, TransactionHistory $history, PersonalPaymentHistoryExport $export)
    {
        $role = $this->scopeRole($request);
        abort_unless(in_array($role, ['student', 'alumni'], true) && $request->user()->hasAnyRole(['student', 'alumni']), 403);
        $filters = $this->filters($request, $role);
        $data = DB::transaction(function () use ($request, $history, $role, $filters) {
            $query = $history->query($request->user()->id, $role, $filters);
            if ((clone $query)->reorder()->count() > PersonalPaymentHistoryExport::PDF_LIMIT) {
                return null;
            }
            $transactions = $query->limit(PersonalPaymentHistoryExport::PDF_LIMIT + 1)->get();
            if ($transactions->count() > PersonalPaymentHistoryExport::PDF_LIMIT) {
                return null;
            }

            return ['transactions' => $transactions, 'summary' => $history->summary($request->user()->id, $role, $filters, false)];
        });
        if ($data === null) {
            return redirect()->route('portal.transactions.index', $filters)->with('history_export_notice', 'PDF exports support up to '.PersonalPaymentHistoryExport::PDF_LIMIT.' records. Narrow the filters or export CSV for all matching records.');
        }

        return Pdf::loadView('transactions.history-pdf', $data + [
            'holder' => $request->user()->name, 'filters' => $filters, 'export' => $export,
            'generatedAt' => now('Asia/Manila')->format('M j, Y g:i A').' PHT',
        ])->setPaper('a4', 'landscape')->download('DTC_Personal_Payment_History_'.now('Asia/Manila')->format('Ymd_His').'.pdf')
            ->header('Cache-Control', 'private, no-store');
    }

    public function exportCsv(Request $request, TransactionHistory $history, PersonalPaymentHistoryExport $export)
    {
        $role = $this->scopeRole($request);
        abort_unless(in_array($role, ['student', 'alumni'], true) && $request->user()->hasAnyRole(['student', 'alumni']), 403);
        $filters = $this->filters($request, $role);
        $ownerId = $request->user()->id;

        return response()->streamDownload(function () use ($history, $export, $ownerId, $role, $filters) {
            $stream = fopen('php://output', 'w');
            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, array_values(PersonalPaymentHistoryExport::COLUMNS), ',', '"', '');
            DB::transaction(function () use ($history, $export, $ownerId, $role, $filters, $stream) {
                foreach ($history->query($ownerId, $role, $filters)->lazy(500) as $record) {
                    fputcsv($stream, $export->csvRow($record), ',', '"', '');
                }
            });
            fclose($stream);
        }, 'DTC_Personal_Payment_History_'.now('Asia/Manila')->format('Ymd_His').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function show(Request $request, TransactionHistory $history, string $transaction)
    {
        $role = $this->scopeRole($request);
        $record = $history->find($request->user()->id, $role, $transaction);
        abort_unless($record, 404);

        return response()->view('transactions.show', ['transaction' => $record, 'role' => $role,
            'receiptEligible' => $history->receiptEligible($record)])->header('Cache-Control', 'private, no-store');
    }

    public function receipt(Request $request, TransactionHistory $history, string $transaction)
    {
        // Scope comes from the portal route, including for accounts that also have staff roles.
        $record = $history->find($request->user()->id, $this->scopeRole($request), $transaction);
        abort_unless($record && $history->receiptEligible($record), 404);

        return Pdf::loadView('transactions.receipt', ['transaction' => $record, 'amountCents' => $history->centavos($record->receipt_amount)])
            ->setPaper('a4')->download('DTC_Receipt_'.$record->transaction_key.'.pdf')
            ->header('Cache-Control', 'private, no-store');
    }

    /** The route group defines the scope; a user's role ordering must never widen portal access. */
    private function scopeRole(Request $request): string
    {
        $name = (string) $request->route()->getName();
        if (str_starts_with($name, 'portal.')) {
            $user = $request->user();

            return $user->hasRole('alumni') ? 'alumni' : ($user->hasRole('student') ? 'student' : 'new_applicant');
        }
        if (str_starts_with($name, 'admin.')) {
            return 'admin';
        }
        if (str_starts_with($name, 'cashier.')) {
            return 'cashier';
        }

        return 'registrar';
    }
}
