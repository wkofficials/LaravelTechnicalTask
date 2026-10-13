<?php

namespace App\Support\Tenancy;

use App\Models\Company;
use Spatie\Permission\PermissionRegistrar;

/**
 * The company (tenant) the current request or queued job is acting for.
 *
 * Registered as a scoped singleton, so it is reset for every request and
 * every queued job. It is only ever set from a server-verified source: the
 * SetCurrentCompany middleware (after a membership check) or a job using the
 * company_id stored on its own model — never from browser input.
 */
class CurrentCompany
{
    private ?Company $company = null;

    public function __construct(private readonly PermissionRegistrar $permissions)
    {
    }

    public function set(Company $company): void
    {
        $this->company = $company;

        // Role/permission checks (Spatie teams) are evaluated inside this company.
        $this->permissions->setPermissionsTeamId($company->id);
    }

    public function clear(): void
    {
        $this->company = null;

        $this->permissions->setPermissionsTeamId(null);
    }

    public function get(): ?Company
    {
        return $this->company;
    }

    public function id(): ?int
    {
        return $this->company?->id;
    }

    public function has(): bool
    {
        return $this->company !== null;
    }

    /**
     * Run a callback inside another company's context, restoring the previous
     * context afterwards (used by queued jobs and membership management).
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public function runAs(Company $company, callable $callback): mixed
    {
        $previous = $this->company;

        $this->set($company);

        try {
            return $callback();
        } finally {
            $previous ? $this->set($previous) : $this->clear();
        }
    }
}
