@extends('layouts.master')

@section('title', 'Dashboard')
@section('hide-page-header', true)

@php
    $greeting = match (true) {
        $localHour < 12 => 'Good morning',
        $localHour < 17 => 'Good afternoon',
        default => 'Good evening',
    };
    $firstName = strtok(auth()->user()->name, ' ');
@endphp

@section('content')
    <div class="pt-4">
        @if (session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif

        {{-- Welcome --}}
        <div class="if-hero mb-4">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-4">
                <div>
                    <div class="small text-uppercase fw-semibold opacity-75" style="letter-spacing: .08em">
                        <i class="bi bi-buildings"></i> {{ $company->name }} &middot; {{ ucfirst($role ?? 'member') }}
                    </div>
                    <h2 class="mt-2 mb-1">{{ $greeting }}, {{ $firstName }} <span aria-hidden="true">👋</span></h2>
                    <p class="mb-0">
                        @isset($importsThisMonth)
                            {{ $importsThisMonth ? "You've run ".number_format($importsThisMonth).' '.Str::plural('import', $importsThisMonth).' this month.' : 'Ready for your first import this month?' }}
                        @else
                            Here's what's happening in your workspace.
                        @endisset
                    </p>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    @can('import records')
                        <a href="{{ route('imports.create') }}" class="btn btn-light btn-lg px-4"><i class="bi bi-cloud-arrow-up-fill me-1"></i> New import</a>
                    @endcan
                    @can('view records')
                        <a href="{{ route('records.index', 'contacts') }}" class="btn btn-glass btn-lg px-4"><i class="bi bi-people-fill me-1"></i> Contacts</a>
                    @endcan
                </div>
            </div>
        </div>

        {{-- Getting started --}}
        @if (! empty($onboarding))
            @php
                $doneSteps = collect($onboarding)->where('done', true)->count();
                $stepPercent = (int) round($doneSteps / count($onboarding) * 100);
            @endphp
            <div class="card mb-4">
                <div class="card-body">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                        <div>
                            <h3 class="card-title mb-0"><i class="bi bi-rocket-takeoff-fill text-primary me-1"></i> Getting started</h3>
                            <small class="text-body-secondary">{{ $doneSteps }} of {{ count($onboarding) }} done</small>
                        </div>
                        <div class="progress flex-grow-1" style="max-width: 16rem; height: .5rem" role="progressbar"
                             aria-label="Setup progress" aria-valuenow="{{ $stepPercent }}" aria-valuemin="0" aria-valuemax="100">
                            <div class="progress-bar" style="width: {{ $stepPercent }}%"></div>
                        </div>
                    </div>
                    <div class="row g-2">
                        @foreach ($onboarding as $step)
                            <div class="col-md-6 col-xl-3">
                                <div class="h-100 d-flex align-items-start gap-2 p-3 rounded-3 border {{ $step['done'] ? 'border-success-subtle bg-success-subtle bg-opacity-25' : '' }}">
                                    <i class="bi {{ $step['done'] ? 'bi-check-circle-fill text-success' : 'bi-circle text-body-secondary' }} fs-5" aria-hidden="true"></i>
                                    <div class="flex-grow-1">
                                        <div class="small {{ $step['done'] ? 'if-step-done' : 'fw-semibold' }}">
                                            {{ $step['label'] }} @if ($step['done'])<span class="visually-hidden">(done)</span>@endif
                                        </div>
                                        @unless ($step['done'])
                                            <a href="{{ $step['url'] }}" class="small fw-semibold text-decoration-none">{{ $step['action'] }} <i class="bi bi-arrow-right"></i></a>
                                        @endunless
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif

        {{-- Stats --}}
        <div class="row g-3 mb-4">
            @isset($recordCount)
                <div class="col-sm-6 col-xl-3">
                    <div class="if-stat">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <div class="if-stat-label">Contacts</div>
                                <div class="if-stat-value mt-1">{{ number_format($recordCount) }}</div>
                            </div>
                            <span class="if-stat-icon if-grad-indigo"><i class="bi bi-person-lines-fill"></i></span>
                        </div>
                        <a href="{{ route('records.index', 'contacts') }}" class="small fw-semibold text-decoration-none d-inline-block mt-3">Browse <i class="bi bi-arrow-right"></i></a>
                    </div>
                </div>
            @endisset

            @isset($importsThisMonth)
                <div class="col-sm-6 col-xl-3">
                    <div class="if-stat">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <div class="if-stat-label">@can('view company imports') Imports @else My imports @endcan this month</div>
                                <div class="if-stat-value mt-1">{{ number_format($importsThisMonth) }}</div>
                            </div>
                            <span class="if-stat-icon if-grad-emerald"><i class="bi bi-cloud-check-fill"></i></span>
                        </div>
                        <div class="small mt-3 text-body-secondary">
                            @if ($withErrorsThisMonth || $failedThisMonth)
                                @if ($withErrorsThisMonth)<span class="badge text-bg-warning">{{ $withErrorsThisMonth }} with errors</span>@endif
                                @if ($failedThisMonth)<span class="badge text-bg-danger">{{ $failedThisMonth }} failed</span>@endif
                            @else
                                <span class="badge text-bg-success"><i class="bi bi-check2"></i> No problems</span>
                            @endif
                        </div>
                    </div>
                </div>
            @endisset

            <div class="col-sm-6 col-xl-3">
                <div class="if-stat">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="if-stat-label">Team</div>
                            <div class="if-stat-value mt-1">{{ number_format($teamCount) }}</div>
                        </div>
                        <span class="if-stat-icon if-grad-pink"><i class="bi bi-people-fill"></i></span>
                    </div>
                    @can('manage team')
                        <a href="{{ route('team.index') }}" class="small fw-semibold text-decoration-none d-inline-block mt-3">Invite people <i class="bi bi-arrow-right"></i></a>
                    @else
                        <div class="small mt-3 text-body-secondary">{{ Str::plural('member', $teamCount) }} in {{ $company->name }}</div>
                    @endcan
                </div>
            </div>

            @isset($rowsUsed)
                @php $percent = $rowsLimit > 0 ? min(100, (int) floor($rowsUsed / $rowsLimit * 100)) : 100; @endphp
                <div class="col-sm-6 col-xl-3">
                    <div class="if-stat d-flex align-items-center gap-3">
                        <div class="if-ring" style="--value: {{ $percent }}; --size: 5.25rem; --ring-color: {{ $percent >= 100 ? '#ef4444' : ($percent >= 80 ? '#f59e0b' : '#7c3aed') }}"
                             role="img" aria-label="{{ $percent }}% of monthly imports used">
                            <span class="if-ring-value fs-6">{{ $percent }}%</span>
                        </div>
                        <div>
                            <div class="if-stat-label">Monthly usage</div>
                            <div class="fw-bold">{{ number_format($rowsUsed) }} <span class="text-body-secondary fw-normal">/ {{ number_format($rowsLimit) }}</span></div>
                            <a href="{{ route('usage') }}" class="small fw-semibold text-decoration-none">{{ $planName }} plan <i class="bi bi-arrow-right"></i></a>
                        </div>
                    </div>
                </div>
            @endisset
        </div>

        {{-- Recent imports --}}
        @isset($recentImports)
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h3 class="card-title mb-0"><i class="bi bi-clock-history text-primary me-1"></i> Recent imports</h3>
                    <a href="{{ route('imports.index') }}" class="btn btn-sm btn-outline-primary">View all</a>
                </div>
                @if ($recentImports->isEmpty())
                    <div class="if-empty">
                        <i class="bi bi-cloud-arrow-up"></i>
                        <div class="fw-semibold text-body-emphasis">No imports yet</div>
                        <div class="small mb-3">Upload an Excel or CSV file to get started.</div>
                        <a href="{{ route('imports.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> New import</a>
                    </div>
                @else
                    <div class="list-group list-group-flush">
                        @foreach ($recentImports as $import)
                            @php
                                $dot = match ($import->status->color()) {
                                    'success' => '#10b981', 'warning' => '#f59e0b', 'danger' => '#ef4444',
                                    'info' => '#0ea5e9', 'primary' => '#6366f1', default => '#94a3b8',
                                };
                            @endphp
                            <a href="{{ route('imports.show', $import) }}" class="list-group-item list-group-item-action d-flex align-items-center gap-3 py-3">
                                <span class="if-stat-icon if-grad-indigo" style="width: 2.4rem; height: 2.4rem; font-size: 1rem; box-shadow: none">
                                    <i class="bi bi-file-earmark-spreadsheet"></i>
                                </span>
                                <div class="flex-grow-1 min-w-0">
                                    <div class="fw-semibold text-truncate">{{ $import->original_filename }}</div>
                                    <div class="small text-body-secondary">{{ $import->user?->name ?? '—' }} &middot; {{ $import->created_at->diffForHumans() }}</div>
                                </div>
                                <div class="text-end d-none d-sm-block">
                                    <div class="fw-semibold">{{ number_format($import->importedRows()) }}</div>
                                    <div class="small text-body-secondary">imported</div>
                                </div>
                                <span class="badge text-bg-{{ $import->status->color() }} d-inline-flex align-items-center gap-1">
                                    <span class="if-status-dot" style="background: {{ $dot }}"></span> {{ $import->status->label() }}
                                </span>
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>
        @endisset
    </div>
@endsection
