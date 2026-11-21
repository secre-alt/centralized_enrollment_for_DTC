@extends('adminlte::page')
@include('partials.navbar')

@section('title', 'Payment History')
@section('content_header')
<x-dtc.page-header title="Payment History" :subtitle="in_array($role, ['admin', 'cashier', 'registrar'], true) ? 'All recorded payments' : 'Your payments and their status'">
    <x-slot name="actions">
    @if(in_array($role, ['student', 'alumni'], true))
        @php $exportFilters = \Illuminate\Support\Arr::only($filters, ['search', 'category', 'method', 'status', 'from', 'to']); @endphp
        <div class="ph-history-exports" aria-label="Download filtered payment history">
            <a class="btn dtc-export-csv" href="{{ route('portal.transactions.export.csv', $exportFilters) }}">Export CSV</a>
            <a class="btn btn-primary" href="{{ route('portal.transactions.export.pdf', $exportFilters) }}">Download PDF</a>
        </div>
    @endif
    </x-slot>
</x-dtc.page-header>
@stop

@push('css')
    <link rel="stylesheet" href="{{ asset('css/payment-history.css') }}">
@endpush

@section('content')
@php
    $isStaff = in_array($role, ['admin', 'cashier', 'registrar'], true);
    $detailRoute = $role === 'admin' ? 'admin.transactions.show' : ($role === 'cashier' ? 'cashier.transactions.show' : ($role === 'registrar' ? 'registrar.transactions.show' : 'portal.transactions.show'));
    $statusLabels = ['pending' => 'Pending', 'pending_verification' => 'Pending verification', 'unpaid' => 'Unpaid', 'verified' => 'Verified', 'rejected' => 'Rejected'];
    $methodLabels = ['walk_in' => 'Walk-in', 'cash' => 'Cash', 'gcash' => 'GCash', 'gcash_qr' => 'GCash QR'];
    $currentStatus = (string) request('status');
    $advancedKeys = array_filter(['category', 'method', $isStaff && in_array($role, ['admin', 'cashier'], true) ? 'staff' : null, 'from', 'to']);
    $advancedCount = collect($advancedKeys)->filter(fn ($key) => filled(request($key)))->count();
    $hasFilters = filled(request('search')) || filled($currentStatus) || $advancedCount > 0;
@endphp
<div class="dtc-payment-history">
    @if(session('history_export_notice'))<div class="alert alert-info" role="status">{{ session('history_export_notice') }}</div>@endif
    @if($summary !== null)
        <section class="ph-totals" aria-label="Payment summary">
            <div><h2>Verified payments</h2><p>{{ \App\Services\Reports\PaymentReportService::money($summary['verified_cents']) }}</p></div>
            <div><h2>Pending transactions</h2><p>{{ number_format($summary['pending_count']) }}</p></div>
            <div><h2>Confirmed refunds</h2><p>{{ \App\Services\Reports\PaymentReportService::money($summary['refund_cents']) }}</p></div>
        </section>
        <details class="ph-summary-help">
            <summary>About these totals</summary>
            <p class="ph-summary-note">Summaries follow search and filters across all statuses. Dates filter when a payment was recorded, not when a refund was confirmed. Verified totals include payments later refunded; pending includes unpaid and awaiting verification.</p>
        </details>
    @endif
    @if($errors->any())<div class="alert alert-danger" role="alert"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <form method="GET" class="ph-filters" aria-label="Filter payment history">
        @if($currentStatus !== '')<input type="hidden" name="status" value="{{ $currentStatus }}">@endif

        <div class="ph-toolbar">
            <div class="dtc-pay-search ph-search">
                <i data-lucide="search" class="dtc-pay-search-icon" aria-hidden="true"></i>
                <input type="search" id="search" name="search" class="dtc-pay-search-input" value="{{ request('search') }}" placeholder="Search transactions…" autocomplete="off" aria-label="Search transactions">
                @if(filled(request('search')))
                    <a class="dtc-pay-search-clear" href="{{ request()->fullUrlWithQuery(['search' => null, 'page' => null]) }}" aria-label="Clear search"><i data-lucide="x"></i></a>
                @endif
            </div>
            <button type="button" class="ph-filter-toggle {{ $advancedCount ? 'has-filters' : '' }}" data-toggle="collapse" data-target="#phAdvanced" aria-expanded="{{ $advancedCount ? 'true' : 'false' }}" aria-controls="phAdvanced">
                <i data-lucide="sliders-horizontal" aria-hidden="true"></i>
                <span>Filters</span>
                @if($advancedCount)<span class="ph-filter-count">{{ $advancedCount }}</span>@endif
            </button>
        </div>

        <div class="collapse {{ $advancedCount ? 'show' : '' }}" id="phAdvanced">
            <div class="ph-advanced">
                <div class="ph-field">
                    <label for="category">Type</label>
                    <select class="form-control" id="category" name="category">
                        <option value="">All</option>
                        @foreach($categories as $category)
                            <option value="{{ $category }}" @selected(request('category') === $category)>{{ ['enrollment' => 'Enrollment', 'document' => 'Document request', 'appointment' => 'Appointment / bundled documents'][$category] }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="ph-field">
                    <label for="method">Method</label>
                    <select class="form-control" id="method" name="method">
                        <option value="">All</option>
                        @foreach(['cash' => 'Cash', 'gcash' => 'GCash'] as $value => $label)<option value="{{ $value }}" @selected(in_array(request('method'), $value === 'cash' ? ['cash', 'walk_in'] : ['gcash', 'gcash_qr'], true))>{{ $label }}</option>@endforeach
                    </select>
                </div>
                @if(in_array($role, ['admin', 'cashier'], true))
                    <div class="ph-field">
                        <label for="staff">Staff</label>
                        <input class="form-control" id="staff" name="staff" value="{{ request('staff') }}" placeholder="Name">
                    </div>
                @endif
                <div class="ph-field">
                    <label for="from">From</label>
                    <input type="date" class="form-control" id="from" name="from" value="{{ request('from') }}">
                </div>
                <div class="ph-field">
                    <label for="to">To</label>
                    <input type="date" class="form-control" id="to" name="to" value="{{ request('to') }}">
                </div>
                @include('partials.table-rows', ['tableRecords' => $transactions, 'tableId' => 'payment-history-table', 'fixedRows' => true])
                <div class="ph-advanced-actions">
                    <button class="btn btn-primary" type="submit">Apply</button>
                    <a class="ph-clear" href="{{ url()->current() }}">Reset</a>
                </div>
            </div>
        </div>

        <div class="dtc-filter-bar ph-status-bar" role="group" aria-label="Filter by status">
            @foreach(['' => 'All'] + $statusLabels as $value => $label)
                <a href="{{ request()->fullUrlWithQuery(['status' => $value !== '' ? $value : null, 'page' => null]) }}"
                   class="dtc-filter-pill {{ $currentStatus === (string) $value ? 'is-active' : '' }}"
                   @if($currentStatus === (string) $value) aria-current="true" @endif><span>{{ $label }}</span><span class="ph-status-count">{{ number_format($value === '' ? $statusCounts->sum() : ($statusCounts[$value] ?? 0)) }}</span></a>
            @endforeach
        </div>
    </form>

    <div class="ph-list">
        <div class="ph-list-heading">
            <h2>Transactions</h2>
            <span>{{ $transactions->total() }} {{ $transactions->total() === 1 ? 'record' : 'records' }}</span>
        </div>
        @if($transactions->count())
            <div class="ph-table-scroll">
<table id="payment-history-table" class="dtc-table ph-table">
                <thead>
                    <tr>
                        <th>Transaction</th>
                        @if($isStaff)<th>Payer</th>@endif
                        <th>For</th>
                        <th class="ph-num">Amount</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th class="ph-action-col"><span class="sr-only">Details</span></th>
                    </tr>
                </thead>
                <tbody>
                @foreach($transactions as $item)
                    @php
                        $tone = match($item->status) { 'verified' => 'success', 'rejected' => 'danger', 'pending', 'pending_verification' => 'warning', default => 'neutral' };
                        $label = $statusLabels[$item->status] ?? ucfirst(str_replace('_', ' ', $item->status));
                        $methodLabel = $item->method ? ($methodLabels[$item->method] ?? ucfirst(str_replace('_', ' ', $item->method))) : null;
                        $date = $item->occurred_at ? \Illuminate\Support\Carbon::parse($item->occurred_at) : null;
                        $href = route($detailRoute, $item->transaction_key);
                    @endphp
                    <tr>
                        <td class="ph-ref">
                            <a class="ph-ref-link dtc-payment-reference" href="{{ $href }}">{{ $item->transaction_key }}</a>
                            <span class="ph-sub">{{ ucfirst($item->category) }}@if($methodLabel) · {{ $methodLabel }}@endif</span>
                        </td>
                        @if($isStaff)<td class="ph-payer">{{ $item->payer }}</td>@endif
                        <td class="ph-related">{{ $item->related }}</td>
                        <td class="ph-amount dtc-payment-amount">₱{{ number_format((float) $item->amount, 2) }}</td>
                        <td class="ph-status">
                            <span class="dtc-status-badge is-{{ $tone }}">{{ $label }}</span>
                            @if($item->refund_status === 'pending')
                                <span class="ph-sub">Refund pending</span>
                            @elseif($item->refund_status === 'refunded')
                                <span class="ph-sub">Refund confirmed</span>
                            @endif
                        </td>
                        <td class="ph-date">{{ $date ? $date->format('M j, Y') : '—' }}</td>
                        <td class="ph-action"><a class="ph-view" href="{{ $href }}" aria-label="View details for {{ $item->transaction_key }}"><i data-lucide="chevron-right" aria-hidden="true"></i><span class="dtc-mobile-action-label">Details</span></a></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
            </div>
        @else
            <div class="dtc-empty-state">
                <div class="dtc-empty-icon"><i data-lucide="receipt" aria-hidden="true"></i></div>
                <p class="dtc-empty-title">{{ $hasFilters ? 'No matching payments' : 'No payments yet' }}</p>
                <p class="dtc-empty-description">{{ $hasFilters ? 'Try a different search or clear the filters.' : 'Payments will show up here once recorded.' }}</p>
                @if($hasFilters)<div class="dtc-empty-actions"><a class="ph-clear" href="{{ url()->current() }}">Clear filters</a></div>@endif
            </div>
        @endif

        @if($transactions->total() > 0)
            <div class="dtc-payment-pagination ph-footer">
                <small class="u-text-secondary">{{ $transactions->firstItem() }}–{{ $transactions->lastItem() }} of {{ $transactions->total() }}</small>
                @if($transactions->hasPages())<div>{{ $transactions->withQueryString()->onEachSide(1)->links('pagination::bootstrap-4') }}</div>@endif
            </div>
        @endif
    </div>
</div>
@stop
