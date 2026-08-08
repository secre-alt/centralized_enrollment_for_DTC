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
        'color'  => '#64748B',
        'bg'     => '#F1F5F9',
        'title'  => 'Audit Logs',
        'desc'   => 'View system activities',
        'url'    => route('admin.settings.audit'),
        'active' => false,
    ],
    [
        'icon'   => 'fa-graduation-cap',
        'color'  => '#64748B',
        'bg'     => '#F1F5F9',
        'title'  => 'Academic Settings',
        'desc'   => 'Programs, courses, subjects',
        'url'    => route('admin.programs.index'),
        'active' => false,
    ],
    [
        'icon'   => 'fa-credit-card',
        'color'  => '#64748B',
        'bg'     => '#F1F5F9',
        'title'  => 'Payment Settings',
        'desc'   => 'Payment methods and fees',
        'url'    => route('admin.settings.payment'),
        'active' => false,
    ],
    [
        'icon'   => 'fa-bell',
        'color'  => '#64748B',
        'bg'     => '#F1F5F9',
        'title'  => 'Notification Settings',
        'desc'   => 'Email, SMS and in-app alerts',
        'url'    => route('admin.settings.notifications'),
        'active' => false,
    ],
    [
        'icon'   => 'fa-shield-alt',
        'color'  => '#64748B',
        'bg'     => '#F1F5F9',
        'title'  => 'System Security',
        'desc'   => 'Password policy and access',
        'url'    => route('admin.settings.security'),
        'active' => false,
    ],
    [
        'icon'   => 'fa-database',
        'color'  => '#64748B',
        'bg'     => '#F1F5F9',
        'title'  => 'Backup & Restore',
        'desc'   => 'Backup your system data',
        'url'    => route('admin.settings.backup'),
        'active' => false,
    ],
];
@endphp

<div style="background:#fff; border-radius:16px; box-shadow:0 2px 12px rgba(0,0,0,0.06); overflow:hidden;">
    @foreach($settingsItems as $item)
    <a href="{{ $item['url'] }}"
       style="display:flex; align-items:center; gap:16px; padding:18px 20px;
              border-bottom:1px solid #F1F5F9; text-decoration:none;
              background:{{ $item['active'] ? '#F0F4FF' : '#fff' }};
              transition:background 0.2s;"
       onmouseover="this.style.background='#F8FAFF'"
       onmouseout="this.style.background='{{ $item['active'] ? '#F0F4FF' : '#fff' }}'">

        <div style="width:44px; height:44px; border-radius:12px;
                    background:{{ $item['active'] ? '#0F4CDB' : $item['bg'] }};
                    display:flex; align-items:center; justify-content:center;
                    flex-shrink:0; font-size:18px;
                    color:{{ $item['active'] ? '#fff' : $item['color'] }};">
            <i class="fas {{ $item['icon'] }}"></i>
        </div>

        <div>
            <div style="font-size:14px; font-weight:{{ $item['active'] ? '700' : '600' }};
                        color:{{ $item['active'] ? '#0F4CDB' : '#1E293B' }};">
                {{ $item['title'] }}
            </div>
            <div style="font-size:12px; color:#64748B; margin-top:2px;">
                {{ $item['desc'] }}
            </div>
        </div>

        @if($item['active'])
        <i class="fas fa-chevron-right" style="margin-left:auto; color:#0F4CDB; font-size:12px;"></i>
        @endif
    </a>
    @endforeach
</div>