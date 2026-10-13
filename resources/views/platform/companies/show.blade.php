@extends('layouts.master')

@section('title', $company->name)
@section('page-title', $company->name)

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="row g-3">
        <div class="col-lg-6">
            <div class="card mb-3">
                <div class="card-header"><h3 class="card-title mb-0">Usage</h3></div>
                <ul class="list-group list-group-flush">
                    @foreach ($usage as $label => $value)
                        <li class="list-group-item d-flex justify-content-between"><span>{{ $label }}</span><span>{{ $value }}</span></li>
                    @endforeach
                </ul>
            </div>

            <div class="card">
                <div class="card-header"><h3 class="card-title mb-0">Imports by status (all time)</h3></div>
                <ul class="list-group list-group-flush">
                    @foreach ($importsByStatus as $label => $count)
                        <li class="list-group-item d-flex justify-content-between"><span>{{ $label }}</span><span>{{ number_format($count) }}</span></li>
                    @endforeach
                </ul>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card mb-3">
                <div class="card-header"><h3 class="card-title mb-0">Account</h3></div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4 fw-normal text-secondary">Slug</dt><dd class="col-sm-8">{{ $company->slug }}</dd>
                        <dt class="col-sm-4 fw-normal text-secondary">Timezone</dt><dd class="col-sm-8">{{ $company->timezone }}</dd>
                        <dt class="col-sm-4 fw-normal text-secondary">Members</dt><dd class="col-sm-8">{{ $company->users_count }}</dd>
                        <dt class="col-sm-4 fw-normal text-secondary">Owners</dt>
                        <dd class="col-sm-8">{{ $owners->map(fn ($o) => $o->name.' <'.$o->email.'>')->implode(', ') ?: '—' }}</dd>
                        <dt class="col-sm-4 fw-normal text-secondary">Created</dt><dd class="col-sm-8">{{ $company->created_at->toDayDateTimeString() }}</dd>
                    </dl>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h3 class="card-title mb-0">Plan &amp; status</h3></div>
                <form method="POST" action="{{ route('platform.companies.update', $company) }}">
                    @csrf
                    @method('PUT')
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="plan_id" class="form-label">Plan <span class="text-secondary small">(manual override &mdash; normally set by billing below)</span></label>
                            <select id="plan_id" name="plan_id" class="form-select">
                                @foreach ($plans as $option)
                                    <option value="{{ $option->id }}" @selected($plan->id === $option->id)>{{ $option->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-0">
                            <label for="status" class="form-label">Status</label>
                            <select id="status" name="status" class="form-select">
                                <option value="active" @selected($company->isActive())>Active</option>
                                <option value="suspended" @selected(! $company->isActive())>Suspended (members are locked out)</option>
                            </select>
                        </div>
                    </div>
                    <div class="card-footer">
                        <button type="submit" class="btn btn-primary"
                                onclick="return document.getElementById('status').value !== 'suspended' || confirm('Suspend this company? All its members lose access immediately.');">
                            Save
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="card mt-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title mb-0">Billing</h3>
            @if ($subscription)
                <span>
                    <span class="badge text-bg-{{ $subscription->statusColor() }}">{{ $subscription->statusLabel() }}</span>
                    <span class="small text-secondary">
                        {{ $subscription->plan->name }}
                        @if ($subscription->current_period_end) &middot; until {{ $subscription->current_period_end->toFormattedDateString() }} @endif
                        @if ($subscription->trial_ends_at) &middot; trial ends {{ $subscription->trial_ends_at->toFormattedDateString() }} @endif
                    </span>
                </span>
            @else
                <span class="small text-secondary">No subscription (default plan)</span>
            @endif
        </div>
        <div class="card-body">
            @if ($errors->any())
                <div class="alert alert-danger">{{ $errors->first() }}</div>
            @endif

            <div class="row g-4">
                <div class="col-lg-7">
                    <h6>Issue an invoice</h6>
                    <form method="POST" action="{{ route('platform.companies.invoices.store', $company) }}" class="row g-2 align-items-end">
                        @csrf
                        <div class="col-sm-4">
                            <label class="form-label small mb-0" for="inv-plan">Plan</label>
                            <select id="inv-plan" name="plan_id" class="form-select form-select-sm">
                                @foreach ($plans as $option)
                                    <option value="{{ $option->id }}" @selected(old('plan_id') == $option->id)>{{ $option->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-sm-3">
                            <label class="form-label small mb-0" for="inv-interval">Interval</label>
                            <select id="inv-interval" name="billing_interval" class="form-select form-select-sm">
                                <option value="monthly">Monthly</option>
                                <option value="yearly">Yearly</option>
                            </select>
                        </div>
                        <div class="col-sm-3">
                            <label class="form-label small mb-0" for="inv-amount">Amount</label>
                            <input id="inv-amount" type="number" step="0.01" min="0" name="amount" value="{{ old('amount') }}"
                                   class="form-control form-control-sm" placeholder="plan price">
                        </div>
                        <div class="col-sm-2">
                            <button type="submit" class="btn btn-sm btn-primary w-100">Issue</button>
                        </div>
                        <div class="col-12 form-text">Leave the amount empty to use the plan's price. The period starts when the current paid period ends (or today).</div>
                    </form>
                </div>
                <div class="col-lg-5">
                    <h6>Start a trial</h6>
                    <form method="POST" action="{{ route('platform.companies.trial', $company) }}" class="d-flex gap-2">
                        @csrf
                        <select name="plan_id" class="form-select form-select-sm" aria-label="Trial plan">
                            @foreach ($plans as $option)
                                <option value="{{ $option->id }}">{{ $option->name }}</option>
                            @endforeach
                        </select>
                        <button type="submit" class="btn btn-sm btn-outline-primary text-nowrap">{{ config('billing.trial_days') }}-day trial</button>
                    </form>
                </div>
            </div>

            <h6 class="mt-4">Invoices</h6>
            <table class="table table-sm align-middle mb-0">
                <tbody>
                    @forelse ($invoices as $invoice)
                        <tr>
                            <td><a href="{{ route('platform.invoices.show', $invoice) }}">{{ $invoice->number }}</a></td>
                            <td class="small">{{ $invoice->description }}</td>
                            <td class="text-end">{{ $invoice->amount() }}</td>
                            <td><span class="badge text-bg-{{ $invoice->statusColor() }}">{{ $invoice->statusLabel() }}</span></td>
                            <td class="text-end">@include('platform._invoice-actions')</td>
                        </tr>
                    @empty
                        <tr><td class="text-secondary">No invoices yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
