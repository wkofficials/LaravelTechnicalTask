@extends('layouts.master')

@section('title', 'Import #'.$import->id)
@section('page-title', 'Import #'.$import->id)

@php
    use App\Enums\ImportBatchStatus;

    $awaiting = $import->status === ImportBatchStatus::AwaitingConfirmation;
    $analyzing = $import->status === ImportBatchStatus::Analyzing;
    // Queued or (once) started: show counters and progress.
    $hasRun = $import->status === ImportBatchStatus::Pending || $import->started_at !== null || $import->processed_rows > 0;
@endphp

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="card mb-4" id="import-summary" data-status-url="{{ route('imports.status', $import) }}"
         data-status="{{ $import->status->value }}"
         data-finished="{{ $import->status->isFinished() ? '1' : '0' }}">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h3 class="card-title mb-0">{{ $import->original_filename }}</h3>
            <div class="d-flex gap-2">
                @if ($import->failed_rows > 0)
                    <a href="{{ route('imports.errors', $import) }}" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-download"></i> Download rejected rows (CSV)
                    </a>
                @endif
                @can('cancel', $import)
                    <form method="POST" action="{{ route('imports.cancel', $import) }}"
                          onsubmit="return confirm('{{ $hasRun ? 'Stop this import? Rows already imported will be kept.' : 'Cancel this import?' }}');">
                        @csrf
                        <button type="submit" class="btn btn-outline-danger btn-sm">
                            <i class="bi bi-x-circle"></i> Cancel import
                        </button>
                    </form>
                @endcan
            </div>
        </div>
        <div class="card-body">
            <dl class="row mb-3">
                <dt class="col-sm-3">Status</dt>
                <dd class="col-sm-9">
                    <span id="status-badge" class="badge text-bg-{{ $import->status->color() }}">{{ $import->status->label() }}</span>
                    @if ($import->cancel_requested_at && ! $import->status->isFinished())
                        <span class="text-secondary ms-2">Cancellation requested&hellip;</span>
                    @endif
                </dd>

                <dt class="col-sm-3">Dataset</dt>
                <dd class="col-sm-9">{{ $import->dataset()->name() }}</dd>

                <dt class="col-sm-3">Uploaded by</dt>
                <dd class="col-sm-9">{{ $import->user?->name ?? '—' }}, {{ $import->created_at->toDayDateTimeString() }}</dd>

                <dt class="col-sm-3">Rows in file</dt>
                <dd class="col-sm-9" id="total-rows">{{ $import->total_rows === null ? 'Counting…' : number_format($import->total_rows) }}</dd>

                @if ($hasRun)
                    <dt class="col-sm-3">Duplicates</dt>
                    <dd class="col-sm-9">
                        {{ $import->duplicate_policy?->label() }}
                        @if ($import->template)
                            <span class="text-secondary">&middot; template "{{ $import->template->name }}"</span>
                        @endif
                    </dd>

                    <dt class="col-sm-3">Added</dt>
                    <dd class="col-sm-9" id="inserted-rows">{{ number_format($import->inserted_rows) }}</dd>

                    <dt class="col-sm-3">Updated</dt>
                    <dd class="col-sm-9" id="updated-rows">{{ number_format($import->updated_rows) }}</dd>

                    <dt class="col-sm-3">Skipped as duplicates</dt>
                    <dd class="col-sm-9" id="skipped-rows">{{ number_format($import->skipped_rows) }}</dd>

                    <dt class="col-sm-3">Rejected</dt>
                    <dd class="col-sm-9" id="failed-rows">{{ number_format($import->failed_rows) }}</dd>
                @endif

                @if ($import->finished_at)
                    <dt class="col-sm-3">Finished</dt>
                    <dd class="col-sm-9">{{ $import->finished_at->toDayDateTimeString() }}</dd>
                @endif
            </dl>

            @if ($analyzing)
                <div class="d-flex align-items-center gap-2 text-secondary">
                    <div class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></div>
                    Reading the file's columns&hellip; this page updates by itself.
                </div>
            @elseif ($awaiting && ! $mappingForm)
                <div class="alert alert-info mb-0">
                    Waiting for {{ $import->user?->name ?? 'the uploader' }} to confirm the column mapping.
                </div>
            @elseif ($hasRun)
                @php $percentage = $import->percentage(); @endphp
                <label class="form-label small text-secondary mb-1">Processing progress</label>
                <div class="progress mb-3" role="progressbar" aria-label="Processing progress"
                     aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $percentage ?? 0 }}">
                    <div id="progress-bar" class="progress-bar {{ $import->status->isFinished() ? '' : 'progress-bar-striped progress-bar-animated' }}"
                         style="width: {{ $percentage ?? 0 }}%">{{ $percentage === null ? 'Working…' : $percentage.'%' }}</div>
                </div>
            @endif

            @if ($import->error_message)
                <div class="alert alert-{{ $import->status->color() }} mb-0 mt-3">{{ $import->error_message }}</div>
            @endif
        </div>
    </div>

    @if ($mappingForm)
        @include('imports._mapping')
    @endif

    @if ($hasRun)
        <div class="card">
            <div class="card-header">
                <h3 class="card-title mb-0">Rejected rows</h3>
            </div>
            <div class="card-body">
                @if ($rowErrors->isEmpty())
                    <p class="text-secondary mb-0">
                        {{ $import->status->isFinished() ? 'No rows were rejected.' : 'No rejected rows so far.' }}
                    </p>
                @else
                    <div class="table-responsive">
                        <table class="table table-sm table-striped align-middle">
                            <thead>
                                <tr>
                                    <th>Row</th>
                                    <th>Problem</th>
                                    <th>Values in the file</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($rowErrors as $rowError)
                                    <tr>
                                        <td>{{ $rowError->row_number }}</td>
                                        <td class="text-danger">{{ $rowError->message }}</td>
                                        <td class="small text-secondary">
                                            @foreach (($rowError->row_data ?? []) as $column => $value)
                                                <span class="me-2"><strong>{{ $column }}:</strong> {{ $value === null || $value === '' ? '—' : $value }}</span>
                                            @endforeach
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    {{ $rowErrors->links() }}
                @endif
            </div>
        </div>
    @endif
@endsection

@push('scripts')
<script>
    // While analysis or processing is running, poll the status; reload once
    // the import moves to another stage so the whole page is up to date.
    document.addEventListener('DOMContentLoaded', function () {
        const summary = document.getElementById('import-summary');
        const initialStatus = summary.dataset.status;

        if (summary.dataset.finished === '1' || initialStatus === 'awaiting_confirmation') {
            return;
        }

        const bar = document.getElementById('progress-bar');
        const setText = function (id, value) {
            const el = document.getElementById(id);
            if (el) {
                el.textContent = value;
            }
        };

        function poll() {
            fetch(summary.dataset.statusUrl, { headers: { 'Accept': 'application/json' } })
                .then(function (response) { return response.ok ? response.json() : Promise.reject(); })
                .then(function (data) {
                    const stageChanged = data.is_finished || data.needs_confirmation
                        || (initialStatus === 'analyzing' && data.status !== 'analyzing');

                    if (stageChanged) {
                        window.location.reload();
                        return;
                    }

                    if (bar) {
                        const percent = data.percentage === null ? 0 : data.percentage;
                        bar.style.width = percent + '%';
                        bar.parentElement.setAttribute('aria-valuenow', percent);
                        bar.textContent = data.percentage === null ? 'Working…' : percent + '%';
                    }

                    setText('total-rows', data.total_rows === null ? 'Counting…' : data.total_rows.toLocaleString());
                    setText('inserted-rows', data.inserted_rows.toLocaleString());
                    setText('updated-rows', data.updated_rows.toLocaleString());
                    setText('skipped-rows', data.skipped_rows.toLocaleString());
                    setText('failed-rows', data.failed_rows.toLocaleString());

                    setTimeout(poll, 2000);
                })
                .catch(function () { setTimeout(poll, 5000); });
        }

        poll();
    });
</script>
@endpush
