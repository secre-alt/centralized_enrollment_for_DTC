<div class="modal fade" id="assessmentModal{{ $document->id }}" tabindex="-1" aria-labelledby="assessmentTitle{{ $document->id }}" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
        <form method="POST" action="{{ route('registrar.documents.assessment', $document) }}">
            @csrf
            <input type="hidden" name="operation_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
            <input type="hidden" name="assessment_version" value="{{ $document->assessment_version }}">
            <div class="modal-header">
                <h5 class="modal-title" id="assessmentTitle{{ $document->id }}">Assess request D-{{ $document->id }}</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">&times;</button>
            </div>
            <div class="modal-body">
                <p>{{ $document->user->name }} · {{ $document->document_label }} × {{ $document->copies }}</p>
                <p><strong>Charter requirements:</strong> @include('portal.partials.document-requirements', ['documentType' => $document->document_type])</p>
                <p>Check which requirements apply to this request and record missing items below. This is a manual Registrar check.</p>
                <div class="form-group">
                    <label for="assessment-status-{{ $document->id }}">Requirements</label>
                    <select id="assessment-status-{{ $document->id }}" name="assessment_status" class="form-control" required>
                        <option value="incomplete" @selected($document->assessment_status !== 'complete')>Incomplete — follow-up needed</option>
                        <option value="complete" @selected($document->assessment_status === 'complete')>Complete — checked by Registrar</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="requirements-notes-{{ $document->id }}">Requirements notes (required when incomplete)</label>
                    <textarea id="requirements-notes-{{ $document->id }}" class="form-control" name="requirements_notes" rows="3" maxlength="1000">{{ $document->requirements_notes }}</textarea>
                    <small class="form-text text-muted">Visible to the requester. Describe the missing requirement and next step.</small>
                </div>
                <p><strong>Stored configured fee: ₱{{ number_format($document->fee, 2) }}</strong></p>
                <p>This assessment confirms the saved fee; it does not reprice this request or change an existing payment. Refer pricing discrepancies to Cashier/Admin before preparation.</p>
                <div class="form-check">
                    <input id="fee-confirmed-{{ $document->id }}" type="checkbox" class="form-check-input" name="fee_confirmed" value="1">
                    <label class="form-check-label" for="fee-confirmed-{{ $document->id }}">I checked and confirm this stored fee (required for a complete assessment).</label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Save assessment</button>
            </div>
        </form>
    </div></div>
</div>
