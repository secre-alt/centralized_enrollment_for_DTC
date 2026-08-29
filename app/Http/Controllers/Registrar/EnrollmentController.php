<?php

namespace App\Http\Controllers\Registrar;

use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\Request;

class EnrollmentController extends Controller
{
    public function index()
    {
        $enrollments = Enrollment::with('user', 'program')
            ->where('status', 'pending')
            ->latest()
            ->paginate(10);

        return view('registrar.enrollments.index', compact('enrollments'));
    }

    public function show(Enrollment $enrollment)
    {
        $enrollment->load('user', 'program');
        $subjects = \App\Models\CourseSubject::whereIn('id', $enrollment->subject_ids)->get();
        return view('registrar.enrollments.show', compact('enrollment', 'subjects'));
    }

    /**
     * Registrar-side COR lookup for any enrollment (reached from search or
     * the enrollment show page), not just the ones pending review. Reuses
     * the same student-facing Blade views since the layout is identical —
     * only the enrollment/subjects data and the "back" link differ.
     */
    public function showCor(Enrollment $enrollment)
    {
        if ($enrollment->status !== 'approved' || ! $enrollment->is_paid) {
            return redirect()->route('registrar.enrollments.show', $enrollment)
                ->with('error', 'A Certificate of Registration is only available once enrollment is approved and paid.');
        }

        $enrollment->load('user', 'program');
        $subjects = \App\Models\CourseSubject::whereIn('id', $enrollment->subject_ids ?? [])
            ->with('schedules')
            ->orderBy('subject_code')
            ->get();
        $totalUnits = $subjects->sum('units');

        return view('student.cor.show', [
            'enrollment' => $enrollment,
            'subjects'   => $subjects,
            'totalUnits' => $totalUnits,
            'backUrl'    => route('registrar.enrollments.show', $enrollment),
        ]);
    }

    public function downloadCor(Enrollment $enrollment)
    {
        if ($enrollment->status !== 'approved' || ! $enrollment->is_paid) {
            return redirect()->route('registrar.enrollments.show', $enrollment)
                ->with('error', 'A Certificate of Registration is only available once enrollment is approved and paid.');
        }

        $enrollment->load('user', 'program');
        $subjects = \App\Models\CourseSubject::whereIn('id', $enrollment->subject_ids ?? [])
            ->with('schedules')
            ->orderBy('subject_code')
            ->get();
        $totalUnits = $subjects->sum('units');

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('student.cor.pdf', compact('enrollment', 'subjects', 'totalUnits'));

        return $pdf->download('COR_' . str_replace(' ', '_', $enrollment->user->name) . '.pdf');
    }

    public function approve(Enrollment $enrollment)
    {
        $enrollment->update(['status' => 'approved']);

        $fee = number_format(\App\Models\Setting::get('enrollment_fee', 500), 2);

        // ── Notify Student ────────────────────────────────────────────
        NotificationService::send(
            $enrollment->user,
            'Enrollment Approved',
            "Your enrollment has been approved by the Registrar. Please proceed to the Cashier to pay the ₱{$fee} enrollment fee.",
            'success',
            route('portal.enrollment.payment-info', $enrollment)
        );

        // ── Notify Cashier ────────────────────────────────────────────
        $cashiers = User::role('cashier')->get();
        foreach ($cashiers as $cashier) {
            NotificationService::send(
                $cashier,
                'Enrollment Ready for Payment',
                $enrollment->user->name . "'s enrollment has been approved. They are ready to pay the ₱{$fee} enrollment fee.",
                'info',
                route('cashier.payments.index')
            );
        }

        // ── Notify Admin ──────────────────────────────────────────────
        $admins = User::role('admin')->get();
        foreach ($admins as $admin) {
            NotificationService::send(
                $admin,
                'Enrollment Approved',
                'Registrar approved ' . $enrollment->user->name . '\'s enrollment for ' .
                $enrollment->program->name . '.',
                'success',
                route('registrar.enrollments.index')
            );
        }

        return redirect()->route('registrar.enrollments.index')
            ->with('success', 'Enrollment approved. Student and Cashier have been notified.');
    }

    public function reject(Request $request, Enrollment $enrollment)
    {
        $validated = $request->validate([
            'remarks' => ['required', 'string', 'max:500'],
        ]);

        $enrollment->update([
            'status'  => 'rejected',
            'remarks' => $validated['remarks'],
        ]);

        // ── Notify Student ────────────────────────────────────────────
        NotificationService::send(
            $enrollment->user,
            'Enrollment Rejected',
            'Your enrollment was rejected. Reason: ' . $validated['remarks'],
            'danger',
            route('portal.enrollment.index')
        );

        // ── Notify Admin ──────────────────────────────────────────────
        $admins = User::role('admin')->get();
        foreach ($admins as $admin) {
            NotificationService::send(
                $admin,
                'Enrollment Rejected',
                'Registrar rejected ' . $enrollment->user->name . '\'s enrollment. Reason: ' . $validated['remarks'],
                'warning',
                route('registrar.enrollments.index')
            );
        }

        return redirect()->route('registrar.enrollments.index')
            ->with('success', 'Enrollment rejected. Student has been notified.');
    }
}