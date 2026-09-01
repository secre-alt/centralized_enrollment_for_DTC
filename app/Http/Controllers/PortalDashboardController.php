<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\Appointment;
use App\Models\CourseSubject;
use App\Models\DocumentRequest;
use App\Models\Enrollment;
use App\Models\Setting;
use App\Models\UserNotification;
use Illuminate\Support\Facades\Auth;

class PortalDashboardController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        if ($user->hasRole('new_applicant')) {
            return $this->newApplicantDashboard($user);
        }

        if ($user->hasRole('alumni')) {
            return $this->alumniDashboard($user);
        }

        return $this->studentDashboard($user);
    }

    private function newApplicantDashboard($user)
    {
        $application = Application::where('user_id', $user->id)
            ->where('status', 'approved')
            ->with(['program', 'reviewer'])
            ->withCount('documents')
            ->latest()
            ->first();

        $enrollment = Enrollment::where('user_id', $user->id)
            ->latest()
            ->first();

        $unreadCount = $user->unreadNotificationsCount();

        $announcements = UserNotification::where('user_id', $user->id)
            ->latest()->take(3)->get();

        $fee = Setting::get('enrollment_fee', 500);
        $nextStep = $this->getNewApplicantNextStep($application, $enrollment, $fee);

        return view('portal.new-applicant.dashboard', compact(
            'application', 'enrollment', 'unreadCount', 'announcements', 'nextStep'
        ));
    }

    private function getNewApplicantNextStep($application, $enrollment, $fee): array
    {
        if (! $application) {
            return [
                'label' => 'No Approved Application Found',
                'desc'  => 'Please contact the Registrar\'s Office if you believe this is an error.',
                'url'   => null,
                'icon'  => 'alert-triangle',
            ];
        }

        if (! $enrollment) {
            return [
                'label' => 'Proceed to Official Enrollment',
                'desc'  => 'Your pre-enrollment application is approved. Complete your official enrollment to continue.',
                'url'   => route('portal.enrollment.create'),
                'icon'  => 'arrow-right',
            ];
        }

        if ($enrollment->status === 'pending') {
            return [
                'label' => 'Wait for Registrar Approval',
                'desc'  => 'Your official enrollment has been submitted and is under review.',
                'url'   => route('portal.enrollment.index'),
                'icon'  => 'hourglass',
            ];
        }

        if ($enrollment->status === 'approved' && ! $enrollment->is_paid) {
            return [
                'label'     => 'Proceed to Payment',
                'desc'      => 'Your official enrollment is approved. Pay the ₱' . number_format($fee, 2) . ' fee at the Cashier\'s Office.',
                'url'       => route('portal.enrollment.payment-info', $enrollment),
                'icon'      => 'banknote',
                'isPayment' => true,
            ];
        }

        if ($enrollment->is_paid) {
            return [
                'label' => 'Enrollment Completed',
                'desc'  => 'Congratulations! Your official enrollment is complete and paid.',
                'url'   => route('portal.enrollment.index'),
                'icon'  => 'check-circle',
            ];
        }

        return [
            'label' => 'View Enrollment Status',
            'desc'  => 'Check the current status of your official enrollment.',
            'url'   => route('portal.enrollment.index'),
            'icon'  => 'list',
        ];
    }

    private function alumniDashboard($user)
    {
        $requests = DocumentRequest::where('user_id', $user->id)
            ->latest()->get();

        $totalRequests = $requests->count();
        $readyRequests = $requests->where('status', 'ready')->count();
        $releasedDocs  = $requests->where('status', 'released')->count();
        $totalFees     = $requests->sum('fee');

        $recentRequests = $requests->take(5);

        $nextAppointment = Appointment::with('slot')
            ->where('user_id', $user->id)
            ->where('status', 'confirmed')
            ->whereHas('slot', fn($q) => $q->whereDate('date', '>=', today()))
            ->latest()->first();

        $unreadCount = $user->unreadNotificationsCount();

        $announcements = UserNotification::where('user_id', $user->id)
            ->latest()->take(3)->get();

        // ── New: load the student profile so the view can show Batch YYYY ──
        $studentProfile = $user->studentProfile;

        return view('portal.alumni.dashboard', compact(
            'totalRequests', 'readyRequests', 'releasedDocs', 'totalFees',
            'recentRequests', 'nextAppointment', 'unreadCount', 'announcements',
            'studentProfile'
        ));
    }

    private function studentDashboard($user)
    {
        $latestEnrollment = Enrollment::where('user_id', $user->id)
            ->latest()->first();

        $totalEnrollments = Enrollment::where('user_id', $user->id)->count();
        $approvedCount    = Enrollment::where('user_id', $user->id)
            ->where('status', 'approved')->count();
        $paidCount        = Enrollment::where('user_id', $user->id)
            ->where('is_paid', true)->count();
        $unreadCount      = $user->unreadNotificationsCount();

        $subjects = collect();
        if ($latestEnrollment) {
            $subjects = CourseSubject::whereIn('id', $latestEnrollment->subject_ids ?? [])->get();
        }

        $nextAppointment = Appointment::with('slot')
            ->where('user_id', $user->id)
            ->where('status', 'confirmed')
            ->whereHas('slot', fn($q) => $q->whereDate('date', '>=', today()))
            ->latest()->first();

        $fee = Setting::get('enrollment_fee', 500);
        $timeline = [];
        $completedSteps = 0;
        if ($latestEnrollment) {
            $timeline = [
                [
                    'label'    => 'Submit Application',
                    'sublabel' => 'Fill enrollment form',
                    'done'     => true,
                    'active'   => false,
                ],
                [
                    'label'    => 'Registrar Review',
                    'sublabel' => 'Await approval',
                    'done'     => in_array($latestEnrollment->status, ['approved', 'rejected']),
                    'active'   => $latestEnrollment->status === 'pending',
                ],
                [
                    'label'    => 'Approval',
                    'sublabel' => 'Get confirmed',
                    'done'     => $latestEnrollment->status === 'approved' || $latestEnrollment->is_paid,
                    'active'   => false,
                ],
                [
                    'label'    => 'Pay Fee',
                    'sublabel' => '₱' . number_format($fee, 2),
                    'done'     => $latestEnrollment->is_paid,
                    'active'   => $latestEnrollment->status === 'approved' && !$latestEnrollment->is_paid,
                ],
                [
                    'label'    => 'Enrolled',
                    'sublabel' => 'Official student',
                    'done'     => $latestEnrollment->is_paid,
                    'active'   => false,
                ],
            ];
            $completedSteps = collect($timeline)->where('done', true)->count();
        }

        $announcements = UserNotification::where('user_id', $user->id)
            ->latest()->take(3)->get();

        $nextSteps = $this->getNextSteps($latestEnrollment, $fee);

        // ── New: load the student profile so the view can show Enrolled AY ──
        $studentProfile = $user->studentProfile;

        return view('portal.student.dashboard', compact(
            'latestEnrollment', 'totalEnrollments', 'approvedCount',
            'paidCount', 'unreadCount', 'subjects', 'nextAppointment',
            'timeline', 'completedSteps', 'announcements', 'nextSteps', 'fee',
            'studentProfile'
        ));
    }

    private function getNextSteps($enrollment, $fee): array
    {
        if (!$enrollment) {
            return [
                ['icon' => 'file-text',      'label' => 'Submit Application',  'desc' => 'Fill out and submit your enrollment form.',        'url' => route('portal.enrollment.create'), 'active' => true],
                ['icon' => 'calendar-check', 'label' => 'Book Appointment',    'desc' => 'Schedule a pickup appointment.',                   'url' => route('portal.appointments.create'), 'active' => false],
            ];
        }

        if ($enrollment->status === 'pending') {
            return [
                ['icon' => 'hourglass',  'label' => 'Await Registrar Review', 'desc' => 'Your enrollment is being reviewed.',           'url' => route('portal.enrollment.index'), 'active' => true],
                ['icon' => 'bell',       'label' => 'Check Notifications',    'desc' => 'You will be notified once approved.',          'url' => route('notifications.index'), 'active' => false],
            ];
        }

        if ($enrollment->status === 'approved' && !$enrollment->is_paid) {
            return [
                ['icon' => 'banknote', 'label' => 'Pay Enrollment Fee', 'desc' => 'Proceed to Cashier and pay ₱' . number_format($fee, 2) . '.',              'url' => route('portal.enrollment.payment-info', $enrollment), 'active' => true, 'isPayment' => true],
                ['icon' => 'receipt',  'label' => 'Get Your Receipt',    'desc' => 'Official receipt will be issued after payment.',  'url' => route('portal.enrollment.index'), 'active' => false],
            ];
        }

        if ($enrollment->is_paid) {
            return [
                ['icon' => 'calendar-plus',  'label' => 'Book Appointment',    'desc' => 'Request documents or book a meeting.',             'url' => route('portal.appointments.create'), 'active' => true],
                ['icon' => 'check-circle',   'label' => 'You Are Enrolled!',   'desc' => 'Congratulations! Your enrollment is complete.',    'url' => route('portal.enrollment.index'), 'active' => false],
            ];
        }

        return [];
    }
}
