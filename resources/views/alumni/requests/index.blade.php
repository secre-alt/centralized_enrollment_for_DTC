@extends('adminlte::page')
@include('partials.navbar')

@section('title', 'My Document Requests')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center dtc-document-page-heading">
        <div class="dtc-document-page-title">
            <h4 class="mb-0 font-weight-bold u-text" >My Document Requests</h4>
            <p class="mb-0 u-text-secondary-sm" >
                Track your document requests and pickup status
            </p>
        </div>
        <a href="{{ route('portal.documents.create') }}" class="btn btn-primary btn-sm dtc-document-page-action">
            <i data-lucide="plus" class="mr-1"></i> Request Document
        </a>
    </div>
@endsection

@section('content')


<div class="dtc-portal-dashboard">
<div class="dtc-dashboard-overview dtc-dashboard-overview--four mb-3" aria-label="Document request summary">
    <div class="dtc-overview-item">
        <span class="dtc-overview-label">Total requests</span>
        <strong>{{ $totalRequests }}</strong>
        <a href="{{ $totalRequests ? '#request-history' : route('portal.documents.create') }}">{{ $totalRequests ? 'View request history' : 'Start a request' }}</a>
    </div>
    <div class="dtc-overview-item">
        <span class="dtc-overview-label">Ready for pickup</span>
        <strong>{{ $readyRequests }}</strong>
        @if($readyRequests > 0)
            <a href="#request-history">View ready requests</a>
        @else
            <span class="dtc-overview-note">No documents awaiting pickup</span>
        @endif
    </div>
    <div class="dtc-overview-item">
        <span class="dtc-overview-label">Released</span>
        <strong>{{ $releasedDocs }}</strong>
        <span class="dtc-overview-note">Completed document requests</span>
    </div>
    <div class="dtc-overview-item">
        <span class="dtc-overview-label">Total fees</span>
        <strong>₱{{ number_format($totalFees, 2) }}</strong>
        <span class="dtc-overview-note">Across all requests</span>
    </div>
</div>
@if ($requests->isEmpty())
    <div class="card">
        <div class="card-body text-center py-5">
            <i data-lucide="folder-open" class="mb-3 u-border-color" style="width:4em;height:4em"></i>
            <h5 style="color:var(--dtc-text); font-weight:700;">No Document Requests Yet</h5>
            <p style="color:var(--dtc-text-secondary); font-size:13px; max-width:360px; margin:0 auto 20px;">
                Submit a document request to get your TOR, Diploma, Certification, or other records.
            </p>
            <a href="{{ route('portal.documents.create') }}" class="btn btn-primary">
                <i data-lucide="plus" class="mr-1"></i> Request Document
            </a>
        </div>
    </div>
@else
    {{-- Request Timeline --}}
    <div class="row">
        <div class="col-lg-8 mb-3">
            <div class="card" id="request-history">
                <div class="card-header font-weight-bold u-text" >
                    Request History
                </div>
                <div class="card-body p-0">
                    @foreach ($requests as $req)
                    <div class="dtc-document-request-row">

                        {{-- Status dot --}}
                        @php
                            $docTone = $req->status === 'released' ? 'success' : ($req->status === 'ready' ? 'purple' : ($req->status === 'processing' ? 'info' : 'warning'));
                        @endphp
                        <div data-doc-status="{{ $req->status }}"
                             class="dtc-icon-swatch is-{{ $docTone }}"
                             style="width:44px; height:44px; border-radius:12px; flex-shrink:0;">
                            <i data-lucide="{{ $req->status === 'released' ? 'check-check' :
                                           ($req->status === 'ready' ? 'package-open' :
                                           ($req->status === 'processing' ? 'loader' : 'clock')) }}" style="font-size:16px;"></i>
                        </div>

                        <div class="dtc-document-request-main">
                            <div class="dtc-document-request-heading">
                                <div style="font-size:14px; font-weight:600; color:var(--dtc-text);">
                                    {{ $req->document_label }}
                                </div>
                                <span data-doc-status="{{ $req->status }}"
                                      class="dtc-status-badge is-{{ $docTone }}"
                                      style="font-weight:700;">
                                    {{ ucfirst($req->status) }}
                                </span>
                            </div>
                            <div class="dtc-document-request-meta">
                                {{ $req->copies }} cop{{ $req->copies > 1 ? 'ies' : 'y' }} •
                                Fee: ₱{{ number_format($req->fee, 2) }} •
                                {{ $req->created_at->format('M d, Y') }}
                            </div>
                            @if($req->purpose)
                            <div style="font-size:12px; color:var(--dtc-text-muted); margin-top:2px;">
                                {{ $req->purpose }}
                            </div>
                            @endif
                            @if($req->remarks)
                            <div class="alert alert-danger" style="font-size:12px; margin-top:4px;
                                        padding:6px 10px; border-radius:8px;">
                                <i data-lucide="info" class="mr-1"></i>{{ $req->remarks }}
                            </div>
                            @endif
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Quick Actions --}}
        <div class="col-lg-4 mb-3">
            <div class="card">
                <div class="card-header font-weight-bold u-text" >Quick Actions</div>
                <div class="card-body p-3">
                    <a href="{{ route('portal.documents.create') }}" class="quick-action-btn">
                        <i data-lucide="plus"></i> Request Document
                    </a>
                    <a href="{{ route('portal.appointments.create') }}" class="quick-action-btn">
                        <i data-lucide="calendar-plus"></i> Book Appointment
                    </a>
                    <a href="{{ route('portal.appointments.index') }}" class="quick-action-btn">
                        <i data-lucide="calendar"></i> My Appointments
                    </a>
                    <a href="{{ route('notifications.index') }}" class="quick-action-btn">
                        <i data-lucide="bell"></i> Notifications
                    </a>
                </div>
            </div>
        </div>
    </div>
@endif

</div>

@endsection
