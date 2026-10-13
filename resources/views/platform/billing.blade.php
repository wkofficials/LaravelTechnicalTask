@extends('layouts.master')

@section('title', 'Billing')
@section('page-title', 'Billing')

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="card h-100"><div class="card-body">
                <div class="text-secondary small">Monthly recurring revenue (paid plans)</div>
                <div class="fs-4 fw-semibold">{{ $mrr->isEmpty() ? '—' : $mrr->implode(' · ') }}</div>
            </div></div>
        </div>
        <div class="col-md-6">
            <div class="card h-100"><div class="card-body">
                <div class="text-secondary small">Outstanding (open invoices)</div>
                <div class="fs-4 fw-semibold">{{ $outstanding->isEmpty() ? '—' : $outstanding->implode(' · ') }}</div>
            </div></div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <ul class="nav nav-pills card-header-pills">
                @foreach (['open' => 'Open', 'overdue' => 'Overdue', 'paid' => 'Paid', 'void' => 'Void'] as $key => $label)
                    <li class="nav-item">
                        <a class="nav-link {{ $filter === $key ? 'active' : '' }}" href="{{ route('platform.billing', ['status' => $key]) }}">{{ $label }}</a>
                    </li>
                @endforeach
            </ul>
        </div>
        <div class="card-body p-0">
            <table class="table align-middle mb-0">
                <thead>
                    <tr><th>Invoice</th><th>Company</th><th class="text-end">Amount</th><th>Due</th><th>Status</th><th class="text-end">Actions</th></tr>
                </thead>
                <tbody>
                    @forelse ($invoices as $invoice)
                        <tr>
                            <td><a href="{{ route('platform.invoices.show', $invoice) }}">{{ $invoice->number }}</a></td>
                            <td><a href="{{ route('platform.companies.show', $invoice->company_id) }}">{{ $invoice->company?->name }}</a></td>
                            <td class="text-end">{{ $invoice->amount() }}</td>
                            <td class="small">{{ $invoice->due_at->toFormattedDateString() }}</td>
                            <td><span class="badge text-bg-{{ $invoice->statusColor() }}">{{ $invoice->statusLabel() }}</span></td>
                            <td class="text-end">@include('platform._invoice-actions')</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-secondary py-4">No invoices.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">{{ $invoices->links() }}</div>
    </div>
@endsection
