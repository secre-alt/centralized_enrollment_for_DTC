@extends('adminlte::page')
@include('partials.navbar')

@section('title', 'My Dashboard')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h4 class="mb-0 font-weight-bold" style="color:#1E293B;">
                <h1 class="dashboard-title">
                    Alumni Dashboard
                </h1>
            </h4>
            <p class="dashboard-subtitle mb-0" style="color:#64748B; font-size:13px;">
                Welcome to Danao Technological College!
            </p>
        </div>
    </div>
@endsection

@section('content')

@php
    $statusColors = [
        'submitted'  => ['bg' => '#FEF9C3', 'color' => '#A16207'],
        'processing' => ['bg' => '#DBEAFE', 'color' => '#1D4ED8'],
        'ready'      => ['bg' => '#EDE9FE', 'color' => '#7C3AED'],
        'released'   => ['bg' => '#DCFCE7', 'color' => '#15803D'],
    ];
@endphp

<!-- TOP STAT CARDS -->
<div class="row mb-3">
    <div class="col-lg-3 col-sm-6 col-12 mb-3">
        <div class="card" style="border-radius:16px; border:none;
             box-shadow:0 2px 12px rgba(0,0,0,0.06); padding:20px;">
            <div style="display:flex; align-items:center; gap:14px;">
                <div style="width:48px; height:48px; border-radius:14px;
                            background:#EEF2FF; display:flex; align-items:center;
                            justify-content:center; flex-shrink:0;">
                    <i class="fas fa-folder-open" style="font-size:20px; color:#0F4CDB;"></i>
                </div>
                <div>
                    <div style="font-size:11px; color:#94A3B8; font-weight:600;
                                text-transform:uppercase; letter-spacing:0.5px;">
                        Total Requests
                    </div>
                    <div style="font-size:15px; font-weight:700; color:#1E293B; margin-top:2px;">
                        {{ $totalRequests }}
                    </div>
                </div>
            </div>
            <a href="{{ route('portal.documents.index') }}"
               style="font-size:12px; color:#0F4CDB; text-decoration:none;
                      display:block; margin-top:12px; font-weight:600;">
                View All →
            </a>
        </div>
    </div>

    <div class="col-lg-3 col-sm-6 col-12 mb-3">
        <div class="card" style="border-radius:16px; border:none;
             box-shadow:0 2px 12px rgba(0,0,0,0.06); padding:20px;">
            <div style="display:flex; align-items:center; gap:14px;">
                <div style="width:48px; height:48px; border-radius:14px;
                            background:#EDE9FE; display:flex; align-items:center;
                            justify-content:center; flex-shrink:0;">
                    <i class="fas fa-box-open" style="font-size:20px; color:#7C3AED;"></i>
                </div>
                <div>
                    <div style="font-size:11px; color:#94A3B8; font-weight:600;
                                text-transform:uppercase; letter-spacing:0.5px;">
                        Ready for Pickup
                    </div>
                    <div style="font-size:15px; font-weight:700; color:#1E293B; margin-top:2px;">
                        {{ $readyRequests }}
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-3 col-sm-6 col-12 mb-3">
        <div class="card" style="border-radius:16px; border:none;
             box-shadow:0 2px 12px rgba(0,0,0,0.06); padding:20px;">
            <div style="display:flex; align-items:center; gap:14px;">
                <div style="width:48px; height:48px; border-radius:14px;
                            background:#DCFCE7; display:flex; align-items:center;
                            justify-content:center; flex-shrink:0;">
                    <i class="fas fa-check-circle" style="font-size:20px; color:#15803D;"></i>
                </div>
                <div>
                    <div style="font-size:11px; color:#94A3B8; font-weight:600;
                                text-transform:uppercase; letter-spacing:0.5px;">
                        Released
                    </div>
                    <div style="font-size:15px; font-weight:700; color:#1E293B; margin-top:2px;">
                        {{ $releasedDocs }}
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-3 col-sm-6 col-12 mb-3">
        <div class="card" style="border-radius:16px; border:none;
             box-shadow:0 2px 12px rgba(0,0,0,0.06); padding:20px;">
            <div style="display:flex; align-items:center; gap:14px;">
                <div style="width:48px; height:48px; border-radius:14px;
                            background:#EDE9FE; display:flex; align-items:center;
                            justify-content:center; flex-shrink:0; position:relative;">
                    <i class="fas fa-bell" style="font-size:20px; color:#7C3AED;"></i>
                    @if($unreadCount > 0)
                        <span style="position:absolute; top:-4px; right:-4px;
                                     background:#EF4444; color:#fff; font-size:9px;
                                     font-weight:700; border-radius:50%; width:18px;
                                     height:18px; display:flex; align-items:center;
                                     justify-content:center;">
                            {{ $unreadCount }}
                        </span>
                    @endif
                </div>
                <div>
                    <div style="font-size:11px; color:#94A3B8; font-weight:600;
                                text-transform:uppercase; letter-spacing:0.5px;">
                        Notifications
                    </div>
                    <div style="font-size:15px; font-weight:700; color:#1E293B; margin-top:2px;">
                        {{ $unreadCount }} New
                    </div>
                </div>
            </div>
            <a href="{{ route('notifications.index') }}"
               style="font-size:12px; color:#7C3AED; text-decoration:none;
                      display:block; margin-top:12px; font-weight:600;">
                View All →
            </a>
        </div>
    </div>
</div>

{{-- ══ WELCOME CARD ════════════════════════════════════════════════════ --}}
<div class="card mb-3"
     style="background:linear-gradient(135deg,#EEF2FF,#E0E7FF);
            border:1.5px solid #C7D2FE; border-radius:16px;">
    <div class="card-body" style="padding:24px;">
        <div style="display:flex; align-items:center; gap:20px;">
            <div style="width:64px; height:64px; border-radius:50%;
                        background:linear-gradient(135deg,#0F4CDB,#1a5feb);
                        display:flex; align-items:center; justify-content:center;
                        color:#fff; font-weight:800; font-size:26px; flex-shrink:0;">
                {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
            </div>
            <div>
                <h4 style="font-weight:800; color:#1E293B; margin:0 0 4px;">
                    Hello, {{ explode(' ', auth()->user()->name)[0] }}! 👋
                </h4>
                <p style="font-size:13px; color:#4338CA; margin:0;">
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
                <span class="font-weight-bold" style="color:#1E293B;">Recent Document Requests</span>
                <a href="{{ route('portal.documents.index') }}"
                   style="font-size:12px; color:#0F4CDB; text-decoration:none; font-weight:600;">
                    View All
                </a>
            </div>
            <div class="card-body p-0">
                @forelse ($recentRequests as $req)
                <div style="padding:14px 20px; border-bottom:1px solid #F1F5F9;
                            display:flex; align-items:center; justify-content:space-between;">
                    <div style="display:flex; align-items:center; gap:12px;">
                        <div style="width:36px; height:36px; border-radius:10px;
                                    background:#EEF2FF; display:flex; align-items:center;
                                    justify-content:center; flex-shrink:0;">
                            <i class="fas fa-file-alt" style="font-size:14px; color:#0F4CDB;"></i>
                        </div>
                        <div>
                            <div style="font-size:13px; font-weight:600; color:#1E293B;">
                                {{ $req->document_label }}
                            </div>
                            <div style="font-size:11px; color:#64748B;">
                                {{ $req->copies }} cop{{ $req->copies > 1 ? 'ies' : 'y' }} ·
                                {{ $req->created_at->format('M d, Y') }}
                            </div>
                        </div>
                    </div>
                    <span style="background:{{ $statusColors[$req->status]['bg'] ?? '#F1F5F9' }};
                                 color:{{ $statusColors[$req->status]['color'] ?? '#475569' }};
                                 font-size:11px; font-weight:700; padding:3px 10px; border-radius:20px;">
                        {{ ucfirst($req->status) }}
                    </span>
                </div>
                @empty
                <div class="text-center py-4" style="color:#94A3B8;">
                    <i class="fas fa-folder-open fa-2x mb-2"></i>
                    <p style="font-size:13px;">No document requests yet.</p>
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
            <div class="card-header font-weight-bold" style="color:#1E293B;">Quick Actions</div>
            <div class="card-body p-0">
                @php
                    $actions = [
                        ['icon' => 'fa-plus-circle', 'label' => 'Request Document', 'sub' => 'TOR, Diploma, Certification',  'url' => route('portal.documents.create')],
                        ['icon' => 'fa-list',        'label' => 'My Requests',      'sub' => 'Track your document requests', 'url' => route('portal.documents.index')],
                        ['icon' => 'fa-calendar-check', 'label' => 'Book Appointment', 'sub' => 'Schedule document pickup',  'url' => route('portal.appointments.create')],
                        ['icon' => 'fa-bell',        'label' => 'Notifications',    'sub' => $unreadCount . ' unread',       'url' => route('notifications.index')],
                    ];
                @endphp
                @foreach($actions as $action)
                <a href="{{ $action['url'] }}"
                   style="display:flex; align-items:center; justify-content:space-between;
                          padding:14px 20px; border-bottom:1px solid #F1F5F9;
                          text-decoration:none; transition:background 0.2s;"
                   onmouseover="this.style.background='#F8FAFC'"
                   onmouseout="this.style.background='transparent'">
                    <div style="display:flex; align-items:center; gap:12px;">
                        <div style="width:36px; height:36px; border-radius:10px;
                                    background:#EEF2FF; display:flex; align-items:center;
                                    justify-content:center;">
                            <i class="fas {{ $action['icon'] }}"
                               style="font-size:14px; color:#0F4CDB;"></i>
                        </div>
                        <div>
                            <div style="font-size:13px; font-weight:600; color:#1E293B;">
                                {{ $action['label'] }}
                            </div>
                            <div style="font-size:11px; color:#64748B;">
                                {{ $action['sub'] }}
                            </div>
                        </div>
                    </div>
                    <i class="fas fa-chevron-right" style="color:#CBD5E1; font-size:12px;"></i>
                </a>
                @endforeach
            </div>
        </div>

        {{-- Next Appointment --}}
        @if($nextAppointment)
        <div class="card"
             style="background:linear-gradient(135deg,#0F4CDB,#1a5feb); border:none; color:#fff;">
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
                        <div style="font-size:13px; font-weight:600;">
                            {{ ucfirst($nextAppointment->document_type) }}
                        </div>
                        <div style="font-size:11px; opacity:0.75; margin-top:2px;">
                            <i class="fas fa-clock mr-1"></i>
                            {{ \Carbon\Carbon::parse($nextAppointment->slot->start_time)->format('h:i A') }}
                        </div>
                        <div style="font-size:11px; opacity:0.75; margin-top:2px;">
                            <i class="fas fa-map-marker-alt mr-1"></i> Registrar Office
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @endif

    </div>
</div>

@endsection