<?php

namespace App\Models;

use App\Enums\StaffRole;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Fortify\TwoFactorAuthenticatable;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

    /** @var array<int, StaffRole|false> */
    private array $roleCache = [];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'must_change_password',
        'email_verified_at',
        'current_organization_id',
        'personal_organization_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'must_change_password' => 'boolean',
        ];
    }

    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class)
            ->using(OrganizationUser::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    public function cachedRoleFor(Organization $organization): ?StaffRole
    {
        if (! array_key_exists($organization->id, $this->roleCache)) {
            $pivot = $this->organizations()
                ->where('organization_id', $organization->id)
                ->first()?->pivot;

            $this->roleCache[$organization->id] = $pivot?->role ?? false;
        }

        $cached = $this->roleCache[$organization->id];

        return $cached === false ? null : $cached;
    }

    public function isPersonalOrganization(Organization $organization): bool
    {
        return $this->personal_organization_id === $organization->id;
    }

    /**
     * Get the user's initials from their name.
     */
    public function initials(): string
    {
        return Str::of($this->name)
            ->explode(' ')
            ->take(2)
            ->map(fn ($word) => Str::substr($word, 0, 1))
            ->implode('');
    }
}
