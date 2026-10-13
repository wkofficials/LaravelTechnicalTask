{{-- Column mapping + duplicate policy confirmation. Expects $import and $mappingForm. --}}
@php
    /** @var \App\Datasets\Dataset $dataset */
    $dataset = $mappingForm['dataset'];
    $mappingValue = fn (string $field) => old("mapping.$field", $mappingForm['suggested'][$field] ?? null);
    $selectedPolicy = old('duplicate_policy', $mappingForm['policy']->value);
@endphp

<div class="card mb-4 border-primary">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h3 class="card-title mb-0">Match columns to {{ $dataset->name() }} fields</h3>

        @if ($mappingForm['templates']->isNotEmpty())
            <form method="GET" action="{{ route('imports.show', $import) }}" class="d-flex gap-2 align-items-center">
                <label for="template" class="small text-secondary text-nowrap">Use template</label>
                <select id="template" name="template" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">— Suggested from headings —</option>
                    @foreach ($mappingForm['templates'] as $template)
                        <option value="{{ $template->id }}" @selected($mappingForm['template']?->id === $template->id)>{{ $template->name }}</option>
                    @endforeach
                </select>
                <noscript><button class="btn btn-sm btn-outline-secondary">Apply</button></noscript>
            </form>
        @endif
    </div>

    <form method="POST" action="{{ route('imports.confirm', $import) }}" id="mapping-form">
        @csrf
        @if ($mappingForm['template'])
            <input type="hidden" name="template_id" value="{{ $mappingForm['template']->id }}">
        @endif

        <div class="card-body">
            @foreach ($mappingForm['warnings'] as $warning)
                <div class="alert alert-warning">{{ $warning }}</div>
            @endforeach

            @if ($errors->any())
                <div class="alert alert-danger">
                    @error('quota')
                        {{ $message }}
                    @else
                        Please fix the highlighted fields below.
                    @enderror
                    @error('save_template') <div>{{ $message }}</div> @enderror
                </div>
            @endif

            <p class="text-secondary">
                We suggested a column for each field from the file's headings &mdash; please check them.
                Columns you don't choose are not imported.
            </p>

            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th style="width: 25%">Field</th>
                            <th style="width: 35%">Column in your file</th>
                            <th>Sample values</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($dataset->fields() as $field)
                            @php $selected = $mappingValue($field->key); @endphp
                            <tr>
                                <td>
                                    <label for="map-{{ $field->key }}" class="fw-semibold mb-0">{{ $field->label }}</label>
                                    @if ($field->required)
                                        <span class="badge text-bg-danger ms-1">required</span>
                                    @endif
                                    @if ($field->key === $dataset->uniqueField())
                                        <span class="badge text-bg-secondary ms-1" title="Used to recognise existing records">unique</span>
                                    @endif
                                </td>
                                <td>
                                    <select id="map-{{ $field->key }}" name="mapping[{{ $field->key }}]"
                                            class="form-select form-select-sm mapping-select @error("mapping.{$field->key}") is-invalid @enderror"
                                            data-field="{{ $field->key }}" @required($field->required)>
                                        <option value="">{{ $field->required ? '— Choose a column —' : "— Don't import —" }}</option>
                                        @foreach ($mappingForm['columns'] as $index => $column)
                                            <option value="{{ $index }}" @selected($selected !== null && (int) $selected === $index)>{{ $column['label'] }}</option>
                                        @endforeach
                                    </select>
                                    @error("mapping.{$field->key}")
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </td>
                                <td class="small text-secondary sample-values" data-field="{{ $field->key }}"></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <fieldset class="mb-3">
                <legend class="fs-6 fw-semibold">
                    When a row's {{ Str::lower($dataset->field($dataset->uniqueField())->label) }} already exists
                </legend>
                @foreach ($mappingForm['policies'] as $policy)
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="duplicate_policy" id="policy-{{ $policy->value }}"
                               value="{{ $policy->value }}" @checked($selectedPolicy === $policy->value)>
                        <label class="form-check-label" for="policy-{{ $policy->value }}">
                            <strong>{{ $policy->label() }}</strong> &mdash; {{ $policy->description() }}
                        </label>
                    </div>
                @endforeach
                <div class="form-text">
                    Rows repeating a {{ Str::lower($dataset->field($dataset->uniqueField())->label) }} that appears earlier in the same file are never imported twice:
                    the first one wins. Valid rows are imported even if other rows have errors.
                </div>
            </fieldset>

            @can('manage templates')
                <div class="border rounded p-3 bg-body-tertiary">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="save_template" value="1" id="save-template"
                               @checked(old('save_template'))>
                        <label class="form-check-label" for="save-template">Save this mapping as a template for future files</label>
                    </div>
                    <div class="mt-2" id="template-name-wrap">
                        <input type="text" name="template_name" value="{{ old('template_name') }}" maxlength="100"
                               class="form-control form-control-sm @error('template_name') is-invalid @enderror"
                               placeholder="Template name, e.g. Monthly supplier list" aria-label="Template name"
                               style="max-width: 24rem;">
                        @error('template_name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            @endcan
        </div>

        <div class="card-footer d-flex justify-content-between align-items-center">
            <span class="text-secondary small">
                {{ $import->total_rows === null ? '' : number_format($import->total_rows).' data rows in the file.' }}
            </span>
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-check2-circle"></i> Confirm &amp; start import
            </button>
        </div>
    </form>
</div>

<div class="card mb-4">
    <div class="card-header">
        <h3 class="card-title mb-0">File preview (first {{ count($import->preview_rows ?? []) }} rows)</h3>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-bordered mb-0 small">
                <thead class="table-light">
                    <tr>
                        <th class="text-secondary">Row</th>
                        @foreach ($mappingForm['columns'] as $column)
                            <th>{{ $column['label'] }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($import->preview_rows ?? [] as $i => $row)
                        <tr>
                            <td class="text-secondary">{{ $i + 2 }}</td>
                            @foreach (array_keys($mappingForm['columns']) as $index)
                                <td>{{ $row[$index] ?? '' }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const samples = @json(collect($mappingForm['columns'])->map(fn ($c) => $c['samples']));
        const selects = document.querySelectorAll('.mapping-select');

        // Show sample values for the chosen column and flag columns used twice.
        function refresh() {
            const counts = {};
            selects.forEach(function (select) {
                if (select.value !== '') {
                    counts[select.value] = (counts[select.value] || 0) + 1;
                }
            });

            selects.forEach(function (select) {
                const cell = document.querySelector('.sample-values[data-field="' + select.dataset.field + '"]');
                const values = select.value === '' ? [] : (samples[select.value] || []);
                cell.textContent = select.value === '' ? '' : (values.length ? values.join(' · ') : '(empty in preview)');

                const duplicated = select.value !== '' && counts[select.value] > 1;
                select.classList.toggle('border-warning', duplicated);
                select.title = duplicated ? 'This column is chosen for more than one field.' : '';
            });
        }

        selects.forEach(function (select) { select.addEventListener('change', refresh); });
        refresh();

        const saveTemplate = document.getElementById('save-template');
        const nameWrap = document.getElementById('template-name-wrap');
        if (saveTemplate && nameWrap) {
            const toggle = function () { nameWrap.classList.toggle('d-none', !saveTemplate.checked); };
            saveTemplate.addEventListener('change', toggle);
            toggle();
        }
    });
</script>
@endpush
