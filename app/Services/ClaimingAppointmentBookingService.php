<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\AppointmentSlot;
use App\Models\AuditLog;
use App\Models\DocumentRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ClaimingAppointmentBookingService
{
    public function book(User $user, int $slotId, array $documentIds, Request $request): Appointment
    {
        abort_unless($user->hasAnyRole(['student', 'alumni']) && $request->user()?->id === $user->id, 403);
        if (! $documentIds || count(array_unique($documentIds)) !== count($documentIds)) {
            throw ValidationException::withMessages(['document_ids' => 'Select each document once.']);
        }
        sort($documentIds, SORT_NUMERIC);

        return app(AppointmentLifecycle::class)->run($request, 'claim.booked', null, [$slotId], $documentIds, function () use ($user, $slotId, $documentIds, $request) {
            $slot = AppointmentSlot::whereKey($slotId)->lockForUpdate()->firstOrFail();
            // A locking read sees committed occupancy even with an earlier repeatable-read snapshot.
            $active = Appointment::where('appointment_slot_id', $slot->id)->whereIn('status', ['pending', 'confirmed'])
                ->orderBy('id')->lockForUpdate()->get();
            if (($slot->status ?? 'open') !== 'open' || $active->count() >= $slot->max_bookings
                || $slot->date < today()->toDateString()
                || ($slot->date === today()->toDateString() && $slot->start_time <= now()->format('H:i:s'))
                || app(AppointmentScheduleService::class)->isClosed($slot->date, $slot->start_time, $slot->end_time)) {
                throw ValidationException::withMessages(['appointment_slot_id' => 'This claiming time is no longer available. Choose another time.']);
            }
            if ($active->contains('user_id', $user->id)) {
                throw ValidationException::withMessages(['appointment_slot_id' => 'You already have an active appointment at this time.']);
            }

            $appointment = Appointment::create(['user_id' => $user->id, 'appointment_slot_id' => $slot->id,
                'visit_type' => 'claiming', 'document_type' => 'claiming', 'status' => 'pending', 'purpose' => 'Document claiming']);
            $documents = DocumentRequest::whereIn('id', $documentIds)->orderBy('id')->lockForUpdate()->get();
            if ($documents->count() !== count($documentIds)) {
                throw ValidationException::withMessages(['document_ids' => 'One or more selected documents are unavailable.']);
            }
            foreach ($documents as $document) {
                if ($document->user_id !== $user->id || $document->status !== 'ready'
                    || ! $document->paymentSatisfied() || $document->pending_claim_appointment_id !== null) {
                    throw ValidationException::withMessages(['document_ids' => 'Each selected document must be yours, ready, payment satisfied, and not already reserved.']);
                }
                $reserved = DocumentRequest::whereKey($document->id)->where('user_id', $user->id)
                    ->where('status', 'ready')->whereNull('pending_claim_appointment_id')
                    ->update(['pending_claim_appointment_id' => $appointment->id]);
                if ($reserved !== 1) {
                    throw ValidationException::withMessages(['document_ids' => 'A selected document was reserved by another booking. Please refresh.']);
                }
            }
            foreach ($documents as $document) {
                $appointment->documentItems()->create(['document_request_id' => $document->id]);
            }
            AuditLog::create(['user_id' => $user->id, 'action' => 'appointment.claiming_booked',
                'description' => "Booked claiming appointment #{$appointment->id}.",
                'ip_address' => $request->ip(), 'user_agent' => $request->userAgent(),
                'metadata' => ['appointment_id' => $appointment->id, 'document_request_ids' => $documents->modelKeys()]]);

            return $appointment;
        }, ['slot_id' => $slotId, 'document_ids' => $documentIds]);
    }
}
