@extends('layouts.master')

@section('title', 'Platform')
@section('page-title', 'Platform overview')

@section('content')
    <div class="alert alert-info small">
        Platform admin area. You see counts and settings only &mdash; company records and files are not accessible here.
    </div>

    <div class="row g-3 mb-4">
        @foreach (['Companies' => $companies, 'Queue' => $queue, 'Imports (last 24h)' => $imports24h] as $title => $metrics)
            <div class="col-lg-4">
                <div class="card h-100">
                    <div class="card-header"><h3 class="card-title mb-0">{{ $title }}</h3></div>
                    <ul class="list-group list-group-flush">
                        @foreach ($metrics as $label => $value)
                            <li class="list-group-item d-flex justify-content-between">
                                <span>{{ $label }}</span>
                                <span class="fw-semibold {{ $label === 'Failed jobs' && $value > 0 ? 'text-danger' : '' }}">{{ number_format($value) }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endforeach
    </div>

    <div class="card">
        <div class="card-header"><h3 class="card-title mb-0">Recent platform admin actions</h3></div>
        <div class="card-body p-0">
            <table class="table table-sm mb-0">
                <tbody>
                    @forelse ($recentActions as $log)
                        <tr>
                            <td class="text-nowrap text-secondary">{{ $log->created_at->diffForHumans() }}</td>
                            <td>{{ $log->actor?->name ?? 'System' }}</td>
                            <td>{{ $log->actionLabel() }}</td>
                            <td>{{ $log->company?->name ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td class="text-secondary p-3">No platform actions yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
