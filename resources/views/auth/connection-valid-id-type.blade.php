<div class="col-md-6">
    <label class="form-label role-register-label" for="valid_id_presented">Valid ID Presented</label>
    <input type="text" class="form-control role-register-input" id="valid_id_presented" name="valid_id_presented" value="{{ old('valid_id_presented') }}" placeholder="Example: National ID, Driver's License" @required(! ($allowMissingDocuments ?? false))>
</div>
