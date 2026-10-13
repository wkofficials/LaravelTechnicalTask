@extends('layouts.master')

@section('title', 'Dashboard')
@section('page-title', 'Dashboard')

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @if (! empty($onboarding))
        <div class="card mb-4 border-primary">
            <div class="card-header"><h3 class="card-title mb-0"><i class="bi bi-rocket-takeoff"></i> Getting started</h3></div>
            <ul class="list-group list-group-flush">
                @foreach ($onboarding as $step)
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span class="{{ $step['done'] ? 'text-secondary text-decoration-line-through' : '' }}">
                            <i class="bi {{ $step['done'] ? 'bi-check-circle-fill text-success' : 'bi-circle text-secondary' }} me-2" aria-hidden="true"></i>
                            {{ $step['label'] }}
                            @if ($step['done']) <span class="visually-hidden">(done)</span> @endif
                        </span>
                        @unless ($step['done'])
                            <a href="{{ $step['url'] }}" class="btn btn-sm btn-outline-primary">{{ $step['action'] }}</a>
                        @endunless
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="row g-3 mb-4">
        @isset($recordCount)
            @if ($recordCount !== null)
                <div class="col-sm-6 col-lg-3">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="text-secondary small">Contacts</div>
                            <div class="fs-3 fw-semibold">{{ number_format($recordCount) }}</div>
                        </div>
                    </div>
                </div>
            @endif
        @endisset

        @isset($importsThisMonth)
            <div class="col-sm-6 col-lg-3">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="text-secondary small">
                            @can('view company imports') Imports this month @else Your imports this month @endcan
                        </div>
                        <div class="fs-3 fw-semibold">{{ number_format($importsThisMonth) }}</div>
                        @if ($withErrorsThisMonth || $failedThisMonth)
                            <div class="small">
                                @if ($withErrorsThisMonth)<span class="text-warning-emphasis">{{ $withErrorsThisMonth }} with errors</span>@endif
                                @if ($failedThisMonth)<span class="text-danger ms-1">{{ $failedThisMonth }} failed</span>@endif
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @endisset

        @isset($rowsUsed)
            @php $percent = $rowsLimit > 0 ? min(100, (int) floor($rowsUsed / $rowsLimit * 100)) : 100; @endphp
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <span class="text-secondary small">Imported rows this month ({{ $planName }} plan)</span>
                            <a href="{{ route('usage') }}" class="small">Usage details</a>
                        </div>
                        <div class="fs-3 fw-semibold">
                            {{ number_format($rowsUsed) }} <span class="fs-6 text-secondary">/ {{ number_format($rowsLimit) }}</span>
                        </div>
                        <div class="progress" role="progressbar" aria-label="Monthly import usage" aria-valuenow="{{ $percent }}" aria-valuemin="0" aria-valuemax="100" style="height: .5rem">
                            <div class="progress-bar {{ $percent >= 100 ? 'bg-danger' : ($percent >= 80 ? 'bg-warning' : '') }}" style="width: {{ $percent }}%"></div>
                        </div>
                    </div>
                </div>
            </div>
        @endisset
    </div>

    @isset($recentImports)
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h3 class="card-title mb-0">Recent imports</h3>
                <a href="{{ route('imports.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-upload"></i> New import</a>
            </div>
            <div class="card-body p-0">
                @if ($recentImports->isEmpty())
                    <p class="text-secondary p-3 mb-0">No imports yet. Upload a spreadsheet to get started.</p>
                @else
                    <table class="table mb-0 align-middle">
                        <tbody>
                            @foreach ($recentImports as $import)
                                <tr>
                                    <td><a href="{{ route('imports.show', $import) }}">{{ $import->original_filename }}</a></td>
                                    <td class="text-secondary small">{{ $import->user?->name ?? '—' }}</td>
                                    <td><span class="badge text-bg-{{ $import->status->color() }}">{{ $import->status->label() }}</span></td>
                                    <td class="text-end small">{{ number_format($import->importedRows()) }} imported</td>
                                    <td class="text-end text-secondary small">{{ $import->created_at->diffForHumans() }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>
    @endisset
@endsection
