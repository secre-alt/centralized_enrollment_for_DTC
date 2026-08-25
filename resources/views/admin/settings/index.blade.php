@php
$settingsItems = [
    [
        'icon'   => 'fa-cog',
        'color'  => '#0F4CDB',
        'bg'     => '#EEF2FF',
        'title'  => 'General Settings',
        'desc'   => 'System name, logo, and basic info',
        'url'    => route('admin.settings.general'),
        'active' => true,
    ],
    [
        'icon'   => 'fa-file-alt',
        'color'  => 'var(--dtc-text-secondary)',
        'bg'     => 'var(--dtc-surface-soft)',
        'title'  => 'Audit Logs',
        'desc'   => 'View system activities',
        'url'    => route('admin.settings.audit'),
        'active' => false,
    ],
    [
        'icon'   => 'fa-graduation-cap',
        'color'  => 'var(--dtc-text-secondary)',
        'bg'     => 'var(--dtc-surface-soft)',
        'title'  => 'Academic Settings',
        'desc'   => 'Programs, courses, subjects',
        'url'    => route('admin.programs.index'),
        'active' => false,
    ],
    [
        'icon'   => 'fa-credit-card',
        'color'  => 'var(--dtc-text-secondary)',
        'bg'     => 'var(--dtc-surface-soft)',
        'title'  => 'Payment Settings',
        'desc'   => 'Payment methods and fees',
        'url'    => route('admin.settings.payment'),
        'active' => false,
    ],
    [
        'icon'   => 'fa-bell',
        'color'  => 'var(--dtc-text-secondary)',
        'bg'     => 'var(--dtc-surface-soft)',
        'title'  => 'Notification Settings',
        'desc'   => 'Email, SMS and in-app alerts',
        'url'    => route('admin.settings.notifications'),
        'active' => false,
    ],
    [
        'icon'   => 'fa-shield-alt',
        'color'  => 'var(--dtc-text-secondary)',
        'bg'     => 'var(--dtc-surface-soft)',
        'title'  => 'System Security',
        'desc'   => 'Password policy and access',
        'url'    => route('admin.settings.security'),
        'active' => false,
    ],
    [
        'icon'   => 'fa-database',
        'color'  => 'var(--dtc-text-secondary)',
        'bg'     => 'var(--dtc-surface-soft)',
        'title'  => 'Backup & Restore',
        'desc'   => 'Backup your system data',
        'url'    => route('admin.settings.backup'),
        'active' => false,
    ],
];
@endphp

<div style="background:var(--dtc-surface); border-radius:16px; box-shadow:0 2px 12px rgba(0,0,0,0.06); overflow:hidden;">
    @foreach($settingsItems as $item)
    <a href="{{ $item['url'] }}"
       class="dtc-settings-item {{ $item['active'] ? 'is-active' : '' }}"
       style="display:flex; align-items:center; gap:16px; padding:18px 20px;
              text-decoration:none;">

        <div style="width:44px; height:44px; border-radius:12px;
                    background:{{ $item['active'] ? '#0F4CDB' : $item['bg'] }};
                    display:flex; align-items:center; justify-content:center;
                    flex-shrink:0; font-size:18px;
                    color:{{ $item['active'] ? '#fff' : $item['color'] }};">
            <i class="fas {{ $item['icon'] }}"></i>
        </div>

        <div>
            <div style="font-size:14px; font-weight:{{ $item['active'] ? '700' : '600' }};
                        color:{{ $item['active'] ? 'var(--dtc-on-primary-soft)' : 'var(--dtc-text)' }};">
                {{ $item['title'] }}
            </div>
            <div  class="u-text-xxs-secondary">
                {{ $item['desc'] }}
            </div>
        </div>

        @if($item['active'])
        <i class="fas fa-chevron-right" style="margin-left:auto; color:var(--dtc-on-primary-soft); font-size:12px;"></i>
        @endif
    </a>
    @endforeach
</div>