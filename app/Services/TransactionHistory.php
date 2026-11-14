<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/** Read-only normalized view of the existing enrollment and document payment records. */
class TransactionHistory
{
    public function query(int $userId, string $role, array $filters = []): Builder
    {
        $staff = in_array($role, ['admin', 'cashier', 'registrar'], true);
        $includeEnrollments = $staff || in_array($role, ['student', 'new_applicant'], true);
        $includeDocuments = $staff || in_array($role, ['alumni', 'student'], true);
        $includeAppointments = $staff || in_array($role, ['alumni', 'student'], true);
        $parts = [];

        if ($includeEnrollments) {
            $parts[] = DB::table('payments as p')
                ->join('enrollments as e', 'e.id', '=', 'p.enrollment_id')
                ->join('users as u', 'u.id', '=', 'e.user_id')
                ->leftJoin('users as processor', 'processor.id', '=', 'p.processed_by')
                ->leftJoin('users as verifier', 'verifier.id', '=', 'p.verified_by')
                ->leftJoin('users as refund_confirmer', 'refund_confirmer.id', '=', 'p.refunded_by')
                ->where(function ($q) use ($staff, $userId) {
                    if (! $staff) {
                        $q->where('e.user_id', $userId);
                    }
                })
                ->selectRaw("CONCAT('E-', p.id) as transaction_key, p.id as source_id, 'enrollment' as category, u.name as payer, u.email as email, CONCAT('Enrollment #', e.id) as related, p.amount as amount, p.payment_method as method, p.status as status, p.receipt_no as receipt_no, p.amount as receipt_amount, p.created_at as occurred_at, p.verified_at as verified_at, COALESCE(verifier.name, processor.name) as staff_name, p.reference_number as reference, e.id as enrollment_id, NULL as document_request_id, p.rejection_reason as rejection_reason, p.refund_status as refund_status, p.refund_amount as refund_amount, p.refund_reference as refund_reference, p.refunded_at as refunded_at, refund_confirmer.name as refund_confirmer");
        }
        if ($includeDocuments) {
            $parts[] = DocumentPaymentIdentity::standalone(DB::table('document_requests as d'))
                ->join('users as u', 'u.id', '=', 'd.user_id')
                ->leftJoin('users as verifier', 'verifier.id', '=', 'd.verified_by')
                ->leftJoin('users as rejecter', 'rejecter.id', '=', 'd.rejected_by')
                ->where(fn ($q) => $q->where('d.status', '<>', 'cancelled')->orWhere('d.payment_status', 'verified'))
                ->where(fn ($q) => $q->where('d.fee', '>', 0)->orWhere('d.is_paid', true))
                ->where(function ($q) use ($staff, $userId) {
                    if (! $staff) {
                        $q->where('d.user_id', $userId);
                    }
                })
                ->selectRaw("CONCAT('D-', d.id) as transaction_key, d.id as source_id, 'document' as category, u.name as payer, u.email as email, CONCAT('Document request #', d.id, ' — ', d.document_type) as related, COALESCE(d.amount_received, d.fee) as amount, d.payment_method as method, d.payment_status as status, d.receipt_no as receipt_no, CASE WHEN d.is_paid = 1 AND d.fee > 0 THEN d.amount_received ELSE NULL END as receipt_amount, COALESCE(d.payment_submitted_at, d.created_at) as occurred_at, d.verified_at as verified_at, COALESCE(verifier.name, rejecter.name) as staff_name, d.reference_number as reference, NULL as enrollment_id, d.id as document_request_id, d.payment_rejection_reason as rejection_reason, NULL as refund_status, NULL as refund_amount, NULL as refund_reference, NULL as refunded_at, NULL as refund_confirmer");
        }
        if ($includeAppointments) {
            $parts[] = DB::table('appointment_payments as ap')
                ->join('appointments as a', 'a.id', '=', 'ap.appointment_id')
                ->join('users as u', 'u.id', '=', 'a.user_id')
                ->leftJoin('users as verifier', 'verifier.id', '=', 'ap.verified_by')
                ->where(function ($q) use ($staff, $userId) {
                    if (! $staff) {
                        $q->where('a.user_id', $userId);
                    }
                })
                ->selectRaw("CONCAT('A-', ap.id) as transaction_key, ap.id as source_id, 'appointment' as category, u.name as payer, u.email as email, CASE WHEN ap.is_document_bundle = 1 THEN CONCAT('Document bundle — Appointment #', a.id) ELSE CONCAT('Appointment #', a.id, ' — ', a.document_type) END as related, ap.amount as amount, ap.payment_method as method, CASE WHEN ap.is_document_bundle = 1 AND ap.status = 'pending' THEN CASE WHEN ap.payment_method IN ('gcash', 'gcash_qr') THEN 'pending_verification' WHEN ap.payment_method IN ('cash', 'walk_in') THEN 'unpaid' ELSE ap.status END ELSE ap.status END as status, ap.receipt_no as receipt_no, ap.amount as receipt_amount, ap.created_at as occurred_at, ap.verified_at as verified_at, verifier.name as staff_name, ap.reference_number as reference, NULL as enrollment_id, NULL as document_request_id, ap.rejection_reason as rejection_reason, NULL as refund_status, NULL as refund_amount, NULL as refund_reference, NULL as refunded_at, NULL as refund_confirmer");
        }

        $union = array_shift($parts);
        foreach ($parts as $part) {
            $union->unionAll($part);
        }
        $query = DB::query()->fromSub($union, 'transactions');
        foreach (['category', 'method', 'status'] as $filter) {
            if (! empty($filters[$filter])) {
                if ($filter === 'method' && in_array($filters[$filter], ['cash', 'gcash'], true)) {
                    $query->whereIn('method', $filters[$filter] === 'cash' ? ['cash', 'walk_in'] : ['gcash', 'gcash_qr']);
                } else {
                    $query->where($filter, $filters[$filter]);
                }
            }
        }
        if ($staff && ! empty($filters['staff'])) {
            $query->where('staff_name', 'like', '%'.addcslashes($filters['staff'], '%_\\').'%');
        }
        if (! empty($filters['search'])) {
            $term = '%'.addcslashes($filters['search'], '%_\\').'%';
            $query->where(function ($q) use ($term, $staff) {
                $q->where('transaction_key', 'like', $term)->orWhere('reference', 'like', $term)->orWhere('receipt_no', 'like', $term)->orWhere('related', 'like', $term);
                if ($staff) {
                    $q->orWhere('payer', 'like', $term)->orWhere('email', 'like', $term)->orWhere('staff_name', 'like', $term);
                }
            });
        }
        if (! empty($filters['from'])) {
            $query->where('occurred_at', '>=', CarbonImmutable::parse($filters['from'], 'Asia/Manila')->startOfDay()->setTimezone(config('app.timezone'))->format('Y-m-d H:i:s'));
        }
        if (! empty($filters['to'])) {
            $query->where('occurred_at', '<', CarbonImmutable::parse($filters['to'], 'Asia/Manila')->startOfDay()->addDay()->setTimezone(config('app.timezone'))->format('Y-m-d H:i:s'));
        }

        return $query->orderByDesc('occurred_at')->orderByDesc('transaction_key');
    }

    public function find(int $userId, string $role, string $key): ?object
    {
        if (! preg_match('/^[EDA]-[1-9][0-9]*$/', $key)) {
            return null;
        }

        return $this->query($userId, $role)->where('transaction_key', $key)->first();
    }

    public function summary(int $userId, string $role, array $filters, bool $allStatuses = true): array
    {
        // Cards describe the filtered payment records, across status tabs, not period-based collection events.
        if ($allStatuses) {
            unset($filters['status']);
        }
        $row = $this->query($userId, $role, $filters)->reorder()->selectRaw("COALESCE(SUM(CASE WHEN status = 'verified' AND receipt_amount > 0 THEN CAST(ROUND(receipt_amount * 100, 0) AS SIGNED) ELSE 0 END), 0) AS verified_cents, SUM(CASE WHEN status IN ('pending', 'pending_verification', 'unpaid') THEN 1 ELSE 0 END) AS pending_count, COALESCE(SUM(CASE WHEN status = 'verified' AND refund_status = 'refunded' AND refunded_at IS NOT NULL AND refund_amount > 0 THEN CAST(ROUND(refund_amount * 100, 0) AS SIGNED) ELSE 0 END), 0) AS refund_cents")->first();

        return ['verified_cents' => (int) $row->verified_cents, 'pending_count' => (int) $row->pending_count, 'refund_cents' => (int) $row->refund_cents];
    }

    public function receiptEligible(object $record): bool
    {
        return $record->status === 'verified' && $record->verified_at !== null
            && trim((string) $record->receipt_no) !== '' && $record->receipt_amount !== null
            && $this->centavos($record->receipt_amount) > 0;
    }

    public function centavos(string $amount): int
    {
        // Database money columns are DECIMAL(8,2); never pass through binary floating point.
        $negative = str_starts_with($amount, '-');
        [$whole, $fraction] = array_pad(explode('.', ltrim($amount, '-'), 2), 2, '0');

        return ($negative ? -1 : 1) * (((int) $whole * 100) + (int) str_pad($fraction, 2, '0'));
    }
}
