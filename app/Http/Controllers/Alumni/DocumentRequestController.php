<?php

namespace App\Http\Controllers\Alumni;

use App\Http\Controllers\Controller;
use App\Models\DocumentRequest;
use App\Models\DocumentPaymentProof;
use App\Services\NotificationService;
use App\Models\User;
use App\Models\Setting;
use App\Models\AuditLog;
use App\Support\PrivateFilePreview;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class DocumentRequestController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:200'],
            'document_type' => ['nullable', 'in:tor,true_copy,certification,diploma'],
        ] + \App\Support\ListDateRange::rules($request));
        $query = DocumentRequest::with(['releaser', 'appointment.slot', 'appointment.documentRequests', 'appointment.payments', 'paymentAppointment.payments', 'appointmentItems.appointment'])->where('user_id', Auth::id());
        if (isset($filters['document_type'])) $query->where('document_type', $filters['document_type']);
        $search = trim($filters['search'] ?? '');
        if ($search !== '') {
            $query->where(fn ($matches) => $matches->where('purpose', 'like', '%'.$search.'%')
                ->orWhere('reference_number', 'like', '%'.$search.'%')
                ->orWhere('receipt_no', 'like', '%'.$search.'%')
                ->orWhere('id', ctype_digit($search) ? (int) $search : -1));
        }
        \App\Support\ListDateRange::apply($query, $filters, 'document_requests.created_at');
        $requests = $query->latest()->get();

        $totalRequests  = $requests->count();
        $readyRequests  = $requests->filter(fn ($item) => $item->status === 'ready' && $item->paymentSatisfied())->count();
        $releasedDocs   = $requests->where('status', 'released')->count();
        $totalFees      = $requests->sum('fee');
        $receiptKeys = $requests->map(fn ($item) => 'D-'.$item->id)->merge(
            $requests->flatMap(fn ($item) => $item->paymentAppointment?->payments ?? collect())
                ->where('is_document_bundle', true)->map(fn ($payment) => 'A-'.$payment->id)
        );
        $history = app(\App\Services\TransactionHistory::class);
        $receipts = $history->query(Auth::id(), Auth::user()->hasRole('student') ? 'student' : 'alumni')
            ->whereIn('transaction_key', $receiptKeys)->get()->filter(fn ($record) => $history->receiptEligible($record))->keyBy('transaction_key');

        return view('alumni.requests.index', compact(
            'requests', 'totalRequests', 'readyRequests',
            'releasedDocs', 'totalFees', 'receipts'
        ));
    }

    public function bookPickup(Request $request, DocumentRequest $documentRequest)
    {
        abort_if($documentRequest->user_id !== Auth::id(), 403);

        throw ValidationException::withMessages(['appointment_slot_id' => 'Pickup bookings are retired. Collect ready, payment-satisfied documents at the Registrar during published office hours.']);
    }

    public function create()
    {
        $documentFees = [
            'tor' => (float) Setting::get('document_fee_tor', 50),
            'diploma' => (float) Setting::get('document_fee_diploma', 100),
            'certification' => (float) Setting::get('document_fee_certification', 30),
            'true_copy' => (float) Setting::get('document_fee_true_copy', 50),
        ];

        $gcashQrPath = Setting::get('gcash_qr_path');
        $gcashQrReady = $gcashQrPath && Storage::disk('local')->exists($gcashQrPath);

        $gcashName = Setting::get('gcash_name');
        $gcashNumber = Setting::get('gcash_number');
        return view('alumni.requests.create', compact('documentFees', 'gcashQrReady', 'gcashName', 'gcashNumber'));
    }

    public function store(Request $request)
    {
        if ($request->exists('documents') && ($request->exists('document_type') || $request->exists('copies'))) {
            throw ValidationException::withMessages([
                'documents' => 'Submit either a single document or a combined document list, not both.',
            ]);
        }

        $validated = $request->validate([
            'document_type'       => ['nullable', 'required_without:documents', 'in:tor,diploma,certification,true_copy'],
            'copies'              => ['required_with:document_type', 'integer', 'min:1', 'max:10'],
            'documents'           => ['required_without:document_type', 'array', 'min:1', 'max:4'],
            'documents.*.document_type' => ['required', 'in:tor,diploma,certification,true_copy'],
            'documents.*.copies'  => ['required', 'integer', 'min:1', 'max:10'],
            'documents.*.payment_method' => ['nullable', 'in:walk_in,gcash,gcash_qr'],
            'documents.*.reference_number' => ['nullable', 'string', 'max:100'],
            'documents.*.proof_of_payment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'mimetypes:image/jpeg,image/png,application/pdf', 'max:5120'],
            'payment_method'      => ['nullable', 'in:walk_in,gcash_qr'],
            'purpose'             => ['nullable', 'string', 'max:500'],
            'reference_number'    => ['nullable', 'string', 'max:100'],
            'proof_of_payment'    => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'mimetypes:image/jpeg,image/png,application/pdf', 'max:5120'],
        ], [], [
            'documents.*.copies' => 'number of copies',
            'documents.*.document_type' => 'document type',
        ]);


        $documents = $validated['documents'] ?? [[
            'document_type' => $validated['document_type'],
            'copies' => $validated['copies'],
        ]];
        $documents = array_values($documents);
        app(\App\Services\DocumentSubmissionService::class)->submit($request, $documents);

        return redirect()->route('portal.documents.index')->with('success', 'Document requests submitted. Each payable document is verified separately.');
    }

    public function storeLegacy(Request $request)
    {
        if ($request->exists('documents') && $request->exists('document_type')) {
            throw ValidationException::withMessages([
                'documents' => 'Submit either a single document or a combined document list, not both.',
            ]);
        }

        $validated = $request->validate([
            'document_type'       => ['nullable', 'required_without:documents', 'in:transcript,diploma,certification,tor'],
            'documents'           => ['required_without:document_type', 'array', 'min:1', 'max:4'],
            'documents.*.document_type' => ['required', 'in:transcript,diploma,certification,tor'],
            'documents.*.copies'  => ['required', 'integer', 'min:1', 'max:10'],
            'documents.*.payment_method' => ['nullable', 'in:walk_in,gcash,gcash_qr'],
            'documents.*.reference_number' => ['nullable', 'string', 'max:100'],
            'documents.*.proof_of_payment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'mimetypes:image/jpeg,image/png,application/pdf', 'max:5120'],
            'purpose'             => ['nullable', 'string', 'max:500'],
            'payment_method'      => ['nullable', 'in:walk_in,gcash'],
            'reference_number'    => ['nullable', 'string', 'max:100'],
            'proof_of_payment'    => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'mimetypes:image/jpeg,image/png,application/pdf', 'max:5120'],
        ], [], [
            'documents.*.copies' => 'number of copies',
            'documents.*.document_type' => 'document type',
        ]);


        $documents = $validated['documents'] ?? [[
            'document_type' => $validated['document_type'],
            'copies' => 1,
        ]];
        $documents = array_values($documents);
        $documents = array_map(function ($document) {
            $document['document_type'] = match ($document['document_type']) {
                'transcript' => 'tor',
                'tor' => 'true_copy',
                default => $document['document_type'],
            };
            return $document;
        }, $documents);
        app(\App\Services\DocumentSubmissionService::class)->submit($request, $documents);

        return redirect()->route('portal.documents.index')->with('success', 'Document requests submitted. Each payable document is verified separately.');
    }

    public function submitStudentPayment(Request $request, DocumentRequest $documentRequest)
    {
        abort_if($documentRequest->user_id !== Auth::id(), 403);
        if ($documentRequest->usesBundledPayment()) {
            throw ValidationException::withMessages(['payment' => 'This payment is managed through its appointment. Review or submit payment for the whole booking.']);
        }
        if ($documentRequest->paymentNotRequired()) {
            return redirect()->route('portal.payments.document', $documentRequest);
        }


        $validated = $request->validate([
            'reference_number' => ['required', 'string', 'max:100'],
            'proof_of_payment' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'mimetypes:image/jpeg,image/png,application/pdf', 'max:5120'],
        ]);
        $qrPath = Setting::get('gcash_qr_path');
        if (! $qrPath || ! Storage::disk('local')->exists($qrPath)) {
            throw ValidationException::withMessages(['payment' => 'GCash payment is unavailable. Please pay at the Cashier.']);
        }

        $proofPath = $request->file('proof_of_payment')->store('document-request-proofs', 'local');
        try {
            $result = DB::transaction(function () use ($documentRequest, $validated, $proofPath, $request) {
                $locked = DocumentRequest::whereKey($documentRequest->id)->lockForUpdate()->firstOrFail();
                if ($locked->paymentNotRequired() || $locked->is_paid || $locked->payment_status === 'verified') {
                    return 'verified';
                }
                if ($locked->payment_status === 'pending_verification') {
                    return 'pending';
                }
                if (! in_array($locked->payment_status, ['unpaid', 'rejected'], true) || in_array($locked->status, ['released', 'cancelled'], true)) {
                    return 'unavailable';
                }
                if ($locked->payment_status === 'rejected' && $locked->proof_of_payment
                    && ! DocumentPaymentProof::where('document_request_id', $locked->id)->where('path', $locked->proof_of_payment)->exists()) {
                    DocumentPaymentProof::create([
                        'document_request_id' => $locked->id,
                        'path' => $locked->proof_of_payment,
                        'reference_number' => $locked->reference_number,
                        'submitted_at' => $locked->payment_submitted_at ?? $locked->updated_at ?? now(),
                        'status' => 'rejected',
                        'reviewed_by' => $locked->rejected_by,
                        'reviewed_at' => $locked->rejected_at,
                        'rejection_reason' => $locked->payment_rejection_reason,
                    ]);
                }

                $locked->update([
                    'payment_method' => 'gcash_qr',
                    'payment_status' => 'pending_verification',
                    'reference_number' => $validated['reference_number'],
                    'proof_of_payment' => $proofPath,
                    'payment_submitted_at' => now(),
                    'payment_rejection_reason' => null,
                    'rejected_by' => null,
                    'rejected_at' => null,
                ]);
                DocumentPaymentProof::create([
                    'document_request_id' => $locked->id,
                    'path' => $proofPath,
                    'reference_number' => $validated['reference_number'],
                    'submitted_by' => Auth::id(),
                    'submitted_at' => now(),
                ]);

                AuditLog::create([
                    'user_id' => Auth::id(),
                    'action' => 'document_payment.submitted',
                    'description' => "Submitted GCash proof for document request #{$locked->id}.",
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'metadata' => [
                        'document_request_id' => $locked->id,
                        'payment_method' => $locked->payment_method,
                        'payment_status' => $locked->payment_status,
                        'reference_number' => $locked->reference_number,
                        'amount_due' => $locked->fee,
                    ],
                ]);

                return 'submitted';
            });
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($proofPath);
            throw $exception;
        }

        if ($result !== 'submitted') {
            Storage::disk('local')->delete($proofPath);
            $message = [
                'verified' => 'This document payment has already been verified.',
                'pending' => 'Your GCash payment is already waiting for Cashier verification.',
                'unavailable' => 'This document request is no longer accepting payment submissions.',
            ][$result];
            return back()->with('error', $message);
        }

        $documentRequest->refresh();
        foreach (User::role(['cashier', 'admin'])->get() as $cashier) {
            NotificationService::sendSafely(
                $cashier,
                'Document Payment Needs Verification',
                Auth::user()->name . ' submitted GCash proof for document request #' . $documentRequest->id . '.',
                'warning',
                route('cashier.document-payments.show', $documentRequest)
            );
        }
        NotificationService::emailDocumentRequestSafely(
            $documentRequest->user,
            $documentRequest,
            'Your GCash payment proof was submitted. The Cashier will verify it against the received funds.'
        );

        return redirect()->route('portal.payments.document', $documentRequest)->with('success', 'GCash proof submitted.');
    }

    public function submitPayment(Request $request, DocumentRequest $documentRequest)
    {
        abort_unless($documentRequest->user_id === Auth::id(), 403);
        if ($documentRequest->usesBundledPayment()) {
            throw ValidationException::withMessages(['payment' => 'This payment is managed through its appointment. Review or submit payment for the whole booking.']);
        }
        if ($documentRequest->paymentNotRequired()) {
            return redirect()->route('portal.payments.document', $documentRequest);
        }

        abort_if($documentRequest->paymentNotRequired() || $documentRequest->status === 'cancelled' || $documentRequest->is_paid || $documentRequest->payment_status === 'verified', 403);

        if ($documentRequest->payment_method !== 'gcash_qr' || $documentRequest->payment_status !== 'rejected') {
            return back()->with('error', 'Payment details can only be resubmitted after a GCash claim was rejected.');
        }

        $validated = $request->validate([
            'reference_number' => ['required', 'string', 'max:100'],
            'proof_of_payment' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'mimetypes:image/jpeg,image/png,application/pdf', 'max:5120'],
        ]);

        $path = $request->file('proof_of_payment')->store('document-request-proofs', 'local');
        try {
            DB::transaction(function () use ($documentRequest, $validated, $path, $request) {
                $locked = DocumentRequest::whereKey($documentRequest->id)->lockForUpdate()->firstOrFail();
                if ($locked->status === 'cancelled' || $locked->is_paid || $locked->payment_status !== 'rejected') {
                    throw ValidationException::withMessages(['payment' => 'This payment can no longer be changed.']);
                }
                if ($locked->proof_of_payment
                    && ! DocumentPaymentProof::where('document_request_id', $locked->id)->where('path', $locked->proof_of_payment)->exists()) {
                    DocumentPaymentProof::create([
                        'document_request_id' => $locked->id,
                        'path' => $locked->proof_of_payment,
                        'reference_number' => $locked->reference_number,
                        'submitted_at' => $locked->payment_submitted_at ?? $locked->updated_at ?? now(),
                        'status' => 'rejected',
                        'reviewed_by' => $locked->rejected_by,
                        'reviewed_at' => $locked->rejected_at,
                        'rejection_reason' => $locked->payment_rejection_reason,
                    ]);
                }

                $locked->update([
                    'reference_number' => $validated['reference_number'],
                    'proof_of_payment' => $path,
                    'payment_status' => 'pending_verification',
                    'payment_submitted_at' => now(),
                    'payment_rejection_reason' => null,
                    'rejected_by' => null,
                    'rejected_at' => null,
                ]);
                DocumentPaymentProof::create([
                    'document_request_id' => $locked->id,
                    'path' => $path,
                    'reference_number' => $validated['reference_number'],
                    'submitted_by' => Auth::id(),
                    'submitted_at' => now(),
                ]);

                AuditLog::create([
                    'user_id' => Auth::id(),
                    'action' => 'document_payment.resubmitted',
                    'description' => "GCash proof resubmitted for document request #{$locked->id}.",
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'metadata' => ['document_request_id' => $locked->id, 'amount_due' => $locked->fee],
                ]);
            });
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($path);
            throw $exception;
        }

        foreach (User::role(['cashier', 'admin'])->get() as $cashier) {
            NotificationService::sendSafely($cashier, 'Document Payment Needs Verification', Auth::user()->name . ' resubmitted GCash proof for document request #' . $documentRequest->id . '.', 'info', route('cashier.document-payments.show', $documentRequest));
        }

        NotificationService::emailDocumentRequestSafely(
            Auth::user(),
            $documentRequest->fresh(),
            'We received your updated GCash proof. Your payment is awaiting Cashier verification.'
        );

        return redirect()->route('portal.payments.document', $documentRequest)->with('success', 'Payment proof resubmitted.');
    }

    public function viewProof(Request $request, DocumentRequest $documentRequest)
    {
        abort_unless($documentRequest->user_id === Auth::id(), 403);
        $path = $documentRequest->proof_of_payment;
        abort_unless($path && Storage::disk('local')->exists($path), 404);
        $backUrl = route('portal.payments.document', $documentRequest);

        return PrivateFilePreview::respond(
            $request,
            Storage::disk('local')->path($path),
            'Payment proof',
            $backUrl,
            basename($path)
        );
    }

    public function showGcashQr()
    {
        $path = Setting::get('gcash_qr_path');
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        if (request()->boolean('download')) {
            $extension = pathinfo($path, PATHINFO_EXTENSION);
            return response()->download(Storage::disk('local')->path($path), 'DTC-GCash-QR.'.$extension, ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
        }
        return response()->file(Storage::disk('local')->path($path), [
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

}
