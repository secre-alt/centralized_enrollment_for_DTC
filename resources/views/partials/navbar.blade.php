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


@section('content_top_nav_right')
@php
    $unread = \App\Models\UserNotification::where('user_id', auth()->id())
        ->whereNull('read_at')->count();
    $recentNotifs = \App\Models\UserNotification::where('user_id', auth()->id())
        ->latest()->take(5)->get();
@endphp

{{-- Dark Mode Toggle --}}
<li class="nav-item navbar-theme-toggle">
    <button
        type="button"
        id="darkModeToggle"
        class="theme-toggle-btn"
        aria-label="Enable dark mode"
        aria-pressed="false"
        title="Enable dark mode">
        <i class="fas fa-moon" id="darkModeIcon" aria-hidden="true"></i>
    </button>
</li>

<!-- Notification Bell Dropdown -->
<li class="nav-item dropdown mr-2 navbar-notification-item" id="notif-dropdown">
    <a href="#" class="nav-link dropdown-toggle navbar-icon-link"
       data-toggle="dropdown" id="notifBell" aria-label="Open notifications"
       aria-haspopup="true" aria-expanded="false">
        <i class="fas fa-bell navbar-icon" aria-hidden="true"></i>
        @if($unread > 0)
            <span id="notif-badge" class="navbar-notification-badge">
                {{ $unread }}
            </span>
        @endif
    </a>

    {{-- Dropdown Panel --}}
    <div class="dropdown-menu dropdown-menu-right p-0 notify-dropdown">

        {{-- Header --}}
        <div class="notify-header">
            <div>
                <div class="notify-header-title">
                    Notifications
                </div>
                <div class="notify-header-subtitle">
                    @if($unread > 0)
                        {{ $unread }} unread notification{{ $unread > 1 ? 's' : '' }}
                    @else
                        All caught up!
                    @endif
                </div>
            </div>
            @if($unread > 0)
            <a href="{{ route('notifications.index') }}" class="notify-mark-read">
                Mark all read
            </a>
            @endif
        </div>

        {{-- Notification List --}}
        <div class="notify-list">
            @forelse($recentNotifs as $notif)
            <a href="{{ route('notifications.read', $notif) }}"
               class="notif-item {{ $notif->isRead() ? 'is-read' : 'is-unread' }}">

                {{-- Icon --}}
                <div class="notif-icon notif-icon-{{ $notif->type }}">
                    <i class="fas {{ $notif->type === 'success' ? 'fa-check-circle' :
                                    ($notif->type === 'danger'   ? 'fa-times-circle' :
                                    ($notif->type === 'warning'  ? 'fa-exclamation-circle' : 'fa-info-circle')) }}"></i>
                </div>

                {{-- Content --}}
                <div class="notif-body">
                    <div class="notif-row">
                        <div class="notif-title {{ $notif->isRead() ? 'is-read' : 'is-unread' }}">
                            {{ $notif->title }}
                        </div>
                        @if(!$notif->isRead())
                            <div class="notif-dot" aria-label="Unread notification"></div>
                        @endif
                    </div>
                    <div class="notif-message">
                        {{ Str::limit($notif->message, 60) }}
                    </div>
                    <div class="notif-time">
                        <i class="fas fa-clock" aria-hidden="true"></i>
                        {{ $notif->created_at->diffForHumans() }}
                    </div>
                </div>
            </a>
            @empty
            <div class="notify-empty">
                <i class="fas fa-bell-slash notify-empty-icon" aria-hidden="true"></i>
                <div class="notify-empty-title">
                    No notifications yet
                </div>
                <div class="notify-empty-subtitle">
                    You're all caught up!
                </div>
            </div>
            @endforelse
        </div>

        {{-- Footer --}}
        @if($recentNotifs->isNotEmpty())
        <div class="notify-footer">
            <a href="{{ route('notifications.index') }}" class="notify-view-all">
                View all notifications →
            </a>
        </div>
        @endif
    </div>
</li>

@endsection

@section('css')
@section('css')
<style>
/* =========================================================
   NAVBAR — THEME TOGGLE + NOTIFICATION
   ========================================================= */

/* Remove Bootstrap dropdown arrow */
#notifBell::after {
    display: none !important;
}

/* Consistent spacing between right-side navbar controls */
.navbar-theme-toggle,
.navbar-notification-item {
    margin-left: 6px !important;
    margin-right: 6px !important;
}

/* Dark mode + notification button containers */
.navbar-theme-toggle,
.navbar-notification-item {
    display: flex;
    align-items: center;
}

/* Dark mode button */
.theme-toggle-btn {
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;

    border: 1px solid #e5e7eb !important;
    border-radius: 10px;

    background: transparent;
    color: inherit;

    padding: 0;
    transition:
        background-color 0.2s ease,
        border-color 0.2s ease,
        color 0.2s ease;
}

/* Notification button */
.navbar-notification-item .navbar-icon-link {
    width: 40px;
    height: 40px;

    display: flex !important;
    align-items: center;
    justify-content: center;

    border: 1px solid #e5e7eb !important;
    border-radius: 10px;

    padding: 0 !important;
    margin: 0 !important;

    background: transparent;
    transition:
        background-color 0.2s ease,
        border-color 0.2s ease,
        color 0.2s ease;
}

/* Hover */
.theme-toggle-btn:hover,
.navbar-notification-item .navbar-icon-link:hover {
    background: rgba(0, 0, 0, 0.04);
    border-color: #d1d5db !important;
}

/* =========================================================
   DARK MODE
   ========================================================= */

body.dtc-dark .theme-toggle-btn,
body.dtc-dark .navbar-notification-item .navbar-icon-link {
    border-color: #374151 !important;
}

/* Dark mode hover */
body.dtc-dark .theme-toggle-btn:hover,
body.dtc-dark .navbar-notification-item .navbar-icon-link:hover {
    background: rgba(255, 255, 255, 0.06);
    border-color: #4b5563 !important;
}

/* =========================================================
   NOTIFICATION BADGE
   ========================================================= */

.navbar-notification-badge {
    position: absolute;
    top: 2px;
    right: 2px;

    min-width: 17px;
    height: 17px;

    display: flex;
    align-items: center;
    justify-content: center;

    padding: 0 4px;

    border-radius: 999px;
    font-size: 10px;
    font-weight: 700;

    line-height: 1;
}
</style>
@endsection
@endsection
