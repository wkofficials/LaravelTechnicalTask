@extends('layouts.master')

@section('title', 'Import Records')
@section('page-title', 'Import Records')

@section('content')
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Upload a spreadsheet</h3>
        </div>
        <div class="card-body">
            <p class="text-secondary">
                Upload an .xlsx, .xls, or .csv file (up to {{ $maxFileSizeMb }}MB and {{ number_format($maxRowsPerImport) }} rows on your plan) whose first row contains column headings.
                Next you'll match the file's columns to the dataset's fields and see a preview &mdash;
                nothing is imported until you confirm.
            </p>

            <form id="import-form" action="{{ route('imports.store') }}" method="POST" enctype="multipart/form-data">
                @csrf

                <div class="mb-3">
                    <label for="dataset" class="form-label">Import into</label>
                    <select name="dataset" id="dataset" class="form-select" style="max-width: 24rem;" required>
                        @foreach ($datasets as $dataset)
                            <option value="{{ $dataset->key() }}" @selected(old('dataset') === $dataset->key())>{{ $dataset->name() }}</option>
                        @endforeach
                    </select>
                    <div class="form-text" id="dataset-help">
                        @foreach ($datasets as $dataset)
                            <span data-dataset-help="{{ $dataset->key() }}">
                                {{ $dataset->description() }}
                                Fields: {{ collect($dataset->fields())->map(fn ($f) => $f->label.($f->required ? ' (required)' : ''))->implode(', ') }}.
                            </span>
                        @endforeach
                    </div>
                    @error('dataset')
                        <div class="text-danger mt-1">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label for="import-file" class="form-label">File</label>
                    <input
                        type="file"
                        name="file"
                        id="import-file"
                        class="form-control"
                        accept=".xlsx,.xls,.csv"
                        required
                    >
                    @error('file')
                        <div class="text-danger mt-1">{{ $message }}</div>
                    @enderror
                </div>

                <div id="import-progress-wrap" class="mb-3 d-none">
                    <label class="form-label small text-secondary mb-1">Upload progress</label>
                    <div class="progress" role="progressbar" aria-label="Upload progress" aria-valuemin="0" aria-valuemax="100">
                        <div
                            id="import-progress-bar"
                            class="progress-bar progress-bar-striped progress-bar-animated"
                            style="width: 0%"
                        >0%</div>
                    </div>
                </div>

                <div id="import-result" class="alert d-none" role="alert"></div>

                <button type="submit" id="import-submit" class="btn btn-primary">
                    <i class="bi bi-upload"></i> Upload
                </button>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const form = document.getElementById('import-form');
        const fileInput = document.getElementById('import-file');
        const datasetSelect = document.getElementById('dataset');
        const submitBtn = document.getElementById('import-submit');
        const progressWrap = document.getElementById('import-progress-wrap');
        const progressBar = document.getElementById('import-progress-bar');
        const resultBox = document.getElementById('import-result');
        const maxBytes = {{ $maxFileSizeMb }} * 1024 * 1024;

        function showDatasetHelp() {
            document.querySelectorAll('[data-dataset-help]').forEach(function (el) {
                el.classList.toggle('d-none', el.dataset.datasetHelp !== datasetSelect.value);
            });
        }
        datasetSelect.addEventListener('change', showDatasetHelp);
        showDatasetHelp();

        function showResult(message, isError) {
            resultBox.textContent = message;
            resultBox.classList.remove('d-none', 'alert-success', 'alert-danger');
            resultBox.classList.add(isError ? 'alert-danger' : 'alert-success');
        }

        function setProgress(percent) {
            progressBar.style.width = percent + '%';
            progressBar.parentElement.setAttribute('aria-valuenow', percent);
            progressBar.textContent = percent + '%';
        }

        // Upload with XHR to show real upload progress (bytes sent), then go to
        // the import's page, where analysis, mapping and processing are tracked.
        form.addEventListener('submit', function (event) {
            event.preventDefault();

            if (!fileInput.files.length) {
                return;
            }

            if (fileInput.files[0].size > maxBytes) {
                showResult('That file is larger than the {{ $maxFileSizeMb }}MB limit of your plan.', true);
                return;
            }

            const xhr = new XMLHttpRequest();

            resultBox.classList.add('d-none');
            progressWrap.classList.remove('d-none');
            progressBar.classList.remove('bg-danger');
            setProgress(0);
            submitBtn.disabled = true;

            xhr.open('POST', form.action, true);
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            xhr.setRequestHeader('Accept', 'application/json');
            xhr.setRequestHeader(
                'X-CSRF-TOKEN',
                document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            );

            xhr.upload.addEventListener('progress', function (event) {
                if (event.lengthComputable) {
                    setProgress(Math.round((event.loaded / event.total) * 100));
                }
            });

            xhr.addEventListener('load', function () {
                let response = {};
                try {
                    response = JSON.parse(xhr.responseText);
                } catch (err) {
                    response = {};
                }

                if (xhr.status >= 200 && xhr.status < 300 && response.batch) {
                    setProgress(100);
                    showResult(response.message || 'Upload complete.', false);
                    window.location.href = response.batch.details_url;
                    return;
                }

                submitBtn.disabled = false;
                progressBar.classList.add('bg-danger');

                if (xhr.status === 422 && response.errors) {
                    showResult(Object.values(response.errors).flat().join(' ') || 'Please choose a valid file.', true);
                } else {
                    showResult(response.message || 'Upload failed. Please try again.', true);
                }
            });

            xhr.addEventListener('error', function () {
                submitBtn.disabled = false;
                progressBar.classList.add('bg-danger');
                showResult('Upload failed. Please check your connection and try again.', true);
            });

            xhr.send(new FormData(form));
        });
    });
</script>
@endpush
