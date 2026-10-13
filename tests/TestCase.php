<?php

namespace Tests;

use App\Models\Company;
use App\Models\User;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Default company for tests that don't care which tenant they run in.
     */
    protected ?Company $company = null;

    /**
     * Create a user who is a member of $company (default: the test's shared
     * company) with the given role, and make that company the active context
     * so model factories create rows inside it.
     */
    protected function companyUser(string $role, ?Company $company = null): User
    {
        $company ??= $this->company ??= Company::factory()->create();

        $user = User::factory()->create();
        $company->addMember($user, $role);
        $user->switchCompany($company);

        app(CurrentCompany::class)->set($company);

        return $user;
    }
    //
}
