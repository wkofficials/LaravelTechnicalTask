<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class CompanyInvitation extends Model
{
    use BelongsToCompany;

    public const EXPIRES_AFTER_DAYS = 7;

    protected $fillable = [
        'email',
        'role',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }

    /**
     * Create an invitation and return it with the one-time plain token, which
     * is never stored — only its hash is.
     *
     * @return array{0: self, 1: string}
     */
    public static function issue(string $email, string $role, User $invitedBy): array
    {
        $token = Str::random(48);

        $invitation = new self(['email' => Str::lower($email), 'role' => $role]);
        $invitation->forceFill([
            'invited_by' => $invitedBy->id,
            'token_hash' => self::hashToken($token),
            'expires_at' => now()->addDays(self::EXPIRES_AFTER_DAYS),
        ])->save();

        return [$invitation, $token];
    }

    /**
     * Look up a usable invitation across all companies (the invitee has no
     * company context yet). Accepted or expired invitations are not returned.
     */
    public static function findPendingByToken(string $token): ?self
    {
        return self::withoutGlobalScope('company')
            ->where('token_hash', self::hashToken($token))
            ->pending()
            ->first();
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    public function scopePending(Builder $query): void
    {
        $query->whereNull('accepted_at')->where('expires_at', '>', now());
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }
}
