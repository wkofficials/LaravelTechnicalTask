<?php

namespace App\Http\Controllers\Platform;

use App\Enums\ImportBatchStatus;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\ImportBatch;
use App\Models\Plan;
use App\Models\Record;
use App\Support\Audit;
use App\Support\PlanLimits;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Tenant management for platform admins: list, plan, suspend/activate.
 * Shows counts only — no tenant records, files or row data.
 */
class CompanyController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));

        $companies = Company::query()
            ->with('plan:id,name')
            ->withCount('users')
            ->when($search !== '', fn ($query) => $query->where(fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('slug', 'like', "%{$search}%")))
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return view('platform.companies.index', ['companies' => $companies, 'search' => $search]);
    }

    public function show(Company $company): View
    {
        $limits = PlanLimits::for($company);

        $importsByStatus = ImportBatch::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return view('platform.companies.show', [
            'company' => $company->loadCount('users'),
            'plan' => $limits->plan(),
            'plans' => Plan::orderBy('id')->get(),
            'usage' => [
                'Imported rows this month' => number_format($limits->importRowsUsed()).' / '.number_format($limits->plan()->monthly_import_rows),
                'Users (incl. pending invitations)' => $limits->seatsUsed().' / '.$limits->plan()->max_users,
                'Templates' => $limits->templatesUsed().' / '.$limits->plan()->max_templates,
                'Records' => number_format(Record::withoutGlobalScopes()->where('company_id', $company->id)->count()),
            ],
            'importsByStatus' => collect(ImportBatchStatus::cases())
                ->mapWithKeys(fn ($status) => [$status->label() => (int) ($importsByStatus[$status->value] ?? 0)]),
            'owners' => $company->owners(),
            'subscription' => $company->subscription()->with('plan')->first(),
            'invoices' => $company->invoices()->withoutGlobalScopes()->latest('id')->limit(10)->get(),
        ]);
    }

    public function update(Request $request, Company $company): RedirectResponse
    {
        $validated = $request->validate([
            'plan_id' => ['required', Rule::exists('plans', 'id')],
            'status' => ['required', Rule::in([Company::STATUS_ACTIVE, Company::STATUS_SUSPENDED])],
        ]);

        $before = ['plan_id' => $company->plan_id, 'status' => $company->status];

        $company->forceFill($validated)->save();

        if ($before['status'] !== $company->status) {
            Audit::log('platform.company_'.($company->status === Company::STATUS_ACTIVE ? 'activated' : 'suspended'), $company, [], $company);
        }

        if ((int) $before['plan_id'] !== (int) $company->plan_id) {
            Audit::log('platform.plan_changed', $company, [
                'from' => Plan::find($before['plan_id'])?->key ?? 'default',
                'to' => Plan::find($company->plan_id)?->key,
            ], $company);
        }

        return back()->with('status', "{$company->name} updated.");
    }
}
