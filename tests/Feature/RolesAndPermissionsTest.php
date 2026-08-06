<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RolesAndPermissionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_exactly_three_roles_exist(): void
    {
        $this->assertSame(3, \Spatie\Permission\Models\Role::count());
        $this->assertTrue(\Spatie\Permission\Models\Role::whereIn('name', ['admin', 'manager', 'viewer'])->count() === 3);
    }

    public function test_admin_has_full_permissions(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        $this->assertTrue($user->can('view records'));
        $this->assertTrue($user->can('create records'));
        $this->assertTrue($user->can('edit records'));
        $this->assertTrue($user->can('delete records'));
        $this->assertTrue($user->can('import records'));
    }

    public function test_manager_can_manage_but_not_delete_records(): void
    {
        $user = User::factory()->create();
        $user->assignRole('manager');

        $this->assertTrue($user->can('view records'));
        $this->assertTrue($user->can('create records'));
        $this->assertTrue($user->can('edit records'));
        $this->assertTrue($user->can('import records'));
        $this->assertFalse($user->can('delete records'));
    }

    public function test_viewer_can_only_view_records(): void
    {
        $user = User::factory()->create();
        $user->assignRole('viewer');

        $this->assertTrue($user->can('view records'));
        $this->assertFalse($user->can('create records'));
        $this->assertFalse($user->can('edit records'));
        $this->assertFalse($user->can('delete records'));
        $this->assertFalse($user->can('import records'));
    }

    public function test_roles_have_different_permission_sets(): void
    {
        $admin = \Spatie\Permission\Models\Role::findByName('admin')->permissions->pluck('name')->sort()->values();
        $manager = \Spatie\Permission\Models\Role::findByName('manager')->permissions->pluck('name')->sort()->values();
        $viewer = \Spatie\Permission\Models\Role::findByName('viewer')->permissions->pluck('name')->sort()->values();

        $this->assertNotEquals($admin, $manager);
        $this->assertNotEquals($manager, $viewer);
        $this->assertNotEquals($admin, $viewer);
    }
}
