<?php

namespace App\Providers;

use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Auth;

class ViewServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        View::composer('*', function ($view) {
            if (! Auth::check()) return;

            $user = Auth::user();

            $unreadCount = $user->unreadNotificationsCount();

            $notifItem = [
                'text'        => 'Notifications',
                'url'         => route('notifications.index'),
                'icon'        => 'fas fa-fw fa-bell',
                'label'       => $unreadCount ?: null,
                'label_color' => 'danger',
            ];

            $sidebar = [];

            // ── ADMIN ─────────────────────────────────────────────────────────
            if ($user->hasRole('admin')) {

                // If we're anywhere inside Settings, swap the whole sidebar for a
                // dedicated "Settings" panel (ngrok-style context switch) instead
                // of the normal admin navigation.
                if (request()->routeIs('admin.settings.*')) {
                    $sidebar = [
                        [
                            'text'    => 'Settings',
                            'url'     => route('admin.dashboard'),
                            'icon'    => 'fas fa-fw fa-arrow-left',
                            'classes' => 'sidebar-context-back',
                        ],

                        ['header' => 'ACCOUNT'],
                        ['text' => 'General',       'url' => route('admin.settings.index'),         'icon' => 'fas fa-fw fa-cog'],
                        ['text' => 'Academic',      'url' => route('admin.settings.academic'),       'icon' => 'fas fa-fw fa-graduation-cap'],
                        ['text' => 'Payment',       'url' => route('admin.settings.payment'),        'icon' => 'fas fa-fw fa-credit-card'],
                        ['text' => 'Notifications', 'url' => route('admin.settings.notifications'),  'icon' => 'fas fa-fw fa-bell'],

                        ['header' => 'SYSTEM'],
                        ['text' => 'Security',      'url' => route('admin.settings.security'),       'icon' => 'fas fa-fw fa-shield-alt'],
                        ['text' => 'Backup & Restore', 'url' => route('admin.settings.backup'),      'icon' => 'fas fa-fw fa-cloud-upload-alt'],
                        ['text' => 'Audit Logs',    'url' => route('admin.settings.audit'),          'icon' => 'fas fa-fw fa-file-alt'],
                    ];
                } else {
                    $sidebar = [
                        ['header' => 'ADMIN PANEL'],
                        ['text' => 'Dashboard',    'url' => route('admin.dashboard'),   'icon' => 'fas fa-fw fa-tachometer-alt'],
                        ['text' => 'Manage Users', 'url' => route('admin.users.index'), 'icon' => 'fas fa-fw fa-users'],
                        ['text' => 'Reports', 'icon' => 'fas fa-fw fa-chart-bar', 'submenu' => [
                            ['text' => 'Enrollment Report', 'url' => route('admin.reports.enrollment'), 'icon' => 'fas fa-fw fa-file-pdf'],
                            ['text' => 'Payment Report',    'url' => route('admin.reports.payment'),    'icon' => 'fas fa-fw fa-file-pdf'],
                        ]],

                        ['header' => 'ACADEMIC'],
                        ['text' => 'Programs & Subjects', 'url' => route('admin.programs.index'), 'icon' => 'fas fa-fw fa-graduation-cap'],

                        ['header' => 'REGISTRAR'],
                        ['text' => 'Registrar Dashboard',  'url' => route('registrar.dashboard'),          'icon' => 'fas fa-fw fa-tachometer-alt'],
                        ['text' => 'Applications', 'url' => route('registrar.applications.index'), 'icon' => 'fas fa-fw fa-user-plus'],
                        ['text' => 'Review Enrollments',   'url' => route('registrar.enrollments.index'),  'icon' => 'fas fa-fw fa-file-alt'],
                        ['text' => 'Appointment Requests', 'url' => route('registrar.appointments.index'), 'icon' => 'fas fa-fw fa-calendar-check'],
                        ['text' => 'Manage Slots',         'url' => route('registrar.appointments.slots'), 'icon' => 'fas fa-fw fa-clock'],
                        ['text' => 'Document Requests',    'url' => route('registrar.documents.index'),    'icon' => 'fas fa-fw fa-folder-open'],

                        ['header' => 'CASHIER'],
                        ['text' => 'Cashier Dashboard', 'url' => route('cashier.dashboard'),      'icon' => 'fas fa-fw fa-tachometer-alt'],
                        ['text' => 'Process Payments',  'url' => route('cashier.payments.index'), 'icon' => 'fas fa-fw fa-money-bill-wave'],

                        ['header' => 'SYSTEM'],
                        $notifItem,
                        ['text' => 'Settings', 'url' => route('admin.settings.index'), 'icon' => 'fas fa-fw fa-cog'],
                    ];
                }
            }

            // ── REGISTRAR ─────────────────────────────────────────────────────
            elseif ($user->hasRole('registrar')) {
                $sidebar = [
                    ['header' => 'REGISTRAR PANEL'],
                    ['text' => 'Dashboard',            'url' => route('registrar.dashboard'),          'icon' => 'fas fa-fw fa-tachometer-alt'],
                    ['text' => 'Applications', 'url' => route('registrar.applications.index'), 'icon' => 'fas fa-fw fa-user-plus'],
                    ['text' => 'Review Enrollments',   'url' => route('registrar.enrollments.index'),  'icon' => 'fas fa-fw fa-file-alt'],
                    ['text' => 'Appointment Requests', 'url' => route('registrar.appointments.index'), 'icon' => 'fas fa-fw fa-calendar-check'],
                    ['text' => 'Manage Slots',         'url' => route('registrar.appointments.slots'), 'icon' => 'fas fa-fw fa-clock'],
                    ['text' => 'Document Requests',    'url' => route('registrar.documents.index'),    'icon' => 'fas fa-fw fa-folder-open'],
                    ['header' => 'SYSTEM'],
                    $notifItem,
                ];
            }

            // ── CASHIER ───────────────────────────────────────────────────────
            elseif ($user->hasRole('cashier')) {
                $sidebar = [
                    ['header' => 'CASHIER PANEL'],
                    ['text' => 'Dashboard',  'url' => route('cashier.dashboard'), 'icon' => 'fas fa-fw fa-tachometer-alt'],
                    ['text' => 'Process Payments', 'url' => route('cashier.payments.index'), 'icon' => 'fas fa-fw fa-money-bill-wave'],
                    ['header' => 'SYSTEM'],
                    $notifItem,
                ];
            }

            // ── STUDENT — full portal access ──────────────────────────────────
            elseif ($user->hasRole('student')) {
                $sidebar = [
                    ['header' => 'MY PORTAL'],
                    ['text' => 'Dashboard', 'url' => route('portal.dashboard'), 'icon' => 'fas fa-fw fa-tachometer-alt'],
                    ['text' => 'Enrollment', 'icon' => 'fas fa-fw fa-file-alt', 'submenu' => [
                        ['text' => 'Enroll Now',           'url' => route('portal.enrollment.create'), 'icon' => 'fas fa-fw fa-plus'],
                        ['text' => 'My Enrollment Status', 'url' => route('portal.enrollment.index'),  'icon' => 'fas fa-fw fa-list'],
                    ]],
                    ['text' => 'Appointments', 'icon' => 'fas fa-fw fa-calendar', 'submenu' => [
                        ['text' => 'Book Appointment', 'url' => route('portal.appointments.create'), 'icon' => 'fas fa-fw fa-plus'],
                        ['text' => 'My Appointments',  'url' => route('portal.appointments.index'),  'icon' => 'fas fa-fw fa-list'],
                    ]],
                    ['header' => 'SYSTEM'],
                    $notifItem,
                ];
            }

            // ── NEW APPLICANT — enrollment only ───────────────────────────────
            elseif ($user->hasRole('new_applicant')) {
                $sidebar = [
                    ['header' => 'MY PORTAL'],
                    ['text' => 'Dashboard', 'url' => route('portal.dashboard'), 'icon' => 'fas fa-fw fa-tachometer-alt'],
                    ['text' => 'My Application', 'url' => route('portal.application.show'), 'icon' => 'fas fa-fw fa-file-alt'],
                    ['text' => 'Enrollment', 'icon' => 'fas fa-fw fa-file-alt', 'submenu' => [
                        ['text' => 'Enroll Now',           'url' => route('portal.enrollment.create'), 'icon' => 'fas fa-fw fa-plus'],
                        ['text' => 'My Enrollment Status', 'url' => route('portal.enrollment.index'),  'icon' => 'fas fa-fw fa-list'],
                    ]],
                    ['header' => 'SYSTEM'],
                    $notifItem,
                ];
            }

            // ── ALUMNI — document requests only ───────────────────────────────
            elseif ($user->hasRole('alumni')) {
                $sidebar = [
                    ['header' => 'MY PORTAL'],
                    ['text' => 'Dashboard', 'url' => route('portal.dashboard'), 'icon' => 'fas fa-fw fa-tachometer-alt'],
                    ['text' => 'Document Requests', 'icon' => 'fas fa-fw fa-folder-open', 'submenu' => [
                        ['text' => 'Request Document', 'url' => route('portal.documents.create'), 'icon' => 'fas fa-fw fa-plus'],
                        ['text' => 'My Requests',      'url' => route('portal.documents.index'),  'icon' => 'fas fa-fw fa-list'],
                    ]],
                    ['header' => 'SYSTEM'],
                    $notifItem,
                ];
            }
            //    $sidebar[] = [
            //         'text'    => 'Logout',
            //         'url'     => '#logout-form',
            //         'icon'    => 'fas fa-fw fa-sign-out-alt',
            //         'classes' => 'sidebar-logout-link',
            //     ];
            config(['adminlte.menu' => $sidebar]);
        });
    }
}