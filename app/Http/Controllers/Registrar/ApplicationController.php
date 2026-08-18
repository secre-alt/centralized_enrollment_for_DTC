<?php

namespace App\Http\Controllers\Registrar;

use App\Http\Controllers\Controller;
use App\Mail\ApplicationApproved;
use App\Mail\ApplicationRejected;
use App\Mail\ApplicationRevisionRequired;
use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ApplicationController extends Controller
{
    public function index(Request $request)
    {
        $applications = Application::with(['program', 'user', 'reviewer', 'documents'])
            ->when($request->filled('status'), function ($query) use ($request) {
                $query->where('status', $request->query('status'));
            })
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('registrar.applications.index', compact('applications'));
    }

    public function show(Application $application)
    {
        $application->load(['program', 'user', 'reviewer', 'documents']);

        return view('registrar.applications.show', compact('application'));
    }

    public function approve(Application $application)
    {
        // ── Prevent duplicate approval ──────────────────────────────────
        if ($application->user_id !== null || $application->status === 'approved') {
            return redirect()->route('registrar.applications.index')
                ->with('error', 'This application has already been approved.');
        }

        // ── Prevent duplicate User by email ─────────────────────────────
        if (User::where('email', $application->email)->exists()) {
            return redirect()->route('registrar.applications.index')
                ->with('error', 'An account with this email address already exists. This application cannot be auto-approved and needs manual review.');
        }

        $setupUrl = null;

        try {
            DB::transaction(function () use ($application, &$setupUrl) {

                $name = trim(
                    $application->first_name . ' ' .
                    ($application->middle_name ? $application->middle_name . ' ' : '') .
                    $application->last_name
                );

                $user = User::create([
                    'name'     => $name,
                    'email'    => $application->email,
                    'status'   => 'active',
                    // Unusable random placeholder — the applicant will set their
                    // own password via the Step 3A password-setup/activation flow.
                    'password' => Hash::make(Str::random(40)),
                ]);

                $user->assignRole('new_applicant');

                $application->update([
                    'status'      => 'approved',
                    'reviewed_by' => auth()->id(),
                    'reviewed_at' => now(),
                    'user_id'     => $user->id,
                ]);

                $token = Password::broker('users')->createToken($user);

                $setupUrl = route('password.setup.show', [
                    'token' => $token,
                    'email' => $application->email,
                ]);
            });
        } catch (\Throwable $e) {
            return redirect()->route('registrar.applications.index')
                ->with('error', 'Approval failed and no changes were saved. Please try again.');
        }

        // ── Send approval email only after the transaction has committed ──
        Mail::to($application->email)->send(
            new ApplicationApproved(
                $application,
                $setupUrl,
                config('auth.passwords.users.expire', 60)
            )
        );

        // ── Notify Admin ─────────────────────────────────────────────────
        $admins = User::role('admin')->get();
        foreach ($admins as $admin) {
            NotificationService::send(
                $admin,
                'Application Approved',
                'Registrar approved the pre-enrollment application for ' . $application->first_name . ' ' . $application->last_name . '.',
                'success',
                route('registrar.applications.show', $application)
            );
        }

        return redirect()->route('registrar.applications.index')
            ->with('success', 'Application approved. The applicant has been emailed instructions to activate their account.');
    }

    public function reject(Request $request, Application $application)
    {
        $validated = $request->validate([
            'remarks' => ['required', 'string', 'max:500'],
        ]);

        $application->update([
            'status'      => 'rejected',
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
            'remarks'     => $validated['remarks'],
        ]);

        Mail::to($application->email)->send(
            new ApplicationRejected($application)
        );

        // ── Notify Admin ─────────────────────────────────────────────────
        $admins = User::role('admin')->get();
        foreach ($admins as $admin) {
            NotificationService::send(
                $admin,
                'Application Rejected',
                'Registrar rejected the pre-enrollment application for ' . $application->first_name . ' ' . $application->last_name . '. Reason: ' . $validated['remarks'],
                'warning',
                route('registrar.applications.show', $application)
            );
        }

        return redirect()->route('registrar.applications.index')
            ->with('success', 'Application rejected. The applicant has been notified by email.');
    }

    public function revision(Request $request, Application $application)
    {
        $validated = $request->validate([
            'remarks' => ['required', 'string', 'max:500'],
        ]);

        $application->update([
            'status'      => 'revision_required',
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
            'remarks'     => $validated['remarks'],
        ]);

        Mail::to($application->email)->send(
            new ApplicationRevisionRequired($application)
        );

        // ── Notify Admin ─────────────────────────────────────────────────
        $admins = User::role('admin')->get();
        foreach ($admins as $admin) {
            NotificationService::send(
                $admin,
                'Application Requires Revision',
                'Registrar requested revision on the pre-enrollment application for ' . $application->first_name . ' ' . $application->last_name . '.',
                'info',
                route('registrar.applications.show', $application)
            );
        }

        return redirect()->route('registrar.applications.index')
            ->with('success', 'Revision requested. The applicant has been notified by email.');
    }

    public function downloadDocument(Application $application, ApplicationDocument $document)
    {
        if ($document->application_id !== $application->id) {
            abort(404);
        }

        if (! Storage::disk('local')->exists($document->file_path)) {
            abort(404, 'The requested document could not be found.');
        }

        return Storage::disk('local')->download(
            $document->file_path,
            $document->original_name
        );
    }
}