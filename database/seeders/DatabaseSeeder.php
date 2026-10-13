<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     *
     * Two demo companies, so tenant isolation can be tried out by hand:
     * nothing created in "Demo Company" is visible to "Other Company".
     */
    public function run(): void
    {
        $this->call(RolesAndPermissionsSeeder::class);

        $demo = Company::firstOrCreate(['slug' => 'demo-company'], ['name' => 'Demo Company']);

        $this->member($demo, 'Admin User', 'admin@example.com', 'owner');
        $this->member($demo, 'Manager User', 'manager@example.com', 'manager');
        $this->member($demo, 'Operator User', 'operator@example.com', 'operator');
        $this->member($demo, 'Viewer User', 'viewer@example.com', 'viewer');

        $other = Company::firstOrCreate(['slug' => 'other-company'], ['name' => 'Other Company']);

        $this->member($other, 'Other Owner', 'other@example.com', 'owner');

        // Platform operator, deliberately not a member of any company.
        User::where('email', 'platform@example.com')->first()
            ?? User::factory()->create(['name' => 'Platform Admin', 'email' => 'platform@example.com'])
                ->forceFill(['is_super_admin' => true])->save();
    }

    private function member(Company $company, string $name, string $email, string $role): void
    {
        $user = User::where('email', $email)->first()
            ?? User::factory()->create(['name' => $name, 'email' => $email]);

        $company->addMember($user, $role);

        if ($user->current_company_id === null) {
            $user->switchCompany($company);
        }
    }
}
