<div class="mb-3">
    <label for="name" class="form-label">Name</label>
    <input
        type="text"
        name="name"
        id="name"
        class="form-control @error('name') is-invalid @enderror"
        value="{{ old('name', $record->name) }}"
        required
    >
    @error('name')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="mb-3">
    <label for="email" class="form-label">Email</label>
    <input
        type="email"
        name="email"
        id="email"
        class="form-control @error('email') is-invalid @enderror"
        value="{{ old('email', $record->email) }}"
        required
    >
    @error('email')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="mb-3">
    <label for="phone" class="form-label">Phone</label>
    <input
        type="text"
        name="phone"
        id="phone"
        class="form-control @error('phone') is-invalid @enderror"
        value="{{ old('phone', $record->phone) }}"
    >
    @error('phone')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="mb-3">
    <label for="company" class="form-label">Company</label>
    <input
        type="text"
        name="company"
        id="company"
        class="form-control @error('company') is-invalid @enderror"
        value="{{ old('company', $record->company) }}"
    >
    @error('company')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="mb-3">
    <label for="city" class="form-label">City</label>
    <input
        type="text"
        name="city"
        id="city"
        class="form-control @error('city') is-invalid @enderror"
        value="{{ old('city', $record->city) }}"
    >
    @error('city')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="mb-3">
    <label for="country" class="form-label">Country</label>
    <input
        type="text"
        name="country"
        id="country"
        class="form-control @error('country') is-invalid @enderror"
        value="{{ old('country', $record->country) }}"
    >
    @error('country')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>
