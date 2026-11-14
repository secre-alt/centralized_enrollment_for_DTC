<?php

namespace App\Services;

use App\Services\Reports\PaymentReportService as Money;

/** Presentation only: ownership, filtering and identities come from TransactionHistory. */
class PersonalPaymentHistoryExport
{
    public function __construct(private TransactionHistory $history)
    {
    }

    public const PDF_LIMIT = 2000;

    public const COLUMNS = [
        'key' => 'Transaction', 'date' => 'Transaction date (Asia/Manila)', 'reference' => 'Receipt/reference',
        'description' => 'Payment description', 'type' => 'Payment type', 'method' => 'Payment method',
        'amount' => 'Listed amount (PHP; not necessarily paid)', 'status' => 'Payment status',
        'verified' => 'Verification date (Asia/Manila)', 'collected' => 'Verified collection (PHP; before refunds)',
        'refund_status' => 'Refund status', 'refund_amount' => 'Confirmed refund (PHP)',
        'refund_reference' => 'Refund reference', 'refunded' => 'Refund confirmation date (Asia/Manila)',
    ];

    public function row(object $record): array
    {
        $history = $this->history;
        $confirmed = $record->status === 'verified' && $record->refund_status === 'refunded' && $record->refunded_at !== null && $record->refund_amount > 0;

        return [
            'key' => $record->transaction_key, 'date' => Money::date($record->occurred_at),
            'reference' => $record->receipt_no ?: ($record->reference ?: $record->transaction_key),
            'description' => $record->related,
            'type' => ['enrollment' => 'Enrollment', 'document' => 'Document request', 'appointment' => 'Appointment / bundled documents'][$record->category],
            'method' => ['cash' => 'Cash', 'walk_in' => 'Cash', 'gcash' => 'GCash', 'gcash_qr' => 'GCash'][$record->method] ?? ($record->method ?: '—'),
            'amount' => Money::decimal($history->centavos($record->amount)),
            'status' => ['pending' => 'Pending', 'pending_verification' => 'Pending verification', 'unpaid' => 'Unpaid', 'verified' => 'Verified', 'rejected' => 'Rejected'][$record->status] ?? $record->status,
            'verified' => $record->status === 'verified' ? Money::date($record->verified_at) : '—',
            'collected' => Money::decimal($record->status === 'verified' && $record->receipt_amount > 0 ? $history->centavos($record->receipt_amount) : 0),
            'refund_status' => ['pending' => 'Refund pending confirmation', 'refunded' => 'Refund confirmed'][$record->refund_status] ?? 'Not applicable',
            'refund_amount' => Money::decimal($confirmed ? $history->centavos($record->refund_amount) : 0),
            'refund_reference' => $record->refund_reference ?: '—',
            'refunded' => $confirmed ? Money::date($record->refunded_at) : '—',
        ];
    }

    public function csvRow(object $record): array
    {
        $row = $this->row($record);
        foreach ($row as $key => $value) {
            // Numeric amounts originate exclusively from database DECIMALs, formatted as integer centavos.
            if (! in_array($key, ['amount', 'collected', 'refund_amount'], true)) {
                $row[$key] = Money::csvText($value);
            }
        }

        return array_values($row);
    }
}
