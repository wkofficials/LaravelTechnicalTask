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
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header"><h3 class="card-title mb-0">Current plan</h3></div>
                <div class="card-body">
                    <div class="fs-4 fw-semibold">{{ $plan->name }}</div>

                    @if ($subscription && $subscription->isLive())
                        <span class="badge text-bg-{{ $subscription->statusColor() }}">{{ $subscription->statusLabel() }}</span>

                        @if ($subscription->status === \App\Models\Subscription::STATUS_TRIALING)
                            <p class="mt-2 mb-0">Trial ends on <strong>{{ $subscription->trial_ends_at->toFormattedDateString() }}</strong>.
                                To keep the plan, request it below and pay the invoice before then.</p>
                        @elseif ($subscription->status === \App\Models\Subscription::STATUS_PAST_DUE)
                            <p class="mt-2 mb-0 text-danger">The period ended on {{ $subscription->current_period_end->toFormattedDateString() }} without payment.
                                Pay the open invoice within {{ config('billing.grace_days') }} days of that date to keep the plan.</p>
                        @else
                            <p class="mt-2 mb-0">
                                Paid {{ $subscription->billing_interval }} until <strong>{{ $subscription->current_period_end->toFormattedDateString() }}</strong>.
                                @if ($subscription->cancel_at_period_end)
                                    It will then move to the free plan.
                                @else
                                    A renewal invoice is sent {{ config('billing.renewal_notice_days') }} days before.
                                @endif
                            </p>
                            <form method="POST" action="{{ route($subscription->cancel_at_period_end ? 'billing.resume' : 'billing.cancel') }}" class="mt-3"
                                  @unless ($subscription->cancel_at_period_end) onsubmit="return confirm('Stop renewing at the end of the paid period? You can undo this until then.');" @endunless>
                                @csrf
                                <button type="submit" class="btn btn-sm {{ $subscription->cancel_at_period_end ? 'btn-outline-primary' : 'btn-outline-danger' }}">
                                    {{ $subscription->cancel_at_period_end ? 'Keep my plan' : 'Cancel at period end' }}
                                </button>
                            </form>
                        @endif
                    @else
                        <p class="mt-2 mb-0 text-secondary">No paid plan. See <a href="{{ route('usage') }}">Usage &amp; Plan</a> for your limits.</p>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header"><h3 class="card-title mb-0">Change plan</h3></div>
                <form method="POST" action="{{ route('billing.request') }}">
                    @csrf
                    <div class="card-body">
                        <p class="small text-secondary">We'll email an invoice. The new plan starts when payment is received.</p>
                        <div class="row g-2">
                            <div class="col-sm-7">
                                <label for="plan_id" class="form-label small mb-0">Plan</label>
                                <select id="plan_id" name="plan_id" class="form-select form-select-sm">
                                    @foreach ($plans as $option)
                                        <option value="{{ $option->id }}">
                                            {{ $option->name }} — {{ $option->price_monthly_cents !== null ? $option->formattedPrice('monthly').'/month' : 'price on request' }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-sm-5">
                                <label for="billing_interval" class="form-label small mb-0">Billed</label>
                                <select id="billing_interval" name="billing_interval" class="form-select form-select-sm">
                                    <option value="monthly">Monthly</option>
                                    <option value="yearly">Yearly</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label for="message" class="form-label small mb-0">Message (optional)</label>
                                <textarea id="message" name="message" rows="2" maxlength="1000" class="form-control form-control-sm"
                                          placeholder="e.g. billing address or VAT number for the invoice"></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer">
                        <button type="submit" class="btn btn-primary btn-sm">Request this plan</button>
                        <a href="{{ route('pricing') }}" class="btn btn-link btn-sm" target="_blank">Compare plans</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h3 class="card-title mb-0">Invoices</h3></div>
        <div class="card-body p-0">
            <table class="table align-middle mb-0">
                <thead>
                    <tr><th>Invoice</th><th>Period</th><th class="text-end">Amount</th><th>Due</th><th>Status</th></tr>
                </thead>
                <tbody>
                    @forelse ($invoices as $invoice)
                        <tr>
                            <td><a href="{{ route('billing.invoices.show', $invoice) }}">{{ $invoice->number }}</a></td>
                            <td class="small">{{ $invoice->period_start->toFormattedDateString() }} – {{ $invoice->period_end->toFormattedDateString() }}</td>
                            <td class="text-end">{{ $invoice->amount() }}</td>
                            <td class="small">{{ $invoice->due_at->toFormattedDateString() }}</td>
                            <td><span class="badge text-bg-{{ $invoice->statusColor() }}">{{ $invoice->statusLabel() }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-secondary text-center py-4">No invoices yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">{{ $invoices->links() }}</div>
    </div>
@endsection
