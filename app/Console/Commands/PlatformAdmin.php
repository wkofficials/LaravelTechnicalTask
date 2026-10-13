<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\Audit;
use Illuminate\Console\Command;

/**
 * The only way to grant or revoke platform admin rights (deliberately not
 * possible from the web UI).
 */
class PlatformAdmin extends Command
{
    protected $signature = 'platform:admin {email : Email of an existing user} {--revoke : Remove platform admin rights}';

    protected $description = 'Grant (or revoke) platform super admin rights';

    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->first();

        if (! $user) {
            $this->error('No user with that email address.');

            return self::FAILURE;
        }

        $grant = ! $this->option('revoke');
        $user->forceFill(['is_super_admin' => $grant])->save();

        Audit::log($grant ? 'platform.admin_granted' : 'platform.admin_revoked', $user, ['via' => 'artisan'], false);

        $this->info(($grant ? 'Granted' : 'Revoked')." platform admin rights for {$user->email}.");

        return self::SUCCESS;
    }
}
