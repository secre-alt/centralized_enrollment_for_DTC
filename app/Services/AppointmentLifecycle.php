<?php

namespace App\Services;

use App\Exceptions\LifecycleOperationDeclined;
use App\Models\Appointment;
use App\Models\AppointmentLifecycleEvent;
use App\Models\AppointmentSlot;
use App\Models\DocumentRequest;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AppointmentLifecycle
{
    public function fingerprint(Request $request, string $action, array $payload = []): string
    {
        return app(\App\Support\WorkflowCommandFingerprint::class)->fingerprint($request, $action, $payload);
    }

    public function replay(Request $request, string $action, string $fingerprint): ?AppointmentLifecycleEvent
    {
        $event = AppointmentLifecycleEvent::where('operation_key', $request->input('operation_key'))->first();
        if ($event && ($event->actor_id !== $request->user()->id || $event->action !== $action || ! hash_equals($event->request_fingerprint, $fingerprint))) {
            throw ValidationException::withMessages(['operation_key' => 'This operation key was already used with different input.']);
        }
        if ($event) {
            $request->attributes->set('lifecycle_replayed', true);
        }

        return $event;
    }

    public function run(Request $request, string $action, ?Appointment $target, array $slotIds, array $documentIds,
        callable $operation, array $payload = [], array $relatedAppointments = [])
    {
        // Trusted service callers may create a command here; HTTP callers must supply form metadata.
        if (! $request->filled('operation_key')) {
            $request->merge(['operation_key' => (string) Str::uuid()]);
        }
        $expected = $target ? (int) $request->input('lifecycle_version', $target->lifecycle_version) : null;
        $fingerprint = $this->fingerprint($request, $action, $payload);
        if ($event = $this->replay($request, $action, $fingerprint)) {
            return $this->unpack($event->outcome);
        }
        $candidates = collect($relatedAppointments)->keyBy('id');
        if ($target) {
            $candidates->put($target->id, $target);
        }
        $slotIds = array_values(array_unique(array_merge($slotIds, $candidates->pluck('appointment_slot_id')->all())));
        sort($slotIds, SORT_NUMERIC);
        $affectedDocumentIds = $documentIds;
        $reason = $payload['remarks'] ?? null;
        try {
            return DB::transaction(function () use ($request, $action, $target, $slotIds, $documentIds, $affectedDocumentIds, $operation, $expected, $fingerprint, $candidates, $reason) {
                $slots = AppointmentSlot::whereIn('id', $slotIds)->orderBy('id')->lockForUpdate()->get();
                if ($slots->count() !== count($slotIds)) {
                    throw (new \Illuminate\Database\Eloquent\ModelNotFoundException())->setModel(AppointmentSlot::class, array_diff($slotIds, $slots->modelKeys()));
                }
                $appointments = Appointment::where(function ($query) use ($slotIds, $candidates) {
                    $query->whereIn('id', $candidates->keys())->orWhere(fn ($active) => $active->whereIn('appointment_slot_id', $slotIds)->whereIn('status', ['pending', 'confirmed']));
                })->orderBy('id')->lockForUpdate()->get()->keyBy('id');
                foreach ($candidates as $candidate) {
                    $current = $appointments->get($candidate->id);
                    if (! $current || $current->appointment_slot_id !== $candidate->appointment_slot_id || $current->user_id !== $candidate->user_id) {
                        throw ValidationException::withMessages(['lifecycle_version' => 'The appointment changed. Refresh before trying again.']);
                    }
                }
                // A completed retry is checked before its now-old version is rejected.
                $event = AppointmentLifecycleEvent::where('operation_key', $request->input('operation_key'))->lockForUpdate()->first();
                if ($event) {
                    if ($event->actor_id !== $request->user()->id || $event->action !== $action || ! hash_equals($event->request_fingerprint, $fingerprint)) {
                        throw ValidationException::withMessages(['operation_key' => 'This operation key was already used with different input.']);
                    }
                    $request->attributes->set('lifecycle_replayed', true);

                    return $this->unpack($event->outcome);
                }
                $locked = $target ? $appointments->get($target->id) : null;
                if ($locked && $locked->lifecycle_version !== $expected) {
                    throw ValidationException::withMessages(['lifecycle_version' => 'This appointment changed. Refresh before submitting again.']);
                }
                if ($locked) {
                    $documentIds = array_merge($documentIds, $locked->documentRequests()->pluck('id')->all(), $locked->documentItems()->pluck('document_request_id')->all());
                }
                DocumentRequest::where(function ($query) use ($documentIds, $action, $locked) {
                    $query->whereIn('id', $documentIds);
                    if ($action === 'appointment.confirmed') {
                        $query->orWhere('user_id', $locked->user_id);
                    }
                })->orderBy('id')->lockForUpdate()->get();
                if ($locked) {
                    $locked->documentItems()->orderBy('document_request_id')->lockForUpdate()->get();
                }
                $before = $locked ? $this->snapshot($locked) : ['appointment' => null,
                    'documents' => DocumentRequest::whereIn('id', $documentIds)->orderBy('id')->lockForUpdate()
                        ->get(['id', 'document_type', 'copies', 'purpose', 'fee', 'status', 'appointment_id', 'payment_appointment_id', 'pending_claim_appointment_id'])->toArray(), 'items' => []];
                $result = $operation();
                if (($action === 'appointment.confirmed' && $result !== 'confirmed')
                    || ($action === 'appointment.cancelled' && ($result['result'] ?? null) !== 'cancelled')) {
                    throw new LifecycleOperationDeclined($result);
                }
                $appointment = $target ? $locked->fresh() : $result;
                if (! $appointment instanceof Appointment) {
                    throw new \LogicException('A booking must return its appointment.');
                }
                $previous = $target ? $expected : 0;
                $updated = Appointment::whereKey($appointment->id)->where('lifecycle_version', $previous)->update(['lifecycle_version' => $previous + 1]);
                if ($updated !== 1) {
                    throw ValidationException::withMessages(['lifecycle_version' => 'This appointment changed. Refresh before submitting again.']);
                }
                $appointment->refresh();
                if ($result instanceof Appointment) {
                    $result->setRawAttributes($appointment->getAttributes(), true);
                }
                if (is_array($result) && ($result['appointment'] ?? null) instanceof Appointment) {
                    $result['appointment']->lifecycle_version = $appointment->lifecycle_version;
                }
                AppointmentLifecycleEvent::create(['appointment_id' => $appointment->id, 'actor_id' => $request->user()->id,
                    'operation_key' => $request->input('operation_key'), 'request_fingerprint' => $fingerprint, 'action' => $action,
                    'previous_version' => $target ? $expected : null, 'resulting_version' => $appointment->lifecycle_version,
                    'document_item_ids' => $appointment->documentItems()->when($action === 'claim.released', fn ($query) => $query->whereIn('document_request_id', $affectedDocumentIds))->orderBy('document_request_id')->pluck('id')->all(),
                    'before' => $before, 'after' => $this->snapshot($appointment), 'outcome' => $this->pack($result),
                    'reason' => $reason, 'created_at' => now()]);

                return $result;
            }, 3);
        } catch (LifecycleOperationDeclined $declined) {
            return $declined->outcome;
        } catch (QueryException $exception) {
            if (($exception->errorInfo[1] ?? null) === 1062 && ($event = $this->replay($request, $action, $fingerprint))) {
                return $this->unpack($event->outcome);
            }
            throw $exception;
        }
    }

    private function snapshot(Appointment $appointment): array
    {
        // These rows are already owned by the command; current reads avoid an older snapshot.
        $items = $appointment->documentItems()->orderBy('document_request_id')->lockForUpdate()->get()->toArray();
        $documents = DocumentRequest::where('appointment_id', $appointment->id)->orWhereIn('id', array_column($items, 'document_request_id'))
            ->orderBy('id')->lockForUpdate()->get(['id', 'document_type', 'copies', 'purpose', 'fee', 'status', 'appointment_id', 'payment_appointment_id', 'pending_claim_appointment_id'])->toArray();

        return ['appointment' => $appointment->only(['id', 'user_id', 'appointment_slot_id', 'status', 'visit_type', 'document_type', 'purpose', 'remarks', 'lifecycle_version', 'completed_at', 'completed_by']),
            'documents' => $documents, 'items' => $items];
    }

    private function pack($value): array
    {
        if ($value instanceof Model) {
            return ['type' => 'model', 'class' => get_class($value), 'attributes' => $value->attributesToArray(), 'relations' => array_map(fn ($relation) => $this->pack($relation), $value->getRelations())];
        }
        if ($value instanceof Collection) {
            return ['type' => 'collection', 'value' => $value->map(fn ($item) => $this->pack($item))->all()];
        }
        if (is_array($value)) {
            return ['type' => 'array', 'value' => array_map(fn ($item) => $this->pack($item), $value)];
        }

        return ['type' => 'scalar', 'value' => $value];
    }

    private function unpack(array $value)
    {
        if ($value['type'] === 'model') {
            if (! in_array($value['class'], [Appointment::class, DocumentRequest::class, \App\Models\User::class, AppointmentSlot::class], true)) {
                throw new \LogicException('Unsupported lifecycle outcome.');
            }
            $model = new $value['class'];
            $model->setRawAttributes($value['attributes'], true);
            $model->exists = true;
            $model->setRelations(array_map(fn ($relation) => $this->unpack($relation), $value['relations']));

            return $model;
        }
        if ($value['type'] === 'collection') {
            return collect(array_map(fn ($item) => $this->unpack($item), $value['value']));
        }
        if ($value['type'] === 'array') {
            return array_map(fn ($item) => $this->unpack($item), $value['value']);
        }

        return $value['value'];
    }
}
