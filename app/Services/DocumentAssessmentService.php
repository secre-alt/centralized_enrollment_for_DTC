<?php

namespace App\Services;

use App\Models\AppointmentLifecycleEvent;
use App\Models\AuditLog;
use App\Models\DocumentRequest;
use App\Models\DocumentWorkflowEvent;
use App\Support\WorkflowCommandFingerprint;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DocumentAssessmentService
{
    public function assess(Request $request, DocumentRequest $document): void
    {
        abort_unless($request->user()->hasAnyRole(['registrar', 'admin']), 403);
        $input = $request->validate([
            'operation_key' => ['required', 'uuid'],
            'assessment_version' => ['required', 'integer', 'min:0'],
            'assessment_status' => ['required', 'in:incomplete,complete'],
            'requirements_notes' => ['required_if:assessment_status,incomplete', 'nullable', 'string', 'max:1000'],
            'fee_confirmed' => ['exclude_unless:assessment_status,complete', 'required', 'accepted'],
        ]);
        $fingerprint = app(WorkflowCommandFingerprint::class)->fingerprint($request, 'document.assessed');
        try {
            DB::transaction(function () use ($request, $document, $input, $fingerprint) {
                $locked = DocumentRequest::whereKey($document->id)->lockForUpdate()->firstOrFail();
                $event = DocumentWorkflowEvent::where('operation_key', $input['operation_key'])->first();
                if ($event) {
                    if ($event->actor_id !== $request->user()->id || $event->action !== 'document.assessed'
                        || ! hash_equals($event->request_fingerprint, $fingerprint)) {
                        throw ValidationException::withMessages(['operation_key' => 'This operation key was already used with different input.']);
                    }

                    return;
                }
                if (AppointmentLifecycleEvent::where('operation_key', $input['operation_key'])->exists()) {
                    throw ValidationException::withMessages(['operation_key' => 'This operation key belongs to another command.']);
                }
                if (! $locked->assessment_required || $locked->status !== 'submitted'
                    || $locked->assessment_version !== (int) $input['assessment_version']) {
                    throw ValidationException::withMessages(['assessment' => 'This request changed or cannot be assessed. Refresh before continuing.']);
                }
                if ($locked->fee === null || ! preg_match('/^\d+\.\d{2}$/D', $locked->fee)) {
                    throw ValidationException::withMessages(['fee_confirmed' => 'The stored document fee requires financial review.']);
                }
                $before = $locked->only(['assessment_status', 'requirements_notes', 'assessed_fee', 'assessed_by', 'assessed_at', 'assessment_version']);
                $locked->forceFill([
                    'assessment_status' => $input['assessment_status'],
                    'requirements_notes' => $input['requirements_notes'] ?? null,
                    'assessed_fee' => $input['assessment_status'] === 'complete' ? $locked->fee : null,
                    'assessed_by' => $request->user()->id, 'assessed_at' => now(),
                    'assessment_version' => $locked->assessment_version + 1,
                ])->save();
                $after = $locked->only(array_keys($before));
                AuditLog::create(['user_id' => $request->user()->id, 'action' => 'document_request.assessed',
                    'description' => 'Assessed document request #'.$locked->id.'.',
                    'ip_address' => $request->ip(), 'user_agent' => $request->userAgent(),
                    'metadata' => ['document_request_id' => $locked->id, 'before' => $before, 'after' => $after, 'fee_basis' => 'stored_configured_fee']]);
                NotificationService::sendForEvent($locked->user, 'document-assessed:'.$locked->id.':'.$locked->assessment_version,
                    'Document Assessment Update',
                    $input['assessment_status'] === 'complete'
                        ? 'Requirements checked for request #'.$locked->id.'. Confirmed fee: PHP '.$locked->fee.'. Review payment and processing status in your document requests.'
                        : 'Requirements are incomplete for request #'.$locked->id.': '.$locked->requirements_notes,
                    'info', route('portal.documents.index'));
                DocumentWorkflowEvent::create(['actor_id' => $request->user()->id,
                    'operation_key' => $input['operation_key'], 'request_fingerprint' => $fingerprint,
                    'action' => 'document.assessed', 'document_request_ids' => [$locked->id],
                    'before' => $before, 'after' => $after, 'outcome' => ['document_request_id' => $locked->id], 'created_at' => now()]);
            }, 3);
        } catch (QueryException $exception) {
            if (($exception->errorInfo[1] ?? null) === 1062) {
                throw ValidationException::withMessages(['operation_key' => 'This operation key was already used. Reload the assessment form.']);
            }

            throw $exception;
        }
    }
}
