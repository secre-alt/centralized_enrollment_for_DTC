<?php

namespace App\Services\Reports;

use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/** Read-only financial events. Every amount is calculated in integer centavos. */
class PaymentReportService
{
    public const PDF_LIMIT = 2000;

    private function enrollment(bool $refund = false): Builder
    {
        $date = $refund ? 'p.refunded_at' : 'p.verified_at';
        $amount = $refund ? 'p.refund_amount' : 'p.amount';
        $event = $refund ? 'refund' : 'collection';
        $reference = $refund ? 'p.refund_reference' : "COALESCE(NULLIF(p.receipt_no, ''), NULLIF(p.reference_number, ''), CONCAT('E-', p.id))";
        $staff = $refund ? 'p.refunded_by' : 'COALESCE(p.verified_by, p.processed_by)';
        $query = DB::table('payments as p')->join('enrollments as e', 'e.id', '=', 'p.enrollment_id')
            ->join('users as u', 'u.id', '=', 'e.user_id')->leftJoin('users as s', 's.id', '=', DB::raw($staff))
            ->where('p.status', 'verified')->where($amount, '>', 0);
        if ($refund) {
            $query->where('p.refund_status', 'refunded');
        }

        return $query->selectRaw("CONCAT('E-', p.id, '-{$event}') AS event_key, '{$event}' AS event, {$date} AS occurred_at, p.verified_at, {$reference} AS reference, u.name AS payer, 'enrollment' AS type, CASE WHEN p.payment_method = 'walk_in' THEN 'cash' ELSE p.payment_method END AS method, CAST(ROUND({$amount} * 100, 0) AS SIGNED) AS cents, s.name AS staff");
    }

    private function appointments(): Builder
    {
        return DB::table('appointment_payments as p')->join('appointments as a', 'a.id', '=', 'p.appointment_id')
            ->join('users as u', 'u.id', '=', 'a.user_id')->leftJoin('users as s', 's.id', '=', 'p.verified_by')
            ->where('p.status', 'verified')->where('p.amount', '>', 0)
            ->selectRaw("CONCAT('A-', p.id) AS event_key, 'collection' AS event, p.verified_at AS occurred_at, p.verified_at, COALESCE(NULLIF(p.receipt_no, ''), NULLIF(p.reference_number, ''), CONCAT('A-', p.id)) AS reference, u.name AS payer, 'documents' AS type, CASE WHEN p.payment_method = 'walk_in' THEN 'cash' ELSE p.payment_method END AS method, CAST(ROUND(p.amount * 100, 0) AS SIGNED) AS cents, s.name AS staff");
    }

    private function documents(): Builder
    {
        $query = DB::table('document_requests as d')->join('users as u', 'u.id', '=', 'd.user_id')
            ->leftJoin('users as s', 's.id', '=', 'd.verified_by')
            ->where('d.payment_status', 'verified')->where('d.is_paid', true)
            ->where('d.fee', '>', 0)->where('d.amount_received', '>', 0);

        return \App\Services\DocumentPaymentIdentity::standalone($query)
            ->selectRaw("CONCAT('D-', d.id) AS event_key, 'collection' AS event, d.verified_at AS occurred_at, d.verified_at, COALESCE(NULLIF(d.receipt_no, ''), NULLIF(d.reference_number, ''), CONCAT('D-', d.id)) AS reference, u.name AS payer, 'documents' AS type, CASE WHEN d.payment_method = 'walk_in' THEN 'cash' WHEN d.payment_method = 'gcash_qr' THEN 'gcash' ELSE d.payment_method END AS method, CAST(ROUND(d.amount_received * 100, 0) AS SIGNED) AS cents, s.name AS staff");
    }

    private function events(PaymentReportFilters $filters): Builder
    {
        $union = $this->enrollment()->unionAll($this->enrollment(true))->unionAll($this->appointments())->unionAll($this->documents());

        return DB::query()->fromSub($union, 'events')
            ->when($filters->type !== 'all', fn (Builder $q) => $q->where('type', $filters->type))
            ->when($filters->method !== 'all', fn (Builder $q) => $q->where('method', $filters->method));
    }

    public function query(PaymentReportFilters $filters): Builder
    {
        // Existing Laravel writers store now() in the configured application timezone (currently Manila).
        return $this->events($filters)
            ->where('occurred_at', '>=', $filters->from->setTimezone(config('app.timezone'))->format('Y-m-d H:i:s'))
            ->where('occurred_at', '<', $filters->until->setTimezone(config('app.timezone'))->format('Y-m-d H:i:s'));
    }

    public function ordered(PaymentReportFilters $filters): Builder
    {
        return $this->query($filters)->orderByDesc('occurred_at')->orderBy('event_key');
    }

    public function summary(PaymentReportFilters $filters): array
    {
        $row = $this->query($filters)->selectRaw("COALESCE(SUM(CASE WHEN event = 'collection' THEN cents ELSE 0 END), 0) AS gross, COALESCE(SUM(CASE WHEN event = 'refund' THEN cents ELSE 0 END), 0) AS refunds, SUM(CASE WHEN event = 'collection' THEN 1 ELSE 0 END) AS verified_count, COUNT(*) AS event_count")->first();

        return ['gross' => (int) $row->gross, 'refunds' => (int) $row->refunds, 'net' => (int) $row->gross - (int) $row->refunds,
            'verified_count' => (int) $row->verified_count, 'event_count' => (int) $row->event_count,
            'undated_count' => $this->events($filters)->whereNull('occurred_at')->count()];
    }

    public function chart(PaymentReportFilters $filters): array
    {
        $monthly = $filters->from->diffInDays($filters->until) > 62;
        $format = $monthly ? '%Y-%m' : '%Y-%m-%d';
        $offset = $filters->from->getOffset() - $filters->from->setTimezone(config('app.timezone'))->getOffset();
        $expression = "DATE_FORMAT(TIMESTAMPADD(SECOND, {$offset}, occurred_at), '{$format}')";
        $buckets = $this->query($filters)->selectRaw("{$expression} AS bucket, SUM(CASE WHEN event = 'collection' THEN cents ELSE 0 END) AS gross, SUM(CASE WHEN event = 'refund' THEN cents ELSE 0 END) AS refunds")
            ->groupByRaw($expression)->get()->keyBy('bucket');
        $points = [];
        for ($date = $monthly ? $filters->from->startOfMonth() : $filters->from; $date->lt($filters->until); $date = $monthly ? $date->addMonth() : $date->addDay()) {
            $row = $buckets->get($date->format($monthly ? 'Y-m' : 'Y-m-d'));
            $points[] = ['label' => $date->format($monthly ? 'M Y' : 'M j, Y'), 'gross' => (int) ($row->gross ?? 0), 'refunds' => (int) ($row->refunds ?? 0)];
        }

        return ['interval' => $monthly ? 'Monthly' : 'Daily', 'points' => $points];
    }

    public static function decimal(int $cents): string
    {
        return ($cents < 0 ? '-' : '').intdiv(abs($cents), 100).'.'.str_pad((string) (abs($cents) % 100), 2, '0', STR_PAD_LEFT);
    }

    public static function money(int $cents): string
    {
        [$whole, $fraction] = explode('.', self::decimal(abs($cents)));

        return ($cents < 0 ? '-' : '').'₱'.preg_replace('/\B(?=(\d{3})+(?!\d))/', ',', $whole).'.'.$fraction;
    }

    public static function date(?string $value): string
    {
        return $value ? CarbonImmutable::parse($value, config('app.timezone'))->setTimezone(PaymentReportFilters::TIMEZONE)->format('Y-m-d H:i:s') : '—';
    }

    public static function csvText(?string $value): string
    {
        $value = str_replace("\0", '', $value ?? '');
        // Neutralize formulas even when preceded by whitespace/control characters.
        return preg_match('/^[\s\x00-\x20]*[=+@-]|^[\t\r\n]/u', $value) ? "'".$value : $value;
    }
}
