<?php

namespace App\Http\Middleware;

use App\Models\Company;
use App\Models\User;
use App\Support\Tenancy\CurrentCompany;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the authenticated user's active company for this request.
 *
 * The company always comes from the server side (users.current_company_id)
 * and is re-checked against the user's memberships every request, so a
 * removed member or a suspended company loses access immediately.
 *
 * Runs before route model binding (see bootstrap/app.php), so bound
 * tenant models are already limited to this company.
 */
class SetCurrentCompany
{
    public function __construct(private readonly CurrentCompany $currentCompany)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $company = $user instanceof User ? $this->resolveCompany($user) : null;

        // Always overwrite: never let a context from earlier work leak into
        // this request (matters for long-running workers and tests).
        $company ? $this->currentCompany->set($company) : $this->currentCompany->clear();

        // Roles may have been loaded before the company (team) was known.
        $user?->unsetRelation('roles')->unsetRelation('permissions');

        return $next($request);
    }

    private function resolveCompany(User $user): ?Company
    {
        $activeCompanies = $user->companies()->where('status', Company::STATUS_ACTIVE);

        if ($user->current_company_id !== null) {
            $current = (clone $activeCompanies)->whereKey($user->current_company_id)->first();

            if ($current) {
                return $current;
            }
        }

        // Saved company is gone (removed / suspended): fall back to another one.
        $fallback = $activeCompanies->orderBy('companies.id')->first();

        if ($fallback?->id !== $user->current_company_id) {
            $user->forceFill(['current_company_id' => $fallback?->id])->save();
        }

        return $fallback;
    }
}
