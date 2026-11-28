<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\AppointmentPayment;
use App\Models\AppointmentSlot;
use App\Models\AppointmentService;
use App\Models\AuditLog;
use App\Models\DocumentRequest;
use App\Models\DocumentPaymentProof;
use App\Models\Setting;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use App\Services\AppointmentScheduleService;
use App\Support\PrivateFilePreview;

class AppointmentController extends Controller
{
    public function advising()
    {
        abort_unless(Auth::user()->hasAnyRole(['student', 'alumni', 'new_applicant']), 403);
        $appointments = Appointment::with(['slot', 'service'])->where('user_id', Auth::id())->whereNotNull('appointment_service_id')->latest()->get();
        $services = AppointmentService::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get()
            ->filter(fn ($service) => collect($service->eligible_roles)->intersect(Auth::user()->getRoleNames())->isNotEmpty())->values();
        $slots = AppointmentSlot::where('date', '>=', today())->orderBy('date')->orderBy('start_time')->get()
            ->filter(fn ($slot) => $slot->isAvailable() && ! app(AppointmentScheduleService::class)->isClosed($slot->date, $slot->start_time, $slot->end_time)
                && ($slot->date !== today()->toDateString() || $slot->start_time > now()->format('H:i:s')))->values();
        return view('student.appointments.advising', compact('appointments', 'services', 'slots'));
    }

    public function bookAdvising(Request $request)
    {
        abort_unless($request->user()->hasAnyRole(['student', 'alumni', 'new_applicant']), 403);
        $data = $request->validate([
            'appointment_service_id' => ['required', 'integer', 'exists:appointment_services,id'],
            'appointment_slot_id' => ['required', 'integer', 'exists:appointment_slots,id'],
            'purpose' => ['required', 'string', 'max:500'],
        ]);
        $service = AppointmentService::whereKey($data['appointment_service_id'])->where('is_active', true)->firstOrFail();
        abort_unless(collect($service->eligible_roles)->intersect($request->user()->getRoleNames())->isNotEmpty(), 403);
        $appointment = app(\App\Services\AppointmentLifecycle::class)->run($request, 'advising.booked', null,
            [(int) $data['appointment_slot_id']], [], function () use ($request, $data, $service) {
                $service = AppointmentService::whereKey($service->id)->lockForUpdate()->firstOrFail();
                if (! $service->is_active || collect($service->eligible_roles)->intersect($request->user()->getRoleNames())->isEmpty()) {
                    throw ValidationException::withMessages(['appointment_service_id' => 'This appointment service is no longer available to your account.']);
                }
                $slot = AppointmentSlot::whereKey($data['appointment_slot_id'])->lockForUpdate()->firstOrFail();
                $active = Appointment::where('appointment_slot_id', $slot->id)->whereIn('status', ['pending', 'confirmed'])->orderBy('id')->lockForUpdate()->get();
                if (($slot->status ?? 'open') !== 'open' || $active->count() >= $slot->max_bookings || $active->contains('user_id', $request->user()->id)
                    || app(AppointmentScheduleService::class)->isClosed($slot->date, $slot->start_time, $slot->end_time)
                    || $slot->date < today()->toDateString()
                    || ($slot->date === today()->toDateString() && $slot->start_time <= now()->format('H:i:s'))) {
                    throw ValidationException::withMessages(['appointment_slot_id' => 'This time is no longer available. Choose another open time.']);
                }
                return Appointment::create(['user_id' => $request->user()->id, 'appointment_service_id' => $service->id,
                    'appointment_slot_id' => $slot->id, 'document_type' => 'consultation', 'visit_type' => 'consultation',
                    'purpose' => $data['purpose'], 'status' => $service->requires_approval ? 'pending' : 'confirmed',
                    'confirmed_at' => $service->requires_approval ? null : now()]);
            }, $data);
        if (! $request->attributes->get('lifecycle_replayed') && $appointment->status === 'pending') {
            foreach (User::role(['registrar', 'admin'])->get() as $staff) {
                NotificationService::sendSafely($staff, 'Appointment request', $request->user()->name.' requested '.$service->name.'.', 'info', route('registrar.appointments.index'));
            }
        } elseif (! $request->attributes->get('lifecycle_replayed')) {
            NotificationService::sendUserUpdateSafely($request->user(), 'appointment-confirmed:'.$appointment->id,
                'Appointment confirmed', 'Your '.$service->name.' is confirmed. Reference: '.$appointment->reference_number.'.',
                'success', route('portal.advising.index'), 'View appointment');
        }
        return redirect()->route('portal.advising.index')->with('success', $appointment->status === 'pending'
            ? 'Appointment request submitted. It will appear here after Registrar confirmation.'
            : 'Appointment confirmed. Your reference is '.$appointment->reference_number.'.');
    }

    public function index()
    {
        $appointments = Appointment::with(['slot', 'documentRequests'])
            ->where('user_id', Auth::id())
            ->whereNull('appointment_service_id')
            ->whereNotIn('visit_type', ['claiming', 'advising'])
            ->latest()
            ->get();

        return view('student.appointments.index', compact('appointments'));
    }

    public function create()
    {
        return app(\App\Http\Controllers\Alumni\DocumentRequestController::class)->create();
    }

    public function store(Request $request)
    {
        return app(\App\Http\Controllers\Alumni\DocumentRequestController::class)->storeLegacy($request);
    }

    public function edit(Appointment $appointment)
    {
        abort_if($appointment->user_id !== Auth::id(), 403);
        abort_unless($appointment->appointment_service_id, 410, 'Historical document appointments are read-only. Manage the request in Document Requests.');
        abort_unless(in_array($appointment->status, ['pending', 'confirmed'], true), 422, 'This appointment can no longer be rescheduled.');
        $slots = AppointmentSlot::where('date', '>=', today())->orderBy('date')->orderBy('start_time')->get()
            ->filter(fn ($slot) => $slot->id === $appointment->appointment_slot_id || ($slot->isAvailable()
                && ! app(AppointmentScheduleService::class)->isClosed($slot->date, $slot->start_time, $slot->end_time)
                && ($slot->date !== today()->toDateString() || $slot->start_time > now()->format('H:i:s'))))->values();

        return view('student.appointments.reschedule', compact('appointment', 'slots'));
    }

    public function update(Request $request, Appointment $appointment)
    {
        abort_if($appointment->user_id !== Auth::id(), 403);
        abort_unless($appointment->appointment_service_id, 410, 'Historical document appointments are read-only. Manage the request in Document Requests.');
        $validated = $request->validate([
            'appointment_slot_id' => ['required', 'integer', 'exists:appointment_slots,id'],
            'purpose' => ['required', 'string', 'max:500'],
        ]);
        $oldSlotId = (int) $appointment->appointment_slot_id;
        $newSlotId = (int) $validated['appointment_slot_id'];
        $appointment = app(\App\Services\AppointmentLifecycle::class)->run($request, 'appointment.updated', $appointment,
            [$newSlotId], [], function () use ($appointment, $validated, $newSlotId) {
                $locked = Appointment::whereKey($appointment->id)->lockForUpdate()->firstOrFail();
                if (! in_array($locked->status, ['pending', 'confirmed'], true) || ! $locked->appointment_service_id) {
                    throw ValidationException::withMessages(['appointment' => 'This appointment can no longer be rescheduled.']);
                }
                if ($newSlotId !== (int) $locked->appointment_slot_id) {
                    $slot = AppointmentSlot::whereKey($newSlotId)->lockForUpdate()->firstOrFail();
                    $active = Appointment::where('appointment_slot_id', $slot->id)->whereIn('status', ['pending', 'confirmed'])->orderBy('id')->lockForUpdate()->get();
                    if (($slot->status ?? 'open') !== 'open' || $active->count() >= $slot->max_bookings
                        || $active->contains('user_id', $locked->user_id)
                        || app(AppointmentScheduleService::class)->isClosed($slot->date, $slot->start_time, $slot->end_time)
                        || $slot->date < today()->toDateString()
                        || ($slot->date === today()->toDateString() && $slot->start_time <= now()->format('H:i:s'))) {
                        throw ValidationException::withMessages(['appointment_slot_id' => 'This time is no longer available. Choose another open time.']);
                    }
                }
                $service = $locked->service;
                $updates = $validated;
                if ($newSlotId !== (int) $locked->appointment_slot_id) {
                    $updates['status'] = $service->requires_approval ? 'pending' : 'confirmed';
                    $updates['confirmed_by'] = null;
                    $updates['confirmed_at'] = $service->requires_approval ? null : now();
                }
                $locked->update($updates);
                return $locked;
            }, $validated);
        if (! $request->attributes->get('lifecycle_replayed')) {
            if ($appointment->status === 'pending') {
                foreach (User::role(['registrar', 'admin'])->get() as $staff) {
                    NotificationService::sendSafely($staff, 'Appointment rescheduled', Auth::user()->name.' rescheduled appointment '.$appointment->reference_number.'.', 'info', route('registrar.appointments.index'));
                }
            }
            NotificationService::sendUserUpdateSafely(Auth::user(), 'appointment-rescheduled:'.$appointment->id.':'.$appointment->lifecycle_version,
                'Appointment rescheduled', 'Your appointment is now scheduled for '.\Carbon\Carbon::parse($appointment->slot->date)->format('M d, Y').' at '.\Carbon\Carbon::parse($appointment->slot->start_time)->format('g:i A').'.'.($appointment->status === 'pending' ? ' Registrar confirmation is required.' : ''),
                'info', route('portal.advising.index'), 'View appointment');
        }

        return redirect()->route('portal.advising.index')->with('success', 'Appointment rescheduled. Your reference remains '.$appointment->reference_number.'.');
    }

    public function cancel(Appointment $appointment)
    {
        abort_if($appointment->user_id !== Auth::id(), 403);
        abort_unless($appointment->appointment_service_id, 410, 'Historical document appointments are read-only. Manage document requests in Document Requests.');
        $result = app(\App\Services\AppointmentCancellationService::class)
            ->cancel($appointment, Auth::user(), request());

        if ($result['result'] === 'cancelled') {
            return redirect()->route('portal.advising.index')->with('success', 'Appointment cancelled.');
        }

        return match ($result['result']) {
            'already_cancelled' => back()->with('error', 'Appointment is already cancelled.'),
            'invalid_status' => back()->withErrors(['error' => 'Only pending appointments can be cancelled here. Contact the Registrar to change a confirmed booking.']),
            'verified_legacy_payment' => back()->withErrors(['error' => 'This appointment has a verified payment. Contact the Registrar to resolve it before cancellation.']),
            'resolved_request' => back()->withErrors(['error' => 'This booking can no longer be cancelled because one of the linked documents is already in progress or has been released.']),
            default => redirect()->route('portal.appointments.index')->with('success', $result['user_message']),
        };
    }

    public function submitGcash(Request $request, Appointment $appointment)
    {
        abort_if($appointment->user_id !== Auth::id(), 403);
        abort_if($appointment->appointment_service_id, 422, 'Consultation appointments do not require payment.');
        abort_if($appointment->visit_type === 'claiming', 422, 'Claiming appointments do not create another payment.');
        if ($appointment->documentRequests()->exists()) {
            return redirect()->route('portal.payments.appointment', $appointment)->with('error', 'Use the document payment page for this booking.');
        }

        if ($appointment->status === 'cancelled') {
            return back()->with('error', 'Cannot submit payment for a cancelled appointment.');
        }

        $validated = $request->validate([
            'reference_number' => ['required', 'string', 'max:50'],
            'proof_of_payment' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);
        $fee = (float) Setting::get('appointment_processing_fee', 0);
        if ($fee <= 0) {
            return back()->with('error', 'No payment is required for this appointment.');
        }

        $result = DB::transaction(function () use ($appointment, $request, $validated, $fee) {
            $lockedAppointment = Appointment::whereKey($appointment->id)->lockForUpdate()->firstOrFail();
            if ($lockedAppointment->status === 'cancelled') {
                return 'cancelled';
            }

            $payments = AppointmentPayment::where('appointment_id', $lockedAppointment->id)
                ->lockForUpdate()
                ->get();

            if ($payments->contains('status', 'pending')) {
                return 'pending';
            }
            if ($payments->contains('status', 'verified')) {
                return 'verified';
            }

            $path = $request->file('proof_of_payment')->store('appointment-proofs', 'local');
            $payment = AppointmentPayment::create([
                'appointment_id'   => $lockedAppointment->id,
                'payment_method'   => 'gcash',
                'reference_number' => $validated['reference_number'],
                'proof_of_payment' => $path,
                'amount'           => $fee,
                'status'           => 'pending',
            ]);

            return $payment;
        });

        if (is_string($result)) {
            $message = [
                'cancelled' => 'Cannot submit payment for a cancelled appointment.',
                'pending' => 'You already have a payment pending verification.',
                'verified' => 'This appointment already has a verified payment.',
            ][$result];

            return back()->with('error', $message);
        }

        NotificationService::sendUserUpdateSafely(
            Auth::user(),
            'appointment-payment-proof-submitted:'.$result->id,
            'GCash Proof Received',
            'We received your appointment payment proof. It is awaiting verification.',
            'info',
            route('portal.payments.appointment', $appointment),
            'View payment status'
        );

        return redirect()->route('portal.payments.appointment', $appointment)->with('success', 'GCash proof submitted.');
    }

    public function submitBundleGcash(Request $request, Appointment $appointment)
    {
        abort_unless($appointment->user_id === Auth::id(), 403);
        abort_if($appointment->appointment_service_id, 422, 'Consultation appointments do not require payment.');
        $validated = $request->validate([
            'reference_number' => ['required', 'string', 'max:100'],
            'proof_of_payment' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'mimetypes:image/jpeg,image/png,application/pdf', 'max:5120'],
        ]);
        $qrPath = Setting::get('gcash_qr_path');
        if (! $qrPath || ! Storage::disk('local')->exists($qrPath)) {
            throw ValidationException::withMessages(['payment' => 'GCash payment is unavailable. Please pay at the Cashier.']);
        }
        $proofPath = $request->file('proof_of_payment')->store('document-request-proofs', 'local');
        if (! $proofPath || ! Storage::disk('local')->exists($proofPath)) {
            if ($proofPath) {
                Storage::disk('local')->delete($proofPath);
            }
            throw ValidationException::withMessages([
                'proof_of_payment' => 'The proof could not be saved. Please try uploading it again.',
            ]);
        }

        try {
            DB::transaction(function () use ($appointment, $validated, $proofPath, $request) {
                $lockedAppointment = Appointment::whereKey($appointment->id)->lockForUpdate()->firstOrFail();
                if ($lockedAppointment->status === 'cancelled') {
                    throw ValidationException::withMessages(['payment' => 'Cannot submit payment for a cancelled appointment.']);
                }
                $payment = AppointmentPayment::where('appointment_id', $lockedAppointment->id)
                    ->where('is_document_bundle', true)
                    ->lockForUpdate()
                    ->latest()
                    ->firstOrFail();
                $firstSubmission = $payment->status === 'pending'
                    && $payment->payment_method === 'gcash'
                    && ! $payment->proof_of_payment
                    && ! $payment->reference_number;
                if ((! $firstSubmission && ($payment->status !== 'rejected' || $payment->payment_method !== 'gcash'))
                    || $lockedAppointment->paymentDocuments()->where(function ($query) {
                        $query->where('is_paid', true)->orWhere('payment_status', 'verified');
                    })->exists()) {
                    throw ValidationException::withMessages(['payment' => 'This bundled payment is not available for GCash submission.']);
                }
                if ($lockedAppointment->paymentDocuments()->where('fee', '>', 0)->whereIn('status', ['cancelled', 'released'])->exists()) {
                    throw ValidationException::withMessages(['payment' => 'This bundled payment is no longer available.']);
                }
                if ($payment->status === 'rejected' && $payment->proof_of_payment
                    && ! DocumentPaymentProof::where('appointment_payment_id', $payment->id)->where('path', $payment->proof_of_payment)->exists()) {
                    DocumentPaymentProof::create([
                        'appointment_payment_id' => $payment->id,
                        'path' => $payment->proof_of_payment,
                        'reference_number' => $payment->reference_number,
                        'submitted_at' => $payment->updated_at ?? now(),
                        'status' => 'rejected',
                        'reviewed_by' => $payment->verified_by,
                        'reviewed_at' => $payment->verified_at,
                        'rejection_reason' => $payment->rejection_reason,
                    ]);
                }
                $payment->update([
                    'status' => 'pending',
                    'reference_number' => $validated['reference_number'],
                    'proof_of_payment' => $proofPath,
                    'rejection_reason' => null,
                    'verified_by' => null,
                    'verified_at' => null,
                ]);
                DocumentPaymentProof::create([
                    'appointment_payment_id' => $payment->id,
                    'path' => $proofPath,
                    'reference_number' => $validated['reference_number'],
                    'submitted_by' => Auth::id(),
                    'submitted_at' => now(),
                ]);
                $lockedAppointment->paymentDocuments()->where('fee', '>', 0)->update([
                    'payment_method' => 'gcash_qr',
                    'payment_status' => 'pending_verification',
                    'reference_number' => null,
                    'proof_of_payment' => null,
                    'payment_submitted_at' => now(),
                    'payment_rejection_reason' => null,
                    'rejected_by' => null,
                    'rejected_at' => null,
                ]);
                AuditLog::create([
                    'user_id' => Auth::id(),
                    'action' => 'document_payment.bundle_submitted',
                    'description' => "Submitted GCash proof for appointment #{$lockedAppointment->id}.",
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'metadata' => [
                        'appointment_id' => $lockedAppointment->id,
                        'appointment_payment_id' => $payment->id,
                        'reference_number' => $validated['reference_number'],
                        'amount_due' => $payment->amount,
                    ],
                ]);
            });
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($proofPath);
            throw $exception;
        }

        $payment = AppointmentPayment::where('appointment_id', $appointment->id)->where('is_document_bundle', true)->latest()->firstOrFail();
        foreach (User::role(['cashier', 'admin'])->get() as $cashier) {
            NotificationService::sendSafely(
                $cashier,
                'Document Payment Needs Verification',
                Auth::user()->name . ' submitted GCash proof for appointment #' . $appointment->id . ' (PHP ' . number_format((float) $payment->amount, 2) . ').',
                'warning',
                route('cashier.document-payments.index', ['status' => 'pending_verification'])
            );
        }

        return redirect()->route('portal.payments.appointment', $appointment)
            ->with('success', 'GCash proof submitted.');
    }

    public function viewProof(AppointmentPayment $payment)
    {
        abort_if($payment->appointment->user_id !== Auth::id(), 403);

        if (! $payment->proof_of_payment || ! Storage::disk('local')->exists($payment->proof_of_payment)) {
            abort(404, 'Proof not found.');
        }

        return PrivateFilePreview::respond(
            request(),
            Storage::disk('local')->path($payment->proof_of_payment),
            'Payment proof',
            route('portal.payments.appointment', $payment->appointment),
            basename($payment->proof_of_payment)
        );
    }

    public function submitDocumentGcash(Request $request, DocumentRequest $documentRequest)
    {
        return app(\App\Http\Controllers\Alumni\DocumentRequestController::class)->submitStudentPayment($request, $documentRequest);
    }

    public function viewDocumentProof(Request $request, DocumentRequest $documentRequest)
    {
        return app(\App\Http\Controllers\Alumni\DocumentRequestController::class)->viewProof($request, $documentRequest);
    }

    public function showGcashQr()
    {
        return app(\App\Http\Controllers\Alumni\DocumentRequestController::class)->showGcashQr();
    }
}
