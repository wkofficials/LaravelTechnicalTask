@extends('layouts.master')

@section('title', 'Records')
@section('page-title', 'Records')

@php
    $sortLink = fn (string $column) => request()->fullUrlWithQuery([
        'sort' => $column,
        'direction' => ($sort === $column && $direction === 'asc') ? 'desc' : 'asc',
        'page' => null,
    ]);
    $sortIcon = fn (string $column) => $sort !== $column
        ? 'bi-arrow-down-up text-secondary'
        : ($direction === 'asc' ? 'bi-sort-up' : 'bi-sort-down');
@endphp

@section('content')
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title mb-0">Records</h3>
            @can('create records')
                <a href="{{ route('records.create') }}" class="btn btn-primary btn-sm">
                    <i class="bi bi-plus-lg"></i> Add Record
                </a>
            @endcan
        </div>
        <div class="card-body">
            @if (session('status'))
                <div class="alert alert-success">{{ session('status') }}</div>
            @endif

            <form method="GET" action="{{ route('records.index') }}" class="mb-3">
                <div class="input-group" style="max-width: 480px;">
                    <input
                        type="text"
                        name="q"
                        value="{{ $search }}"
                        class="form-control"
                        placeholder="Search name, email, company, city, country&hellip;"
                    >
                    <button type="submit" class="btn btn-outline-secondary">
                        <i class="bi bi-search"></i> Search
                    </button>
                    @if ($search !== '')
                        <a href="{{ route('records.index') }}" class="btn btn-outline-secondary">Clear</a>
                    @endif
                </div>
            </form>

            <p class="text-secondary">
                {{ number_format($records->total()) }} total record{{ $records->total() === 1 ? '' : 's' }}
                @if ($search !== '')
                    matching "{{ $search }}"
                @endif
            </p>

            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle">
                    <thead>
                        <tr>
                            @foreach (['name' => 'Name', 'email' => 'Email', 'phone' => 'Phone', 'company' => 'Company', 'city' => 'City', 'country' => 'Country'] as $column => $label)
                                <th>
                                    <a href="{{ $sortLink($column) }}" class="text-decoration-none text-reset">
                                        {{ $label }} <i class="bi {{ $sortIcon($column) }}"></i>
                                    </a>
                                </th>
                            @endforeach
                            @canany(['edit records', 'delete records'])
                                <th class="text-end">Actions</th>
                            @endcanany
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($records as $record)
                            <tr>
                                <td>{{ $record->name }}</td>
                                <td>{{ $record->email }}</td>
                                <td>{{ $record->phone }}</td>
                                <td>{{ $record->company }}</td>
                                <td>{{ $record->city }}</td>
                                <td>{{ $record->country }}</td>
                                @canany(['edit records', 'delete records'])
                                    <td class="text-end">
                                        @can('edit records')
                                            <a
                                                href="{{ route('records.edit', $record) }}"
                                                class="btn btn-sm btn-outline-secondary"
                                            >
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                        @endcan
                                        @can('delete records')
                                            <form
                                                action="{{ route('records.destroy', $record) }}"
                                                method="POST"
                                                class="d-inline"
                                                onsubmit="return confirm('Delete this record?');"
                                            >
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        @endcan
                                    </td>
                                @endcanany
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-secondary">No records found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $records->links() }}
        </div>
    </div>
@endsection
