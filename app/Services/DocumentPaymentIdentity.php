<?php

namespace App\Services;

use Illuminate\Database\Query\Builder;

/** Identity rules for document rows that mirror an appointment payment. */
final class DocumentPaymentIdentity
{
    /** The document query must use the alias d. */
    public static function standalone(Builder $query): Builder
    {
        return $query
            // Bundle children mirror the authoritative appointment payment, even if the booking was cancelled.
            ->whereNotExists(function (Builder $query) {
                $query->selectRaw('1')->from('appointment_payments as ap')->whereColumn('ap.appointment_id', 'd.appointment_id')
                    ->where(function (Builder $duplicate) {
                        $duplicate->where('ap.is_document_bundle', true)
                            // Legacy processing fees may be separate payments. Suppress only an exact linked payment identity.
                            ->orWhere(function (Builder $legacy) {
                                $legacy->where('ap.status', 'verified')->whereColumn('ap.amount', 'd.amount_received')
                                    ->whereRaw("ap.payment_method = CASE WHEN d.payment_method = 'gcash_qr' THEN 'gcash' ELSE d.payment_method END")
                                    ->where(function (Builder $identity) {
                                        $identity->where(fn (Builder $q) => $q->whereNotNull('ap.receipt_no')->where('ap.receipt_no', '<>', '')->whereColumn('ap.receipt_no', 'd.receipt_no'))
                                            ->orWhere(fn (Builder $q) => $q->whereNotNull('ap.reference_number')->where('ap.reference_number', '<>', '')->whereColumn('ap.reference_number', 'd.reference_number'));
                                    });
                            });
                    });
            });
    }
}
