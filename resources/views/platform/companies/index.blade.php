@extends('layouts.master')

@section('title', 'Companies')
@section('page-title', 'Companies')

@section('content')
    <div class="card">
        <div class="card-header">
            <form method="GET" action="{{ route('platform.companies.index') }}" class="d-flex gap-2" style="max-width: 28rem;">
                <input type="search" name="q" value="{{ $search }}" class="form-control form-control-sm" placeholder="Search name or slug" aria-label="Search companies">
                <button class="btn btn-sm btn-outline-secondary">Search</button>
            </form>
        </div>
        <div class="card-body p-0">
            <table class="table table-striped align-middle mb-0">
                <thead>
                    <tr>
                        <th>Company</th>
                        <th>Status</th>
                        <th>Plan</th>
                        <th class="text-end">Members</th>
                        <th>Created</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($companies as $company)
                        <tr>
                            <td><a href="{{ route('platform.companies.show', $company) }}">{{ $company->name }}</a> <span class="text-secondary small">{{ $company->slug }}</span></td>
                            <td><span class="badge text-bg-{{ $company->isActive() ? 'success' : 'danger' }}">{{ ucfirst($company->status) }}</span></td>
                            <td>{{ $company->plan?->name ?? 'Default' }}</td>
                            <td class="text-end">{{ $company->users_count }}</td>
                            <td class="text-secondary">{{ $company->created_at->toFormattedDateString() }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-secondary py-4">No companies found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">{{ $companies->links() }}</div>
    </div>
@endsection
