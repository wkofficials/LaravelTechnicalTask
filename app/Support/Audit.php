<?php

namespace App\Support;

use App\Models\ActivityLog;
use App\Models\Company;
use App\Models\User;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Database\Eloquent\Model;

/**
 * Records audit events: who (actor), where (company), what (action + target)
 * and safe metadata.
 *
 * Callers must keep metadata free of secrets and personal data: log which
 * fields changed, not their values; never passwords, tokens or row contents.
 */
class Audit
{
    /**
     * "jane.doe@corp.com" → "j***@corp.com": enough to recognise an address
     * in the log without storing it in full.
     */
    public static function maskEmail(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');

        return mb_substr($local, 0, 1).'***@'.$domain;
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @param  Company|false|null  $company  null = current company; false = platform-wide.
     * @param  User|null  $actor  null = the signed-in user.
     */
    public static function log(string $action, ?Model $target = null, array $metadata = [], Company|false|null $company = null, ?User $actor = null): ActivityLog
    {
        $companyId = match (true) {
            $company === false => null,
            $company instanceof Company => $company->id,
            default => app(CurrentCompany::class)->id(),
        };

        $log = new ActivityLog();
        $log->forceFill([
            'company_id' => $companyId,
            'actor_id' => $actor?->id ?? auth()->id(),
            'action' => $action,
            'target_type' => $target ? class_basename($target) : null,
            'target_id' => $target?->getKey(),
            'metadata' => $metadata === [] ? null : $metadata,
            // Null for console commands and queued jobs (no client).
            'ip_address' => request()->ip(),
        ])->save();

        return $log;
    }
}
