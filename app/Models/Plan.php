<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Usage limits of a subscription tier. Managed by platform admins only.
 */
class Plan extends Model
{
    /**
     * Limit columns, with their labels, as shown and edited on screens.
     */
    public const LIMITS = [
        'max_users' => 'Users (members + pending invitations)',
        'max_rows_per_import' => 'Rows per import',
        'max_file_size_mb' => 'File size (MB)',
        'monthly_import_rows' => 'Imported rows per month',
        'max_templates' => 'Saved templates',
    ];

    /**
     * Uploads never exceed this, whatever a plan says (server upload limit).
     */
    public const HARD_MAX_FILE_SIZE_MB = 100;

    protected $fillable = [
        'name',
        'max_users',
        'max_rows_per_import',
        'max_file_size_mb',
        'monthly_import_rows',
        'max_templates',
        'price_monthly_cents',
        'price_yearly_cents',
        'currency',
        'is_public',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_public' => 'boolean',
        ];
    }

    /**
     * Price in cents for a billing interval, or null when not set.
     */
    public function priceFor(string $interval): ?int
    {
        return $interval === Subscription::INTERVAL_YEARLY ? $this->price_yearly_cents : $this->price_monthly_cents;
    }

    public function formattedPrice(string $interval): string
    {
        return Money::format($this->priceFor($interval), $this->currency);
    }

    public function companies(): HasMany
    {
        return $this->hasMany(Company::class);
    }

    public static function default(): self
    {
        return static::where('is_default', true)->firstOrFail();
    }
}
