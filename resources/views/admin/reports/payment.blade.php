<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>DTC Payment Report</title>
    @include('admin.reports._report-styles')
</head>
<body>
<div class="footer">DTC Enrollment Management System · Confidential Payment Report · {{ $transactions->count() }} events</div>
@php use App\Services\Reports\PaymentReportService as Money; @endphp
<table class="brand"><tr>
    <td><div class="college">Danao Technological College</div><div class="sub">Centralized Enrollment System</div></td>
    <td class="title"><b>Payment Report</b><span>Generated {{ $generatedAt }}</span></td>
</tr></table>
<p><strong>{{ $filters->rangeLabel() }}</strong><br>{{ \App\Services\Reports\PaymentReportFilters::PERIODS[$filters->period] }} · {{ $filters->typeLabel() }} · {{ $filters->methodLabel() }}</p>
<table class="stats"><tr>
    <td><span class="num">{{ Money::money($summary['gross']) }}</span><span class="lbl">Gross Collected</span></td>
    <td><span class="num">{{ Money::money($summary['refunds']) }}</span><span class="lbl">Confirmed Refunds</span></td>
    <td class="ok"><span class="num">{{ Money::money($summary['net']) }}</span><span class="lbl">Net Collected</span></td>
    <td><span class="num">{{ number_format($summary['verified_count']) }}</span><span class="lbl">Verified Transactions</span></td>
</tr></table>
<p class="report-note">Collections use verification dates; refunds use confirmation dates. Refunded payments remain counted in their original collection period. Refund amounts below are negative.</p>
@if($summary['undated_count'])<p>{{ $summary['undated_count'] }} verified payment/refund records across the selected type and method lack required timestamps and are excluded from dated reporting.</p>@endif
<h3>Transactions</h3>
<table class="data"><thead><tr>
    <th style="width:12%">Event date (PHT)</th><th style="width:12%">Verification date (PHT)</th><th style="width:17%">Receipt/reference</th><th style="width:14%">Payer</th><th style="width:8%">Type</th><th style="width:8%">Method</th><th style="width:8%">Event</th><th style="width:10%" class="num-col">Amount</th><th style="width:11%">Processing staff</th>
</tr></thead><tbody>
@forelse($transactions as $row)
<tr>
    <td>{{ Money::date($row->occurred_at) }}</td><td>{{ Money::date($row->verified_at) }}</td><td>{{ $row->reference ?: '—' }}</td><td>{{ $row->payer }}</td><td>{{ ucfirst($row->type) }}</td><td>{{ $row->method === 'cash' ? 'Cash' : ($row->method === 'gcash' ? 'GCash' : $row->method) }}</td><td>{{ ucfirst($row->event) }}</td><td class="num-col">{{ Money::money(($row->event === 'refund' ? -1 : 1) * (int) $row->cents) }}</td><td>{{ $row->staff ?: '—' }}</td>
</tr>
@empty<tr><td colspan="9" class="empty">No verified collections or confirmed refunds match these filters.</td></tr>@endforelse
</tbody></table>

</body>
</html>
