<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\DocumentPaymentProof;
use App\Models\DocumentRequest;
use App\Models\DocumentWorkflowEvent;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class DocumentSubmissionService
{
    public function submit(Request $request, array $documents): void
    {
        abort_unless($request->user()->hasAnyRole(['student', 'alumni']), 403);
        $request->validate(['operation_key' => ['required', 'uuid']]);
        $fingerprint = app(\App\Support\WorkflowCommandFingerprint::class)->fingerprint($request, 'documents.submitted');
        if ($this->replay($request, $fingerprint)) {
            return;
        }
        $quote = app(DocumentRequestPricing::class)->quote($documents);
        $payable = collect($quote['documents'])->where('fee', '>', 0)->count();
        if ($payable > 1 && ($request->hasFile('proof_of_payment') || $request->filled('reference_number'))) {
            throw ValidationException::withMessages(['proof_of_payment' => 'Provide a separate payment reference and proof for each payable document.']);
        }
        $payments = [];
        $proofHashes = [];
        $references = [];
        foreach ($quote['documents'] as $i => $document) {
            $inputIndex = array_keys($request->input('documents', []))[$i] ?? $i;
            $method = $documents[$i]['payment_method'] ?? $request->input('payment_method');
            $method = $method === 'gcash' ? 'gcash_qr' : $method;
            if ($document['fee'] == 0) {
                $method = 'walk_in';
            }
            if (! in_array($method, ['walk_in', 'gcash_qr'], true)) {
                throw ValidationException::withMessages(['payment_method' => 'Choose a payment method for each payable document.']);
            }
            $reference = $documents[$i]['reference_number'] ?? ($payable === 1 ? $request->input('reference_number') : null);
            $proof = $documents[$i]['proof_of_payment'] ?? ($payable === 1 ? $request->file('proof_of_payment') : null);
            if ($method === 'gcash_qr') {
                $missingReference = $reference === null || $reference === '';
                if ($missingReference || ! $proof) {
                    $prefix = $request->exists('documents') ? "documents.$inputIndex." : '';
                    $errors = [];
                    if ($missingReference) {
                        $errors[$prefix.'reference_number'] = 'A separate GCash reference is required.';
                    }
                    if (! $proof) {
                        $errors[$prefix.'proof_of_payment'] = 'A separate GCash proof is required.';
                    }
                    throw ValidationException::withMessages($errors);
                }
                $hash = hash_file('sha256', $proof->getRealPath());
                if (in_array($hash, $proofHashes, true) || in_array(trim($reference), $references, true)) {
                    throw ValidationException::withMessages(["documents.$inputIndex.proof_of_payment" => 'Do not reuse a GCash proof or reference for multiple documents.']);
                }
                $proofHashes[] = $hash;
                $references[] = trim($reference);
                $qr = Setting::get('gcash_qr_path');
                if (! $qr || ! Storage::disk('local')->exists($qr)) {
                    throw ValidationException::withMessages(['payment_method' => 'GCash is unavailable. Please pay at the Cashier.']);
                }
            }
            $payments[$i] = ['method' => $method, 'reference' => $reference, 'proof' => $proof];
        }
        $paths = [];
        try {
            foreach ($payments as $i => $payment) {
                if ($payment['method'] !== 'gcash_qr') {
                    continue;
                }
                $path = $payment['proof']->store('document-request-proofs', 'local');
                if ($path) {
                    $paths[$i] = $path;
                }
                if (! $path || ! Storage::disk('local')->exists($path)) {
                    throw ValidationException::withMessages(['proof_of_payment' => 'The proof could not be saved. Please try again.']);
                }
            }
            DB::transaction(function () use ($request, $quote, $payments, $paths, $fingerprint) {
                $ids = [];
                $staff = User::role(['registrar', 'admin'])->get();
                foreach ($quote['documents'] as $i => $document) {
                    $gcash = $payments[$i]['method'] === 'gcash_qr';
                    $record = DocumentRequest::create(array_merge($document, [
                        'user_id' => $request->user()->id, 'appointment_id' => null,
                        'purpose' => $request->input('purpose'), 'status' => 'submitted',
                        'assessment_required' => true, 'assessment_status' => 'pending',
                        'payment_method' => $payments[$i]['method'], 'payment_status' => $gcash ? 'pending_verification' : 'unpaid',
                        'reference_number' => $gcash ? $payments[$i]['reference'] : null,
                        'proof_of_payment' => $paths[$i] ?? null, 'payment_submitted_at' => $gcash ? now() : null,
                    ]));
                    $ids[] = $record->id;
                    if ($gcash) {
                        DocumentPaymentProof::create(['document_request_id' => $record->id,
                            'path' => $paths[$i], 'reference_number' => $payments[$i]['reference'],
                            'submitted_by' => $request->user()->id, 'submitted_at' => now()]);
                    }
                    app(DocumentRequestPricing::class)->auditNoPayment($record, $request);
                    AuditLog::create(['user_id' => $request->user()->id, 'action' => 'document_request.created',
                        'description' => "Standalone document request #{$record->id} submitted.",
                        'ip_address' => $request->ip(), 'user_agent' => $request->userAgent(),
                        'metadata' => ['document_request_id' => $record->id, 'amount_due' => $record->fee, 'payment_status' => $record->payment_status]]);
                    foreach ($staff as $recipient) {
                        NotificationService::sendForEvent($recipient, 'document-submitted:'.$record->id,
                            'New Document Request',
                            $request->user()->name.' requested '.$record->document_label.' (request #'.$record->id.', copies: '.$record->copies.').',
                            'info', route('registrar.documents.index', ['claimant_id' => $record->user_id]));
                    }
                }
                DocumentWorkflowEvent::create(['actor_id' => $request->user()->id,
                    'operation_key' => $request->input('operation_key'), 'request_fingerprint' => $fingerprint,
                    'action' => 'documents.submitted', 'document_request_ids' => $ids, 'before' => [],
                    'after' => DocumentRequest::whereIn('id', $ids)->get()->toArray(),
                    'outcome' => ['document_request_ids' => $ids], 'created_at' => now()]);
            }, 3);
        } catch (\Throwable $exception) {
            foreach ($paths as $path) {
                Storage::disk('local')->delete($path);
            }
            if ($exception instanceof QueryException && $this->replay($request, $fingerprint)) {
                return;
            }
            throw $exception;
        }
    }

    public function replay(Request $request, string $fingerprint): bool
    {
        $event = DocumentWorkflowEvent::where('operation_key', $request->input('operation_key'))->first();
        if (! $event) {
            if (\App\Models\AppointmentLifecycleEvent::where('operation_key', $request->input('operation_key'))->exists()) {
                throw ValidationException::withMessages(['operation_key' => 'This command belongs to a historical appointment. Reload the document request form.']);
            }

            return false;
        }
        if ($event->actor_id !== $request->user()->id || $event->action !== 'documents.submitted' || $event->request_fingerprint !== $fingerprint) {
            throw ValidationException::withMessages(['operation_key' => 'This operation key was already used with different input.']);
        }

        return true;
    }
}
