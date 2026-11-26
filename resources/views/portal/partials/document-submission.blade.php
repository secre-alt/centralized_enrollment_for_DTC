<form method="POST" action="{{ route($submissionRoute) }}" enctype="multipart/form-data" class="dr-grid" data-document-submission>
    @csrf
    <input type="hidden" name="operation_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
    @if ($errors->any())
        <div class="dr-alert dr-alert--error" style="grid-column: 1 / -1" role="alert"><strong>Please review your request.</strong><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif
    <div class="dr-form">
    <ol class="dr-steps" aria-label="Request steps">
        @foreach (['Document', 'Purpose', 'Payment'] as $stepLabel)
            <li data-request-step-indicator><span class="dr-steps-dot">{{ $loop->iteration }}</span> <span class="dr-steps-name">{{ $stepLabel }}</span></li>
        @endforeach
    </ol>
    <div class="dr-sheet"><section class="dr-sec" data-request-step="0">
    <h2 class="dr-sec-title">Which documents do you need?</h2>
    <p class="dr-sec-hint">Select one or more documents, then choose your copies. Up to 10 copies per document.</p>
    <p class="dr-field-error" data-document-selection-error role="alert" hidden>Select at least one document to continue.</p>
    <div class="dr-opts dr-document-options">
    @foreach ($documentOptions as $value => $label)
        @php
            $index = $loop->index;
            $oldDocument = collect(old('documents', []))->firstWhere('document_type', $value);
            $descriptions = ['tor' => 'Your academic record and grades.', 'transcript' => 'Your academic record and grades.', 'diploma' => 'Your graduation credential.', 'certification' => 'An official certification from the Registrar.', 'true_copy' => 'A true copy of your school records.'];
        @endphp
        <div class="dr-opt dr-opt--stack" data-document-option data-document-name="{{ $label }}" data-document-cents="{{ (int) round($documentFees[$value] * 100) }}">
            <input id="document-{{ $index }}" class="dr-opt-input dr-visually-hidden" type="checkbox" data-document-toggle {{ $oldDocument ? 'checked' : '' }}>
            <label class="dr-document-choice" for="document-{{ $index }}">
                <span class="dr-opt-icon" aria-hidden="true"><i data-lucide="{{ $value === 'diploma' ? 'award' : ($value === 'certification' ? 'badge-check' : 'file-text') }}"></i></span>
                <span class="dr-opt-text"><span class="dr-opt-name">{{ $label }}</span><span class="dr-opt-desc">{{ $label === 'True Copy of Records' ? $descriptions['true_copy'] : $descriptions[$value] }}</span></span>
                <span class="dr-opt-price">{{ $documentFees[$value] == 0 ? 'Free' : '₱'.number_format($documentFees[$value], 2) }}<small>per copy</small></span>
                <span class="dr-opt-mark" aria-hidden="true"></span>
            </label>
            <div class="dr-sec" data-document-controls hidden>
            <div class="dr-sec-hint">
                <strong>Requirements from the Citizen's Charter:</strong>
                @include('portal.partials.document-requirements', ['documentType' => $value === 'tor' && $label === 'True Copy of Records' ? 'true_copy' : $value])
                Present required originals to the Registrar. Submitting this form does not confirm that your requirements have been checked; contact the Registrar for clearance instructions and to confirm what to bring.
            </div>
            <input type="hidden" name="documents[{{ $index }}][document_type]" value="{{ $value }}" data-document-field disabled>
            <div class="dr-copy-row">
            <label for="copies-{{ $index }}">Copies <small>Maximum 10</small></label>
            <div class="dr-copy-picker">
            <button type="button" data-document-copy-step="-1" aria-label="Remove one {{ $label }} copy" disabled>−</button>
            <input id="copies-{{ $index }}" class="dr-input" type="number" name="documents[{{ $index }}][copies]" value="{{ $oldDocument['copies'] ?? 1 }}" min="1" max="10" required data-document-field data-document-copies aria-describedby="copies-error-{{ $index }}" disabled>
            <button type="button" data-document-copy-step="1" aria-label="Add one {{ $label }} copy" disabled>+</button>
            </div></div>
            <span id="copies-error-{{ $index }}" class="dr-field-error">{{ $errors->first('documents.'.$index.'.copies') }}</span>
            <div class="dr-document-payment" data-document-payment-section>
            <p class="dr-payment-amount" data-document-payment-amount aria-live="polite"></p>
            @if ($documentFees[$value] > 0)
                <fieldset class="dr-payment-methods" aria-describedby="payment-hint-{{ $index }}">
                    <legend>Choose how to pay</legend>
                    <label><input type="radio" name="documents[{{ $index }}][payment_method]" value="walk_in" data-document-field data-document-payment {{ ($oldDocument['payment_method'] ?? 'walk_in') !== 'gcash_qr' || !$gcashQrReady ? 'checked' : '' }} disabled> Cash at Cashier</label>
                    @if ($gcashQrReady)
                        <label><input type="radio" name="documents[{{ $index }}][payment_method]" value="gcash_qr" data-document-field data-document-payment {{ ($oldDocument['payment_method'] ?? '') === 'gcash_qr' ? 'checked' : '' }} disabled> GCash</label>
                    @endif
                </fieldset>
                <p id="payment-hint-{{ $index }}" class="dr-sec-hint" data-document-payment-hint>Pay at the school Cashier. Your payment status updates after verification.</p>
                @error('documents.'.$index.'.payment_method')<p class="dr-field-error">{{ $message }}</p>@enderror
                <div data-document-gcash hidden>
                    <section class="dr-gcash-step" aria-labelledby="pay-title-{{ $index }}">
                    <h4 id="pay-title-{{ $index }}">1. Pay the document amount</h4>
                    @if ($gcashQrReady)
                        @include('portal.partials.document-gcash-account', ['gcashAmount' => null])
                    @endif
                    </section>
                    <section class="dr-gcash-step" aria-labelledby="qr-title-{{ $index }}">
                    <h4 id="qr-title-{{ $index }}">2. Scan or save the QR</h4>
                    @if ($gcashQrReady)
                        <div class="dr-gcash-scan">
                        <img src="{{ route($qrRoute) }}" alt="Official DTC GCash QR code" width="192" height="192" class="dr-payment-qr">
                        <a class="dr-gcash-save" href="{{ route($qrRoute, ['download' => 1]) }}">Download QR</a>
                        </div>
                    @endif
                    <p class="dr-sec-hint">Using this phone? Save the QR and upload it in GCash.</p>
                    </section>
                    <section class="dr-gcash-step" aria-labelledby="proof-title-{{ $index }}">
                    <h4 id="proof-title-{{ $index }}">3. Enter payment details</h4>
                    <label for="reference-{{ $index }}">GCash reference number</label>
                    <input id="reference-{{ $index }}" class="dr-input" name="documents[{{ $index }}][reference_number]" maxlength="100" value="{{ $oldDocument['reference_number'] ?? '' }}" data-document-proof aria-describedby="reference-error-{{ $index }}" disabled>
                    <span id="reference-error-{{ $index }}" class="dr-field-error">{{ $errors->first('documents.'.$index.'.reference_number') }}</span>
                    <label for="proof-{{ $index }}">Upload payment screenshot</label>
                    <div class="dr-payment-upload" data-document-upload>
                        <input id="proof-{{ $index }}" class="dr-visually-hidden" type="file" name="documents[{{ $index }}][proof_of_payment]" accept="image/jpeg,image/png,application/pdf" data-document-proof aria-describedby="proof-hint-{{ $index }} proof-error-{{ $index }}" disabled>
                        <label for="proof-{{ $index }}" class="dr-upload-choice" data-upload-choice>Choose file</label>
                        <span class="dr-upload-filename" data-upload-filename aria-live="polite">No file selected</span>
                        <button type="button" class="dr-gcash-copy" data-upload-remove hidden>Remove</button>
                    </div>
                    <span id="proof-error-{{ $index }}" class="dr-field-error">{{ $errors->first('documents.'.$index.'.proof_of_payment') }}</span>
                    <p id="proof-hint-{{ $index }}" class="dr-sec-hint">JPG, PNG or PDF, up to 5 MB. Re-select files after a validation error.</p>
                    </section>
                    <p class="dr-sec-hint">Cashier verification is required. Pay separately for each document.</p>
                </div>
            @else
                <p>No Payment Required.</p>
            @endif
            </div>
            </div>
        </div>
    @endforeach
    </div>
    </section>
    <section class="dr-sec" data-request-step="1" hidden>
    <h2 class="dr-sec-title">What are the documents for?</h2>
    <div class="dr-field">
        <label for="document-purpose">Purpose (optional)</label>
        <textarea id="document-purpose" class="dr-textarea" name="purpose" maxlength="500">{{ old('purpose') }}</textarea>
        @error('purpose')<p class="dr-field-error">{{ $message }}</p>@enderror
    </div>
    </section>
    <section class="dr-sec" data-request-step="2" hidden>
        <h2 class="dr-sec-title">Payment</h2>
        <p class="dr-sec-hint">Choose a payment method for each document. Free documents need no payment.</p>
        <div data-request-payments></div>
    </section></div></div>
    <aside class="dr-aside" aria-label="Request information">
        <div class="dr-slip">
            <details class="dr-slip-body" data-request-details>
                <summary class="dr-slip-title">View request summary</summary>
                <div data-document-summary aria-live="polite" aria-atomic="true"><p>Select documents to see your estimate.</p></div>
                <dl class="dr-slip-rows">
                    <div class="dr-slip-row"><dt>Payment</dt><dd>Separate payment for each payable document</dd></div>
                    <div class="dr-slip-row"><dt>Collection</dt><dd>Wait for the Registrar's ready notification. Bring a valid ID when collecting.</dd></div>
                </dl>
                <p class="dr-slip-note">Estimate only. Final fees are calculated by the server; payment remains subject to Cashier verification.</p>
            </details>
            <div class="dr-slip-tear" aria-hidden="true"></div>
            <div class="dr-slip-foot">
                <p class="dr-mobile-total" data-mobile-request-total aria-live="polite" aria-atomic="true">Select your documents</p>
                <button type="button" class="dr-slip-back" data-request-back hidden>Back</button>
                <button type="button" class="dr-btn dr-btn--gold" data-request-next>Next: Purpose</button>
                <button class="dr-btn dr-btn--gold" type="submit" data-document-submit hidden>Submit request</button></div>
        </div>
        <details class="dr-sheet dr-sec dr-collection" data-request-details>
            <summary class="dr-sec-title">What happens next</summary>
            <ol>
                <li>Submit your document requests.</li>
                <li>Present required documents for Registrar checking. Track requirement and fee assessment in My document requests.</li>
                <li>Complete payment for each payable document, then wait for preparation. Contact the Registrar for the applicable working-day lead time.</li>
                <li>Watch for an email and in-app notification when ready.</li>
                <li>Visit the Registrar during approved office hours.</li>
                <li>Present a valid ID and bring your claim stub if one was issued, plus your receipt if payment was required.</li>
                <li>The Registrar records the physical handover.</li>
            </ol>
            <p>No claiming appointment is required. You can collect ready documents separately; uncollected documents remain available.</p>
            @foreach (['registrar_office' => 'Office', 'registrar_hours' => 'Office hours'] as $key => $label)
                @if (filled($detail = \App\Models\Setting::get($key)))<p><strong>{{ $label }}:</strong> {{ $detail }}</p>@endif
            @endforeach
        </details>
    </aside>
    <noscript><p>JavaScript is required to select documents in this form.</p></noscript>
</form>
