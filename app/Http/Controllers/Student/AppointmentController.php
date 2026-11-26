<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\AppointmentPayment;
use App\Models\AppointmentSlot;
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
    public function index()
    {
        $appointments = Appointment::with(['slot', 'latestPayment', 'documentRequest', 'documentRequests.releaser'])
            ->where('user_id', Auth::id())
            ->where('visit_type', '!=', 'claiming')
            ->latest()
            ->get();

        $slots = AppointmentSlot::where('date', '>=', today())
            ->orderBy('date')
            ->orderBy('start_time')
            ->get()
            ->filter(fn($slot) => $slot->isAvailable() && !app(AppointmentScheduleService::class)->isClosed($slot->date, $slot->start_time, $slot->end_time) && ($slot->date !== today()->toDateString() || $slot->start_time > now()->format('H:i:s')))
            ->values();

        $gcashQrPath = Setting::get('gcash_qr_path');
        $gcashQrReady = $gcashQrPath && Storage::disk('local')->exists($gcashQrPath);
        $appointmentProcessingFee = (float) Setting::get('appointment_processing_fee', 0);

        return view('student.appointments.index', compact('appointments', 'slots', 'gcashQrReady', 'appointmentProcessingFee'));
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
        abort_if($appointment->visit_type === 'claiming', 422, 'Claiming appointment changes are not available through submission editing.');

        if ($appointment->status !== 'pending') {
            return redirect()->route('portal.appointments.index')
                ->with('error', 'Only pending appointments can be edited.');
        }

        $slots = AppointmentSlot::where('date', '>=', today())
            ->orderBy('date')
            ->orderBy('start_time')
            ->get()
            ->filter(fn($slot) => $slot->id === $appointment->appointment_slot_id || ($slot->isAvailable() && !app(AppointmentScheduleService::class)->isClosed($slot->date, $slot->start_time, $slot->end_time) && ($slot->date !== today()->toDateString() || $slot->start_time > now()->format('H:i:s'))))
            ->values();

        return view('student.appointments.edit', compact('appointment', 'slots'));
    }

    public function update(Request $request, Appointment $appointment)
    {
        abort_if($appointment->user_id !== Auth::id(), 403);
        abort_if($appointment->visit_type === 'claiming', 422, 'Claiming appointment changes are not available through submission editing.');

        $validated = $request->validate([
            'appointment_slot_id' => ['required', 'exists:appointment_slots,id'],
            'document_type'       => ['required', 'in:transcript,diploma,certification,tor'],
            'purpose'             => ['required', 'string', 'max:500'],
        ]);

        $original = $appointment->getOriginal();
        $changes = [];
        if ($original['document_type'] !== $validated['document_type']) $changes[] = 'document type changed to ' . ucfirst($validated['document_type']);
        if ($original['appointment_slot_id'] != $validated['appointment_slot_id']) {
            $newSlot = AppointmentSlot::findOrFail($validated['appointment_slot_id']);
            $changes[] = 'schedule changed to ' . \Carbon\Carbon::parse($newSlot->date)->format('M d, Y') . ' at ' . \Carbon\Carbon::parse($newSlot->start_time)->format('h:i A');
        }
        if ($original['purpose'] !== $validated['purpose']) $changes[] = 'purpose updated';

        app(\App\Services\AppointmentLifecycle::class)->run($request, 'appointment.updated', $appointment,
            [(int) $validated['appointment_slot_id']], [], function () use ($validated, $appointment) {
            $appointment = Appointment::whereKey($appointment->id)->lockForUpdate()->firstOrFail();
            if ($appointment->status !== 'pending') {
                throw ValidationException::withMessages(['appointment' => 'This booking has changed. Only pending appointments can be edited.']);
            }
            $linkedRequests = DocumentRequest::where('appointment_id', $appointment->id)->orderBy('id')->lockForUpdate()->get();
            $linkedRequest = $linkedRequests->first();
            $immutableRequests = $linkedRequests->whereIn('status', ['processing', 'ready', 'released']);
            $requestType = match ($validated['document_type']) {
                'transcript' => 'tor',
                'tor' => 'true_copy',
                default => $validated['document_type'],
            };
            $typeChanged = $linkedRequest && $linkedRequest->document_type !== $requestType;
            if ($linkedRequests->count() > 1 && $appointment->document_type !== $validated['document_type']) {
                throw ValidationException::withMessages(['document_type' => 'Change document types through a new request when an appointment contains multiple documents.']);
            }
            if ($typeChanged && $immutableRequests->isNotEmpty()) {
                throw ValidationException::withMessages(['document_type' => 'A document type cannot be changed after processing has started. Submit a new request instead.']);
            }
            if ($typeChanged && ($linkedRequest->is_paid || in_array($linkedRequest->payment_status, ['pending_verification', 'verified'], true))) {
                throw ValidationException::withMessages(['document_type' => 'The document type cannot be changed after payment has been submitted. Contact the Registrar for help.']);
            }
            if ($validated['appointment_slot_id'] != $appointment->appointment_slot_id) {
                $slot = AppointmentSlot::whereKey($validated['appointment_slot_id'])->lockForUpdate()->firstOrFail();
                $active = Appointment::where('appointment_slot_id', $slot->id)->whereIn('status', ['pending', 'confirmed'])->orderBy('id')->lockForUpdate()->get();
                if (($slot->status ?? 'open') !== 'open' || $active->count() >= $slot->max_bookings || app(AppointmentScheduleService::class)->isClosed($slot->date, $slot->start_time, $slot->end_time) || $slot->date < today()->toDateString() || ($slot->date === today()->toDateString() && $slot->start_time <= now()->format('H:i:s'))) {
                    throw ValidationException::withMessages(['appointment_slot_id' => 'This slot is no longer available. Please select another.']);
                }
                if ($active->contains('user_id', Auth::id())) {
                    throw ValidationException::withMessages(['appointment_slot_id' => 'You already have a booking for this slot.']);
                }
            }

            $appointment->update($validated);
            foreach ($linkedRequests as $item) {
                if (in_array($item->status, ['processing', 'ready', 'released'], true)) {
                    continue;
                }
                $updates = ['purpose' => $validated['purpose']];
                if ($item->id === $linkedRequest?->id && $typeChanged) {
                    $updates['document_type'] = $requestType;
                    $newFee = app(\App\Services\DocumentRequestPricing::class)->quote([['document_type' => $requestType, 'copies' => $item->copies]])['total'];
                    if (((float) $item->fee === 0.0) !== ($newFee == 0)) {
                        throw ValidationException::withMessages(['document_type' => 'Submit a new request when changing between a free and a payable document.']);
                    }
                    $updates['fee'] = $newFee;
                }
                $item->update($updates);
            }
            if ($typeChanged && $linkedRequests->isNotEmpty()) {
                $bundlePayment = AppointmentPayment::where('appointment_id', $appointment->id)->where('is_document_bundle', true)->first();
                if ($bundlePayment && $bundlePayment->status !== 'verified') {
                    $bundlePayment->update(['amount' => $linkedRequests->sum(fn ($item) => (float) $item->fresh()->fee)]);
                }
            }
        }, $validated);

        if ($request->attributes->get('lifecycle_replayed')) return redirect()->route('portal.appointments.index')->with('success', 'Appointment updated.');

        // Notify registrars of the update
        $slot = AppointmentSlot::find($appointment->appointment_slot_id);
        $summary = count($changes)
            ? implode('; ', $changes)
            : 'details updated';

        $notifyUsers = User::role(['registrar', 'admin'])->get();
        foreach ($notifyUsers as $staff) {
            NotificationService::send(
                $staff,
                'Appointment Updated',
                Auth::user()->name . ' has updated their appointment booking (' . $summary . ').',
                'warning',
                route('registrar.appointments.index')
            );
        }

        return redirect()->route('portal.appointments.index')
            ->with('success', 'Appointment updated. The Registrar has been notified.');
    }

    public function cancel(Appointment $appointment)
    {
        abort_if($appointment->user_id !== Auth::id(), 403);
        $result = app(\App\Services\AppointmentCancellationService::class)
            ->cancel($appointment, Auth::user(), request());

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
