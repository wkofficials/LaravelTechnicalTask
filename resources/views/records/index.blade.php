@extends('layouts.master')

@section('title', $dataset->name())
@section('page-title', $dataset->name())

@php
    /** @var \App\Datasets\RecordQuery $query */
    $sortLink = fn (string $column) => request()->fullUrlWithQuery([
        'sort' => $column,
        'direction' => ($query->sort === $column && $query->direction === 'asc') ? 'desc' : 'asc',
        'page' => null,
    ]);
    $sortIcon = fn (string $column) => $query->sort !== $column
        ? 'bi-arrow-down-up text-secondary'
        : ($query->direction === 'asc' ? 'bi-sort-up' : 'bi-sort-down');
    $sortable = $dataset->sortableFields();
    $canAct = auth()->user()->canAny(['edit records', 'delete records']);
@endphp

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif
    @error('export')
        <div class="alert alert-danger">{{ $message }}</div>
    @enderror

    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h3 class="card-title mb-0">{{ $dataset->name() }}</h3>
            <div class="d-flex gap-2">
                @can('export records')
                    <form method="POST" action="{{ route('records.export', $dataset->key()) }}">
                        @csrf
                        <input type="hidden" name="q" value="{{ $query->search }}">
                        @foreach ($query->filters as $field => $value)
                            <input type="hidden" name="filter[{{ $field }}]" value="{{ $value }}">
                        @endforeach
                        <input type="hidden" name="sort" value="{{ $query->sort }}">
                        <input type="hidden" name="direction" value="{{ $query->direction }}">
                        <button type="submit" class="btn btn-outline-secondary btn-sm"
                                title="{{ $query->isFiltered() ? 'Exports only the records matching the current search and filters' : 'Exports all records' }}">
                            <i class="bi bi-download"></i>
                            Export {{ $query->isFiltered() ? 'matching' : 'all' }} ({{ number_format($records->total()) }}) to CSV
                        </button>
                    </form>
                @endcan
                @can('create records')
                    <a href="{{ route('records.create', $dataset->key()) }}" class="btn btn-primary btn-sm">
                        <i class="bi bi-plus-lg"></i> Add {{ $dataset->recordLabel() }}
                    </a>
                @endcan
            </div>
        </div>
        <div class="card-body">
            <form method="GET" action="{{ route('records.index', $dataset->key()) }}" class="row g-2 mb-3 align-items-end">
                <input type="hidden" name="sort" value="{{ $query->sort === 'id' ? '' : $query->sort }}">
                <input type="hidden" name="direction" value="{{ $query->direction }}">

                <div class="col-md-5">
                    <label for="q" class="form-label small mb-0">Search</label>
                    <input type="search" id="q" name="q" value="{{ $query->search }}" class="form-control"
                           placeholder="{{ collect($dataset->searchableFields())->map(fn ($f) => $dataset->columnLabel($f))->implode(', ') }}&hellip;">
                </div>

                @foreach ($filterOptions as $field => $options)
                    <div class="col-md-2">
                        <label for="filter-{{ $field }}" class="form-label small mb-0">{{ $dataset->columnLabel($field) }}</label>
                        <select id="filter-{{ $field }}" name="filter[{{ $field }}]" class="form-select">
                            <option value="">All</option>
                            @foreach ($options as $option)
                                <option value="{{ $option }}" @selected(($query->filters[$field] ?? null) === $option)>{{ $option }}</option>
                            @endforeach
                        </select>
                    </div>
                @endforeach

                <div class="col-md-auto d-flex gap-2">
                    <button type="submit" class="btn btn-outline-primary">
                        <i class="bi bi-search"></i> Apply
                    </button>
                    @if ($query->isFiltered())
                        <a href="{{ route('records.index', $dataset->key()) }}" class="btn btn-outline-secondary">Clear</a>
                    @endif
                </div>
            </form>

            <p class="text-secondary">
                {{ number_format($records->total()) }} {{ Str::plural($dataset->recordLabel(), $records->total()) }}
                @if ($query->search !== '')
                    matching "{{ $query->search }}"
                @endif
                @foreach ($query->filters as $field => $value)
                    &middot; {{ $dataset->columnLabel($field) }}: {{ $value }}
                @endforeach
            </p>

            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle">
                    <thead>
                        <tr>
                            @foreach ($dataset->fields() as $field)
                                <th>
                                    @if (in_array($field->key, $sortable, true))
                                        <a href="{{ $sortLink($field->key) }}" class="text-decoration-none text-reset">
                                            {{ $field->label }} <i class="bi {{ $sortIcon($field->key) }}"></i>
                                        </a>
                                    @else
                                        {{ $field->label }}
                                    @endif
                                </th>
                            @endforeach
                            @if ($canAct)
                                <th class="text-end">Actions</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($records as $record)
                            <tr>
                                @foreach ($dataset->fields() as $field)
                                    <td>{{ $record->getAttribute($field->key) }}</td>
                                @endforeach
                                @if ($canAct)
                                    <td class="text-end text-nowrap">
                                        @can('edit records')
                                            <a href="{{ route('records.edit', [$dataset->key(), $record]) }}"
                                               class="btn btn-sm btn-outline-secondary" aria-label="Edit">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                        @endcan
                                        @can('delete records')
                                            <form action="{{ route('records.destroy', [$dataset->key(), $record]) }}" method="POST" class="d-inline"
                                                  onsubmit="return confirm('Delete this {{ $dataset->recordLabel() }}?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger" aria-label="Delete">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        @endcan
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ count($dataset->fields()) + ($canAct ? 1 : 0) }}" class="text-center text-secondary py-4">
                                    No records found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $records->links() }}
        </div>
    </div>

    @if ($recentExports->isNotEmpty())
        <div class="card">
            <div class="card-header">
                <h3 class="card-title mb-0">Your recent exports</h3>
            </div>
            <div class="card-body p-0">
                <table class="table mb-0 align-middle">
                    <tbody>
                        @foreach ($recentExports as $export)
                            <tr data-export-status-url="{{ $export->isRunning() ? route('exports.status', $export) : '' }}">
                                <td>{{ $export->created_at->diffForHumans() }}</td>
                                <td class="small text-secondary">
                                    @php $options = $export->options ?? []; @endphp
                                    {{ ($options['q'] ?? '') !== '' ? 'Search "'.$options['q'].'"' : 'All records' }}
                                    @foreach ($options['filter'] ?? [] as $field => $value)
                                        &middot; {{ $dataset->columnLabel($field) }}: {{ $value }}
                                    @endforeach
                                </td>
                                <td class="export-state text-end">
                                    @if ($export->isDownloadable())
                                        <a href="{{ route('exports.download', $export) }}" class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-download"></i> Download ({{ number_format($export->row_count) }} rows)
                                        </a>
                                    @elseif ($export->isRunning())
                                        <span class="text-secondary"><span class="spinner-border spinner-border-sm"></span> Preparing&hellip;</span>
                                    @elseif ($export->status === \App\Models\DataExport::STATUS_FAILED)
                                        <span class="text-danger">{{ $export->error_message }}</span>
                                    @else
                                        <span class="text-secondary">Expired</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
@endsection

@push('scripts')
<script>
    // Swap "Preparing…" for a download link once a running export finishes.
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-export-status-url]').forEach(function (row) {
            const url = row.dataset.exportStatusUrl;
            if (!url) {
                return;
            }

            const cell = row.querySelector('.export-state');

            function poll() {
                fetch(url, { headers: { 'Accept': 'application/json' } })
                    .then(function (response) { return response.ok ? response.json() : Promise.reject(); })
                    .then(function (data) {
                        if (data.download_url) {
                            const link = document.createElement('a');
                            link.href = data.download_url;
                            link.className = 'btn btn-sm btn-outline-primary';
                            link.textContent = 'Download (' + Number(data.row_count).toLocaleString() + ' rows)';
                            cell.replaceChildren(link);
                        } else if (data.status === 'failed') {
                            cell.textContent = data.error_message || 'Export failed.';
                            cell.classList.add('text-danger');
                        } else {
                            setTimeout(poll, 2000);
                        }
                    })
                    .catch(function () { setTimeout(poll, 5000); });
            }

            poll();
        });
    });
</script>
@endpush
