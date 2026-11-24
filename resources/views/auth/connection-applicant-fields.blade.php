<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label role-register-label" for="first_name">First Name</label>
        <input type="text" class="form-control role-register-input" id="first_name" name="first_name" value="{{ old('first_name') }}" required>
    </div>
    <div class="col-md-6">
        <label class="form-label role-register-label" for="last_name">Last Name</label>
        <input type="text" class="form-control role-register-input" id="last_name" name="last_name" value="{{ old('last_name') }}" required>
    </div>
    <div class="col-md-6">
        <label class="form-label role-register-label" for="service_country">Country</label>
        <input type="text" class="form-control role-register-input" id="service_country" name="service_country" value="{{ $serviceArea['country'] }}" readonly required>
    </div>
    <div class="col-md-6">
        <label class="form-label role-register-label" for="service_city">City</label>
        <input type="text" class="form-control role-register-input" id="service_city" name="service_city" value="{{ $serviceArea['city'] }}" readonly required>
    </div>
    <div class="col-md-6">
        <label class="form-label role-register-label" for="service_barangay_subdivision">Barangay/Subdivision</label>
        <input type="text" class="form-control role-register-input" id="service_barangay_subdivision" name="service_barangay_subdivision" value="{{ $serviceArea['barangay_subdivision'] }}" readonly required>
    </div>
    <div class="col-md-6">
        <label class="form-label role-register-label" for="service_purok">Purok</label>
        <select class="form-select role-register-select" id="service_purok" name="service_purok" required>
            <option value="">Select your Purok</option>
            @foreach ($serviceArea['puroks'] as $purok)
                <option value="{{ $purok }}" @selected(old('service_purok') === $purok)>{{ $purok }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-6">
        <label class="form-label role-register-label" for="address_block">Block</label>
        <input type="text" class="form-control role-register-input" id="address_block" name="address_block" value="{{ old('address_block') }}" placeholder="e.g. Block 12" required>
    </div>
    <div class="col-md-6">
        <label class="form-label role-register-label" for="address_street">Street</label>
        <input type="text" class="form-control role-register-input" id="address_street" name="address_street" value="{{ old('address_street') }}" placeholder="e.g. Maharlika Street" required>
    </div>
    <div class="col-md-6">
        <label class="form-label role-register-label" for="contact_number">Contact Number</label>
        <input type="text" inputmode="numeric" pattern="[0-9]{10,15}" autocomplete="tel-national" class="form-control role-register-input js-digits-only" id="contact_number" name="contact_number" value="{{ old('contact_number') }}" placeholder="09XXXXXXXXX" required>
        <div class="form-text">Numbers only, 10 to 15 digits.</div>
    </div>
    <div class="col-md-6">
        <label class="form-label role-register-label" for="email">Email Address</label>
        <input type="email" class="form-control role-register-input" id="email" name="email" value="{{ old('email') }}" placeholder="name@example.com" required>
    </div>
</div>
