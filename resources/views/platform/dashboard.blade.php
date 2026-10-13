@extends('layouts.master')

@section('title', 'Overview')
@section('page-title', 'Platform overview')
@section('page-subtitle', 'Counts and settings only — company records and files are not accessible here.')

@section('content')
    @php
        $failedJobs = $queue['Failed jobs'];
        $importsDay = $imports24h->sum();
        $problemsDay = ($imports24h['Failed'] ?? 0) + ($imports24h['Completed with errors'] ?? 0);
    @endphp

    <div class="row g-3 mb-4">
        @foreach ([
            ['Active companies', $companies['Active'], $companies['Suspended'].' suspended', 'bi-buildings-fill', 'if-grad-indigo'],
            ['Imports (24h)', $importsDay, $problemsDay.' with errors or failed', 'bi-cloud-arrow-up-fill', 'if-grad-emerald'],
            ['Queue', $queue['Jobs waiting'], $queue['Imports running'].' running · '.$queue['Imports queued'].' queued', 'bi-hourglass-split', 'if-grad-amber'],
            ['Failed jobs', $failedJobs, $failedJobs ? 'Check php artisan queue:failed' : 'All clear', 'bi-exclamation-octagon-fill', 'if-grad-pink'],
        ] as [$label, $value, $hint, $icon, $grad])
            <div class="col-sm-6 col-xl-3">
                <div class="if-stat">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="if-stat-label">{{ $label }}</div>
                            <div class="if-stat-value mt-1">{{ number_format($value) }}</div>
                        </div>
                        <span class="if-stat-icon {{ $grad }}"><i class="bi {{ $icon }}"></i></span>
                    </div>
                    <div class="small mt-3 text-body-secondary">{{ $hint }}</div>
                </div>
            </div>
        @endforeach
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
