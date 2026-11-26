<?php

namespace App\Http\Controllers\Registrar;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\DocumentRequest;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DocumentRequestController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:200'],
            'claimant_id' => ['nullable', 'integer', 'exists:users,id'],
            'status' => ['nullable', 'in:all,submitted,processing,ready,released,cancelled'],
            'document_type' => ['nullable', 'in:tor,true_copy,certification,diploma'],
            'payment_status' => ['nullable', 'in:unpaid,pending_verification,verified,rejected,no_payment_required'],
            'pickup_from' => ['nullable', 'date_format:Y-m-d'],
            'pickup_to' => array_merge(['nullable', 'date_format:Y-m-d'], $request->filled('pickup_from') ? ['after_or_equal:pickup_from'] : []),
        ]);
        $search = trim($filters['search'] ?? '');
        $status = $filters['status'] ?? 'all';
        $claimant = isset($filters['claimant_id']) ? \App\Models\User::findOrFail($filters['claimant_id']) : null;
        $query = DocumentRequest::query();
        if ($claimant) $query->where('user_id', $claimant->id);
        if ($search !== '') {
            $documentTypes = collect(['tor' => 'Transcript of Records', 'true_copy' => 'True Copy of Records', 'certification' => 'Certification', 'diploma' => 'Diploma'])
                ->filter(fn ($label) => stripos($label, $search) !== false)->keys()->all();
            $query->where(function ($matches) use ($search, $documentTypes) {
                $matches->whereHas('user', fn ($user) => $user->where('name', 'like', '%'.$search.'%')->orWhere('email', 'like', '%'.$search.'%'))
                    ->orWhere('purpose', 'like', '%'.$search.'%')
                    ->orWhere('document_type', 'like', '%'.$search.'%');
                if ($documentTypes) $matches->orWhereIn('document_type', $documentTypes);
            });
        }
        if (! empty($filters['document_type'])) {
            $query->where('document_type', $filters['document_type']);
        }
        if (! empty($filters['payment_status'])) {
            if ($filters['payment_status'] === 'no_payment_required') {
                $query->where('fee', 0)->where('is_paid', false)->where('payment_status', '!=', 'verified');
            } else {
                $query->where('payment_status', $filters['payment_status']);
                if ($filters['payment_status'] === 'unpaid') {
                    $query->where(fn ($paid) => $paid->whereNull('fee')->orWhere('fee', '!=', 0)->orWhere('is_paid', true));
                }
            }
        }
        if (! empty($filters['pickup_from']) || ! empty($filters['pickup_to'])) {
            $query->whereHas('appointment.slot', function ($slots) use ($filters) {
                if (! empty($filters['pickup_from'])) {
                    $slots->where('date', '>=', $filters['pickup_from']);
                }
                if (! empty($filters['pickup_to'])) {
                    $slots->where('date', '<=', $filters['pickup_to']);
                }
            });
        }
        $statusCounts = (clone $query)->selectRaw('status, COUNT(*) AS aggregate')->groupBy('status')->pluck('aggregate', 'status');
        $allCount = $statusCounts->sum();
        if ($status !== 'all') $query->where('status', $status);
        $requests = $query->with(['user', 'releaser', 'assessor', 'appointment.slot', 'appointmentItems.appointment'])
            ->latest()
            ->paginate(\App\Support\TablePagination::perPage(10))->withQueryString();

        $releaseGroups = DocumentRequest::with('user')->whereIn('user_id', $requests->getCollection()->pluck('user_id'))
            ->where('status', 'ready')->orderBy('id')->get()->groupBy('user_id');

        return view('registrar.documents.index', compact('requests', 'statusCounts', 'allCount', 'status', 'claimant', 'releaseGroups'));
    }

    public function releaseWalkIn(Request $request)
    {
        $validated = $request->validate([
            'claimant_id' => ['required', 'integer', 'exists:users,id'],
            'document_ids' => ['required', 'array', 'min:1', 'max:100'],
            'document_ids.*' => ['required', 'integer', 'distinct'],
            'handover_confirmed' => ['required', 'accepted'],
        ]);
        $queued = app(\App\Services\WalkInDocumentReleaseService::class)->release($request, (int) $validated['claimant_id'], $validated['document_ids']);
        return redirect()->route('registrar.documents.index')
            ->with('success', 'Selected documents physically released.'.($queued ? '' : ' Email delivery needs retry; release is recorded.'));
    }

    public function assess(Request $request, DocumentRequest $documentRequest)
    {
        app(\App\Services\DocumentAssessmentService::class)->assess($request, $documentRequest);

        return redirect()->route('registrar.documents.index', ['claimant_id' => $documentRequest->user_id])
            ->with('success', 'Document assessment recorded.');
    }

    public function updateStatus(Request $request, DocumentRequest $documentRequest)
    {
        $validated = $request->validate([
            'status'  => ['required', 'in:submitted,processing,ready,released'],
            'remarks' => ['nullable', 'string', 'max:500'],
        ]);

        if ($validated['status'] === 'released') {
            throw ValidationException::withMessages(['status' => 'Use physical collection with exact document selection and confirmed handover to release documents.']);
        }
        if ($validated['status'] === 'ready') {
            $queued = app(\App\Services\DocumentReadyService::class)->mark($request, $documentRequest);
            return redirect()->route('registrar.documents.index')->with('success', 'Request status updated.'.($queued ? '' : ' Email could not be queued; retry is available.'));
        }

        $messages = [
            'processing' => 'Your document request is now being processed.',
            'released' => 'Your document has been released. Thank you!',
        ];

        $operation = function () use ($request, $documentRequest, $validated) {
                $locked = DocumentRequest::whereKey($documentRequest->id)->lockForUpdate()->firstOrFail();
                $next = $validated['status'];
                $allowed = [
                    'submitted' => 'processing',
                    'processing' => 'ready',
                    'ready' => 'released',
                ];

                if (($allowed[$locked->status] ?? null) !== $next) {
                    throw ValidationException::withMessages([
                        'status' => $locked->status === $next
                            ? 'This request already has that status.'
                            : 'Invalid status change. Requests must move from submitted to processing, then ready, then released.',
                    ]);
                }

                $previous = $locked->status;
                if (! $locked->assessmentSatisfied()) {
                    throw ValidationException::withMessages(['assessment' => 'Complete the requirement and fee assessment before preparation.']);
                }
                app(\App\Services\DocumentRequestPricing::class)->auditNoPayment($locked, $request);
                $locked->update([
                    'status' => $next,
                    'remarks' => $validated['remarks'] ?? $locked->remarks,
                ]);

                AuditLog::create([
                    'user_id' => auth()->id(),
                    'action' => 'document_request.status_changed',
                    'description' => "Document request #{$locked->id} status changed from {$previous} to {$next}.",
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'metadata' => [
                        'document_request_id' => $locked->id,
                        'previous_status' => $previous,
                        'new_status' => $next,
                        'remarks' => $validated['remarks'] ?? null,
                    ],
                ]);

                return $locked->fresh(['user']);
        };
        $change = DB::transaction($operation);
        $message = $messages[$change->status];
        $tone = 'info';
        $destination = route('portal.documents.index');
        if (!$change->user->hasAnyRole(['student', 'alumni'])) $destination = route('portal.dashboard');
        $notificationSaved = NotificationService::sendSafely($change->user, 'Document Request Update', $message, $tone, $destination);
        $emailQueued = NotificationService::emailDocumentRequestSafely($change->user, $change, $message);

        return redirect()->route('registrar.documents.index')
            ->with('success', 'Request status updated.' . (! $notificationSaved ? ' Applicant in-app notification could not be saved; the failure was logged.' : '') . (! $emailQueued ? ' Email could not be queued; the failure was logged.' : ''));
    }

}
