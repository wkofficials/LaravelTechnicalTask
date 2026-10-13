@extends('layouts.master')

@section('title', 'Audit Log')
@section('page-title', 'Audit Log')

@section('content')
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h3 class="card-title mb-0">Activity in this company</h3>
            <form method="GET" action="{{ route('audit.index') }}">
                <select name="area" class="form-select form-select-sm" aria-label="Filter by area" onchange="this.form.submit()">
                    <option value="">All activity</option>
                    @foreach ($areas as $key => $label)
                        <option value="{{ $key }}" @selected($area === $key)>{{ $label }}</option>
                    @endforeach
                </select>
                <noscript><button class="btn btn-sm btn-outline-secondary mt-1">Filter</button></noscript>
            </form>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-striped align-middle mb-0">
                    <thead>
                        <tr>
                            <th>When</th>
                            <th>Who</th>
                            <th>Action</th>
                            <th>Target</th>
                            <th>Details</th>
                            <th>IP</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($logs as $log)
                            {{-- Platform staff stay anonymous to tenants (no name, no IP). --}}
                            @php $byPlatform = str_starts_with($log->action, 'platform.'); @endphp
                            <tr>
                                <td class="text-nowrap" title="{{ $log->created_at }}">{{ $log->created_at->diffForHumans() }}</td>
                                <td>{{ $byPlatform ? 'Platform admin' : ($log->actor?->name ?? 'System') }}</td>
                                <td>{{ $log->actionLabel() }}</td>
                                <td class="text-secondary">{{ $log->target_type ? $log->target_type.' #'.$log->target_id : '—' }}</td>
                                <td class="small text-secondary">
                                    @foreach ($log->metadata ?? [] as $key => $value)
                                        <span class="me-2"><strong>{{ str_replace('_', ' ', $key) }}:</strong>
                                            {{ is_array($value) ? json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : (is_bool($value) ? ($value ? 'yes' : 'no') : $value) }}</span>
                                    @endforeach
                                </td>
                                <td class="small text-secondary">{{ $byPlatform ? '' : $log->ip_address }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-secondary py-4">No activity recorded yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer">{{ $logs->links() }}</div>
    </div>
@endsection
