<?php

namespace App\Support;

use App\Enums\ImportBatchStatus;
use App\Models\Company;
use App\Models\ImportBatch;
use App\Models\CompanyInvitation;
use App\Models\ImportTemplate;
use App\Models\Plan;
use App\Models\UsageRecord;
use App\Notifications\UsageWarningNotification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * A company's plan limits and how much of them is used. Every check returns
 * null when allowed, or a message the user can act on.
 *
 * Monthly usage counts rows actually written (added + updated) and is
 * checked when an import is confirmed, against the rows in the file.
 */
class PlanLimits
{
    public const WARNING_THRESHOLD = 0.8;

    public function __construct(public readonly Company $company)
    {
    }

    public static function for(Company $company): self
    {
        return new self($company);
    }

    public function plan(): Plan
    {
        return $this->company->plan ?? Plan::default();
    }

    /**
     * YYYY-MM of "now" in the company's timezone (usage resets monthly).
     */
    public function period(): string
    {
        return now($this->company->timezone ?: 'UTC')->format('Y-m');
    }

    public function maxFileSizeKb(): int
    {
        return min($this->plan()->max_file_size_mb, Plan::HARD_MAX_FILE_SIZE_MB) * 1024;
    }

    public function importRowsUsed(): int
    {
        return (int) UsageRecord::where('company_id', $this->company->id)
            ->where('period', $this->period())
            ->where('metric', UsageRecord::METRIC_IMPORT_ROWS)
            ->sum('amount');
    }

    /**
     * Rows still to come from imports already confirmed (queued or running).
     * Counted against the allowance so that confirming many imports before
     * the queue gets to them can't exceed it.
     */
    public function importRowsReserved(): int
    {
        return (int) ImportBatch::withoutGlobalScopes()
            ->where('company_id', $this->company->id)
            ->whereIn('status', [ImportBatchStatus::Pending->value, ImportBatchStatus::Processing->value])
            ->sum(DB::raw('CASE WHEN total_rows > processed_rows THEN total_rows - processed_rows ELSE 0 END'));
    }

    public function importRowsRemaining(): int
    {
        return max(0, $this->plan()->monthly_import_rows - $this->importRowsUsed() - $this->importRowsReserved());
    }

    public function seatsUsed(): int
    {
        return $this->company->users()->count()
            + CompanyInvitation::withoutGlobalScope('company')->where('company_id', $this->company->id)->pending()->count();
    }

    public function templatesUsed(): int
    {
        return ImportTemplate::withoutGlobalScope('company')->where('company_id', $this->company->id)->count();
    }

    public function checkRowsPerImport(?int $rows): ?string
    {
        $max = $this->plan()->max_rows_per_import;

        return $rows !== null && $rows > $max
            ? 'This file has '.number_format($rows).' rows; your plan allows up to '.number_format($max).' rows per import. Split the file or upgrade your plan.'
            : null;
    }

    public function checkMonthlyRows(?int $rows): ?string
    {
        $remaining = $this->importRowsRemaining();

        return $rows !== null && $rows > $remaining
            ? 'This import could add up to '.number_format($rows).' rows, but only '.number_format($remaining)
                .' of your '.number_format($this->plan()->monthly_import_rows).' monthly rows remain'
                .' (counting imports that are queued or still running). Usage resets at the start of next month.'
            : null;
    }

    public function checkCanAddMember(): ?string
    {
        $max = $this->plan()->max_users;

        return $this->seatsUsed() >= $max
            ? "Your plan allows {$max} users (members plus pending invitations). Remove someone or upgrade your plan."
            : null;
    }

    public function checkCanAddTemplate(): ?string
    {
        $max = $this->plan()->max_templates;

        return $this->templatesUsed() >= $max
            ? "Your plan allows {$max} saved templates. Delete one or upgrade your plan."
            : null;
    }

    /**
     * Tell the company's owners once per month when imports pass 80% of the
     * monthly allowance (Cache::add is atomic, so concurrent imports can't
     * both send it).
     */
    public function warnIfNearMonthlyLimit(): void
    {
        $limit = $this->plan()->monthly_import_rows;
        $used = $this->importRowsUsed();

        if ($limit === 0 || $used < $limit * self::WARNING_THRESHOLD) {
            return;
        }

        if (! Cache::add("usage-warning:{$this->company->id}:{$this->period()}", true, now()->addDays(40))) {
            return;
        }

        foreach ($this->company->owners() as $owner) {
            $owner->notify(new UsageWarningNotification($this->company, $used, $limit));
        }
    }
}
