<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\Appointment;
use App\Models\CourseSubject;
use App\Models\DocumentRequest;
use App\Models\Enrollment;
use App\Models\UserNotification;
use Illuminate\Support\Facades\Auth;

class PortalDashboardController extends Controller
{
    /**
     * Shared entry point for all three portal roles (student, alumni,
     * new_applicant). Route name/URI stay the same for every role — only
     * the data source and view differ, chosen here based on the
     * authenticated user's role.
     */
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

    /**
     * new_applicant: driven by the approved pre-enrollment Application,
     * not by Enrollment. Distinguishes "Pre-Enrollment Application" status
     * from "Official Enrollment" status rather than conflating the two.
     */
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

        // Determine the single "next step" message/action for this applicant.
        $nextStep = $this->getNewApplicantNextStep($application, $enrollment);

        return view('portal.new-applicant.dashboard', compact(
            'application', 'enrollment', 'unreadCount', 'announcements', 'nextStep'
        ));
    }

    private function getNewApplicantNextStep($application, $enrollment): array
    {
        if (! $application) {
            return [
                'label' => 'No Approved Application Found',
                'desc'  => 'Please contact the Registrar\'s Office if you believe this is an error.',
                'url'   => null,
                'icon'  => 'fa-exclamation-triangle',
            ];
        }

        if (! $enrollment) {
            return [
                'label' => 'Proceed to Official Enrollment',
                'desc'  => 'Your pre-enrollment application is approved. Complete your official enrollment to continue.',
                'url'   => route('portal.enrollment.create'),
                'icon'  => 'fa-arrow-right',
            ];
        }

        if ($enrollment->status === 'pending') {
            return [
                'label' => 'Wait for Registrar Approval',
                'desc'  => 'Your official enrollment has been submitted and is under review.',
                'url'   => route('portal.enrollment.index'),
                'icon'  => 'fa-hourglass-half',
            ];
        }

        if ($enrollment->status === 'approved' && ! $enrollment->is_paid) {
            return [
                'label' => 'Proceed to Payment',
                'desc'  => 'Your official enrollment is approved. Pay the ₱500.00 fee at the Cashier\'s Office.',
                'url'   => route('portal.enrollment.payment-info', $enrollment),
                'icon'  => 'fa-money-bill-wave',
            ];
        }

        if ($enrollment->is_paid) {
            return [
                'label' => 'Enrollment Completed',
                'desc'  => 'Congratulations! Your official enrollment is complete and paid.',
                'url'   => route('portal.enrollment.index'),
                'icon'  => 'fa-check-circle',
            ];
        }

        return [
            'label' => 'View Enrollment Status',
            'desc'  => 'Check the current status of your official enrollment.',
            'url'   => route('portal.enrollment.index'),
            'icon'  => 'fa-list',
        ];
    }

    /**
     * alumni: driven by DocumentRequest, reusing the same aggregation
     * already used in Alumni\DocumentRequestController@index rather than
     * duplicating that logic differently here.
     */
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

        return view('portal.alumni.dashboard', compact(
            'totalRequests', 'readyRequests', 'releasedDocs', 'totalFees',
            'recentRequests', 'nextAppointment', 'unreadCount', 'announcements'
        ));
    }

    /**
     * student: unchanged from the original shared controller — same
     * queries, same variables, only relocated into its own method and
     * pointed at the new view path.
     */
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

        // Subjects
        $subjects = collect();
        if ($latestEnrollment) {
            $subjects = CourseSubject::whereIn('id', $latestEnrollment->subject_ids ?? [])->get();
        }

        // Next appointment
        $nextAppointment = Appointment::with('slot')
            ->where('user_id', $user->id)
            ->where('status', 'confirmed')
            ->whereHas('slot', fn($q) => $q->whereDate('date', '>=', today()))
            ->latest()->first();

        // Enrollment timeline steps
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
                    'sublabel' => '₱500.00',
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

        // Recent notifications (as announcements)
        $announcements = UserNotification::where('user_id', $user->id)
            ->latest()->take(3)->get();

        // Next steps based on status
        $nextSteps = $this->getNextSteps($latestEnrollment);

        return view('portal.student.dashboard', compact(
            'latestEnrollment', 'totalEnrollments', 'approvedCount',
            'paidCount', 'unreadCount', 'subjects', 'nextAppointment',
            'timeline', 'completedSteps', 'announcements', 'nextSteps'
        ));
    }

    private function getNextSteps($enrollment): array
    {
        if (!$enrollment) {
            return [
                ['icon' => 'fa-file-alt',      'label' => 'Submit Application',  'desc' => 'Fill out and submit your enrollment form.',        'url' => route('portal.enrollment.create'), 'active' => true],
                ['icon' => 'fa-calendar-check', 'label' => 'Book Appointment',    'desc' => 'Schedule a pickup appointment.',                   'url' => route('portal.appointments.create'), 'active' => false],
            ];
        }

        if ($enrollment->status === 'pending') {
            return [
                ['icon' => 'fa-hourglass-half', 'label' => 'Await Registrar Review', 'desc' => 'Your enrollment is being reviewed.',           'url' => route('portal.enrollment.index'), 'active' => true],
                ['icon' => 'fa-bell',            'label' => 'Check Notifications',    'desc' => 'You will be notified once approved.',          'url' => route('notifications.index'), 'active' => false],
            ];
        }

        if ($enrollment->status === 'approved' && !$enrollment->is_paid) {
            return [
                ['icon' => 'fa-money-bill-wave', 'label' => 'Pay Enrollment Fee', 'desc' => 'Proceed to Cashier and pay ₱500.00.',              'url' => route('portal.enrollment.payment-info', $enrollment), 'active' => true],
                ['icon' => 'fa-receipt',         'label' => 'Get Your Receipt',    'desc' => 'Official receipt will be issued after payment.',  'url' => route('portal.enrollment.index'), 'active' => false],
            ];
        }

        if ($enrollment->is_paid) {
            return [
                ['icon' => 'fa-calendar-plus',  'label' => 'Book Appointment',    'desc' => 'Request documents or book a meeting.',             'url' => route('portal.appointments.create'), 'active' => true],
                ['icon' => 'fa-check-circle',   'label' => 'You Are Enrolled!',   'desc' => 'Congratulations! Your enrollment is complete.',    'url' => route('portal.enrollment.index'), 'active' => false],
            ];
        }

        return [];
    }
}