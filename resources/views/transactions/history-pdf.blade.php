<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><title>Personal Payment History</title>
@include('admin.reports._report-styles')

</head>
<body>
<div class="footer">DTC EMS · Personal Payment History · {{ $transactions->count() }} records · Confidential</div>
@php use App\Services\Reports\PaymentReportService as Money; @endphp
<table class="brand"><tr><td><div class="college">Danao Technological College</div><div class="sub">Centralized Enrollment System</div></td><td class="title"><b>Personal Payment History</b><span>Generated {{ $generatedAt }}</span></td></tr></table>
<p><strong>Account holder:</strong> {{ $holder }}<br>
<strong>Recorded date range (Asia/Manila):</strong> {{ $filters['from'] ?? 'Earliest record' }} through {{ $filters['to'] ?? 'Latest record' }}<br>
<strong>Filters:</strong>
@foreach(['search' => 'Search', 'category' => 'Type', 'method' => 'Method', 'status' => 'Status'] as $key => $label)
{{ $label }}: {{ filled($filters[$key] ?? null) ? str_replace('_', ' ', $filters[$key]) : 'All' }}{{ !$loop->last ? ' · ' : '' }}
@endforeach
</p>
<table class="stats"><tr>
    <td><span class="num">{{ Money::money($summary['verified_cents']) }}</span><span class="lbl">Verified collections before refunds</span></td>
    <td><span class="num">{{ $summary['pending_count'] }}</span><span class="lbl">Pending / unpaid transactions</span></td>
    <td><span class="num">{{ Money::money($summary['refund_cents']) }}</span><span class="lbl">Confirmed refunds</span></td>
</tr></table>
<div class="report-note"><p>Only verified collections count as paid. Pending, unpaid and rejected amounts are not collections. Verified totals include payments later refunded; confirmed refunds are shown separately. Filters use recorded payment dates and the selected statuses.</p>
<p>Personal history, not an official receipt. Download individual receipts separately for eligible verified payments.</p></div><h3>Transactions</h3>
<table class="data"><thead><tr>
    <th style="width:15%">Recorded / verified (PHT)</th><th style="width:16%">Transaction / reference</th><th style="width:20%">Payment details</th><th style="width:12%" class="num-col">Listed amount (PHP)</th><th style="width:11%">Status</th><th style="width:12%" class="num-col">Collected (PHP)</th><th style="width:14%">Refund</th>
</tr></thead><tbody>
@forelse($transactions as $record)
    @php $row = $export->row($record); @endphp
    <tr>
        <td>{{ $row['date'] }}<br><small>Verified: {{ $row['verified'] }}</small></td>
        <td><strong>{{ $row['key'] }}</strong>@if($row['reference'] !== $row['key'])<br>{{ $row['reference'] }}@endif</td>
        <td>{{ $row['description'] }}<br><small>{{ $row['type'] }} &middot; {{ $row['method'] }}</small></td>
        <td class="num-col">{{ $row['amount'] }}</td>
        <td>{{ $row['status'] }}</td>
        <td class="num-col">{{ $row['collected'] }}</td>
        <td>{{ $row['refund_status'] }}@if($record->refund_status === 'refunded')<br>PHP {{ $row['refund_amount'] }}<br>{{ $row['refunded'] }}@endif @if($record->refund_reference)<br>{{ $row['refund_reference'] }}@endif</td>
    </tr>
@empty<tr><td colspan="7" class="empty">No transactions match these filters.</td></tr>@endforelse
</tbody></table>

</body></html>
