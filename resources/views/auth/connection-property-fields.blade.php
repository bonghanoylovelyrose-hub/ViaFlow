<div class="col-md-6">
    <label class="form-label role-register-label" for="property_type">Type of Property</label>
    <select class="form-select role-register-select" id="property_type" name="property_type" required>
        <option value="">Select property type</option>
        <option value="Residential" @selected(old('property_type') === 'Residential')>Residential</option>
        <option value="Commercial" @selected(old('property_type') === 'Commercial')>Commercial</option>
    </select>
</div>
<div class="col-md-6">
    <label class="form-label role-register-label" for="ownership_status">Ownership Status</label>
    <select class="form-select role-register-select" id="ownership_status" name="ownership_status" required>
        <option value="">Select ownership status</option>
        <option value="Owner" @selected(old('ownership_status') === 'Owner')>Owner</option>
        <option value="Tenant" @selected(old('ownership_status') === 'Tenant')>Tenant</option>
    </select>
</div>
