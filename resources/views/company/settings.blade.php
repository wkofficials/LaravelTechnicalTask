@extends('layouts.master')

@section('title', 'Company Settings')
@section('page-title', 'Company Settings')

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="card" style="max-width: 40rem;">
        <form method="POST" action="{{ route('company.settings.update') }}">
            @csrf
            @method('PUT')
            <div class="card-body">
                <div class="mb-3">
                    <label for="name" class="form-label">Company name</label>
                    <input type="text" id="name" name="name" value="{{ old('name', $company->name) }}" maxlength="255"
                           class="form-control @error('name') is-invalid @enderror" required>
                    @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="mb-3">
                    <label for="timezone" class="form-label">Timezone</label>
                    <select id="timezone" name="timezone" class="form-select @error('timezone') is-invalid @enderror">
                        @foreach ($timezones as $timezone)
                            <option value="{{ $timezone }}" @selected(old('timezone', $company->timezone) === $timezone)>{{ $timezone }}</option>
                        @endforeach
                    </select>
                    <div class="form-text">Monthly usage resets at midnight on the 1st in this timezone.</div>
                    @error('timezone') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="form-check">
                    <input type="hidden" name="email_import_notifications" value="0">
                    <input class="form-check-input" type="checkbox" id="email_import_notifications" name="email_import_notifications" value="1"
                           @checked(old('email_import_notifications', $company->email_import_notifications))>
                    <label class="form-check-label" for="email_import_notifications">
                        Email the uploader when an import finishes or fails
                    </label>
                    <div class="form-text">In-app notifications are always shown. Emails never contain row data.</div>
                </div>
            </div>
            <div class="card-footer">
                <button type="submit" class="btn btn-primary">Save settings</button>
            </div>
        </form>
    </div>
@endsection
