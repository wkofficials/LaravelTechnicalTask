@extends('layouts.master')

@section('title', 'Import History')
@section('page-title', 'Import History')

@section('content')
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title mb-0">
                @can('view company imports') All company imports @else My imports @endcan
            </h3>
            <a href="{{ route('imports.create') }}" class="btn btn-primary btn-sm">
                <i class="bi bi-upload"></i> New import
            </a>
        </div>
        <div class="card-body">
            <form method="GET" action="{{ route('imports.index') }}" class="mb-3">
                <div class="input-group" style="max-width: 320px;">
                    <label class="input-group-text" for="status-filter">Status</label>
                    <select id="status-filter" name="status" class="form-select" onchange="this.form.submit()">
                        <option value="">All</option>
                        @foreach ($statuses as $option)
                            <option value="{{ $option->value }}" @selected($status === $option->value)>{{ $option->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <noscript><button type="submit" class="btn btn-outline-secondary mt-2">Filter</button></noscript>
            </form>

            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>File</th>
                            @can('view company imports')
                                <th>Uploaded by</th>
                            @endcan
                            <th>Status</th>
                            <th class="text-end">Rows</th>
                            <th class="text-end">Imported</th>
                            <th class="text-end">Skipped</th>
                            <th class="text-end">Rejected</th>
                            <th>Uploaded</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($imports as $import)
                            <tr>
                                <td>{{ $import->id }}</td>
                                <td><a href="{{ route('imports.show', $import) }}">{{ $import->original_filename }}</a></td>
                                @can('view company imports')
                                    <td>{{ $import->user?->name ?? '—' }}</td>
                                @endcan
                                <td><span class="badge text-bg-{{ $import->status->color() }}">{{ $import->status->label() }}</span></td>
                                <td class="text-end">{{ $import->total_rows === null ? '—' : number_format($import->total_rows) }}</td>
                                <td class="text-end">{{ number_format($import->importedRows()) }}</td>
                                <td class="text-end">{{ number_format($import->skipped_rows) }}</td>
                                <td class="text-end">{{ number_format($import->failed_rows) }}</td>
                                <td title="{{ $import->created_at }}">{{ $import->created_at->diffForHumans() }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center text-secondary py-4">No imports yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $imports->links() }}
        </div>
    </div>
@endsection
