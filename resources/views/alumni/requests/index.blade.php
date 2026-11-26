@extends('adminlte::page')
@include('partials.navbar')

@section('title', 'My Document Requests')

@section('content_header')
    <div class="dr dr-head">
        <div>
            <h1>My document requests</h1>
            <p>Track status, payment and pickup.</p>
        </div>
        <a href="{{ route('portal.documents.create') }}" class="dr-btn dr-btn--primary">
            <i data-lucide="plus" aria-hidden="true"></i> Request a document
        </a>
    </div>
@endsection

@php
    $items = method_exists($requests, 'getCollection') ? $requests->getCollection() : collect($requests);

    $stageOf   = ['submitted' => 1, 'processing' => 2, 'ready' => 3, 'released' => 4];
    $stageName = ['Submitted', 'Assessment', 'Preparing', 'Ready', 'Released'];
    $groupOf   = fn ($request) => $request->status === 'cancelled'
        ? 'cancelled'
        : ($request->status === 'ready' ? 'ready' : ($request->status === 'released' ? 'released' : 'progress'));
    $badgeText = ['submitted' => 'Submitted', 'processing' => 'Being prepared', 'ready' => 'Ready for pickup', 'released' => 'Released'];
    $badgeTone = ['submitted' => 'warning', 'processing' => 'info', 'ready' => 'purple', 'released' => 'success'];

    $counts = ['all' => $items->count(), 'progress' => 0, 'ready' => 0, 'released' => 0, 'cancelled' => 0];
    foreach ($items as $r) { $counts[$groupOf($r)]++; }
    $payTone  = ['unpaid' => 'warning', 'pending_verification' => 'info', 'verified' => 'success', 'rejected' => 'danger'];
    $payLabel = ['unpaid' => 'Payment due', 'pending_verification' => 'Under verification', 'verified' => 'Verified', 'rejected' => 'Payment not verified'];
@endphp

@push('css')<link rel="stylesheet" href="{{ asset('css/dtc-filters.css') }}">@endpush
@push('js')<script src="{{ asset('js/dtc-filters.js') }}"></script>@endpush
@section('content')
<div class="dr dr-index dr-alumni-history dr-document-history" id="request-history">
    <p class="dr-history-intro">Collect ready documents at the Registrar with a valid ID. No appointment is required; uncollected documents remain available.</p>


    <details class="dr-history-summary">
        <summary>Request overview <span>{{ $totalRequests }} total &middot; {{ $readyRequests }} ready</span></summary>
    <div class="dtc-dashboard-overview dtc-dashboard-overview--four mb-3" aria-label="Document request summary">
        <div class="dtc-overview-item">
            <span class="dtc-overview-label">Total requests</span>
            <strong>{{ $totalRequests }}</strong>
        </div>
        <div class="dtc-overview-item">
            <span class="dtc-overview-label">Ready for pickup</span>
            <strong>{{ $readyRequests }}</strong>
            <span class="dtc-overview-note">Ready for collection</span>
        </div>
        <div class="dtc-overview-item">
            <span class="dtc-overview-label">Released</span>
            <strong>{{ $releasedDocs }}</strong>
        </div>
        <div class="dtc-overview-item">
            <span class="dtc-overview-label">Total fees</span>
            <strong>&#8369;{{ number_format($totalFees, 2) }}</strong>
        </div>
    </div>

    </details>

@php
    $listFilterCount = collect(['document_type', 'date_from', 'date_to'])->filter(fn ($key) => request()->filled($key))->count();
@endphp
<form method="GET" action="{{ route('portal.documents.index') }}" class="dtc-fl dtc-fl-expanded" data-dtc-filters aria-label="Filter your document requests">
    <div class="dtc-fl-toolbar">
        <div class="dtc-pay-search dtc-fl-search"><i data-lucide="search" class="dtc-pay-search-icon" aria-hidden="true"></i><input type="search" name="search" class="dtc-pay-search-input" value="{{ request('search') }}" placeholder="Search requests" aria-label="Search your document requests" data-dtc-filter-search></div>
        <button type="button" class="dtc-fl-toggle {{ $listFilterCount ? 'has-filters' : '' }}" data-toggle="collapse" data-target="#historyFilterPanel" aria-controls="historyFilterPanel" aria-expanded="{{ $listFilterCount ? 'true' : 'false' }}" data-dtc-filter-toggle><i data-lucide="sliders-horizontal" aria-hidden="true"></i><span>Filters</span><span class="dtc-fl-count" data-dtc-filter-count @if(!$listFilterCount) hidden @endif>{{ $listFilterCount }}</span></button>
    </div>
    <div class="collapse {{ $listFilterCount ? 'show' : '' }}" id="historyFilterPanel"><div class="dtc-fl-panel">
@include('partials.list-select-filters', ['selectPrefix' => 'history', 'selectFilters' => [
'document_type' => ['label' => 'Document type', 'all' => 'All documents', 'options' => ['tor' => 'Transcript of Records', 'true_copy' => 'True Copy of Records', 'certification' => 'Certification', 'diploma' => 'Diploma']],
]])
@include('partials.list-date-filters', ['datePrefix' => 'history', 'dateLabel' => 'Request submitted'])
<div class="dtc-fl-actions"><button type="submit" class="btn btn-primary">Apply</button><a class="dtc-fl-clear" href="{{ route('portal.documents.index') }}">Clear all</a></div>
    </div></div>
</form>

    @if ($items->isEmpty())
        <div class="dr-empty">
            <h2>{{ request()->anyFilled(['search', 'document_type', 'date_from', 'date_to']) ? 'No requests match your filters' : 'No requests yet' }}</h2>
            <p>Your requests will appear here.</p>
        </div>
    @else
        <div class="dr-filters" role="group" aria-label="Filter requests" data-dr-filters>
            <button type="button" class="dr-filter" data-filter="all" aria-pressed="true">All <span class="dr-filter-count">{{ $counts['all'] }}</span></button>
            <button type="button" class="dr-filter" data-filter="progress" aria-pressed="false">In progress <span class="dr-filter-count">{{ $counts['progress'] }}</span></button>
            <button type="button" class="dr-filter" data-filter="ready" aria-pressed="false">Ready <span class="dr-filter-count">{{ $counts['ready'] }}</span></button>
            <button type="button" class="dr-filter" data-filter="released" aria-pressed="false">Released <span class="dr-filter-count">{{ $counts['released'] }}</span></button>
            <button type="button" class="dr-filter" data-filter="cancelled" aria-pressed="false">Cancelled <span class="dr-filter-count">{{ $counts['cancelled'] }}</span></button>
        </div>

        <ul class="dr-list">
            @foreach ($items as $req)
                @php
                    $stage    = $stageOf[$req->status] ?? 0;
                    $paid     = $req->paymentSatisfied();
                    $rejected = $req->payment_status === 'rejected';
                    $collect  = $req->status === 'ready' && $paid;
                    $copiesTx = $req->copies.($req->copies > 1 ? ' copies' : ' copy');
                    $bundlePayment = $req->paymentAppointment?->payments->first(fn ($payment) => $payment->is_document_bundle);
                    $cancelled = $req->status === 'cancelled';
                    $schedulePassed = $req->appointment?->scheduleHasPassed() ?? false;
                    $hasPaymentAction = !$cancelled
                        && !$req->paymentNotRequired()
                        && !$req->is_paid
                        && (float) $req->fee > 0
                        && in_array($req->payment_status, ['unpaid', 'pending_verification', 'rejected'], true);
                    $paymentActionLabel = $req->appointment?->status === 'cancelled' || $schedulePassed
                        ? 'Review payment'
                        : 'Payment details';
                @endphp
                <li class="dr-req {{ $cancelled ? 'is-muted' : ($rejected ? 'needs-fix' : ($collect ? 'needs-action' : '')) }}" data-group="{{ $groupOf($req) }}">
                    <div class="dr-req-top">
                        <div>
                            <h2 class="dr-req-name">{{ $req->document_label }} <small>D-{{ $req->id }}</small></h2>
                            @if ($req->purpose)<p class="dr-req-purpose">{{ $req->purpose }}</p>@endif
                            <p class="dr-req-meta">{{ $copiesTx }}, &#8369;{{ number_format($req->fee, 2) }}, requested {{ $req->created_at->timezone('Asia/Manila')->format('M j, Y g:i A') }}@if ($bundlePayment) · Appointment combined total &#8369;{{ number_format($bundlePayment->amount, 2) }}@endif</p>
                        </div>
                        @if ($cancelled)
                            <span class="dtc-status-badge is-neutral">Cancelled</span>
                        @else
                            <span class="dtc-status-badge is-{{ $badgeTone[$req->status] ?? 'neutral' }}">{{ $badgeText[$req->status] ?? 'Submitted' }}</span>
                        @endif
                    </div>

                    @if (! $cancelled)
                        <ol class="dr-track dr-document-track" aria-label="Document progress: {{ $stageName[$stage] }}">
                            @foreach ($stageName as $i => $name)
                                <li class="{{ $i < $stage || ($i === $stage && $req->status === 'released') ? 'is-done' : '' }} {{ $i === $stage ? 'is-current' : '' }}"
                                    @if ($i === $stage) aria-current="step" @endif>{{ $name }}</li>
                            @endforeach
                        </ol>
                    @else
                        <p class="dr-step-note">This document request was cancelled and is kept here for your records.</p>
                    @endif

                    @if ($req->status === 'ready')
                    <p class="dr-step-note">{{ $paid ? 'Ready for collection at the Registrar during published office hours. Bring a valid ID. No appointment is required.' : 'Prepared; collection is available after payment requirements are satisfied.' }}</p>
                    @elseif ($req->released_at)
                    <p class="dr-step-note">Physically released {{ $req->released_at->timezone('Asia/Manila')->format('M d, Y g:i A') }} by {{ $req->releaser?->name ?? 'Recorded staff' }}.</p>
                    @endif
                    @if (!$cancelled && $req->status !== 'released')
                        <div class="dr-step-note">
                            <strong>Next step:</strong>
                            @if ($req->assessment_required && !$req->assessmentSatisfied())
                                {{ $req->assessment_status === 'incomplete' ? 'Provide the missing requirements described in the assessment below. Contact the Registrar if you need help.' : 'Await Registrar requirement and fee assessment. Online submission does not confirm that requirements are complete.' }}
                            @elseif (!$paid)
                                @if ($req->payment_status === 'pending_verification')
                                    Wait for the Cashier to verify your payment. An uploaded proof is not yet a verified payment.
                                @elseif ($rejected)
                                    Review the payment notice and provide corrected payment details through Payment details.
                                @else
                                    Pay at the Cashier or review the available payment instructions in Payment details.
                                @endif
                            @elseif ($collect)
                                Visit the Registrar during published office hours with a valid ID, your claim stub if issued, and your receipt if payment was required.
                            @elseif ($req->status === 'submitted')
                                Await Registrar review. Contact the Registrar to confirm the required documents and present any required originals; online submission does not confirm requirements are complete.
                            @else
                                Wait for the Registrar's ready notification before visiting to collect. Contact the Registrar for the applicable working-day lead time.
                            @endif
                        </div>
                    @endif
                    @if ($req->appointment && $req->appointment->slot)
                        <div class="dr-pickup-summary" aria-label="Pickup appointment">
                            <i data-lucide="calendar-days" aria-hidden="true"></i>
                            <span>
                                <strong>Appointment #{{ $req->appointment->id }} · Pickup:</strong>
                                {{ \Carbon\Carbon::parse($req->appointment->slot->date)->format('M j, Y') }}
                                at {{ \Carbon\Carbon::parse($req->appointment->slot->start_time)->format('g:i A') }}
                                — {{ ucfirst($req->appointment->status) }}.
                                Historical appointment; no new pickup booking is required.
                            </span>
                        </div>
                        @if (in_array($req->appointment->status, ['pending', 'confirmed'], true))
                            <button type="button" class="dr-btn dr-btn--ghost dr-btn--sm mt-2" data-toggle="modal" data-target="#registrarContactModal" data-booking-reference="{{ $req->appointment->id }}">Contact Registrar</button>
                        @endif
                        @if ($req->appointment->documentRequests->count() > 1)
                            @php
                                $releasedInAppointment = $req->appointment->documentRequests->where('status', 'released')->count();
                            @endphp
                            @if ($releasedInAppointment > 0 && $req->appointment->documentRequests->contains('status', 'ready'))
                                <p class="dr-step-note">Partially released: {{ $releasedInAppointment }} document request(s) collected. Ready documents remain awaiting collection.</p>
                            @endif
                            <details class="dr-request-details">
                                <summary>Documents in this appointment ({{ $req->appointment->documentRequests->count() }})</summary>
                                @foreach ($req->appointment->documentRequests as $appointmentDocument)
                                    <p class="dr-req-purpose">
                                        {{ $appointmentDocument->document_label }} × {{ $appointmentDocument->copies }}
                                        — {{ ucfirst($appointmentDocument->status) }} · ₱{{ number_format($appointmentDocument->fee, 2) }}
                                    </p>
                                @endforeach
                            </details>
                        @endif
                    @endif

                    <details class="dr-request-details">
                        <summary>Request details</summary>
                        @if ($req->purpose)<p class="dr-req-purpose">Purpose: {{ $req->purpose }}</p>@endif
                        @if ($req->assessment_required)
                            <p>Assessment: {{ ucfirst($req->assessment_status ?? 'pending') }}</p>
                            @if ($req->requirements_notes)<p>Requirements: {{ $req->requirements_notes }}</p>@endif
                            @if ($req->assessed_fee !== null)<p>Confirmed fee: ₱{{ number_format($req->assessed_fee, 2) }}</p>@endif
                            @if ($req->assessed_at)<p>Assessed: {{ $req->assessed_at->timezone('Asia/Manila')->format('M j, Y g:i A') }}</p>@endif
                        @endif
                        <p>Processing: {{ $cancelled ? 'Cancelled' : ($badgeText[$req->status] ?? 'Submitted') }}</p>
                        <p>Collection: {{ $req->status === 'released' ? 'Collected' : ($collect ? 'Awaiting collection' : ($cancelled ? 'Not available' : 'Awaiting processing or payment')) }}</p>
                        @if ($req->payment_submitted_at)<p>Payment submitted: {{ $req->payment_submitted_at->timezone('Asia/Manila')->format('M j, Y g:i A') }}</p>@endif
                        @if ($paid && $req->verified_at)<p>Payment verified: {{ $req->verified_at->timezone('Asia/Manila')->format('M j, Y g:i A') }}</p>@endif
                    </details>

                    <div class="dr-pay">
                        <div class="dr-pay-line"><span class="dtc-status-badge is-{{ $payTone[$req->payment_status] ?? 'neutral' }}">{{ $req->paymentNotRequired() ? 'No Payment Required' : ($payLabel[$req->payment_status] ?? ucfirst(str_replace('_', ' ', $req->payment_status))) }}</span></div>
                        @php
                            $receiptKey = $bundlePayment ? 'A-'.$bundlePayment->id : 'D-'.$req->id;
                        @endphp
                        @if ($receipts->has($receiptKey))
                            <a class="dr-btn dr-btn--ghost dr-btn--sm" href="{{ route('portal.transactions.receipt', $receiptKey) }}">{{ $bundlePayment ? 'Bundle receipt' : 'View receipt' }}</a>
                        @endif
                        @if ($hasPaymentAction)
                            <div class="dr-actions"><a href="{{ route('portal.payments.document', $req) }}" class="dr-btn dr-btn--primary dr-btn--sm">{{ $paymentActionLabel }}</a></div>
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>

        <div class="dr-empty dr-empty--filter" id="drFilterEmpty" role="status">
            <p>No requests in this filter.</p>
        </div>

        @if (method_exists($requests, 'links'))
            <div style="margin-top:20px;">{{ $requests->links() }}</div>
        @endif
    @endif
</div>
@include('portal.partials.registrar-contact')
@endsection

@section('css')
    <link rel="stylesheet" href="{{ asset('css/document-request-flow.css') }}">
    <link rel="stylesheet" href="{{ asset('css/pickup-picker.css') }}">
    <link rel="stylesheet" href="{{ asset('css/alumni-request-history.css') }}?v={{ filemtime(public_path('css/alumni-request-history.css')) }}">
@endsection

@section('js')
    <script src="{{ asset('js/document-request.js') }}" defer></script>
    <script>
        // Show the chosen file name on the resubmit form.
        document.querySelectorAll('.dr-resubmit input[type="file"]').forEach(function (input) {
            input.addEventListener('change', function () {
                var label = input.closest('.dr-file');
                var name = label.querySelector('.dr-file-name');
                var file = input.files && input.files[0];
                if (file && file.size > 5 * 1024 * 1024) {
                    input.value = '';
                    name.textContent = 'That file is over 5 MB. Choose a smaller one.';
                    label.classList.remove('has-file');
                    return;
                }
                name.textContent = file ? file.name : 'JPG, PNG or PDF, up to 5 MB';
                label.classList.toggle('has-file', !!file);
            });
        });
    </script>
@endsection
