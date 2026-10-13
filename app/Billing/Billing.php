<?php

namespace App\Billing;

use App\Models\Company;
use App\Models\Invoice;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Notifications\BillingNotification;
use App\Support\Audit;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

/**
 * Manual billing: invoices are issued (by a platform admin or automatically
 * before a renewal), paid by bank transfer, and marked paid by a platform
 * admin. Every state change goes through this class.
 *
 * The company's effective plan is companies.plan_id. It changes only here:
 * when a trial starts, when an invoice is paid, and on downgrade to the
 * default plan after non-payment. Billing never deletes company data.
 */
class Billing
{
    public function startTrial(Company $company, Plan $plan, ?User $actor = null): Subscription
    {
        return DB::transaction(function () use ($company, $plan, $actor) {
            $subscription = $this->subscriptionFor($company);
            $subscription->forceFill([
                'plan_id' => $plan->id,
                'status' => Subscription::STATUS_TRIALING,
                'billing_interval' => null,
                'trial_ends_at' => now()->addDays((int) config('billing.trial_days')),
                'current_period_start' => null,
                'current_period_end' => null,
                'cancel_at_period_end' => false,
                'ended_at' => null,
            ])->save();

            $company->forceFill(['plan_id' => $plan->id])->save();

            Audit::log('billing.trial_started', $subscription, ['plan' => $plan->key, 'ends' => $subscription->trial_ends_at->toDateString()], $company, $actor);

            return $subscription;
        });
    }

    /**
     * @param  int|null  $amountCents  Defaults to the plan's price for the interval.
     * @param  CarbonImmutable|null  $periodStart  Defaults to the end of the current paid period, else now.
     */
    public function issueInvoice(
        Company $company,
        Plan $plan,
        string $interval,
        ?int $amountCents = null,
        ?CarbonImmutable $periodStart = null,
        ?CarbonImmutable $dueAt = null,
        ?User $actor = null,
    ): Invoice {
        if (! in_array($interval, [Subscription::INTERVAL_MONTHLY, Subscription::INTERVAL_YEARLY], true)) {
            throw new InvalidArgumentException("Unknown billing interval [{$interval}].");
        }

        $amountCents ??= $plan->priceFor($interval);

        if ($amountCents === null) {
            throw new InvalidArgumentException("Plan {$plan->name} has no {$interval} price; enter an amount.");
        }

        $subscription = $company->subscription;
        $periodStart ??= $subscription?->status === Subscription::STATUS_ACTIVE && $subscription->current_period_end?->isFuture()
            ? CarbonImmutable::instance($subscription->current_period_end)
            : CarbonImmutable::now();
        $periodEnd = $interval === Subscription::INTERVAL_YEARLY ? $periodStart->addYearNoOverflow() : $periodStart->addMonthNoOverflow();

        $invoice = DB::transaction(function () use ($company, $plan, $interval, $amountCents, $periodStart, $periodEnd, $dueAt, $actor) {
            $invoice = new Invoice();
            $invoice->forceFill([
                'company_id' => $company->id,
                'plan_id' => $plan->id,
                'status' => Invoice::STATUS_OPEN,
                'billing_interval' => $interval,
                'currency' => $plan->currency,
                'amount_cents' => $amountCents,
                'description' => "{$plan->name} plan, {$interval} ({$periodStart->toFormattedDateString()} – {$periodEnd->toFormattedDateString()})",
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'due_at' => $dueAt ?? now()->addDays((int) config('billing.invoice_due_days')),
                'issued_by' => $actor?->id,
            ])->save();

            // Sequential per year, derived from the unique id: no gaps from races.
            $invoice->forceFill(['number' => sprintf('INV-%s-%06d', now()->format('Y'), $invoice->id)])->save();

            Audit::log('billing.invoice_issued', $invoice, ['number' => $invoice->number, 'amount' => $invoice->amount()], $company, $actor);

            return $invoice;
        });

        $this->notifyOwners($company, "Invoice {$invoice->number} ({$invoice->amount()})",
            "A new invoice for {$company->name} is due on {$invoice->due_at->toFormattedDateString()}. ".config('billing.seller.payment_instructions'),
            'billing.invoices.show', [$invoice], 'warning');

        return $invoice;
    }

    /**
     * Record a received payment: the invoice's plan and period become the
     * company's active subscription.
     */
    public function markPaid(Invoice $invoice, ?string $reference, ?User $actor = null): Invoice
    {
        $paid = DB::transaction(function () use ($invoice, $reference, $actor) {
            // Billing is platform-level: never depend on the request's tenant scope.
            $invoice = Invoice::withoutGlobalScopes()->lockForUpdate()->findOrFail($invoice->id);

            if ($invoice->status !== Invoice::STATUS_OPEN) {
                throw new InvalidArgumentException("Invoice {$invoice->number} is {$invoice->status}, not open.");
            }

            $invoice->forceFill(['status' => Invoice::STATUS_PAID, 'paid_at' => now(), 'payment_reference' => $reference])->save();

            $company = $invoice->company;
            $subscription = $this->subscriptionFor($company);
            $periodEnd = $subscription->current_period_end && $subscription->isLive() && $subscription->current_period_end->gt($invoice->period_end)
                ? $subscription->current_period_end   // an older period paid late: keep the later end
                : $invoice->period_end;

            $subscription->forceFill([
                'plan_id' => $invoice->plan_id,
                'status' => Subscription::STATUS_ACTIVE,
                'billing_interval' => $invoice->billing_interval,
                'trial_ends_at' => null,
                'current_period_start' => $invoice->period_start,
                'current_period_end' => $periodEnd,
                'ended_at' => null,
            ])->save();

            $company->forceFill(['plan_id' => $invoice->plan_id])->save();

            Audit::log('billing.invoice_paid', $invoice, ['number' => $invoice->number, 'reference_recorded' => $reference !== null && $reference !== ''], $company, $actor);

            return $invoice;
        });

        $this->notifyOwners($paid->company, "Payment received for {$paid->number}",
            "Thank you — {$paid->amount()} received. {$paid->plan->name} is active until {$paid->company->subscription->current_period_end->toFormattedDateString()}.",
            'billing.invoices.show', [$paid], 'success');

        return $paid;
    }

    public function void(Invoice $invoice, ?User $actor = null): void
    {
        if ($invoice->status !== Invoice::STATUS_OPEN) {
            throw new InvalidArgumentException("Only open invoices can be voided.");
        }

        $invoice->forceFill(['status' => Invoice::STATUS_VOID])->save();

        Audit::log('billing.invoice_voided', $invoice, ['number' => $invoice->number], $invoice->company, $actor);
    }

    /**
     * Owner choice: stop at the end of the paid period (no renewal invoice).
     */
    public function setCancelAtPeriodEnd(Company $company, bool $cancel, ?User $actor = null): void
    {
        $subscription = $company->subscription;

        if (! $subscription || $subscription->status !== Subscription::STATUS_ACTIVE) {
            throw new InvalidArgumentException('Only an active paid plan can be cancelled or resumed.');
        }

        $subscription->forceFill(['cancel_at_period_end' => $cancel])->save();

        Audit::log($cancel ? 'billing.cancellation_requested' : 'billing.cancellation_withdrawn', $subscription, [], $company, $actor);
    }

    /**
     * Move a company to the default (free) plan. Nothing is deleted; limits
     * just shrink (existing data above them stays, new work is blocked).
     */
    public function downgrade(Company $company, string $reason): void
    {
        DB::transaction(function () use ($company, $reason) {
            $subscription = $this->subscriptionFor($company);
            $subscription->forceFill(['status' => Subscription::STATUS_CANCELLED, 'ended_at' => now(), 'cancel_at_period_end' => false])->save();

            $company->forceFill(['plan_id' => Plan::default()->id])->save();

            Audit::log('billing.downgraded', $subscription, ['reason' => $reason], $company);
        });

        $this->notifyOwners($company, "{$company->name} moved to the ".Plan::default()->name.' plan',
            "Reason: {$reason}. Your data is kept; plan limits now apply. Contact us to reactivate a paid plan.",
            'billing.index', [], 'danger');
    }

    /**
     * The daily lifecycle run (scheduled `billing:run`). Safe to run more
     * than once a day: every step checks the current state first.
     *
     * @return array<string, int>  What was done, for logging.
     */
    public function runDaily(): array
    {
        $done = ['trials_ended' => 0, 'cancelled_at_period_end' => 0, 'renewal_invoices' => 0, 'past_due' => 0, 'downgraded' => 0];
        $now = now();

        Subscription::with('company', 'plan')->where('status', Subscription::STATUS_TRIALING)->where('trial_ends_at', '<=', $now)
            ->each(function (Subscription $s) use (&$done) {
                $this->downgrade($s->company, 'the trial ended without a paid invoice');
                $done['trials_ended']++;
            });

        Subscription::with('company', 'plan')->where('status', Subscription::STATUS_ACTIVE)->where('cancel_at_period_end', true)->where('current_period_end', '<=', $now)
            ->each(function (Subscription $s) use (&$done) {
                $this->downgrade($s->company, 'the plan was cancelled at the end of its period');
                $done['cancelled_at_period_end']++;
            });

        // Renewal invoices, due when the current period ends.
        Subscription::with('company', 'plan')->where('status', Subscription::STATUS_ACTIVE)->where('cancel_at_period_end', false)
            ->where('current_period_end', '<=', $now->copy()->addDays((int) config('billing.renewal_notice_days')))
            ->each(function (Subscription $s) use (&$done) {
                if ($this->hasInvoiceFrom($s->company, $s->current_period_end)) {
                    return;
                }

                try {
                    $this->issueInvoice($s->company, $s->plan, $s->billing_interval, null,
                        CarbonImmutable::instance($s->current_period_end), CarbonImmutable::instance($s->current_period_end));
                    $done['renewal_invoices']++;
                } catch (InvalidArgumentException $e) {
                    Log::warning("Billing: no renewal invoice for company #{$s->company_id}: {$e->getMessage()}");
                }
            });

        Subscription::with('company')->where('status', Subscription::STATUS_ACTIVE)->where('current_period_end', '<=', $now)
            ->each(function (Subscription $s) use (&$done) {
                $s->forceFill(['status' => Subscription::STATUS_PAST_DUE])->save();
                Audit::log('billing.past_due', $s, [], $s->company);
                $this->notifyOwners($s->company, "Payment overdue for {$s->company->name}",
                    'Your plan period ended without payment. It stays active for '.config('billing.grace_days').' more days; after that the company moves to the free plan.',
                    'billing.index', [], 'danger');
                $done['past_due']++;
            });

        Subscription::with('company')->where('status', Subscription::STATUS_PAST_DUE)
            ->where('current_period_end', '<=', $now->copy()->subDays((int) config('billing.grace_days')))
            ->each(function (Subscription $s) use (&$done) {
                $this->downgrade($s->company, 'the invoice was not paid within the grace period');
                $done['downgraded']++;
            });

        return $done;
    }

    private function subscriptionFor(Company $company): Subscription
    {
        $subscription = Subscription::where('company_id', $company->id)->first();

        if ($subscription === null) {
            $subscription = new Subscription();
            $subscription->forceFill(['company_id' => $company->id, 'plan_id' => $company->plan_id ?? Plan::default()->id]);
        }

        return $subscription;
    }

    private function hasInvoiceFrom(Company $company, $periodStart): bool
    {
        return Invoice::withoutGlobalScopes()->where('company_id', $company->id)
            ->whereIn('status', [Invoice::STATUS_OPEN, Invoice::STATUS_PAID])
            ->where('period_start', '>=', $periodStart)
            ->exists();
    }

    /**
     * Billing emails must never break a billing action.
     */
    private function notifyOwners(Company $company, string $title, string $message, string $route, array $parameters, string $level): void
    {
        foreach ($company->owners() as $owner) {
            try {
                $owner->notify(new BillingNotification($company, $title, $message, $route, $parameters, $level));
            } catch (Throwable $e) {
                report($e);
            }
        }
    }
}
