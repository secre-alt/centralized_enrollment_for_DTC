@section('content_top_nav_left')
<li class="nav-item navbar-search-item">
    <div class="navbar-search-wrapper">

        {{-- Icon --}}
        <i class="fas fa-search navbar-search-icon"></i>

        {{-- Input --}}
        <input type="text"
               id="global-search"
               placeholder="Search anything..."
               class="navbar-search-input"
               autocomplete="off">

        {{-- Ctrl+K badge --}}
        <div class="search-kbd" id="search-kbd">
            <span class="search-kbd-key">Ctrl</span>
            <span class="search-kbd-key">K</span>
        </div>

        {{-- Clear button --}}
        <button class="search-clear-btn" id="search-clear" onclick="clearSearch()">
            <i class="fas fa-times"></i>
        </button>

    </div>

    {{-- Dropdown --}}
    <div class="search-dropdown" id="search-dropdown">

        {{-- Default: Suggestions + Recent --}}
        <div id="search-default">
            <div class="search-section">
                <div class="search-section-label">Suggestions</div>
                <div id="suggestions-list"></div>
            </div>

            <div class="search-section-footer">
                <span class="search-section-label" style="margin:0;">Recent Searches</span>
                <button class="search-clear-recent" onclick="clearRecent()">Clear</button>
            </div>
            <div class="search-section" style="padding-top:10px;">
                <div class="search-recent-chips" id="recent-list"></div>
                <div class="search-no-recent" id="no-recent" style="display:none;">
                    No recent searches yet.
                </div>
            </div>
        </div>

        {{-- Loading --}}
        <div class="search-loading" id="search-loading" style="display:none;">
            <i class="fas fa-spinner fa-spin"></i>
        </div>

        {{-- Results --}}
        <div id="search-results" style="display:none;">
            <div class="search-section">
                <div class="search-section-label">Results</div>
                <div id="results-list"></div>
            </div>
        </div>

        {{-- Empty --}}
        <div class="search-empty" id="search-empty" style="display:none;">
            <i class="fas fa-search"></i>
            <div class="search-empty-title">No results found</div>
            <div class="search-empty-sub" id="empty-query"></div>
        </div>

    </div>
</li>
@endsection

<!-- {{-- Pinned sidebar logout --}}
@push('sidebar_custom')
    <div class="sidebar-logout-wrapper">
        <form action="{{ route('logout') }}" method="POST">
            @csrf
            <button type="submit" class="sidebar-logout-btn">
                <i class="fas fa-sign-out-alt"></i> Logout
            </button>
        </form>
    </div>
@endpush -->

@section('content_top_nav_right')
@php
    $unread = \App\Models\UserNotification::where('user_id', auth()->id())
        ->whereNull('read_at')->count();
    $recentNotifs = \App\Models\UserNotification::where('user_id', auth()->id())
        ->latest()->take(5)->get();
@endphp

<!-- Notification Bell Dropdown -->
<li class="nav-item dropdown mr-2 navbar-notification-item" id="notif-dropdown">
    <a href="#" class="nav-link dropdown-toggle"
       data-toggle="dropdown" id="notifBell"
       style="position:relative; padding:8px 10px; display:flex; align-items:center;">
        <i class="fas fa-bell" style="font-size:18px; color:#64748B;"></i>
        @if($unread > 0)
            <span id="notif-badge"
                  style="position:absolute; top:2px; right:2px;
                         background:#EF4444; color:#fff; font-size:9px;
                         font-weight:700; border-radius:50%;
                         min-width:16px; height:16px; padding:0 4px;
                         display:inline-flex; align-items:center;
                         justify-content:center; line-height:1;
                         font-family:'Poppins',sans-serif;
                         z-index:10; border:2px solid #fff;">
                {{ $unread }}
            </span>
        @endif
    </a>

    {{-- Dropdown Panel --}}
    <div class="dropdown-menu dropdown-menu-right p-0 notify-dropdown"
         style="width:360px; border:none; border-radius:16px;
                box-shadow:0 8px 40px rgba(0,0,0,0.15); overflow:hidden;
                margin-top:8px;">

        {{-- Header --}}
        <div style="padding:16px 20px; background:linear-gradient(135deg,#0F4CDB,#1a5feb);
                    display:flex; justify-content:space-between; align-items:center;">
            <div>
                <div style="font-size:14px; font-weight:700; color:#fff;">
                    Notifications
                </div>
                <div style="font-size:11px; color:rgba(255,255,255,0.7); margin-top:2px;">
                    @if($unread > 0)
                        {{ $unread }} unread notification{{ $unread > 1 ? 's' : '' }}
                    @else
                        All caught up!
                    @endif
                </div>
            </div>
            @if($unread > 0)
            <a href="{{ route('notifications.index') }}"
               style="font-size:11px; color:#FFC72C; font-weight:600;
                      text-decoration:none; background:rgba(255,255,255,0.15);
                      padding:4px 10px; border-radius:20px;">
                Mark all read
            </a>
            @endif
        </div>

        {{-- Notification List --}}
        <div style="max-height:320px; overflow-y:auto;">
            @forelse($recentNotifs as $notif)
            <a href="{{ route('notifications.read', $notif) }}"
               class="notif-item"
               style="display:flex; align-items:flex-start; gap:12px;
                      padding:14px 20px; border-bottom:1px solid #F1F5F9;
                      text-decoration:none; transition:background 0.2s;
                      background:{{ $notif->isRead() ? '#ffffff' : '#F8FAFF' }};">

                {{-- Icon --}}
                <div style="width:36px; height:36px; border-radius:10px; flex-shrink:0;
                            display:flex; align-items:center; justify-content:center;
                            background:{{ $notif->type === 'success' ? '#DCFCE7' :
                                          ($notif->type === 'danger'  ? '#FEE2E2' :
                                          ($notif->type === 'warning' ? '#FEF9C3' : '#DBEAFE')) }};
                            font-size:14px;
                            color:{{ $notif->type === 'success' ? '#15803D' :
                                     ($notif->type === 'danger'  ? '#DC2626' :
                                     ($notif->type === 'warning' ? '#A16207' : '#1D4ED8')) }};">
                    <i class="fas {{ $notif->type === 'success' ? 'fa-check-circle' :
                                    ($notif->type === 'danger'   ? 'fa-times-circle' :
                                    ($notif->type === 'warning'  ? 'fa-exclamation-circle' : 'fa-info-circle')) }}"></i>
                </div>

                {{-- Content --}}
                <div style="flex:1; min-width:0;">
                    <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:8px;">
                        <div style="font-size:13px; font-weight:{{ $notif->isRead() ? '500' : '700' }};
                                    color:#1E293B; line-height:1.4;">
                            {{ $notif->title }}
                        </div>
                        @if(!$notif->isRead())
                            <div style="width:8px; height:8px; border-radius:50%;
                                        background:#0F4CDB; flex-shrink:0; margin-top:4px;"></div>
                        @endif
                    </div>
                    <div style="font-size:12px; color:#64748B; margin-top:3px;
                                white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                        {{ Str::limit($notif->message, 60) }}
                    </div>
                    <div style="font-size:11px; color:#94A3B8; margin-top:4px;">
                        <i class="fas fa-clock" style="font-size:10px; margin-right:3px;"></i>
                        {{ $notif->created_at->diffForHumans() }}
                    </div>
                </div>
            </a>
            @empty
            <div style="padding:32px 20px; text-align:center;">
                <i class="fas fa-bell-slash" style="font-size:32px; color:#E2E8F0; display:block; margin-bottom:12px;"></i>
                <div style="font-size:13px; font-weight:600; color:#1E293B; margin-bottom:4px;">
                    No notifications yet
                </div>
                <div style="font-size:12px; color:#94A3B8;">
                    You're all caught up!
                </div>
            </div>
            @endforelse
        </div>

        {{-- Footer --}}
        @if($recentNotifs->isNotEmpty())
        <div style="padding:12px 20px; text-align:center;
                    border-top:1px solid #F1F5F9; background:#F8FAFC;">
            <a href="{{ route('notifications.index') }}"
               style="font-size:13px; font-weight:600; color:#0F4CDB; text-decoration:none;">
                View all notifications →
            </a>
        </div>
        @endif
    </div>
</li>

{{-- User Profile Dropdown --}}
<li class="nav-item dropdown mr-2 navbar-profile-item">
    <a href="#" class="nav-link dropdown-toggle d-flex align-items-center"
       data-toggle="dropdown" style="gap:10px; padding:4px 8px;">
        <div style="width:36px; height:36px; border-radius:50%;
                    background:linear-gradient(135deg,#0F4CDB,#1a5feb);
                    display:flex; align-items:center; justify-content:center;
                    color:#fff; font-weight:700; font-size:14px;
                    font-family:'Poppins',sans-serif; flex-shrink:0;">
            {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
        </div>
        <div style="line-height:1.2;">
            <div style="font-size:13px; font-weight:600; color:#1E293B;">
                {{ auth()->user()->name }}
            </div>
            <div style="font-size:11px; color:#64748B;">
                {{ ucfirst(str_replace('_', ' ', auth()->user()->getRoleNames()->first() ?? '')) }}
            </div>
        </div>
    </a>
    <div class="dropdown-menu dropdown-menu-right"
         style="border:none; border-radius:14px;
                box-shadow:0 8px 30px rgba(0,0,0,0.12);
                padding:8px; min-width:200px; margin-top:8px;">

        <div class="navbar-user-info" style="line-height:1.2;">
            <div style="font-size:13px; font-weight:600; color:#1E293B;">
                {{ auth()->user()->name }}
            </div>
            <div style="font-size:11px; color:#64748B;">
                {{ auth()->user()->email }}
            </div>
        </div>

        <a class="dropdown-item" href="{{ route('notifications.index') }}"
           style="border-radius:8px; font-size:13px; padding:10px 14px;
                  font-family:'Poppins',sans-serif; color:#1E293B;">
            <i class="fas fa-bell mr-2" style="color:#0F4CDB; width:16px;"></i>
            Notifications
            @if($unread > 0)
                <span style="float:right; background:#EF4444; color:#fff; font-size:9px;
                             font-weight:700; border-radius:50%; width:18px; height:18px;
                             display:inline-flex; align-items:center; justify-content:center;">
                    {{ $unread }}
                </span>
            @endif
        </a>

        <div style="border-top:1px solid #F1F5F9; margin:6px 0;"></div>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="dropdown-item"
                    style="border-radius:8px; font-size:13px; padding:10px 14px;
                           font-family:'Poppins',sans-serif; color:#EF4444;
                           background:none; border:none; width:100%;
                           text-align:left; cursor:pointer;">
                <i class="fas fa-sign-out-alt mr-2" style="width:16px;"></i>
                Logout
            </button>
        </form>
    </div>
</li>
@endsection

@section('css')
<style>
.notif-item:hover {
    background: #F0F4FF !important;
    text-decoration: none !important;
}

/* Scrollbar inside notification dropdown */
.dropdown-menu div::-webkit-scrollbar { width: 4px; }
.dropdown-menu div::-webkit-scrollbar-track { background: #F1F5F9; }
.dropdown-menu div::-webkit-scrollbar-thumb { background: #CBD5E1; border-radius: 10px; }

/* Hide Bootstrap's dropdown arrow on bell */
#notifBell::after { display: none !important; }
</style>
@endsection