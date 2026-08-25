@extends('adminlte::page')
@include('partials.navbar')

@section('title', 'Notifications')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h4 class="mb-0 font-weight-bold u-text" >Notifications</h4>
            <p class="mb-0 u-text-secondary-sm" >Your system alerts and updates</p>
        </div>
    </div>
@endsection

@section('content')

@if ($notifications->isEmpty())
    <div class="card">
        <div class="card-body text-center py-5">
            <i class="fas fa-bell-slash fa-4x mb-3 u-border-color" ></i>
            <h5 style="color:var(--dtc-text); font-weight:700;">No Notifications</h5>
            <p  class="u-text-secondary-sm">You're all caught up! No notifications at the moment.</p>
        </div>
    </div>
@else
    <div class="row justify-content-center">
        <div class="col-lg-8">
            @foreach ($notifications as $notification)
            <div class="card mb-2"
                 style="border-left:4px solid
                    {{ $notification->type === 'success' ? '#22C55E' :
                       ($notification->type === 'danger'  ? '#EF4444' :
                       ($notification->type === 'warning' ? '#FFC72C' : '#3B82F6')) }};
                    opacity: {{ $notification->isRead() ? '0.75' : '1' }};">
                <div class="card-body u-pad-card" >
                    <div class="d-flex justify-content-between align-items-start">
                        <div style="display:flex; gap:14px; align-items:flex-start; flex:1;">

                            {{-- Icon --}}
                            <div style="width:40px; height:40px; border-radius:12px; flex-shrink:0;
                                        display:flex; align-items:center; justify-content:center;
                                        background:{{ $notification->type === 'success' ? '#DCFCE7' :
                                                      ($notification->type === 'danger'  ? '#FEE2E2' :
                                                      ($notification->type === 'warning' ? '#FEF9C3' : '#DBEAFE')) }};
                                        color:{{ $notification->type === 'success' ? '#15803D' :
                                                 ($notification->type === 'danger'  ? '#DC2626' :
                                                 ($notification->type === 'warning' ? '#A16207' : '#1D4ED8')) }};">
                                <i class="fas {{ $notification->type === 'success' ? 'fa-check-circle' :
                                               ($notification->type === 'danger'   ? 'fa-times-circle' :
                                               ($notification->type === 'warning'  ? 'fa-exclamation-circle' : 'fa-info-circle')) }}"
                                   style="font-size:18px;"></i>
                            </div>

                            {{-- Content --}}
                            <div  class="u-flex-1">
                                <div style="display:flex; align-items:center; gap:8px; margin-bottom:4px;">
                                    <span style="font-size:13px; font-weight:700; color:var(--dtc-text);">
                                        {{ $notification->title }}
                                    </span>
                                    @if(!$notification->isRead())
                                        <span style="background:#EF4444; color:#fff; font-size:9px;
                                                     font-weight:700; padding:2px 7px; border-radius:20px;">
                                            NEW
                                        </span>
                                    @endif
                                </div>
                                <p style="font-size:13px; color:var(--dtc-text-secondary); margin:0 0 8px;">
                                    {{ $notification->message }}
                                </p>
                                <div  class="u-text-xs-muted">
                                    <i class="fas fa-clock mr-1"></i>
                                    {{ $notification->created_at->diffForHumans() }}
                                </div>
                            </div>
                        </div>

                        {{-- Action --}}
                        @if($notification->link)
                        <a href="{{ route('notifications.read', $notification) }}"
                           style="background:#EEF2FF; color:#0F4CDB; padding:6px 14px;
                                  border-radius:8px; font-size:11px; font-weight:600;
                                  text-decoration:none; flex-shrink:0; margin-left:12px;">
                            View
                        </a>
                        @endif
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
@endif

@endsection