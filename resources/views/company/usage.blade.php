@extends('layouts.master')

@section('title', 'Usage & Plan')
@section('page-title', 'Usage & Plan')

@section('content')
    <div class="card mb-4">
        <div class="card-header">
            <h3 class="card-title mb-0">{{ $plan->name }} plan</h3>
        </div>
        <div class="card-body">
            <p class="text-secondary">
                Monthly usage for {{ \Carbon\Carbon::createFromFormat('Y-m', $period)->format('F Y') }}
                (resets on the 1st, in your company's timezone). Imported rows count rows added or updated;
                rejected and skipped rows don't count. Plan changes are handled by our team &mdash; contact support to upgrade.
            </p>

            @foreach ($meters as $meter)
                @php $percent = $meter['limit'] > 0 ? min(100, (int) floor($meter['used'] / $meter['limit'] * 100)) : 100; @endphp
                <div class="mb-3">
                    <div class="d-flex justify-content-between">
                        <span>{{ $meter['label'] }}</span>
                        <span class="text-secondary">{{ number_format($meter['used']) }} / {{ number_format($meter['limit']) }}</span>
                    </div>
                    <div class="progress" role="progressbar" aria-label="{{ $meter['label'] }}" aria-valuenow="{{ $percent }}" aria-valuemin="0" aria-valuemax="100">
                        <div class="progress-bar {{ $percent >= 100 ? 'bg-danger' : ($percent >= 80 ? 'bg-warning' : '') }}" style="width: {{ $percent }}%">{{ $percent }}%</div>
                    </div>
                </div>
            @endforeach

            <dl class="row mb-0 mt-4">
                @foreach ($perImport as $label => $value)
                    <dt class="col-sm-4 fw-normal text-secondary">{{ $label }}</dt>
                    <dd class="col-sm-8">{{ $value }}</dd>
                @endforeach
                <dt class="col-sm-4 fw-normal text-secondary">Contacts stored</dt>
                <dd class="col-sm-8">{{ number_format($recordCount) }}</dd>
            </dl>
        </div>
    </div>
@endsection
