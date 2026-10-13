<?php

namespace App\Models;

use App\Support\Tenancy\CurrentCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Company extends Model
{
    /** @use HasFactory<\Database\Factories\CompanyFactory> */
    use HasFactory;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_SUSPENDED = 'suspended';

    /**
     * Company roles, from most to least privileged.
     */
    public const ROLES = ['owner', 'manager', 'operator', 'viewer'];

    /**
     * Roles a team manager may hand out. "owner" is deliberately excluded so
     * invitations and role changes can never be used to escalate to owner.
     */
    public const ASSIGNABLE_ROLES = ['manager', 'operator', 'viewer'];

    protected $fillable = [
        'name',
        'slug',
        'status',
        'timezone',
    ];

    public static function createWithUniqueSlug(string $name): self
    {
        $base = Str::slug($name) ?: 'company';
        $slug = $base;

        while (static::where('slug', $slug)->exists()) {
            $slug = $base.'-'.Str::lower(Str::random(5));
        }

        return static::create(['name' => $name, 'slug' => $slug]);
    }

    protected function casts(): array
    {
        return [
            'email_import_notifications' => 'boolean',
        ];
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    /**
     * Only platform admins change this (forceFill), never company users.
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * Members holding the owner role in this company.
     *
     * @return Collection<int, User>
     */
    public function owners(): Collection
    {
        return app(CurrentCompany::class)->runAs($this, fn () => $this->users()->role('owner')->get());
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(CompanyInvitation::class);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * Add (or keep) the user as a member and give them exactly one role
     * inside this company. Roles in the user's other companies are untouched.
     */
    public function addMember(User $user, string $role): void
    {
        $this->users()->syncWithoutDetaching([$user->id]);

        $this->setRoleOf($user, $role);
    }

    public function setRoleOf(User $user, string $role): void
    {
        app(CurrentCompany::class)->runAs($this, function () use ($user, $role) {
            $user->unsetRelation('roles')->unsetRelation('permissions');
            $user->syncRoles([$role]);
            $user->unsetRelation('roles')->unsetRelation('permissions');
        });
    }

    public function removeMember(User $user): void
    {
        app(CurrentCompany::class)->runAs($this, function () use ($user) {
            $user->unsetRelation('roles')->unsetRelation('permissions');
            $user->syncRoles([]);
            $user->syncPermissions([]);
            $user->unsetRelation('roles')->unsetRelation('permissions');
        });

        $this->users()->detach($user->id);

        if ($user->current_company_id === $this->id) {
            $user->forceFill(['current_company_id' => null])->save();
        }
    }

    public function roleOf(User $user): ?string
    {
        return app(CurrentCompany::class)->runAs($this, function () use ($user) {
            $user->unsetRelation('roles');
            $role = $user->getRoleNames()->first();
            $user->unsetRelation('roles');

            return $role;
        });
    }
}
