<div class="col-12 mt-3">
    <div class="role-register-section-title">Upload Valid ID @unless ($allowMissingDocuments ?? false)<span class="text-danger">(Required)</span>@endunless</div>
    <div class="role-register-section-copy">Upload one clear JPG, PNG, or PDF file.</div>
</div>
<div class="col-12">
    <label class="form-label role-register-label" for="valid_id_file">Valid ID @unless ($allowMissingDocuments ?? false)<span class="text-danger">*</span>@endunless</label>
    <input type="file" class="form-control role-register-input" id="valid_id_file" name="valid_id_file" accept=".jpg,.jpeg,.png,.pdf,application/pdf" @required(! ($allowMissingDocuments ?? false))>
</div>
