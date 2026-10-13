<?php

namespace App\Http\Controllers\Platform;

use App\Enums\ImportBatchStatus;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Company;
use App\Models\ImportBatch;
use App\Models\Plan;
use App\Support\Audit;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Platform overview (operational metrics), plan limits, and the log of
 * platform-admin actions.
 */
class PlatformController extends Controller
{
    public function dashboard(): View
    {
        $since = now()->subDay();

        $imports24h = ImportBatch::withoutGlobalScopes()
            ->where('created_at', '>=', $since)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return view('platform.dashboard', [
            'companies' => [
                'Active' => Company::where('status', Company::STATUS_ACTIVE)->count(),
                'Suspended' => Company::where('status', Company::STATUS_SUSPENDED)->count(),
            ],
            'queue' => [
                'Jobs waiting' => DB::table('jobs')->count(),
                'Failed jobs' => DB::table('failed_jobs')->count(),
                'Imports running' => ImportBatch::withoutGlobalScopes()->where('status', ImportBatchStatus::Processing->value)->count(),
                'Imports queued' => ImportBatch::withoutGlobalScopes()->where('status', ImportBatchStatus::Pending->value)->count(),
            ],
            'imports24h' => collect(ImportBatchStatus::cases())
                ->mapWithKeys(fn ($status) => [$status->label() => (int) ($imports24h[$status->value] ?? 0)]),
            'recentActions' => ActivityLog::where('action', 'like', 'platform.%')
                ->with('actor:id,name', 'company:id,name')
                ->latest('id')
                ->limit(15)
                ->get(),
        ]);
    }

    public function plans(): View
    {
        return view('platform.plans', [
            'plans' => Plan::withCount('companies')->orderBy('id')->get(),
            'limits' => Plan::LIMITS,
        ]);
    }

    public function updatePlan(Request $request, Plan $plan): RedirectResponse
    {
        $rules = ['name' => ['required', 'string', 'max:100']];

        foreach (array_keys(Plan::LIMITS) as $limit) {
            $rules[$limit] = ['required', 'integer', 'min:0', 'max:100000000'];
        }

        $rules['max_file_size_mb'][] = 'max:'.Plan::HARD_MAX_FILE_SIZE_MB;
        $rules['price_monthly'] = ['nullable', 'numeric', 'min:0', 'max:1000000'];
        $rules['price_yearly'] = ['nullable', 'numeric', 'min:0', 'max:10000000'];
        $rules['currency'] = ['required', 'string', 'size:3', 'alpha'];
        $rules['is_public'] = ['boolean'];

        $validated = $request->validate($rules);

        $plan->update([
            ...collect($validated)->only(['name', ...array_keys(Plan::LIMITS)])->all(),
            'price_monthly_cents' => Money::toCents($validated['price_monthly'] ?? null),
            'price_yearly_cents' => Money::toCents($validated['price_yearly'] ?? null),
            'currency' => strtoupper($validated['currency']),
            'is_public' => $request->boolean('is_public'),
        ]);

        if ($changes = array_diff_key($plan->getChanges(), ['updated_at' => true])) {
            Audit::log('platform.plan_limits_updated', $plan, ['plan' => $plan->key, 'changes' => $changes], false);
        }

        return back()->with('status', "Plan \"{$plan->name}\" saved.");
    }
}
