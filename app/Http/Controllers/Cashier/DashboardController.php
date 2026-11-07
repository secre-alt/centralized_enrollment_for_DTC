<?php

namespace App\Http\Controllers\Cashier;

use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use App\Models\Payment;

class DashboardController extends Controller
{
    public function index()
    {
        $todayCollection   = Payment::where('status', 'verified')->whereDate('paid_at', today())->sum('amount')
            - Payment::where('refund_status', 'refunded')->whereDate('refunded_at', today())->sum('refund_amount');
        $pendingPayments   = Enrollment::whereIn('status', ['pending', 'approved'])
            ->where('is_paid', false)
            ->whereDoesntHave('payment', fn ($query) => $query->where('status', 'verified'))
            ->count();
        $paidThisMonth     = Payment::whereMonth('paid_at', now()->month)->count();
        $walkinPayments    = Payment::whereMonth('paid_at', now()->month)->count();
        $totalRevenue      = Payment::where('status', 'verified')->sum('amount')
            - Payment::where('refund_status', 'refunded')->sum('refund_amount');
        $totalPaid         = Payment::count();
        $totalTransactions = Payment::count();

        $recentPayments = Payment::with('enrollment.user', 'enrollment.program')
            ->latest()->limit(5)->get();

        return view('cashier.dashboard', compact(
            'todayCollection',
            'pendingPayments',
            'paidThisMonth',
            'walkinPayments',
            'totalRevenue',
            'totalPaid',
            'totalTransactions',
            'recentPayments'
        ));
    }
}