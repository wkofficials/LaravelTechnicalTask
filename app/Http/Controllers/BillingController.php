<?php

namespace App\Http\Controllers;

use App\Billing\Billing;
use App\Models\Invoice;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Notifications\BillingNotification;
use App\Support\Audit;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;

/**
 * Billing for company owners: current plan, invoices, upgrade requests and
 * cancellation. Payments are recorded by platform admins (manual billing).
 */
class BillingController extends Controller
{
    public function __construct(private readonly CurrentCompany $currentCompany)
    {
    }

    public function index(): View
    {
        $company = $this->currentCompany->get();

        return view('billing.index', [
            'company' => $company,
            'plan' => $company->plan ?? Plan::default(),
            'subscription' => $company->subscription,
            'invoices' => Invoice::latest('id')->paginate(15),
            'plans' => Plan::where('is_public', true)->orderBy('id')->get(),
        ]);
    }

    /**
     * Printable invoice (use the browser's "Save as PDF").
     */
    public function showInvoice(Invoice $invoice): View
    {
        // Route binding is tenant-scoped: other companies' invoices are 404s.
        return view('billing.invoice', ['invoice' => $invoice->load('company', 'plan'), 'backUrl' => route('billing.index')]);
    }

    public function requestUpgrade(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'plan_id' => ['required', Rule::exists('plans', 'id')->where('is_public', true)],
            'billing_interval' => ['required', Rule::in([Subscription::INTERVAL_MONTHLY, Subscription::INTERVAL_YEARLY])],
            'message' => ['nullable', 'string', 'max:1000'],
        ]);

        $company = $this->currentCompany->get();
        $plan = Plan::findOrFail($validated['plan_id']);

        Notification::send(
            User::where('is_super_admin', true)->get(),
            new BillingNotification(null, "Plan request: {$company->name} → {$plan->name} ({$validated['billing_interval']})",
                trim("Requested by {$request->user()->name} <{$request->user()->email}>. ".($validated['message'] ?? '')),
                'platform.companies.show', [$company], 'info')
        );

        Audit::log('billing.plan_requested', $company, ['plan' => $plan->key, 'interval' => $validated['billing_interval']]);

        return back()->with('status', "Thanks — we'll send you an invoice for the {$plan->name} plan shortly.");
    }

    public function cancel(Request $request, Billing $billing): RedirectResponse
    {
        return $this->setCancellation($request, $billing, true, 'Your plan will end at the end of the current period. You can resume it until then.');
    }

    public function resume(Request $request, Billing $billing): RedirectResponse
    {
        return $this->setCancellation($request, $billing, false, 'Your plan will renew as usual.');
    }

    private function setCancellation(Request $request, Billing $billing, bool $cancel, string $message): RedirectResponse
    {
        try {
            $billing->setCancelAtPeriodEnd($this->currentCompany->get(), $cancel, $request->user());
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['billing' => $e->getMessage()]);
        }

        return back()->with('status', $message);
    }
}
