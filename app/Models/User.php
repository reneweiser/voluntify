<?php

namespace App\Models;

use App\Enums\StaffRole;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Fortify\TwoFactorAuthenticatable;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

    /** @var array<int, StaffRole|false> Org-level role cache keyed by organization ID */
    private array $orgRoleCache = [];

    /** @var array<int, StaffRole|false> Project-level role cache keyed by project ID */
    private array $projectRoleCache = [];

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

    /**
     * Resolve the user's org-level role for a specific organization.
     *
     * Returns the StaffRole from the organization_user pivot, or null if the
     * user has no org-level membership. After M9, the only valid org-level
     * role is Organizer.
     */
    public function orgRoleFor(Organization $organization): ?StaffRole
    {
        if (! array_key_exists($organization->id, $this->orgRoleCache)) {
            $pivot = $this->organizations()
                ->where('organization_id', $organization->id)
                ->first()?->pivot;

            $this->orgRoleCache[$organization->id] = $pivot?->role ?? false;
        }

        $cached = $this->orgRoleCache[$organization->id];

        return $cached === false ? null : $cached;
    }

    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class)
            ->using(ProjectUser::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * Resolve the user's effective role for a specific project.
     *
     * Org-level Organizers inherit full access to all projects in their org.
     * Otherwise, checks the project_user pivot for a direct assignment.
     */
    public function projectRoleFor(Project $project): ?StaffRole
    {
        if (! array_key_exists($project->id, $this->projectRoleCache)) {
            // Org-level Organizer inherits all project access
            $orgRole = $this->orgRoleFor($project->organization);
            if ($orgRole === StaffRole::Organizer) {
                $this->projectRoleCache[$project->id] = StaffRole::Organizer;
            } else {
                // Check project-level assignment
                $pivot = $this->projects()
                    ->where('project_id', $project->id)
                    ->first()?->pivot;

                $this->projectRoleCache[$project->id] = $pivot?->role ?? false;
            }
        }

        $cached = $this->projectRoleCache[$project->id];

        return $cached === false ? null : $cached;
    }

    /**
     * Check if user is an Organizer at org level.
     *
     * Convenience method used by policies and components. Project-only
     * Organizers are NOT org Organizers.
     */
    public function isOrgOrganizerFor(Organization $organization): bool
    {
        return $this->orgRoleFor($organization) === StaffRole::Organizer;
    }

    /**
     * Batch-load project roles for multiple projects in a single query.
     *
     * Avoids N+1 on list pages where events span multiple projects.
     * Call this before looping through events that call projectRoleFor().
     *
     * @param  array<int>  $projectIds
     */
    public function preloadProjectRoles(array $projectIds): void
    {
        $alreadyLoaded = array_keys($this->projectRoleCache);
        $toLoad = array_diff($projectIds, $alreadyLoaded);

        if (empty($toLoad)) {
            return;
        }

        $rows = DB::table('project_user')
            ->where('user_id', $this->id)
            ->whereIn('project_id', $toLoad)
            ->get(['project_id', 'role']);

        $foundIds = [];
        foreach ($rows as $row) {
            $this->projectRoleCache[$row->project_id] = StaffRole::from($row->role);
            $foundIds[] = $row->project_id;
        }

        // Mark projects with no assignment as false (null)
        foreach (array_diff($toLoad, $foundIds) as $missingId) {
            $this->projectRoleCache[$missingId] = false;
        }
    }

    /**
     * Check if user has any access to an organization,
     * via direct org membership or project membership.
     */
    public function hasAccessToOrganization(Organization $organization): bool
    {
        return $this->isOrgOrganizerFor($organization)
            || $this->projects()->where('projects.organization_id', $organization->id)->exists();
    }

    /**
     * Get IDs of all organizations this user can access
     * (via direct org membership or project membership).
     *
     * @return array<int>
     */
    public function accessibleOrganizationIds(): array
    {
        $orgIds = $this->organizations()->pluck('organizations.id')->toArray();

        $projectOrgIds = DB::table('project_user')
            ->join('projects', 'project_user.project_id', '=', 'projects.id')
            ->where('project_user.user_id', $this->id)
            ->pluck('projects.organization_id')
            ->toArray();

        return array_values(array_unique(array_merge($orgIds, $projectOrgIds)));
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
