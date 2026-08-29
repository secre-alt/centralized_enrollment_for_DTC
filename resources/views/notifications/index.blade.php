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
            <i data-lucide="bell-off" class="mb-3 u-border-color" style="width:4em;height:4em"></i>
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
                    {{ $notification->type === 'success' ? 'var(--dtc-success)' :
                       ($notification->type === 'danger'  ? 'var(--dtc-danger)' :
                       ($notification->type === 'warning' ? 'var(--dtc-accent)' : 'var(--dtc-primary)')) }};
                    opacity: {{ $notification->isRead() ? '0.75' : '1' }};">
                <div class="card-body u-pad-card" >
                    <div class="d-flex justify-content-between align-items-start">
                        <div style="display:flex; gap:14px; align-items:flex-start; flex:1;">

                            {{-- Icon --}}
                            @php
                                $notifTone = $notification->type === 'success' ? 'success' : ($notification->type === 'danger' ? 'danger' : ($notification->type === 'warning' ? 'warning' : 'info'));
                            @endphp
                            <div class="dtc-icon-swatch is-{{ $notifTone }}" style="width:40px; height:40px; border-radius:12px; flex-shrink:0;">
                                <i data-lucide="{{ $notification->type === 'success' ? 'check-circle' :
                                               ($notification->type === 'danger'   ? 'x-circle' :
                                               ($notification->type === 'warning'  ? 'alert-circle' : 'info')) }}" style="font-size:18px;"></i>
                            </div>

                            {{-- Content --}}
                            <div  class="u-flex-1">
                                <div style="display:flex; align-items:center; gap:8px; margin-bottom:4px;">
                                    <span style="font-size:13px; font-weight:700; color:var(--dtc-text);">
                                        {{ $notification->title }}
                                    </span>
                                    @if(!$notification->isRead())
                                        <span class="dtc-status-badge is-danger"
                                          style="font-size:9px; font-weight:700; padding:2px 7px;">
                                            NEW
                                        </span>
                                    @endif
                                </div>
                                <p style="font-size:13px; color:var(--dtc-text-secondary); margin:0 0 8px;">
                                    {{ $notification->message }}
                                </p>
                                <div  class="u-text-xs-muted">
                                    <i data-lucide="clock" class="mr-1"></i>
                                    {{ $notification->created_at->diffForHumans() }}
                                </div>
                            </div>
                        </div>

                        {{-- Action --}}
                        @if($notification->link)
                        <a href="{{ route('notifications.read', $notification) }}"
                           style="background:var(--dtc-primary-soft); color:var(--dtc-primary); padding:6px 14px;
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