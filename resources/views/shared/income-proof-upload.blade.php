<div class="income-upload" data-income-upload>
    <label class="form-label fw-semibold" for="{{ $uploadId }}">{{ $uploadLabel ?? 'Proof of Income' }}</label>
    <input class="form-control" type="file" name="proof_of_income" id="{{ $uploadId }}" accept="image/jpeg,image/png,application/pdf,.jpg,.jpeg,.png,.pdf" required aria-describedby="{{ $uploadId }}-help {{ $uploadId }}-status">
    <div class="loan-field-help" id="{{ $uploadId }}-help">JPG, PNG, or PDF, up to 10 MB.</div>
    <div class="income-upload-preview mt-2" data-preview hidden>
        <div class="small text-secondary mb-2" data-metadata></div>
        <button type="button" class="income-preview-button" data-enlarge hidden aria-label="Enlarge proof of income"><img data-image alt="Selected proof of income preview"></button>
        <iframe data-pdf title="Selected proof of income PDF" hidden></iframe>
        <div class="small mt-2" id="{{ $uploadId }}-status" data-quality role="status" aria-live="polite"></div>
        <div class="small text-secondary mt-1">Image quality is only an estimate; an administrator must verify the document and income.</div>
        <button type="button" class="btn btn-sm btn-outline-primary mt-2" data-replace>Replace File</button>
    </div>
    <div class="small text-danger mt-1" data-error role="alert"></div>
</div>
