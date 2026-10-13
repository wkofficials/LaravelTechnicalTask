<?php

namespace App\Models\Concerns;

use App\Models\Company;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Marks a model as tenant-owned.
 *
 * - Every query is limited to the current company whenever a company context
 *   is set (always the case for authenticated web requests and import jobs),
 *   so another tenant's rows can't be found — route model binding returns 404.
 * - New rows are stamped with the current company_id automatically, and
 *   creating one without a company context is refused.
 */
trait BelongsToCompany
{
    public static function bootBelongsToCompany(): void
    {
        static::addGlobalScope('company', function (Builder $query) {
            $companyId = app(CurrentCompany::class)->id();

            if ($companyId !== null) {
                $query->where($query->qualifyColumn('company_id'), $companyId);
            }
        });

        static::creating(function (self $model) {
            if ($model->company_id !== null) {
                return;
            }

            $model->company_id = app(CurrentCompany::class)->id()
                ?? throw new LogicException('Cannot create '.static::class.' without a company context.');
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
