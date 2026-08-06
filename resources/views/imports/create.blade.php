@extends('layouts.master')

@section('title', 'Import Records')
@section('page-title', 'Import Records')

@section('content')
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Upload Excel File</h3>
        </div>
        <div class="card-body">
            <p class="text-secondary">
                Upload an .xlsx, .xls, or .csv file (up to 100MB). The file will be queued and processed
                in the background &mdash; you don't need to keep this page open.
            </p>

            <p class="text-secondary">
                The first row must be a header row with these column names (order doesn't matter,
                <code>phone</code>, <code>company</code>, <code>city</code> and <code>country</code> are optional):
                <code>name</code>, <code>email</code>, <code>phone</code>, <code>company</code>, <code>city</code>, <code>country</code>.
            </p>

            @if (session('status'))
                <div class="alert alert-success">{{ session('status') }}</div>
            @endif

            <form id="import-form" action="{{ route('imports.store') }}" method="POST" enctype="multipart/form-data">
                @csrf

                <div class="mb-3">
                    <label for="import-file" class="form-label">Excel file</label>
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
                    <div class="progress" role="progressbar" aria-valuemin="0" aria-valuemax="100">
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

            <div id="processing-section" class="mt-4 d-none">
                <hr>
                <h5>Processing status</h5>
                <p id="processing-filename" class="text-secondary mb-2"></p>

                <div class="mb-2">
                    <div class="progress" role="progressbar" aria-valuemin="0" aria-valuemax="100">
                        <div
                            id="processing-progress-bar"
                            class="progress-bar progress-bar-striped progress-bar-animated"
                            style="width: 0%"
                        >0%</div>
                    </div>
                </div>

                <p id="processing-counts" class="text-secondary mb-2"></p>

                <div id="processing-result" class="alert d-none" role="alert"></div>

                <div id="processing-errors" class="d-none">
                    <p class="mb-1"><strong>Some rows couldn't be imported:</strong></p>
                    <ul id="processing-errors-list" class="small text-danger"></ul>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const form = document.getElementById('import-form');
        const fileInput = document.getElementById('import-file');
        const submitBtn = document.getElementById('import-submit');
        const progressWrap = document.getElementById('import-progress-wrap');
        const progressBar = document.getElementById('import-progress-bar');
        const resultBox = document.getElementById('import-result');
        const maxBytes = 100 * 1024 * 1024;

        const processingSection = document.getElementById('processing-section');
        const processingFilename = document.getElementById('processing-filename');
        const processingBar = document.getElementById('processing-progress-bar');
        const processingCounts = document.getElementById('processing-counts');
        const processingResult = document.getElementById('processing-result');
        const processingErrorsWrap = document.getElementById('processing-errors');
        const processingErrorsList = document.getElementById('processing-errors-list');

        const statusUrlTemplate = '{{ route('imports.status', ['importBatch' => '__ID__']) }}';
        let pollTimer = null;
        let pollFailures = 0;

        function showResult(message, isError) {
            resultBox.textContent = message;
            resultBox.classList.remove('d-none', 'alert-success', 'alert-danger');
            resultBox.classList.add(isError ? 'alert-danger' : 'alert-success');
        }

        function setProgress(percent) {
            progressBar.style.width = percent + '%';
            progressBar.setAttribute('aria-valuenow', percent);
            progressBar.textContent = percent + '%';
        }

        form.addEventListener('submit', function (event) {
            event.preventDefault();

            if (!fileInput.files.length) {
                return;
            }

            if (fileInput.files[0].size > maxBytes) {
                showResult('That file is larger than the 100MB limit.', true);
                return;
            }

            const formData = new FormData(form);
            const xhr = new XMLHttpRequest();

            resultBox.classList.add('d-none');
            progressWrap.classList.remove('d-none');
            progressBar.classList.remove('bg-danger', 'text-bg-danger');
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
                if (!event.lengthComputable) {
                    return;
                }
                setProgress(Math.round((event.loaded / event.total) * 100));
            });

            xhr.addEventListener('load', function () {
                submitBtn.disabled = false;

                let response = {};
                try {
                    response = JSON.parse(xhr.responseText);
                } catch (err) {
                    response = {};
                }

                if (xhr.status >= 200 && xhr.status < 300) {
                    setProgress(100);
                    showResult(response.message || 'Upload complete.', false);
                    form.reset();

                    if (response.batch && response.batch.id) {
                        startTracking(response.batch.id);
                    }
                } else if (xhr.status === 422 && response.errors) {
                    const messages = Object.values(response.errors).flat().join(' ');
                    progressBar.classList.add('bg-danger');
                    showResult(messages || 'Please choose a valid file.', true);
                } else {
                    progressBar.classList.add('bg-danger');
                    showResult(response.message || 'Upload failed. Please try again.', true);
                }
            });

            xhr.addEventListener('error', function () {
                submitBtn.disabled = false;
                progressBar.classList.add('bg-danger');
                showResult('Upload failed. Please check your connection and try again.', true);
            });

            xhr.send(formData);
        });

        function statusUrl(id) {
            return statusUrlTemplate.replace('__ID__', encodeURIComponent(id));
        }

        function setProcessingProgress(percent) {
            const value = percent === null || percent === undefined ? 0 : percent;
            processingBar.style.width = value + '%';
            processingBar.setAttribute('aria-valuenow', value);
            processingBar.textContent = (percent === null || percent === undefined) ? 'Working…' : value + '%';
        }

        function stopPolling() {
            if (pollTimer) {
                clearTimeout(pollTimer);
                pollTimer = null;
            }
        }

        function renderStatus(data) {
            processingFilename.textContent = data.original_filename;
            setProcessingProgress(data.percentage);

            const total = data.total_rows === null ? '?' : data.total_rows;
            processingCounts.textContent = data.processed_rows + ' / ' + total + ' rows processed'
                + (data.failed_rows ? ' — ' + data.failed_rows + ' failed' : '');

            if (!data.is_finished) {
                return;
            }

            stopPolling();
            processingBar.classList.remove('progress-bar-animated');
            processingResult.classList.remove('d-none', 'alert-success', 'alert-warning', 'alert-danger');
            processingBar.classList.remove('bg-warning', 'bg-danger');

            if (data.status === 'completed') {
                processingResult.classList.add('alert-success');
                processingResult.textContent = 'Import complete: ' + data.processed_rows + ' rows processed successfully.';
            } else if (data.status === 'completed_with_errors') {
                processingBar.classList.add('bg-warning');
                processingResult.classList.add('alert-warning');
                processingResult.textContent = 'Import finished with ' + data.failed_rows + ' row(s) skipped. See details below.';
            } else {
                processingBar.classList.add('bg-danger');
                processingResult.classList.add('alert-danger');
                processingResult.textContent = data.error_message || 'Import failed.';
            }

            if (data.row_errors && data.row_errors.length) {
                processingErrorsWrap.classList.remove('d-none');
                processingErrorsList.innerHTML = '';
                data.row_errors.slice(0, 20).forEach(function (err) {
                    const li = document.createElement('li');
                    li.textContent = 'Row ' + err.row + ': ' + err.error;
                    processingErrorsList.appendChild(li);
                });
            }
        }

        function poll(batchId) {
            fetch(statusUrl(batchId), {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            })
                .then(function (response) {
                    if (!response.ok) {
                        throw new Error('status ' + response.status);
                    }
                    pollFailures = 0;
                    return response.json();
                })
                .then(function (data) {
                    renderStatus(data);
                    if (!data.is_finished) {
                        pollTimer = setTimeout(function () { poll(batchId); }, 1500);
                    }
                })
                .catch(function () {
                    pollFailures++;
                    if (pollFailures < 5) {
                        pollTimer = setTimeout(function () { poll(batchId); }, 3000);
                    } else {
                        processingResult.classList.remove('d-none');
                        processingResult.classList.add('alert-danger');
                        processingResult.textContent = 'Lost connection while checking import status. Reload the page to check again.';
                    }
                });
        }

        function startTracking(batchId) {
            processingSection.classList.remove('d-none');
            processingResult.classList.add('d-none');
            processingErrorsWrap.classList.add('d-none');
            processingBar.classList.remove('bg-warning', 'bg-danger');
            processingBar.classList.add('progress-bar-animated');
            setProcessingProgress(0);

            const url = new URL(window.location.href);
            url.searchParams.set('batch', batchId);
            window.history.replaceState({}, '', url);

            pollFailures = 0;
            stopPolling();
            poll(batchId);
        }

        const existingBatchId = new URLSearchParams(window.location.search).get('batch');
        if (existingBatchId) {
            startTracking(existingBatchId);
        }
    });
</script>
@endpush
