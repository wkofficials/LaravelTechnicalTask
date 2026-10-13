<?php

namespace Tests\Feature;

use App\Billing\Billing;
use App\Models\ActivityLog;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Notifications\BillingNotification;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class BillingTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $admin;

    private Plan $standard;

    private Billing $billing;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        Notification::fake();
        $this->travelTo(CarbonImmutable::parse('2026-10-01 09:00:00'));

        $this->owner = $this->companyUser('owner');
        $this->admin = User::factory()->create();
        $this->admin->forceFill(['is_super_admin' => true])->save();

        $this->standard = Plan::where('key', 'standard')->first();
        $this->standard->forceFill(['price_monthly_cents' => 4900, 'price_yearly_cents' => 49000, 'currency' => 'USD'])->save();

        $this->billing = app(Billing::class);
    }

    private function freePlanId(): int
    {
        return Plan::default()->id;
    }

    // --- Issuing and paying ---------------------------------------------

    public function test_admin_issues_an_invoice_and_owners_are_notified(): void
    {
        $this->actingAs($this->admin)->post(route('platform.companies.invoices.store', $this->company), [
            'plan_id' => $this->standard->id,
            'billing_interval' => 'monthly',
        ])->assertSessionHasNoErrors();

        $invoice = Invoice::withoutGlobalScopes()->sole();
        $this->assertSame([4900, 'USD', 'open'], [$invoice->amount_cents, $invoice->currency, $invoice->status]);
        $this->assertSame(sprintf('INV-2026-%06d', $invoice->id), $invoice->number);
        $this->assertSame('2026-10-15', $invoice->due_at->toDateString());
        $this->assertSame('2026-11-01', $invoice->period_end->toDateString());

        Notification::assertSentTo($this->owner, BillingNotification::class);
        // Nothing changes until it is paid.
        $this->assertNull($this->company->fresh()->plan_id);
    }

    public function test_an_amount_is_required_when_the_plan_has_no_price(): void
    {
        $business = Plan::where('key', 'business')->first(); // no price set

        $this->actingAs($this->admin)->post(route('platform.companies.invoices.store', $this->company), [
            'plan_id' => $business->id,
            'billing_interval' => 'monthly',
        ])->assertSessionHasErrors('amount');

        $this->actingAs($this->admin)->post(route('platform.companies.invoices.store', $this->company), [
            'plan_id' => $business->id,
            'billing_interval' => 'yearly',
            'amount' => '1200.50',
        ])->assertSessionHasNoErrors();

        $this->assertSame(120050, Invoice::withoutGlobalScopes()->sole()->amount_cents);
    }

    public function test_marking_paid_activates_the_plan_for_the_period(): void
    {
        $invoice = $this->billing->issueInvoice($this->company, $this->standard, 'monthly');

        $this->actingAs($this->admin)->post(route('platform.invoices.paid', $invoice), ['payment_reference' => 'HBL-123'])
            ->assertSessionHasNoErrors();

        $subscription = $this->company->fresh()->subscription;
        $this->assertSame(Subscription::STATUS_ACTIVE, $subscription->status);
        $this->assertSame('2026-11-01', $subscription->current_period_end->toDateString());
        $this->assertSame($this->standard->id, $this->company->fresh()->plan_id);
        $this->assertSame('HBL-123', $invoice->fresh()->payment_reference);
        $this->assertTrue(ActivityLog::where('action', 'billing.invoice_paid')->where('company_id', $this->company->id)->exists());
    }

    public function test_an_invoice_cannot_be_paid_twice_or_after_voiding(): void
    {
        $invoice = $this->billing->issueInvoice($this->company, $this->standard, 'monthly');
        $this->billing->markPaid($invoice, null);

        $this->actingAs($this->admin)->post(route('platform.invoices.paid', $invoice))->assertSessionHasErrors('invoice');
        $this->actingAs($this->admin)->post(route('platform.invoices.void', $invoice))->assertSessionHasErrors('invoice');

        $other = $this->billing->issueInvoice($this->company, $this->standard, 'monthly');
        $this->billing->void($other);
        $this->actingAs($this->admin)->post(route('platform.invoices.paid', $other))->assertSessionHasErrors('invoice');
    }

    // --- Renewal, past due, downgrade ------------------------------------

    public function test_full_lifecycle_renewal_paid_on_time(): void
    {
        $this->billing->markPaid($this->billing->issueInvoice($this->company, $this->standard, 'monthly'), null);

        // 7 days before the period ends a renewal invoice is issued, due at period end.
        $this->travelTo(CarbonImmutable::parse('2026-10-25 10:00'));
        $this->assertSame(1, $this->billing->runDaily()['renewal_invoices']);
        $this->assertSame(0, $this->billing->runDaily()['renewal_invoices'], 'not issued twice');

        $renewal = Invoice::withoutGlobalScopes()->where('status', 'open')->sole();
        $this->assertSame(['2026-11-01', '2026-12-01', '2026-11-01'], [
            $renewal->period_start->toDateString(), $renewal->period_end->toDateString(), $renewal->due_at->toDateString(),
        ]);

        $this->billing->markPaid($renewal, 'ref');
        $this->travelTo(CarbonImmutable::parse('2026-11-02 03:00'));
        $this->billing->runDaily();

        $subscription = $this->company->fresh()->subscription;
        $this->assertSame(Subscription::STATUS_ACTIVE, $subscription->status);
        $this->assertSame('2026-12-01', $subscription->current_period_end->toDateString());
    }

    public function test_unpaid_renewal_goes_past_due_then_downgrades_after_grace(): void
    {
        $this->billing->markPaid($this->billing->issueInvoice($this->company, $this->standard, 'monthly'), null);

        $this->travelTo(CarbonImmutable::parse('2026-11-01 10:00'));
        $this->billing->runDaily();
        $this->assertSame(Subscription::STATUS_PAST_DUE, $this->company->fresh()->subscription->status);
        $this->assertSame($this->standard->id, $this->company->fresh()->plan_id, 'plan kept during grace');

        $this->travelTo(CarbonImmutable::parse('2026-11-08 10:00'));
        $this->assertSame(1, $this->billing->runDaily()['downgraded']);

        $this->assertSame(Subscription::STATUS_CANCELLED, $this->company->fresh()->subscription->status);
        $this->assertSame($this->freePlanId(), $this->company->fresh()->plan_id);
        Notification::assertSentTo($this->owner, BillingNotification::class, fn ($n) => str_contains($n->toArray($this->owner)['title'], 'moved to the Free plan'));
    }

    public function test_paying_while_past_due_restores_the_subscription(): void
    {
        $this->billing->markPaid($this->billing->issueInvoice($this->company, $this->standard, 'monthly'), null);
        $this->travelTo(CarbonImmutable::parse('2026-10-25'));
        $this->billing->runDaily();
        $this->travelTo(CarbonImmutable::parse('2026-11-03'));
        $this->billing->runDaily();

        $this->billing->markPaid(Invoice::withoutGlobalScopes()->where('status', 'open')->sole(), 'late');

        $subscription = $this->company->fresh()->subscription;
        $this->assertSame(Subscription::STATUS_ACTIVE, $subscription->status);
        $this->assertSame('2026-12-01', $subscription->current_period_end->toDateString());
    }

    public function test_cancel_at_period_end_stops_renewal_and_downgrades_at_the_end(): void
    {
        $this->billing->markPaid($this->billing->issueInvoice($this->company, $this->standard, 'monthly'), null);

        $this->actingAs($this->owner)->post(route('billing.cancel'))->assertSessionHasNoErrors();

        $this->travelTo(CarbonImmutable::parse('2026-10-26'));
        $this->assertSame(0, $this->billing->runDaily()['renewal_invoices']);

        $this->travelTo(CarbonImmutable::parse('2026-11-01 10:00'));
        $this->assertSame(1, $this->billing->runDaily()['cancelled_at_period_end']);
        $this->assertSame($this->freePlanId(), $this->company->fresh()->plan_id);
    }

    public function test_owner_can_resume_before_the_period_ends(): void
    {
        $this->billing->markPaid($this->billing->issueInvoice($this->company, $this->standard, 'monthly'), null);
        $this->actingAs($this->owner)->post(route('billing.cancel'));
        $this->actingAs($this->owner)->post(route('billing.resume'));

        $this->assertFalse($this->company->fresh()->subscription->cancel_at_period_end);
    }

    public function test_trial_gives_the_plan_and_ends_on_the_free_plan_without_payment(): void
    {
        $this->actingAs($this->admin)->post(route('platform.companies.trial', $this->company), ['plan_id' => $this->standard->id]);

        $this->assertSame($this->standard->id, $this->company->fresh()->plan_id);
        $this->assertSame('2026-10-15', $this->company->fresh()->subscription->trial_ends_at->toDateString());

        $this->travelTo(CarbonImmutable::parse('2026-10-15 10:00'));
        $this->assertSame(1, $this->billing->runDaily()['trials_ended']);
        $this->assertSame($this->freePlanId(), $this->company->fresh()->plan_id);
    }

    public function test_paying_during_a_trial_converts_it(): void
    {
        $this->billing->startTrial($this->company, $this->standard);
        $this->billing->markPaid($this->billing->issueInvoice($this->company, $this->standard, 'yearly'), null);

        $this->travelTo(CarbonImmutable::parse('2026-10-20'));
        $this->assertSame(0, $this->billing->runDaily()['trials_ended']);
        $this->assertSame('2027-10-01', $this->company->fresh()->subscription->current_period_end->toDateString());
    }

    // --- Owner screens and access ----------------------------------------

    public function test_owner_sees_billing_and_can_open_own_invoices_only(): void
    {
        $mine = $this->billing->issueInvoice($this->company, $this->standard, 'monthly');
        $otherCompany = Company::factory()->create();
        $theirs = $this->billing->issueInvoice($otherCompany, $this->standard, 'monthly');

        $this->actingAs($this->owner)->get(route('billing.index'))->assertOk()->assertSee($mine->number)->assertDontSee($theirs->number);
        $this->actingAs($this->owner)->get(route('billing.invoices.show', $mine))->assertOk()->assertSee('USD 49.00');
        $this->actingAs($this->owner)->get(route('billing.invoices.show', $theirs))->assertNotFound();
    }

    public function test_only_owners_can_see_billing(): void
    {
        $this->actingAs($this->companyUser('manager'))->get(route('billing.index'))->assertForbidden();
    }

    public function test_owner_can_request_a_plan_and_platform_admins_are_told(): void
    {
        $this->actingAs($this->owner)->post(route('billing.request'), [
            'plan_id' => $this->standard->id,
            'billing_interval' => 'yearly',
            'message' => 'VAT 12345',
        ])->assertSessionHasNoErrors();

        Notification::assertSentTo($this->admin, BillingNotification::class, fn ($n) => str_contains($n->toArray($this->admin)['message'], 'VAT 12345'));
        $this->assertNull($this->company->fresh()->plan_id, 'a request alone changes nothing');
    }

    public function test_company_users_cannot_issue_or_pay_invoices(): void
    {
        $invoice = $this->billing->issueInvoice($this->company, $this->standard, 'monthly');

        $this->actingAs($this->owner)->post(route('platform.invoices.paid', $invoice))->assertNotFound();
        $this->actingAs($this->owner)->post(route('platform.companies.invoices.store', $this->company), ['plan_id' => $this->standard->id, 'billing_interval' => 'monthly'])->assertNotFound();

        $this->assertSame('open', $invoice->fresh()->status);
    }

    public function test_mrr_uses_what_companies_actually_pay(): void
    {
        // Discounted monthly invoice and a yearly one (counted as 1/12).
        $this->billing->markPaid($this->billing->issueInvoice($this->company, $this->standard, 'monthly', 3000), null);
        $other = Company::factory()->create();
        $this->billing->markPaid($this->billing->issueInvoice($other, $this->standard, 'yearly', 120000), null);

        $this->actingAs($this->admin)->get(route('platform.billing'))
            ->assertOk()
            ->assertSee('USD 130.00'); // 30.00 + 1200.00 / 12
    }

    public function test_daily_command_runs(): void
    {
        $this->artisan('billing:run')->assertSuccessful();
    }
}
