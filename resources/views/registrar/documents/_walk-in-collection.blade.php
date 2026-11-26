@hasanyrole('registrar|admin')
<div class="modal fade dq-release" id="releaseModal{{ $claimant->id }}" tabindex="-1" aria-labelledby="releaseTitle{{ $claimant->id }}" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable" role="document">
        <form class="modal-content" method="POST" action="{{ route('registrar.documents.walk-in-release') }}" data-release-form>
            @csrf
            <input type="hidden" name="operation_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
            <input type="hidden" name="claimant_id" value="{{ $claimant->id }}">
            <div class="modal-header">
                <h5 class="modal-title" id="releaseTitle{{ $claimant->id }}">Release documents</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">&times;</button>
            </div>
            <div class="modal-body">
                <div class="dq-release-claimant">
                    <strong>{{ $claimant->name }}</strong>
                    <span>{{ $claimant->email }}</span>
                </div>
                <fieldset>
                    <legend>Select the documents being handed over</legend>
                    @foreach ($collectionDocuments as $document)
                        <label class="dq-release-choice">
                            <input type="checkbox" name="document_ids[]" value="{{ $document->id }}" @disabled(!$document->paymentSatisfied() || !$document->assessmentSatisfied())>
                            <span>
                                <strong>{{ $document->document_label }} &times; {{ $document->copies }}</strong>
                                <small>D-{{ $document->id }} &middot; {{ !$document->assessmentSatisfied() ? 'Assessment required' : ($document->paymentNotRequired() ? 'No Payment Required' : ($document->paymentSatisfied() ? 'Payment verified' : 'Awaiting payment verification')) }}</small>
                                @if ($document->pending_claim_appointment_id)<small>Historical reservation #{{ $document->pending_claim_appointment_id }} will be checked on release.</small>@endif
                            </span>
                        </label>
                    @endforeach
                </fieldset>
                <label class="dq-release-confirm">
                    <input type="checkbox" name="handover_confirmed" value="1" required>
                    <span>I verified the claimant's identity and handed over the selected documents.</span>
                </label>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" type="button" data-dismiss="modal">Cancel</button>
                <button class="btn btn-primary" type="submit" data-release-submit disabled>Release documents</button>
            </div>
        </form>
    </div>
</div>
@endhasanyrole
