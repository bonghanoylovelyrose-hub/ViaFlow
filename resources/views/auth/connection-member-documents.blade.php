<div class="register-section">
    <div class="register-section-title">{{ $membershipDocumentHeading ?? '4. Documents Submitted' }} @unless ($allowMissingDocuments ?? false)<span class="register-required-badge">Required</span>@endunless</div>
    <p class="register-section-copy">Select one supporting document type, then attach clear JPG or PNG photos of the selected document.</p>
    <div class="register-checklist">
        <div class="form-check">
            <input class="form-check-input" type="radio" value="Housing Loan Contract (Pag-IBIG)" id="membership_document_housing" name="membership_document_type" @checked(old('membership_document_type', $selectedDocumentType ?? null) === 'Housing Loan Contract (Pag-IBIG)') @required(! ($allowMissingDocuments ?? false))>
            <label class="form-check-label" for="membership_document_housing">Housing Loan Contract (Pag-IBIG)</label>
        </div>
        <div class="form-check">
            <input class="form-check-input" type="radio" value="Proof of Residency" id="membership_document_residency" name="membership_document_type" @checked(old('membership_document_type', $selectedDocumentType ?? null) === 'Proof of Residency') @required(! ($allowMissingDocuments ?? false))>
            <label class="form-check-label" for="membership_document_residency">Proof of Residency</label>
        </div>
        @error('membership_document_type')
            <div class="text-danger small">{{ $message }}</div>
        @enderror
        <div class="document-upload-panel" id="membership_document_upload_panel">
            <label class="form-label fw-semibold" for="membership_document_photos">Selected document photos @unless ($allowMissingDocuments ?? false)<span class="text-danger">*</span>@endunless</label>
            <input
                type="file"
                class="form-control"
                id="membership_document_photos"
                name="membership_document_photos[]"
                accept=".jpg,.jpeg,.png,image/jpeg,image/png"
                multiple
                @required(! ($allowMissingDocuments ?? false))
            >
            <div class="form-text">Upload one or more clear JPG, JPEG, or PNG photos. Use multiple photos for documents with several pages.</div>
            <div class="text-danger small mt-2" id="membership_document_photo_error" style="display:none;"></div>
            @error('membership_document_photos')
                <div class="text-danger small mt-2">{{ $message }}</div>
            @enderror
            @error('membership_document_photos.*')
                <div class="text-danger small mt-2">{{ $message }}</div>
            @enderror
            <div class="document-preview-grid mt-3" id="membership_document_preview_grid"></div>
        </div>
        @if ($showOtherDocument ?? true)
            <div>
                <label class="form-label fw-semibold" for="other_document_details">Other Document</label>
                <input type="text" class="form-control" id="other_document_details" name="other_document_details" value="{{ old('other_document_details') }}" placeholder="Optional: write any other document you will submit">
            </div>
        @endif
    </div>
</div>
