<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Enrollment;
use App\Models\Payment;           // ← make sure this is here
use App\Models\Appointment;
use App\Models\UserNotification;

class DashboardController extends Controller
{
    public function index()
    {
        $totalStudents      = User::role('student')->count();
        $totalAlumni        = User::role('alumni')->count();
        $pendingEnrollments = Enrollment::where('status', 'pending')
            ->where('is_paid', true)
            ->whereHas('payment', fn ($query) => $query->where('status', 'verified'))
            ->count();
        $appointmentsToday  = Appointment::whereHas('slot', fn($q) =>
            $q->whereDate('date', today()))->count();
        $totalRevenue       = Payment::where('status', 'verified')->sum('amount')
            - Payment::where('refund_status', 'refunded')->sum('refund_amount');

        $enrollmentData = [];
        for ($m = 1; $m <= 12; $m++) {
            $enrollmentData[] = Enrollment::whereYear('created_at', now()->year)
                ->whereMonth('created_at', $m)
                ->count();
        }

        $totalPaid    = Payment::count();
        $totalUnpaid  = Enrollment::whereIn('status', ['pending', 'approved'])->where('is_paid', false)->count();
        $totalPending = $pendingEnrollments;

        $recentActivities = UserNotification::with('user')
            ->latest()->take(6)->get();

        $upcomingAppointments = Appointment::with('user', 'slot')
            ->whereHas('slot', fn($q) => $q->whereDate('date', '>=', today()))
            ->where('status', 'confirmed')
            ->orderBy('created_at')
            ->take(5)->get();

        return view('admin.dashboard', compact(
            'totalStudents',
            'totalAlumni',
            'pendingEnrollments',
            'appointmentsToday',
            'totalRevenue',
            'enrollmentData',
            'totalPaid',
            'totalUnpaid',
            'totalPending',
            'recentActivities',
            'upcomingAppointments'
        ));
    }
}