<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\AppointmentDocumentItem;
use App\Models\AppointmentLifecycleEvent;
use App\Models\AppointmentSlot;
use App\Models\AuditLog;
use App\Models\DocumentRequest;
use App\Models\DocumentWorkflowEvent;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Ramsey\Uuid\Uuid;

class WalkInDocumentReleaseService
{
    public function release(Request $request, int $claimantId, array $documentIds, ?int $expectedClaim = null): bool
    {
        abort_unless($request->user()?->hasAnyRole(['registrar', 'admin']), 403);
        $request->validate(['operation_key' => ['required', 'uuid'], 'handover_confirmed' => ['required', 'accepted']]
            + ($expectedClaim !== null ? ['lifecycle_version' => ['required', 'integer', 'min:0']] : []));
        $documentIds = array_map('intval', $documentIds);
        if (! $documentIds || count(array_unique($documentIds)) !== count($documentIds)) {
            $this->invalid();
        }
        sort($documentIds, SORT_NUMERIC);
        $fingerprint = hash('sha256', app(\App\Support\WorkflowCommandFingerprint::class)->fingerprint($request, 'documents.walk_in_released').json_encode([$claimantId, $documentIds, $expectedClaim]));
        if ($event = $this->replay($request, $fingerprint)) {
            return $this->dispatch($event);
        }

        // Discover before acquiring locks; changed relationships require a fresh command.
        $candidates = DocumentRequest::whereIn('id', $documentIds)->get(['id', 'pending_claim_appointment_id']);
        $appointmentIds = AppointmentDocumentItem::whereIn('document_request_id', $documentIds)->pluck('appointment_id')
            ->merge($candidates->pluck('pending_claim_appointment_id'))->filter()->unique()->all();
        if ($expectedClaim !== null) {
            $appointmentIds[] = $expectedClaim;
        }
        $appointments = Appointment::whereIn('id', $appointmentIds)->get();
        $allIds = AppointmentDocumentItem::whereIn('appointment_id', $appointments->where('visit_type', 'claiming')->modelKeys())
            ->pluck('document_request_id')->merge($documentIds)->unique()->all();
        try {
            $event = DB::transaction(function () use ($request, $claimantId, $documentIds, $expectedClaim, $fingerprint, $candidates, $appointments, $allIds) {
                $slotIds = $appointments->pluck('appointment_slot_id')->filter()->unique()->sort()->values()->all();
                $slots = AppointmentSlot::whereIn('id', $slotIds)->orderBy('id')->lockForUpdate()->get();
                $lockedAppointments = Appointment::whereIn('id', $appointments->modelKeys())->orderBy('id')->lockForUpdate()->get()->keyBy('id');
                foreach ($appointments as $candidate) {
                    $current = $lockedAppointments->get($candidate->id);
                    if (! $current || $current->appointment_slot_id !== $candidate->appointment_slot_id || $current->user_id !== $candidate->user_id) {
                        $this->invalid();
                    }
                }
                if ($slots->count() !== count($slotIds)) {
                    $this->invalid();
                }
                $documents = DocumentRequest::whereIn('id', $allIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
                $items = AppointmentDocumentItem::where(fn ($query) => $query->whereIn('document_request_id', $allIds)
                    ->orWhereIn('appointment_id', $lockedAppointments->where('visit_type', 'claiming')->modelKeys()))
                    ->orderBy('document_request_id')->orderBy('id')->lockForUpdate()->get();
                if ($items->contains(fn ($item) => ! $documents->has($item->document_request_id))) {
                    $this->invalid();
                }
                if ($event = $this->replay($request, $fingerprint, true)) {
                    return $event;
                }
                if ($expectedClaim !== null) {
                    $expected = $lockedAppointments->get($expectedClaim);
                    if (! $expected || $expected->visit_type !== 'claiming' || ! in_array($expected->status, ['pending', 'confirmed'], true)) {
                        throw ValidationException::withMessages(['appointment' => 'Only an active historical claiming appointment can record handover.']);
                    }
                }
                $owner = User::findOrFail($claimantId);
                if (! $owner->hasAnyRole(['student', 'alumni'])) {
                    $this->invalid();
                }
                $before = $this->snapshot($documents, $lockedAppointments, $items);
                $claims = [];
                foreach ($documentIds as $id) {
                    $document = $documents->get($id);
                    $candidate = $candidates->firstWhere('id', $id);
                    if (! $document || ! $candidate || $document->user_id !== $claimantId || $document->status !== 'ready'
                        || $document->released_at !== null || $document->released_by !== null || ! $document->paymentSatisfied() || ! $document->assessmentSatisfied()
                        || $document->pending_claim_appointment_id !== $candidate->pending_claim_appointment_id) {
                        $this->invalid();
                    }
                    if ($expectedClaim !== null && $document->pending_claim_appointment_id !== $expectedClaim) {
                        $this->invalid();
                    }
                    $matching = null;
                    foreach ($items->where('document_request_id', $id) as $item) {
                        $appointment = $lockedAppointments->get($item->appointment_id);
                        if (! $appointment) {
                            $this->invalid();
                        }
                        if ($appointment->visit_type !== 'claiming') {
                            continue;
                        }
                        if ($item->released_at !== null || $item->released_by !== null || $item->resolution !== null
                            || $item->resolved_at !== null || $item->resolved_by !== null) {
                            $this->invalid();
                        }
                        if ($document->pending_claim_appointment_id !== $appointment->id || ! in_array($appointment->status, ['pending', 'confirmed'], true)
                            || $appointment->user_id !== $claimantId || $matching) {
                            $this->invalid();
                        }
                        $matching = $item;
                    }
                    if ($document->pending_claim_appointment_id !== null && ! $matching) {
                        $this->invalid();
                    }
                    if ($matching) {
                        $claims[$matching->appointment_id][] = $id;
                    }
                }
                if ($expectedClaim !== null && (int) $request->input('lifecycle_version', -1) !== $lockedAppointments->get($expectedClaim)?->lifecycle_version) {
                    throw ValidationException::withMessages(['lifecycle_version' => 'This appointment changed. Refresh before handover.']);
                }
                $releasedAt = now();
                $notificationIds = [];
                foreach ($documentIds as $id) {
                    $document = $documents->get($id);
                    $claim = $document->pending_claim_appointment_id;
                    if ($claim !== null) {
                        $item = $items->first(fn ($item) => $item->document_request_id === $id && $item->appointment_id === $claim);
                        $item->update(['released_at' => $releasedAt, 'released_by' => $request->user()->id]);
                    }
                    $document->status = 'released';
                    $document->released_at = $releasedAt;
                    $document->released_by = $request->user()->id;
                    $document->pending_claim_appointment_id = null;
                    $document->save();
                    AuditLog::create(['user_id' => $request->user()->id, 'action' => $claim ? 'document_request.claim_released' : 'document_request.walk_in_released',
                        'description' => "Physically handed over document request #{$id} to claimant #{$claimantId}.",
                        'ip_address' => $request->ip(), 'user_agent' => $request->userAgent(),
                        'metadata' => ['document_request_id' => $id, 'appointment_id' => $claim, 'released_at' => $releasedAt->toIso8601String(), 'released_by' => $request->user()->id]]);
                    $notice = NotificationService::prepareUserUpdate($owner, 'document-released:'.$id, 'Document Released',
                        "Your {$document->document_label} (request #{$id}) was physically released on ".$releasedAt->copy()->timezone('Asia/Manila')->format('M d, Y g:i A').'. Other uncollected documents keep their own status.',
                        'success', route('portal.documents.index'));
                    $notificationIds[] = $notice->id;
                }
                foreach ($claims as $appointmentId => $selectedIds) {
                    $appointment = $lockedAppointments->get($appointmentId);
                    $claimItems = $items->where('appointment_id', $appointmentId);
                    if ($claimItems->isNotEmpty() && $claimItems->every(fn ($item) => $item->released_at !== null && $item->released_by !== null
                        && $documents->get($item->document_request_id)?->status === 'released'
                        && $documents->get($item->document_request_id)?->user_id === $claimantId
                        && $documents->get($item->document_request_id)?->pending_claim_appointment_id === null)) {
                        $appointment->status = 'completed';
                        $appointment->completed_at = $releasedAt;
                        $appointment->completed_by = $request->user()->id;
                        AuditLog::create(['user_id' => $request->user()->id, 'action' => 'appointment.claiming_completed',
                            'description' => "Completed historical claiming appointment #{$appointmentId} after all documents were physically handed over.",
                            'ip_address' => $request->ip(), 'user_agent' => $request->userAgent(), 'metadata' => ['appointment_id' => $appointmentId]]);
                    }
                    $previous = $appointment->lifecycle_version;
                    $updated = Appointment::whereKey($appointmentId)->where('lifecycle_version', $previous)->update([
                        'lifecycle_version' => $previous + 1,
                        'status' => $appointment->status, 'completed_at' => $appointment->completed_at,
                        'completed_by' => $appointment->completed_by,
                    ]);
                    if ($updated !== 1) {
                        throw ValidationException::withMessages(['lifecycle_version' => 'This appointment changed. Refresh before handover.']);
                    }
                    $appointment->refresh();
                    AppointmentLifecycleEvent::create(['appointment_id' => $appointmentId, 'actor_id' => $request->user()->id,
                        'operation_key' => (string) Uuid::uuid5(Uuid::NAMESPACE_URL, 'walk-in:'.$request->input('operation_key').':'.$appointmentId),
                        'request_fingerprint' => $fingerprint, 'action' => $expectedClaim ? 'claim.released' : 'claim.walk_in_released',
                        'previous_version' => $previous, 'resulting_version' => $appointment->lifecycle_version,
                        'document_item_ids' => $claimItems->whereIn('document_request_id', $selectedIds)->pluck('id')->values()->all(),
                        'before' => $before, 'after' => $this->snapshot($documents, $lockedAppointments, $items),
                        'outcome' => ['document_request_ids' => $selectedIds, 'document_workflow_operation_key' => $request->input('operation_key')], 'created_at' => now()]);
                }

                return DocumentWorkflowEvent::create(['actor_id' => $request->user()->id, 'operation_key' => $request->input('operation_key'),
                    'request_fingerprint' => $fingerprint, 'action' => 'documents.walk_in_released', 'document_request_ids' => $documentIds,
                    'before' => $before, 'after' => $this->snapshot($documents, $lockedAppointments, $items),
                    'outcome' => ['document_request_ids' => $documentIds, 'notification_ids' => $notificationIds], 'created_at' => now()]);
            }, 3);
        } catch (QueryException $exception) {
            $event = $this->replay($request, $fingerprint);
            if (! $event) {
                throw $exception;
            }
        }

        return $this->dispatch($event);
    }

    private function invalid(): void
    {
        throw ValidationException::withMessages(['document_ids' => 'Every selected document must belong to this claimant, be ready and payment satisfied, and have no previous handover or conflicting reservation. Historical inconsistencies require Registrar review.']);
    }

    private function replay(Request $request, string $fingerprint, bool $lock = false): ?DocumentWorkflowEvent
    {
        $query = DocumentWorkflowEvent::where('operation_key', $request->input('operation_key'));
        $event = ($lock ? $query->lockForUpdate() : $query)->first();
        if (($event && ($event->actor_id !== $request->user()->id || $event->action !== 'documents.walk_in_released' || $event->request_fingerprint !== $fingerprint))
            || (! $event && AppointmentLifecycleEvent::where('operation_key', $request->input('operation_key'))->exists())) {
            throw ValidationException::withMessages(['operation_key' => 'This operation key was already used with different input.']);
        }

        return $event;
    }

    private function snapshot($documents, $appointments, $items): array
    {
        return ['documents' => $documents->map(fn ($document) => $document->only(['id', 'user_id', 'status', 'appointment_id', 'payment_appointment_id', 'pending_claim_appointment_id', 'released_at', 'released_by']))->values()->all(),
            'appointments' => $appointments->map(fn ($appointment) => $appointment->only(['id', 'status', 'lifecycle_version', 'completed_at', 'completed_by']))->values()->all(),
            'items' => $items->map(fn ($item) => $item->only(['id', 'appointment_id', 'document_request_id', 'released_at', 'released_by', 'resolution', 'resolved_at', 'resolved_by']))->values()->all()];
    }

    private function dispatch(DocumentWorkflowEvent $event): bool
    {
        $queued = true;
        foreach (UserNotification::with('user')->whereIn('id', $event->outcome['notification_ids'])->get() as $notice) {
            $sent = NotificationService::dispatchWorkflowEmail($notice, $notice->user);
            $queued = $sent && $queued;
        }

        return $queued;
    }
}
