{{-- ══ Settings tab list (shared across all settings pages) ═══════════════════ --}}
<div class="col-lg-3 mb-3">
    <div class="card settings-tabs">

        <a href="{{ route('admin.settings.index') }}" class="settings-tab-item {{ request()->routeIs('admin.settings.index') ? 'active' : '' }}">
            <div class="settings-tab-icon"><i class="fas fa-cog"></i></div>
            <div>
                <div class="settings-tab-title">General Settings</div>
                <div class="settings-tab-desc">System name, logo, and basic info</div>
            </div>
        </a>

        <a href="{{ route('admin.settings.academic') }}" class="settings-tab-item {{ request()->routeIs('admin.settings.academic') ? 'active' : '' }}">
            <div class="settings-tab-icon"><i class="fas fa-graduation-cap"></i></div>
            <div>
                <div class="settings-tab-title">Academic Settings</div>
                <div class="settings-tab-desc">Programs, courses, subjects</div>
            </div>
        </a>

        <a href="{{ route('admin.settings.payment') }}" class="settings-tab-item {{ request()->routeIs('admin.settings.payment') ? 'active' : '' }}">
            <div class="settings-tab-icon"><i class="fas fa-credit-card"></i></div>
            <div>
                <div class="settings-tab-title">Payment Settings</div>
                <div class="settings-tab-desc">Payment methods and fees</div>
            </div>
        </a>

        <a href="{{ route('admin.settings.notifications') }}" class="settings-tab-item {{ request()->routeIs('admin.settings.notifications') ? 'active' : '' }}">
            <div class="settings-tab-icon"><i class="fas fa-bell"></i></div>
            <div>
                <div class="settings-tab-title">Notification Settings</div>
                <div class="settings-tab-desc">Email, SMS and in-app alerts</div>
            </div>
        </a>

        <a href="{{ route('admin.settings.security') }}" class="settings-tab-item {{ request()->routeIs('admin.settings.security') ? 'active' : '' }}">
            <div class="settings-tab-icon"><i class="fas fa-shield-alt"></i></div>
            <div>
                <div class="settings-tab-title">System Security</div>
                <div class="settings-tab-desc">Password policy and access</div>
            </div>
        </a>

        <a href="{{ route('admin.settings.backup') }}" class="settings-tab-item {{ request()->routeIs('admin.settings.backup') ? 'active' : '' }}">
            <div class="settings-tab-icon"><i class="fas fa-cloud-upload-alt"></i></div>
            <div>
                <div class="settings-tab-title">Backup &amp; Restore</div>
                <div class="settings-tab-desc">Backup your system data</div>
            </div>
        </a>

        <a href="{{ route('admin.settings.audit') }}" class="settings-tab-item {{ request()->routeIs('admin.settings.audit') ? 'active' : '' }}">
            <div class="settings-tab-icon"><i class="fas fa-file-alt"></i></div>
            <div>
                <div class="settings-tab-title">Audit Logs</div>
                <div class="settings-tab-desc">View system activities</div>
            </div>
        </a>

    </div>
</div>
