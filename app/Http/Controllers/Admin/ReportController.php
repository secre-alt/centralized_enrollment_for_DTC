<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use App\Http\Requests\PaymentReportRequest;
use Barryvdh\DomPDF\Facade\Pdf;

class ReportController extends Controller
{
    public function enrollmentReport()
    {
        $enrollments = Enrollment::with('user', 'program')->latest()->get();
        $pdf = Pdf::loadView('admin.reports.enrollment', compact('enrollments'))
            ->setPaper('a4', 'landscape');

        return $pdf->download('DTC_Enrollment_Report_' . date('Ymd') . '.pdf');
    }

    public function paymentReport(PaymentReportRequest $request)
    {
        return redirect()->route('reports.payments.index', $request->filters()->query());
    }
}
