@extends('adminlte::page')
@include('partials.navbar')

@section('title', 'My Dashboard')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h4 class="mb-0 font-weight-bold">Alumni Dashboard</h4>
            <p class="mb-0 u-text-secondary-sm" >Welcome to Danao Technological College!</p>
        </div>
    </div>
@endsection

@section('content')

@php
    $statusTones = [
        'submitted'  => 'warning',
        'processing' => 'info',
        'ready'      => 'purple',
        'released'   => 'success',
    ];
@endphp

<!-- TOP STAT CARDS -->
<div class="row mb-3">
    <div class="col-lg-3 col-sm-6 col-12 mb-3">
        <x-dtc.stat-card
            icon="folder-open" color="primary"
            label="Total Requests" value="{{ $totalRequests }}"
            href="{{ route('portal.documents.index') }}" />
    </div>

    <div class="col-lg-3 col-sm-6 col-12 mb-3">
        <x-dtc.stat-card
            icon="package-open" color="info"
            label="Ready for Pickup" value="{{ $readyRequests }}" />
    </div>

    <div class="col-lg-3 col-sm-6 col-12 mb-3">
        <x-dtc.stat-card
            icon="check-circle" color="success"
            label="Released" value="{{ $releasedDocs }}" />
    </div>

    <div class="col-lg-3 col-sm-6 col-12 mb-3">
        <x-dtc.stat-card
            icon="bell" color="info" :badge="$unreadCount"
            label="Notifications" value="{{ $unreadCount }} New"
            href="{{ route('notifications.index') }}" />
    </div>
</div>

{{-- ══ WELCOME CARD ════════════════════════════════════════════════════ --}}
<div class="card mb-3 dtc-welcome-card">
    <div class="card-body" style="padding:24px;">
        <div style="display:flex; align-items:center; gap:20px;">
            <div style="width:64px; height:64px; border-radius:50%;
                        background:linear-gradient(135deg,#0F4CDB,#1a5feb);
                        display:flex; align-items:center; justify-content:center;
                        color:#fff; font-weight:800; font-size:26px; flex-shrink:0;">
                {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
            </div>
            <div>
                <h4 class="dtc-welcome-title" style="font-weight:800; margin:0 0 4px;">
                    Hello, {{ explode(' ', auth()->user()->name)[0] }}! 👋
                </h4>
                <p class="dtc-welcome-text" style="font-size:13px; margin:0;">
                    Welcome back! Request transcripts, diplomas, and other documents anytime.
                </p>
            </div>
        </div>
    </div>
</div>

{{-- ══ MAIN CONTENT ROW ════════════════════════════════════════════════ --}}
<div class="row">

    {{-- LEFT: Recent Requests --}}
    <div class="col-lg-8">
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span class="font-weight-bold">Recent Document Requests</span>
                <a href="{{ route('portal.documents.index') }}"
                   style="font-size:12px; color:var(--dtc-primary); text-decoration:none; font-weight:600;">
                    View All
                </a>
            </div>
            <div class="card-body p-0">
                @forelse ($recentRequests as $req)
                <div style="padding:14px 20px; border-bottom:1px solid var(--dtc-border-soft);
                            display:flex; align-items:center; justify-content:space-between;">
                    <div  class="u-flex-center-gap-12">
                        <div class="dtc-icon-swatch is-primary" style="flex-shrink:0;">
                            <i data-lucide="file-text" class="u-link-md"></i>
                        </div>
                        <div>
                            <div  class="u-text-sm-bold">
                                {{ $req->document_label }}
                            </div>
                            <div  class="u-text-xs-secondary">
                                {{ $req->copies }} cop{{ $req->copies> 1 ? 'ies' : 'y' }} ·
                                {{ $req->created_at->format('M d, Y') }}
                            </div>
                        </div>
                    </div>
                    <span class="dtc-status-badge is-{{ $statusTones[$req->status] ?? 'neutral' }}" style="font-weight:700;">
                        {{ ucfirst($req->status) }}
                    </span>
                </div>
                @empty
                <div class="text-center py-4 u-muted" >
                    <i data-lucide="folder-open" class="mb-2" style="width:2em;height:2em"></i>
                    <p  class="u-text-sm">No document requests yet.</p>
                    <a href="{{ route('portal.documents.create') }}"
                       class="btn btn-primary btn-sm">Request Document</a>
                </div>
                @endforelse
            </div>
        </div>
    </div>

    {{-- RIGHT: Quick Actions + Next Appointment --}}
    <div class="col-lg-4">

        <div class="card mb-3">
            <div class="card-header font-weight-bold">Quick Actions</div>
            <div class="card-body p-0">
                @php
                    $actions = [
                        ['icon' => 'plus-circle', 'label' => 'Request Document', 'sub' => 'TOR, Diploma, Certification',  'url' => route('portal.documents.create')],
                        ['icon' => 'list',        'label' => 'My Requests',      'sub' => 'Track your document requests', 'url' => route('portal.documents.index')],
                        ['icon' => 'calendar-check', 'label' => 'Book Appointment', 'sub' => 'Schedule document pickup',  'url' => route('portal.appointments.create')],
                        ['icon' => 'bell',        'label' => 'Notifications',    'sub' => $unreadCount . ' unread',       'url' => route('notifications.index')],
                    ];
                @endphp
                @foreach($actions as $action)
                <a href="{{ $action['url'] }}"
                   class="dtc-quick-action-row"
                   style="display:flex; align-items:center; justify-content:space-between;
                          padding:14px 20px; border-bottom:1px solid var(--dtc-border-soft);
                          text-decoration:none; transition:background 0.2s;">
                    <div  class="u-flex-center-gap-12">
                        <div class="dtc-icon-swatch is-primary">
                            <i data-lucide="{{ $action['icon'] }}" class="u-link-md"
                               ></i>
                        </div>
                        <div>
                            <div  class="u-text-sm-bold">
                                {{ $action['label'] }}
                            </div>
                            <div  class="u-text-xs-secondary">
                                {{ $action['sub'] }}
                            </div>
                        </div>
                    </div>
                    <i data-lucide="chevron-right" style="color:var(--dtc-text-muted); font-size:12px;"></i>
                </a>
                @endforeach
            </div>
        </div>

        {{-- Next Appointment --}}
        @if($nextAppointment)
        <div class="card u-primary-gradient-btn"
             >
            <div class="card-body p-3">
                <div style="font-size:11px; font-weight:600; opacity:0.75; margin-bottom:8px;
                            text-transform:uppercase; letter-spacing:0.5px;">
                    Upcoming Appointment
                </div>
                <div style="display:flex; align-items:center; gap:14px;">
                    <div style="background:rgba(255,255,255,0.15); border-radius:12px;
                                padding:10px 14px; text-align:center; min-width:52px;">
                        <div style="font-size:22px; font-weight:800; line-height:1; color:#FFC72C;">
                            {{ \Carbon\Carbon::parse($nextAppointment->slot->date)->format('d') }}
                        </div>
                        <div style="font-size:10px; font-weight:600; opacity:0.8;">
                            {{ \Carbon\Carbon::parse($nextAppointment->slot->date)->format('M') }}
                        </div>
                    </div>
                    <div>
                        <div  class="u-text-sm-bold">
                            {{ ucfirst($nextAppointment->document_type) }}
                        </div>
                        <div style="font-size:11px; opacity:0.75; margin-top:2px;">
                            <i data-lucide="clock" class="mr-1"></i>
                            {{ \Carbon\Carbon::parse($nextAppointment->slot->start_time)->format('h:i A') }}
                        </div>
                        <div style="font-size:11px; opacity:0.75; margin-top:2px;">
                            <i data-lucide="map-pin" class="mr-1"></i> Registrar Office
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @endif

    </div>
</div>

@endsection