<?php

namespace App\Services;

use App\Models\AppointmentLifecycleEvent;
use App\Models\AuditLog;
use App\Models\DocumentRequest;
use App\Models\DocumentWorkflowEvent;
use App\Models\Setting;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DocumentReadyService
{
    public function mark(Request $request, DocumentRequest $document): bool
    {
        abort_unless($request->user()->hasAnyRole(['registrar', 'admin']), 403);
        $request->validate(['operation_key' => ['required', 'uuid']]);
        $fingerprint = app(\App\Support\WorkflowCommandFingerprint::class)->fingerprint($request, 'document.ready');
        try {
            $event = DB::transaction(function () use ($request, $document, $fingerprint) {
                $locked = DocumentRequest::whereKey($document->id)->lockForUpdate()->firstOrFail();
                $event = $this->replay($request, $fingerprint, true);
                if ($event) {
                    return $event;
                }
                if ($locked->status !== 'processing') {
                    throw ValidationException::withMessages(['status' => 'Only processing documents can be marked ready.']);
                }
                if (! $locked->assessmentSatisfied()) {
                    throw ValidationException::withMessages(['assessment' => 'Complete the requirement and fee assessment before marking ready.']);
                }
                $before = $this->state($locked);
                app(DocumentRequestPricing::class)->auditNoPayment($locked, $request);
                $locked->update(['status' => 'ready', 'remarks' => $request->input('remarks') ?? $locked->remarks]);
                AuditLog::create(['user_id' => $request->user()->id, 'action' => 'document_request.status_changed',
                    'description' => "Document request #{$locked->id} status changed from processing to ready.",
                    'ip_address' => $request->ip(), 'user_agent' => $request->userAgent(),
                    'metadata' => ['document_request_id' => $locked->id, 'previous_status' => 'processing', 'new_status' => 'ready', 'remarks' => $request->input('remarks')]]);
                $owner = $locked->user;
                $link = route($owner->hasAnyRole(['student', 'alumni']) ? 'portal.documents.index' : 'portal.dashboard');
                $key = 'document-ready:'.$locked->id;
                $notice = NotificationService::prepareUserUpdate($owner, $key, 'Document Request Update', $this->message($locked), $locked->paymentSatisfied() ? 'success' : 'info', $link);
                $payment = $locked->paymentNotRequired() ? 'No Payment Required' : match ($locked->payment_status) {
                    'verified' => 'payment verified', 'pending_verification' => 'GCash payment awaiting verification',
                    'rejected' => 'GCash payment rejected; corrected proof is needed', default => 'payment due at the Cashier',
                };
                foreach (User::role(['cashier', 'admin'])->where('users.id', '!=', $owner->id)->get() as $staff) {
                    NotificationService::sendForEvent($staff, $key, 'Document Ready for Pickup', "Document request #{$locked->id} for {$locked->document_label} ({$owner->name}) is ready. {$payment}.", 'info', route('cashier.document-payments.show', $locked));
                }

                return DocumentWorkflowEvent::create(['actor_id' => $request->user()->id, 'operation_key' => $request->input('operation_key'),
                    'request_fingerprint' => $fingerprint, 'action' => 'document.ready', 'document_request_ids' => [$locked->id],
                    'before' => $before, 'after' => $this->state($locked), 'outcome' => ['document_request_id' => $locked->id, 'notification_id' => $notice->id, 'status' => 'ready'], 'created_at' => now()]);
            }, 3);
        } catch (QueryException $exception) {
            $event = $this->replay($request, $fingerprint);
            if (! $event) {
                throw $exception;
            }
        }
        $notice = UserNotification::with('user')->find($event->outcome['notification_id']);
        // Respect an owner's explicit notification deletion on a completed command retry.
        if (! $notice) {
            return true;
        }

        return NotificationService::dispatchWorkflowEmail($notice, $notice->user);
    }

    private function replay(Request $request, string $fingerprint, bool $lock = false): ?DocumentWorkflowEvent
    {
        $query = DocumentWorkflowEvent::where('operation_key', $request->input('operation_key'));
        $event = ($lock ? $query->lockForUpdate() : $query)->first();
        if (($event && ($event->actor_id !== $request->user()->id || $event->action !== 'document.ready' || $event->request_fingerprint !== $fingerprint))
            || (! $event && AppointmentLifecycleEvent::where('operation_key', $request->input('operation_key'))->exists())) {
            throw ValidationException::withMessages(['operation_key' => 'This operation key was already used with different input.']);
        }

        return $event;
    }

    private function state(DocumentRequest $document): array
    {
        return $document->only(['id', 'user_id', 'status', 'remarks', 'appointment_id', 'payment_appointment_id', 'pending_claim_appointment_id']);
    }

    private function message(DocumentRequest $document): string
    {
        $message = "Your {$document->document_label} (request #{$document->id}) has been prepared and is ready. ";
        $message .= $document->paymentNotRequired() ? 'No Payment Required. ' : ($document->paymentSatisfied() ? 'Your payment is verified. ' : match ($document->payment_status) {
            'pending_verification' => 'The Cashier is still verifying your GCash payment. ',
            'rejected' => 'Payment verification was rejected; review your payment notice and provide corrected details before collection. ',
            default => 'Payment is still outstanding; arrange Cashier verification before collection. ',
        });
        $message .= 'No claiming appointment is required. Visit the Registrar during published office hours with a valid ID after payment requirements are satisfied. Bring your claim stub if one was issued and your receipt if payment was required. Existing appointment records remain in your history. ';
        foreach (['registrar_email', 'registrar_phone', 'registrar_office', 'registrar_hours'] as $key) {
            $value = Setting::get($key);
            if ($value) {
                $message .= $value.'. ';
            }
        }

        return trim($message);
    }
}
