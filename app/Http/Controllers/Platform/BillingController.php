<?php

namespace App\Http\Controllers\Platform;

use App\Billing\Billing;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Plan;
use App\Models\Subscription;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;

/**
 * Platform side of manual billing: issue invoices, record payments, void,
 * start trials, and see what is outstanding.
 */
class BillingController extends Controller
{
    public function __construct(private readonly Billing $billing)
    {
    }

    public function index(Request $request): View
    {
        $filter = in_array($request->query('status'), ['open', 'overdue', 'paid', 'void'], true) ? $request->query('status') : 'open';

        $invoices = Invoice::with('company:id,name')
            ->when($filter === 'overdue', fn ($q) => $q->where('status', Invoice::STATUS_OPEN)->where('due_at', '<', now()))
            ->when($filter !== 'overdue', fn ($q) => $q->where('status', $filter))
            ->orderBy($filter === 'paid' ? 'paid_at' : 'due_at', $filter === 'paid' ? 'desc' : 'asc')
            ->paginate(25)
            ->withQueryString();

        // Monthly recurring revenue: what each live paid subscription actually
        // pays (its latest paid invoice, so discounts count), yearly / 12.
        $mrr = Subscription::whereIn('status', [Subscription::STATUS_ACTIVE, Subscription::STATUS_PAST_DUE])->get()
            ->map(fn ($s) => Invoice::withoutGlobalScopes()->where('company_id', $s->company_id)
                ->where('status', Invoice::STATUS_PAID)->latest('paid_at')->first())
            ->filter()
            ->groupBy('currency')
            ->map(fn ($invoices, $currency) => Money::format((int) round($invoices->sum(
                fn ($invoice) => $invoice->billing_interval === Subscription::INTERVAL_YEARLY ? $invoice->amount_cents / 12 : $invoice->amount_cents
            )), $currency));

        return view('platform.billing', [
            'invoices' => $invoices,
            'filter' => $filter,
            'mrr' => $mrr,
            'outstanding' => Invoice::where('status', Invoice::STATUS_OPEN)->get()->groupBy('currency')
                ->map(fn ($invoices, $currency) => Money::format($invoices->sum('amount_cents'), $currency)),
        ]);
    }

    public function showInvoice(Invoice $invoice): View
    {
        return view('billing.invoice', ['invoice' => $invoice->load('company', 'plan'), 'backUrl' => route('platform.companies.show', $invoice->company)]);
    }

    public function issueInvoice(Request $request, Company $company): RedirectResponse
    {
        $validated = $request->validate([
            'plan_id' => ['required', Rule::exists('plans', 'id')],
            'billing_interval' => ['required', Rule::in([Subscription::INTERVAL_MONTHLY, Subscription::INTERVAL_YEARLY])],
            'amount' => ['nullable', 'numeric', 'min:0', 'max:10000000'],
            'period_start' => ['nullable', 'date'],
        ]);

        try {
            $invoice = $this->billing->issueInvoice(
                $company,
                Plan::findOrFail($validated['plan_id']),
                $validated['billing_interval'],
                Money::toCents($validated['amount'] ?? null),
                isset($validated['period_start']) ? CarbonImmutable::parse($validated['period_start']) : null,
                actor: $request->user(),
            );
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['amount' => $e->getMessage()])->withInput();
        }

        return back()->with('status', "Invoice {$invoice->number} issued and sent to the owners.");
    }

    public function markPaid(Request $request, Invoice $invoice): RedirectResponse
    {
        $validated = $request->validate(['payment_reference' => ['nullable', 'string', 'max:255']]);

        try {
            $this->billing->markPaid($invoice, $validated['payment_reference'] ?? null, $request->user());
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['invoice' => $e->getMessage()]);
        }

        return back()->with('status', "Invoice {$invoice->number} marked paid; the plan is active.");
    }

    public function void(Request $request, Invoice $invoice): RedirectResponse
    {
        try {
            $this->billing->void($invoice, $request->user());
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['invoice' => $e->getMessage()]);
        }

        return back()->with('status', "Invoice {$invoice->number} voided.");
    }

    public function startTrial(Request $request, Company $company): RedirectResponse
    {
        $validated = $request->validate(['plan_id' => ['required', Rule::exists('plans', 'id')]]);

        $subscription = $this->billing->startTrial($company, Plan::findOrFail($validated['plan_id']), $request->user());

        return back()->with('status', "Trial started; it ends {$subscription->trial_ends_at->toFormattedDateString()}.");
    }
}
