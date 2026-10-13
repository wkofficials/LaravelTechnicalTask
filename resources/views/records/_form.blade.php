{{-- Fields come from the dataset definition. Expects $dataset and $record. --}}
@foreach ($dataset->fields() as $field)
    <div class="mb-3">
        <label for="{{ $field->key }}" class="form-label">
            {{ $field->label }}
            @if ($field->required)
                <span class="text-danger" aria-hidden="true">*</span>
            @endif
        </label>
        <input
            type="{{ $field->type === $field::TYPE_EMAIL ? 'email' : 'text' }}"
            name="{{ $field->key }}"
            id="{{ $field->key }}"
            class="form-control @error($field->key) is-invalid @enderror"
            value="{{ old($field->key, $record->getAttribute($field->key)) }}"
            maxlength="{{ $field->maxLength }}"
            @required($field->required)
        >
        @error($field->key)
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
@endforeach
