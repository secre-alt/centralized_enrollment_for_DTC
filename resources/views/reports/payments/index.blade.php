@extends('adminlte::page')
@include('partials.navbar')
@section('title', 'Payment Reports')
@section('plugins.ChartJs', true)
@section('content_header')
<x-dtc.page-header title="Payment Reports" subtitle="Verified collections and confirmed refunds">
    <x-slot name="actions">
        <a class="btn dtc-export-csv" href="{{ route('reports.payments.csv', $filters->query()) }}">Export CSV</a>
        <a class="btn btn-primary" href="{{ route('reports.payments.pdf', $filters->query()) }}">Download PDF</a>
    </x-slot>
</x-dtc.page-header>
@endsection
@section('css')
<link rel="stylesheet" href="{{ asset('css/payment-reports.css') }}">
@endsection
@section('content')
@php use App\Services\Reports\PaymentReportService as Money; @endphp
<div class="payment-reports" id="payment-reports">
    @if(session('report_notice'))<div class="alert alert-info" role="status">{{ session('report_notice') }}</div>@endif
    @if($errors->any())
        <div class="alert alert-danger" role="alert"><p>Please check the report filters.</p><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif
    <form class="pr-panel pr-filters" action="{{ route('reports.payments.index') }}" method="GET" id="pr-filter-form" aria-label="Payment report filters">
        <div class="pr-field"><label for="pr-period">Period</label><select id="pr-period" name="period" class="form-control">
            @foreach(\App\Services\Reports\PaymentReportFilters::PERIODS as $value => $label)<option value="{{ $value }}" @selected(old('period', $filters->period) === $value)>{{ $label }}</option>@endforeach
        </select></div>
        <div class="pr-field"><label for="pr-type">Payment type</label><select id="pr-type" name="type" class="form-control">
            @foreach(['all' => 'All', 'enrollment' => 'Enrollment', 'documents' => 'Documents'] as $value => $label)<option value="{{ $value }}" @selected(old('type', $filters->type) === $value)>{{ $label }}</option>@endforeach
        </select></div>
        <div class="pr-field"><label for="pr-method">Payment method</label><select id="pr-method" name="method" class="form-control">
            @foreach(['all' => 'All', 'cash' => 'Cash', 'gcash' => 'GCash'] as $value => $label)<option value="{{ $value }}" @selected(old('method', $filters->method) === $value)>{{ $label }}</option>@endforeach
        </select></div>
        <div class="pr-field pr-custom"><label for="pr-from">From</label><input class="form-control" id="pr-from" type="date" name="from" min="2000-01-01" max="2100-12-31" value="{{ old('from', $filters->query()['from']) }}"></div>
        <div class="pr-field pr-custom"><label for="pr-to">Through</label><input class="form-control" id="pr-to" type="date" name="to" min="2000-01-01" max="2100-12-31" value="{{ old('to', $filters->query()['to']) }}"></div>
        <div class="pr-filter-actions"><button class="btn btn-primary" type="submit" id="pr-apply">Apply filters</button><a href="{{ route('reports.payments.index') }}">Reset</a></div>
        <p class="pr-range">{{ $filters->rangeLabel() }} <span>· {{ $filters->typeLabel() }} · {{ $filters->methodLabel() }}</span></p>
        <p id="pr-loading" role="status" hidden>Updating report…</p>
    </form>

    @if($summary['undated_count'])<p class="pr-warning" role="status">{{ number_format($summary['undated_count']) }} verified payment/refund {{ $summary['undated_count'] === 1 ? 'record has' : 'records have' }} no required event timestamp across the selected payment type and method. These records cannot be placed in a date range and are excluded.</p>@endif

    <section class="pr-stats" aria-label="Financial summary">
        @foreach(['gross' => 'Gross Collected', 'refunds' => 'Confirmed Refunds', 'net' => 'Net Collected', 'verified_count' => 'Verified Transactions'] as $key => $label)
            <div class="pr-panel pr-stat {{ $key === 'net' ? 'pr-net' : '' }}"><h2>{{ $label }}</h2><p>{{ $key === 'verified_count' ? number_format($summary[$key]) : Money::money($summary[$key]) }}</p></div>
        @endforeach
    </section>
    <p class="pr-note">Collections use verification dates; refunds use confirmation dates. Refunded payments remain in the verified transaction count for their original collection period. Refund rows are shown as negative amounts.</p>

    <section class="pr-panel pr-chart" aria-labelledby="pr-chart-title">
        <div class="pr-section-heading"><h2 id="pr-chart-title">Collections and refunds</h2><span>{{ $chart['interval'] }} totals · PHP</span></div>
        @if($summary['event_count'])
            <div class="pr-chart-canvas"><canvas id="pr-collection-chart" role="img" aria-label="{{ $chart['interval'] }} gross collections and confirmed refunds in Philippine pesos. Exact values follow in the chart data table."></canvas></div>
            <p id="pr-chart-fallback" class="pr-note" hidden>The chart could not load. Exact totals are available below.</p>
            <details class="pr-chart-data"><summary>View chart data</summary><div class="pr-table-scroll" tabindex="0" aria-label="Collection chart data"><table><thead><tr><th>Period</th><th>Gross collected</th><th>Confirmed refunds</th></tr></thead><tbody>
                @foreach($chart['points'] as $point)<tr><td>{{ $point['label'] }}</td><td>{{ Money::money($point['gross']) }}</td><td>{{ Money::money($point['refunds']) }}</td></tr>@endforeach
            </tbody></table></div></details>
        @else<p class="pr-empty">No verified collections or confirmed refunds for this period.</p>@endif
    </section>

    <section class="pr-panel" aria-labelledby="pr-transactions-title">
        <div class="pr-section-heading pr-table-heading"><h2 id="pr-transactions-title">Transactions</h2><span>{{ number_format($transactions->total()) }} events</span></div>
        <div class="pr-table-scroll" tabindex="0" aria-label="Payment report transactions">
            <table class="pr-transactions" role="table"><thead><tr><th>Event date (PHT)</th><th>Verification date (PHT)</th><th>Receipt/reference</th><th>Payer</th><th>Type</th><th>Method</th><th>Event</th><th class="pr-amount">Amount</th><th>Processing staff</th></tr></thead><tbody>
            @forelse($transactions as $row)
                <tr><td data-label="Event date (PHT)"><span>{{ Money::date($row->occurred_at) }}</span></td><td data-label="Verification date (PHT)"><span>{{ Money::date($row->verified_at) }}</span></td><td data-label="Receipt/reference"><span>{{ $row->reference ?: '—' }}</span></td><td data-label="Payer"><span>{{ $row->payer }}</span></td><td data-label="Type"><span>{{ ucfirst($row->type) }}</span></td><td data-label="Method"><span>{{ $row->method === 'cash' ? 'Cash' : ($row->method === 'gcash' ? 'GCash' : $row->method) }}</span></td><td data-label="Event"><span>{{ ucfirst($row->event) }}</span></td><td class="pr-amount" data-label="Amount"><span>{{ Money::money(($row->event === 'refund' ? -1 : 1) * (int) $row->cents) }}</span></td><td data-label="Processing staff"><span>{{ $row->staff ?: '—' }}</span></td></tr>
            @empty<tr><td colspan="9" class="pr-empty">No transactions match these filters. Choose another period or reset the filters.</td></tr>@endforelse
            </tbody></table>
        </div>
        @if($transactions->hasPages())<div class="pr-pagination">{{ $transactions->links() }}</div>@endif
    </section>
    <p class="pr-note">PDF exports support up to {{ number_format(Money::PDF_LIMIT) }} rows. CSV includes all matching events. Reports include only payer names and payment details.</p>
</div>
@endsection
@section('js')
<script id="pr-chart-values" type="application/json">@json($chart)</script>
<script src="{{ asset('js/payment-reports.js') }}" defer></script>
@endsection
