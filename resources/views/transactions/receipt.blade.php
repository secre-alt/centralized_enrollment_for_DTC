<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><title>DTC Payment Receipt</title>
@include('admin.reports._report-styles')
</head>
<body>
<div class="footer">DTC EMS · Generated {{ now('Asia/Manila')->format('M j, Y g:i A') }} PHT · Electronic copy</div>
@php
    use App\Services\Reports\PaymentReportService as Money;
    $date = fn ($value) => Money::date($value);
@endphp
<table class="brand"><tr><td><div class="college">Danao Technological College</div><div class="sub">Centralized Enrollment System</div></td><td class="title"><b>Payment Receipt</b><span>Official receipt no. {{ $transaction->receipt_no }}</span></td></tr></table>
<p>Electronic copy of the recorded official payment receipt</p>
<table class="data"><tbody>
    <tr><td>Receipt number</td><td>{{ $transaction->receipt_no }}</td></tr>
    <tr><td>Transaction</td><td>{{ $transaction->transaction_key }}</td></tr>
    <tr><td>Received from</td><td>{{ $transaction->payer }}</td></tr>
    <tr><td>For</td><td>{{ $transaction->related }}</td></tr>
    <tr><td>Payment method</td><td>{{ in_array($transaction->method, ['gcash', 'gcash_qr'], true) ? 'GCash' : 'Cash' }}</td></tr>
    @if($transaction->reference)<tr><td>Payment reference</td><td>{{ $transaction->reference }}</td></tr>@endif
    <tr><td>Verified on (Asia/Manila)</td><td>{{ $date($transaction->verified_at) }}</td></tr>
    <tr><td>Verified / processed by</td><td>{{ $transaction->staff_name ?: '—' }}</td></tr>
    <tr><td><strong>Amount received</strong></td><td><strong>{{ Money::money($amountCents) }}</strong></td></tr>
</tbody></table>
@if(in_array($transaction->refund_status, ['pending', 'refunded'], true))
    <h3>{{ $transaction->refund_status === 'refunded' ? 'Full refund confirmed' : 'Refund pending confirmation' }}</h3>
    @if($transaction->refund_status === 'refunded')
        <p>Refund amount: {{ Money::money((new \App\Services\TransactionHistory)->centavos($transaction->refund_amount ?? '0.00')) }}<br>
        Refund confirmed (Asia/Manila): {{ $date($transaction->refunded_at) }}<br>Refund reference: {{ $transaction->refund_reference ?: '—' }}</p>
    @endif
    <p>This receipt records the original collection. It does not certify a current outstanding balance.</p>
@endif

</body></html>
