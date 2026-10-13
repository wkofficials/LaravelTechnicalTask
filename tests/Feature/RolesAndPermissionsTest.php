<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Support\Tenancy\CurrentCompany;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RolesAndPermissionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_the_four_company_roles_exist(): void
    {
        $this->assertSame(4, Role::count());
        $this->assertSame(4, Role::whereIn('name', Company::ROLES)->whereNull('company_id')->count());
    }

    public function test_owner_has_full_permissions(): void
    {
        $user = $this->companyUser('owner');

        $this->assertTrue($user->can('view records'));
        $this->assertTrue($user->can('create records'));
        $this->assertTrue($user->can('edit records'));
        $this->assertTrue($user->can('delete records'));
        $this->assertTrue($user->can('import records'));
        $this->assertTrue($user->can('view company imports'));
        $this->assertTrue($user->can('manage team'));
    }

    public function test_manager_can_manage_but_not_delete_records_or_team(): void
    {
        $user = $this->companyUser('manager');

        $this->assertTrue($user->can('view records'));
        $this->assertTrue($user->can('create records'));
        $this->assertTrue($user->can('edit records'));
        $this->assertTrue($user->can('import records'));
        $this->assertTrue($user->can('view company imports'));
        $this->assertFalse($user->can('delete records'));
        $this->assertFalse($user->can('manage team'));
    }

    public function test_operator_can_view_and_import_only(): void
    {
        $user = $this->companyUser('operator');

        $this->assertTrue($user->can('view records'));
        $this->assertTrue($user->can('import records'));
        $this->assertFalse($user->can('create records'));
        $this->assertFalse($user->can('edit records'));
        $this->assertFalse($user->can('delete records'));
        $this->assertFalse($user->can('view company imports'));
        $this->assertFalse($user->can('manage team'));
    }

    public function test_viewer_can_only_view_records(): void
    {
        $user = $this->companyUser('viewer');

        $this->assertTrue($user->can('view records'));
        $this->assertFalse($user->can('create records'));
        $this->assertFalse($user->can('edit records'));
        $this->assertFalse($user->can('delete records'));
        $this->assertFalse($user->can('import records'));
        $this->assertFalse($user->can('manage team'));
    }

    public function test_roles_have_different_permission_sets(): void
    {
        $sets = collect(Company::ROLES)->map(
            fn (string $role) => Role::findByName($role)->permissions->pluck('name')->sort()->values()->all()
        );

        $this->assertSame($sets->count(), $sets->unique()->count());
    }

    public function test_a_role_only_applies_inside_the_company_it_was_given_in(): void
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();

        $user = $this->companyUser('owner', $companyA);
        $companyB->addMember($user, 'viewer');

        $current = app(CurrentCompany::class);

        $current->set($companyA);
        $user->unsetRelation('roles')->unsetRelation('permissions');
        $this->assertTrue($user->can('delete records'));

        $current->set($companyB);
        $user->unsetRelation('roles')->unsetRelation('permissions');
        $this->assertFalse($user->can('delete records'));
        $this->assertTrue($user->can('view records'));
    }
}
