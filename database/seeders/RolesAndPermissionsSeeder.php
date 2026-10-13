<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Seed the application's roles and permissions.
     *
     * Roles are global definitions (company_id = null); which role a user
     * holds is assigned per company through Spatie's teams feature.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);

        $permissions = [
            'view records',
            'create records',
            'edit records',
            'delete records',
            'import records',
            'view company imports',
            'manage templates',
            'export records',
            'view usage',
            'view audit log',
            'manage company',
            'manage billing',
            'manage team',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        $rolePermissions = [
            'owner' => $permissions,
            'manager' => [
                'view records',
                'create records',
                'edit records',
                'import records',
                'view company imports',
                'manage templates',
                'export records',
                'view usage',
            ],
            'operator' => [
                'view records',
                'import records',
            ],
            'viewer' => [
                'view records',
            ],
        ];

        foreach ($rolePermissions as $roleName => $granted) {
            Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web', 'company_id' => null])
                ->syncPermissions($granted);
        }
    }
}
