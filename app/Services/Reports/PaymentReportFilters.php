<?php

namespace App\Services\Reports;

use Carbon\CarbonImmutable;

final class PaymentReportFilters
{
    public const TIMEZONE = 'Asia/Manila';

    public const PERIODS = [
        'today' => 'Today', 'last_7_days' => 'Last 7 Days', 'last_30_days' => 'Last 30 Days',
        'this_month' => 'This Month', 'last_month' => 'Last Month', 'this_year' => 'This Year', 'custom' => 'Custom Range',
    ];

    public string $period;

    public string $type;

    public string $method;

    public CarbonImmutable $from;

    public CarbonImmutable $until;

    public static function fromArray(array $input): self
    {
        $filters = new self;
        $filters->period = $input['period'] ?? 'this_month';
        $filters->type = $input['type'] ?? 'all';
        $filters->method = $input['method'] ?? 'all';
        $today = CarbonImmutable::now(self::TIMEZONE)->startOfDay();
        [$filters->from, $filters->until] = match ($filters->period) {
            'today' => [$today, $today->addDay()],
            'last_7_days' => [$today->subDays(6), $today->addDay()],
            'last_30_days' => [$today->subDays(29), $today->addDay()],
            'last_month' => [$today->startOfMonth()->subMonth(), $today->startOfMonth()],
            'this_year' => [$today->startOfYear(), $today->startOfYear()->addYear()],
            'custom' => [CarbonImmutable::parse($input['from'], self::TIMEZONE)->startOfDay(), CarbonImmutable::parse($input['to'], self::TIMEZONE)->startOfDay()->addDay()],
            default => [$today->startOfMonth(), $today->startOfMonth()->addMonth()],
        };

        return $filters;
    }

    public function query(): array
    {
        return ['period' => $this->period, 'type' => $this->type, 'method' => $this->method,
            'from' => $this->from->toDateString(), 'to' => $this->until->subDay()->toDateString()];
    }

    public function rangeLabel(): string
    {
        return $this->from->format('M j, Y').' – '.$this->until->subDay()->format('M j, Y').' (Asia/Manila)';
    }

    public function typeLabel(): string
    {
        return ['all' => 'All payment types', 'enrollment' => 'Enrollment', 'documents' => 'Documents'][$this->type];
    }

    public function methodLabel(): string
    {
        return ['all' => 'All payment methods', 'cash' => 'Cash', 'gcash' => 'GCash'][$this->method];
    }
}
