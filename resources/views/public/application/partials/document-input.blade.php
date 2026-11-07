<div class="document-input">

    <label for="document_{{ $group ?? 'general' }}_{{ $name }}">
        {{ $label }}
    </label>

    <div class="document-input-control @error('documents.' . $name) is-invalid @enderror">
        <label for="document_{{ $group ?? 'general' }}_{{ $name }}" class="document-input-btn">
            <i data-lucide="upload" class="mr-1" aria-hidden="true"></i>
            Choose File
        </label>

        <span class="document-input-filename" data-placeholder="No file chosen">
            No file chosen
        </span>

        <input
            type="file"
            name="documents[{{ $name }}]"
            id="document_{{ $group ?? 'general' }}_{{ $name }}"
            class="document-input-file @error('documents.' . $name) is-invalid @enderror"
            accept=".pdf,.jpg,.jpeg,.png"
        >
    </div>

    @error('documents.' . $name)
        <div class="wiz-err-msg text-danger small mt-1">
            {{ $message }}
        </div>
    @enderror

</div>
