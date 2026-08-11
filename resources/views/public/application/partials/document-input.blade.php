<div class="document-input">

    <label for="document_{{ $name }}">
        {{ $label }}
    </label>

    @if (!empty($description))
        <div class="text-muted small mb-2">
            {{ $description }}
        </div>
    @endif

    <input
        type="file"
        name="documents[{{ $name }}]"
        id="document_{{ $name }}"
        class="form-control-file @error('documents.' . $name) is-invalid @enderror"
        accept=".pdf,.jpg,.jpeg,.png"
    >

    <small class="form-text text-muted">
        PDF, JPG, JPEG, or PNG. Maximum file size: 5 MB.
        Uploading is optional.
    </small>

    @error('documents.' . $name)
        <div class="text-danger small mt-1">
            {{ $message }}
        </div>
    @enderror

</div>