# Milestone: m9-roles — Roles & Team

**Features:** project_user pivot, role hierarchy (org-Organizer inherits project access), scanner roles become assignment concepts, ProjectMembers component, MemberManagement Organizer-only
**Issues:** #65, #63, #62, #60
**Dependencies:** m7-foundation (complete), m8-project-scoped (complete)

## Plan
- **Status:** complete
- **Gate summary:** 2 migrations, 1 new pivot model, 3 new methods on User (projectRoleFor, isOrgOrganizerFor, preloadProjectRoles), 3 policies rewritten for two-tier role model, 2 new actions, 1 new Livewire component, ~15 components/middleware updated, ~40 test files updated. 14 review concerns resolved (6 high, 6 medium, 2 low).

## Implement
- **Status:** complete
- **Iteration:** 1
- **Gate summary:** 981 tests green (974 + 19 new - 12 removed). migrate:fresh --seed clean. Pint passes. 42 files changed, 10 new files. All 14 plan-review concerns + 13 accepted impl-review concerns addressed. Key impl-review fixes: EventPolicy helper extraction, User::hasAccessToOrganization(), #[Locked] on 3 components, Gate::authorize in scanners, preloadProjectRoles() wired up, aria-labels on buttons.
- **Tasks:**
  - [x] Phase 1: Migration #14 — create_project_user_table (FKs, unique constraint, role column)
  - [x] Phase 1: Migration #15 — remove_non_organizer_roles_from_org_user (data cleanup)
  - [x] Phase 2: ProjectUser pivot model
  - [x] Phase 2: Project::users() BelongsToMany relationship
  - [x] Phase 2: User — rename cachedRoleFor→orgRoleFor (D8), $roleCache→$orgRoleCache
  - [x] Phase 2: User — add projects() BelongsToMany
  - [x] Phase 2: User — add projectRoleFor() with org Organizer inheritance
  - [x] Phase 2: User — add isOrgOrganizerFor() convenience method
  - [x] Phase 2: User — add preloadProjectRoles() batch method (D11)
  - [x] Phase 2: User — add accessibleOrganizationIds()
  - [x] Phase 2: StaffRole — add PHPDoc explaining valid contexts per value
  - [x] Phase 2: Rename cachedRoleFor→orgRoleFor across ALL files (Dashboard, ScannerApiController, 3 policies)
  - [x] Phase 3: ProjectPolicy — two-tier role resolution, add manageMembers (D10)
  - [x] Phase 3: EventPolicy — project-based resolution, Organizer-only for scanner/attendance (D10)
  - [x] Phase 3: OrganizationPolicy — view() includes project membership
  - [x] Code style: Pint --dirty passes
  - [x] Phase 4.1: AddProjectMember action (D9 — no org_user row)
  - [x] Phase 4.2: RemoveProjectMember action
  - [x] Phase 4.3: ProjectMembers Livewire component + route + Flux UI template
  - [x] Phase 4.4: MemberManagement — Organizer-only invite, remove role selects (D5)
  - [x] Phase 4.5: Sidebar — replace VA/ES pivot checks with isOrgOrganizerFor + project exists
  - [x] Phase 4.6: Scanner components — project-based Organizer access check
  - [x] Phase 4.7: ScannerApiController — projectRoleFor resolution
  - [x] Phase 4.8: ResolveOrganization middleware — short-circuit + project membership (D12)
  - [x] Phase 4.9: OrganizationSwitcher — accessibleOrganizationIds()
  - [x] Phase 4.10: VolunteerDetail — Organizer-only promote (D4, D14)
  - [x] Phase 4.11: LeaveOrganization — also remove project_user rows
  - [x] Phase 4.12: ActivityFeed — isOrgOrganizerFor + project check (D7)
  - [x] Phase 4.13: ProjectShow — Members link for org Organizers
  - [x] Phase 4.14: Eager loading project.organization on EventList, Dashboard, ScannerEventSelect (D13)
  - [x] Phase 4.15: Route registration for projects.members
  - [x] Phase 5: Fix all failing tests — rewrite VA/ES-based tests for two-tier model
  - [x] Phase 5: New tests — AddProjectMemberTest, RemoveProjectMemberTest, ProjectMembersTest
  - [x] Phase 5: Test helper — createUserWithProjectOrganization()
  - [x] Phase 5: Update MemberManagementTest — Organizer-only invites
  - [x] Phase 5: Update policy tests — two-tier (org Organizer, project Organizer, non-member)
  - [x] Code style: Pint --dirty passes
  - [x] All 981 tests pass (2103 assertions)

## Test
- **Status:** complete
- 981 tests pass (2103 assertions)

## Security Audit
- **Status:** complete
- **Gate summary:** 8/8 targeted checks pass. Mass assignment protected (hardcoded role), #[Locked] on 3 model properties, Gate::authorize on all public mutating methods, no $request->all() mass assignment, role injection prevented, scanner auth via Gate, middleware validates org access before trusting session, AddProjectMember confirmed no org_user row. All 4 implement-review security fixes verified.

## Decisions

| # | Stage | Decision | Rationale | Affects |
|---|---|---|---|---|
| D1 | plan | Keep StaffRole enum with all 3 values | VA/ES needed in M11 for scanner assignees. Removing now would require adding back. | StaffRole, VolunteerPromotion, tests |
| D2 | plan | Add `isOrgOrganizer()` + `isProjectOrganizer()` helpers to User | Avoids complex multi-site resolution in policies. Clear, cacheable, composable. | User model, all policies |
| D3 | plan | Scanner components: change access check to Organizer-only for M9 | VA/ES as org roles go away. Scanner rewrite (M11) adds temp-auth links for VA/ES. Organizer-only is correct bridge. | QrScanner, ManualLookup, ScannerEventSelect, sidebar, scanner tests |
| D4 | plan | Promote volunteer: offer only Organizer role at org level | VA/ES not valid org roles after M9. Promotion creates org-level membership. | VolunteerDetail, PromoteVolunteer action |
| D5 | plan | MemberManagement: invite Organizer-only, no role change dropdown | Only one org-level role exists. Role column becomes fixed at 'organizer'. | MemberManagement component + blade + tests |
| D6 | plan | No data migration for existing VA/ES rows | App not in production. `migrate:fresh` always available. Migration #15 simply removes non-Organizer rows. | Migration |
| D7 | plan | `cachedRoleFor()` stays for org-level resolution | Still needed for org-level Organizer check. Add separate `cachedProjectRoleFor()` for project-level. | User model |
| D8 | plan-review | Rename role methods: cachedRoleFor→orgRoleFor, cachedProjectRoleFor→projectRoleFor | "cached" prefix obscures intent; naming should convey scope | User model, all policies, all tests |
| D9 | plan-review | AddProjectMember must NOT create organization_user row | Project-only users have no org_user row; access via project_user only | AddProjectMember, middleware, policies |
| D10 | plan-review | EventPolicy::create restricted to org Organizers only | Project Organizers create events from ProjectShow (project context set) | EventPolicy, EventList |
| D11 | plan-review | Add preloadProjectRoles() batch method on User | Avoids N+1 on list pages with events from multiple projects | User model, list components |
| D12 | plan-review | Short-circuit ResolveOrganization middleware | Trust current_organization_id with single check; full chain only on first login | ResolveOrganization |
| D13 | plan-review | Require explicit eager-loading of project.organization | No model-level $with; list components must add ->with('project.organization') | All event list components |
| D14 | plan-review | PromoteVolunteer creates org-level Organizer | Promotion = full staff access, not project-scoped | PromoteVolunteer, VolunteerDetail |
| D15 | impl-review | Extract EventPolicy::isProjectOrganizer() helper | 9 methods had identical bodies; helper prevents silent divergence | EventPolicy |
| D16 | impl-review | Add User::hasAccessToOrganization() | Replaced 6 inline duplications of org+project access check | User, policies, sidebar, components |
| D17 | impl-review | Add #[Locked] to model properties in Livewire components | Prevents Livewire wire protocol manipulation of Project, Event, Volunteer | ProjectMembers, VolunteerDetail |
| D18 | impl-review | Scanner mount() uses Gate::authorize instead of inline check | Delegates to policy; single source of truth for access rules | QrScanner, ManualLookup |

## Reviews

### plan — 2026-03-31

| # | Perspective | Concern | Severity | Resolution | Rationale |
|---|---|---|---|---|---|
| 1 | Devil's Advocate | `AddProjectMember` contradicts "no org_user row" — code creates org_user row despite Option 3 | high | accepted | Remove org-attach from AddProjectMember. Project-only users have NO organization_user row. |
| 2 | Devil's Advocate | `EventPolicy::create` + `CreateEvent` auto-project broken for project-only users | high | accepted | Restrict EventPolicy::create to org Organizers only. Project Organizers create events from ProjectShow. |
| 3 | Scalability | `cachedProjectRoleFor()` fires 2 queries per distinct project on list pages | high | accepted | Add preloadProjectRoles() batch method on User. |
| 4 | Scalability | ResolveOrganization middleware worst-case 4 queries per request | high | accepted | Short-circuit: trust current_organization_id with single membership check. |
| 5 | Scalability | Missing eager-load enforcement for $event->project causes N+1 | high | accepted | Require explicit ->with('project.organization') in all list components. |
| 6 | Junior Dev | Three role methods with unclear naming — "cached" prefix obscures intent | high | accepted | Rename: cachedRoleFor→orgRoleFor, cachedProjectRoleFor→projectRoleFor. Add PHPDoc. |
| 7 | Devil's Advocate | Sidebar/ActivityFeed direct pivot queries bypass new policy layer | medium | accepted | Replace with isOrgOrganizerFor() / projectRoleFor() methods. |
| 8 | Devil's Advocate | PromoteVolunteer: should create project-level or org-level Organizer? | medium | accepted | Org-level Organizer is correct — promotion grants full staff access. |
| 9 | Scalability | OrganizationPolicy::view() with whereHas subquery on every check | medium | accepted | Unique index covers query. Verify projects.organization_id index. |
| 10 | Junior Dev | StaffRole enum has 3 values but only 1 valid — confusing | medium | accepted | Add PHPDoc explaining valid contexts per value. |
| 11 | Junior Dev | ProjectMembers duplicates AddProjectMember logic | medium | accepted | Delegate from component to action. |
| 12 | Junior Dev | Contradictory instructions left in plan (org-attach code vs Option 3) | medium | accepted | Final decisions clear from resolutions; implementation follows resolutions. |
| 13 | Scalability | OrganizationSwitcher runs 2 queries then merges | low | deferred | Acceptable at current scale. Optimize in future if needed. |
| 14 | Junior Dev | Compound test descriptions mask failures | low | accepted | Split into one test per policy method per role tier. |

### implement — 2026-03-31

| # | Perspective | Concern | Severity | Resolution | Rationale |
|---|---|---|---|---|---|
| 1 | Simplicity | 9 EventPolicy methods with identical bodies | high | accepted | Extracted isProjectOrganizer() private helper |
| 2 | Simplicity | "Has any org access" check inlined 6 times | high | accepted | Added User::hasAccessToOrganization() method |
| 3 | Security | ProjectMembers::$project not #[Locked] | high | accepted | Added #[Locked] |
| 4 | Security | ProjectMembers::removeMember() accepts any userId without validation | high | accepted | Added membership check before detach |
| 5 | Accessibility | Icon-only trash buttons have no accessible name | high | accepted | Added aria-label="Remove :name" |
| 6 | Simplicity | preloadProjectRoles() called nowhere | medium | accepted | Added calls in EventList, ScannerEventSelect, Dashboard |
| 7 | Simplicity | QrScanner/ManualLookup duplicate policy check in mount() | medium | accepted | Replaced with Gate::authorize('scan', $event) |
| 8 | Security | ManualLookup::confirmArrival() has no re-authorization | medium | accepted | Added Gate::authorize('scan', $event) |
| 9 | Security | VolunteerDetail::$event and $volunteer not #[Locked] | medium | accepted | Added #[Locked] to both |
| 10 | Accessibility | ProjectMembers sections lack landmark semantics | medium | accepted | Added section aria-labelledby wrappers |
| 11 | Accessibility | x-action-message lacks role="status" | medium | deferred | Codebase-wide issue; track for M13 |
| 12 | Accessibility | Promote modal missing focusable attribute | medium | accepted | Added focusable |
| 13 | Simplicity | VolunteerDetail::$promoteRole is dead optionality | low | accepted | Removed property, hardcoded in action |
| 14 | Security | orgOrganizers data in wire snapshot | low | rejected | Only org Organizers see this component |
| 15 | Accessibility | Focus not managed after member removal | low | deferred | Nice-to-have; track for M13 |

## Feedback Loops

| # | Date | Direction | Trigger | Fix | Resolution |
|---|---|---|---|---|---|

## Cross-Milestone Interface

| Category | Items |
|---|---|
| Tables (new) | `project_user` (project_id, user_id, role, timestamps; unique on project_id+user_id) |
| Tables (modified) | `organization_user` — data cleanup: only Organizer rows remain |
| Models (new) | `ProjectUser` pivot (casts role to StaffRole) |
| Models (modified) | `User` (+orgRoleFor, projectRoleFor, isOrgOrganizerFor, hasAccessToOrganization, preloadProjectRoles, accessibleOrganizationIds, projects()), `Project` (+users() BelongsToMany) |
| Actions (new) | `AddProjectMember` (attach to project, no org_user row, set current_org_id), `RemoveProjectMember` (detach from project) |
| Actions (modified) | `LeaveOrganization` (+cleanup project_user rows in org) |
| Policies (modified) | `EventPolicy` (all methods via event->project, isProjectOrganizer helper), `ProjectPolicy` (+manageMembers, two-tier resolution), `OrganizationPolicy` (view via hasAccessToOrganization) |
| Components (new) | `ProjectMembers` (route: projects/{projectId}/members) |
| Components (modified) | `MemberManagement` (Organizer-only, no role select), `QrScanner`/`ManualLookup` (Gate::authorize), `ScannerEventSelect` (project-scoped events), `VolunteerDetail` (Organizer-only promote, #[Locked]), `ActivityFeed`/sidebar (hasAccessToOrganization), `OrganizationSwitcher` (accessibleOrganizationIds), `EventList`/`Dashboard` (+preloadProjectRoles, +eager load project.organization) |
| Middleware (modified) | `ResolveOrganization` (short-circuit + project membership fallback) |
| Routes (new) | `projects.members` → `/admin/projects/{projectId}/members` |
| Key methods | `User::orgRoleFor()` (org-level), `User::projectRoleFor()` (two-tier with inheritance), `User::isOrgOrganizerFor()`, `User::hasAccessToOrganization()`, `User::preloadProjectRoles()`, `User::accessibleOrganizationIds()` |
| M11 depends on | `project_user` table exists; StaffRole enum has VA/ES values; scanner components are Organizer-only (M11 adds temp-auth scanner assignees) |
| M10 depends on | ProjectPolicy `update` allows project_user Organizers (for signup config); User::projectRoleFor() resolves event access through project |
| M13 depends on | Role hierarchy in policies; `x-action-message` needs `role="status"` (deferred from impl review #11); focus management after member removal (deferred #15) |

---

## Detailed Implementation Plan

### Phase 1: Migrations

#### Migration #14: `create_project_user_table`

```sql
CREATE TABLE project_user (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    project_id  BIGINT UNSIGNED NOT NULL,
    user_id     BIGINT UNSIGNED NOT NULL,
    role        VARCHAR(255) NOT NULL DEFAULT 'organizer',
    created_at  TIMESTAMP NULL,
    updated_at  TIMESTAMP NULL,
    UNIQUE KEY project_user_project_id_user_id_unique (project_id, user_id),
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
```

**Laravel migration blueprint:**
```php
Schema::create('project_user', function (Blueprint $table) {
    $table->id();
    $table->foreignId('project_id')->constrained()->cascadeOnDelete();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->string('role')->default('organizer');
    $table->timestamps();
    $table->unique(['project_id', 'user_id']);
});
```

**Notes:**
- `role` is a string column (not enum) matching the existing `organization_user.role` pattern
- Unique constraint prevents duplicate assignments
- `cascadeOnDelete` on both FKs: removing a project or user removes pivot rows
- No `down()` method (forward-only)

#### Migration #15: `remove_non_organizer_roles_from_org_user`

```php
// This migration removes all non-Organizer rows from organization_user.
// In production you'd need a data migration strategy; since we only use
// migrate:fresh, this is safe.

DB::table('organization_user')
    ->where('role', '!=', 'organizer')
    ->delete();
```

**Notes:**
- Simple DELETE statement — no schema change needed
- The `organization_user.role` column stays (still stores 'organizer')
- No `down()` method
- This is a data cleanup migration, not a structural migration
- Existing VA/ES users lose org access (acceptable: M11 replaces with scanner assignees)

---

### Phase 2: Models

#### 2.1 New Model: `ProjectUser` (pivot)

**File:** `app/Models/ProjectUser.php`

```php
namespace App\Models;

use App\Enums\StaffRole;
use Illuminate\Database\Eloquent\Relations\Pivot;

class ProjectUser extends Pivot
{
    protected $table = 'project_user';

    protected function casts(): array
    {
        return [
            'role' => StaffRole::class,
        ];
    }
}
```

#### 2.2 New Factory: `ProjectUserFactory`

Not needed — pivot models are created via `attach()`. No standalone factory required.

#### 2.3 Update Model: `Project`

Add `users()` relationship:

```php
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

public function users(): BelongsToMany
{
    return $this->belongsToMany(User::class)
        ->using(ProjectUser::class)
        ->withPivot('role')
        ->withTimestamps();
}
```

#### 2.4 Update Model: `User`

Add `projects()` relationship + project-level role resolution:

```php
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** @var array<int, StaffRole|false> */
private array $projectRoleCache = [];

public function projects(): BelongsToMany
{
    return $this->belongsToMany(Project::class)
        ->using(ProjectUser::class)
        ->withPivot('role')
        ->withTimestamps();
}

/**
 * Resolve the user's role for a specific project.
 * Returns Organizer if user is an org-level Organizer (inherits all project access)
 * or if user is a project-level Organizer.
 */
public function cachedProjectRoleFor(Project $project): ?StaffRole
{
    if (! array_key_exists($project->id, $this->projectRoleCache)) {
        // Org-level Organizer inherits all project access
        $orgRole = $this->cachedRoleFor($project->organization);
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
 * Convenience method used by policies and components.
 */
public function isOrgOrganizerFor(Organization $organization): bool
{
    return $this->cachedRoleFor($organization) === StaffRole::Organizer;
}
```

**`cachedRoleFor()` stays unchanged** — it still resolves the org-level role. After M9, the only valid org-level role is `Organizer`, so it returns `StaffRole::Organizer` or `null`.

**Cache invalidation:** Both caches (`$roleCache`, `$projectRoleCache`) are per-request (instance properties). No cross-request caching needed.

#### 2.5 Update Enum: `StaffRole`

**No changes.** All 3 values remain:
- `Organizer` — used at org level and project level
- `VolunteerAdmin` — kept for M11 scanner assignees, `VolunteerPromotion` records
- `EntranceStaff` — kept for M11 scanner assignees, `VolunteerPromotion` records

**Rationale:** Removing VA/ES now would require re-adding them in M11 and break the `VolunteerPromotion` model which stores historical role values.

---

### Phase 3: Policies

The core change: all resource policies that previously checked "any org role" or "Organizer org role" now need two-tier resolution:

1. **Org-level Organizer** → full access to everything in the org (including all projects)
2. **Project-level Organizer** → access scoped to their assigned project(s) only

#### 3.1 `ProjectPolicy` — Updated

```php
namespace App\Policies;

use App\Models\Organization;
use App\Models\Project;
use App\Models\User;

class ProjectPolicy
{
    public function viewAny(User $user, Organization $organization): bool
    {
        // Org Organizer: sees all projects
        // Project-level users: sees project list (filtered in query)
        return $user->isOrgOrganizerFor($organization)
            || $user->projects()->where('projects.organization_id', $organization->id)->exists();
    }

    public function view(User $user, Project $project): bool
    {
        return $user->cachedProjectRoleFor($project) !== null;
    }

    public function create(User $user, Organization $organization): bool
    {
        // Only org-level Organizers can create projects
        return $user->isOrgOrganizerFor($organization);
    }

    public function update(User $user, Project $project): bool
    {
        return $user->cachedProjectRoleFor($project) === StaffRole::Organizer;
    }

    public function delete(User $user, Project $project): bool
    {
        // Only org-level Organizers can delete projects
        return $user->isOrgOrganizerFor($project->organization);
    }

    public function manageMembers(User $user, Project $project): bool
    {
        // Only org-level Organizers can manage project members
        return $user->isOrgOrganizerFor($project->organization);
    }
}
```

**Key decisions:**
- `viewAny`: org Organizer OR has any project assignment in the org
- `create`: org Organizer only (project Organizer can't create new projects)
- `update`: any Organizer (org-level or project-level) — `cachedProjectRoleFor` handles inheritance
- `delete`: org Organizer only (project Organizer shouldn't delete projects they were invited to)
- `manageMembers` (NEW): org Organizer only — prevents project Organizers from escalating their own access

#### 3.2 `EventPolicy` — Updated

Events belong to Projects. Access is resolved through the event's project:

```php
namespace App\Policies;

use App\Enums\StaffRole;
use App\Models\Event;
use App\Models\Organization;
use App\Models\User;

class EventPolicy
{
    public function viewAny(User $user, Organization $organization): bool
    {
        // Same as ProjectPolicy::viewAny — if you can see any project, you can see events
        return $user->isOrgOrganizerFor($organization)
            || $user->projects()->where('projects.organization_id', $organization->id)->exists();
    }

    public function view(User $user, Event $event): bool
    {
        return $user->cachedProjectRoleFor($event->project) !== null;
    }

    public function create(User $user, Organization $organization): bool
    {
        // Org Organizer or any project Organizer in this org can create events
        // (they'll select which project during creation)
        return $user->isOrgOrganizerFor($organization)
            || $user->projects()->where('projects.organization_id', $organization->id)->exists();
    }

    public function update(User $user, Event $event): bool
    {
        return $user->cachedProjectRoleFor($event->project) === StaffRole::Organizer;
    }

    public function publish(User $user, Event $event): bool
    {
        return $user->cachedProjectRoleFor($event->project) === StaffRole::Organizer;
    }

    public function archive(User $user, Event $event): bool
    {
        return $user->cachedProjectRoleFor($event->project) === StaffRole::Organizer;
    }

    public function manageJobs(User $user, Event $event): bool
    {
        return $user->cachedProjectRoleFor($event->project) === StaffRole::Organizer;
    }

    public function markAttendance(User $user, Event $event): bool
    {
        // After M9, only Organizers can mark attendance
        // M11 will add scanner-assignee-based access for VA role
        return $user->cachedProjectRoleFor($event->project) === StaffRole::Organizer;
    }

    public function manageCustomFields(User $user, Event $event): bool
    {
        return $user->cachedProjectRoleFor($event->project) === StaffRole::Organizer;
    }

    public function manageGear(User $user, Event $event): bool
    {
        return $user->cachedProjectRoleFor($event->project) === StaffRole::Organizer;
    }

    public function trackGearPickup(User $user, Event $event): bool
    {
        // After M9, only Organizers can track gear pickup
        // M11 will add scanner-assignee-based access for VA role
        return $user->cachedProjectRoleFor($event->project) === StaffRole::Organizer;
    }

    public function scan(User $user, Event $event): bool
    {
        // After M9, only Organizers can scan
        // M11 will replace with temp-auth scanner links for ES role
        return $user->cachedProjectRoleFor($event->project) === StaffRole::Organizer;
    }
}
```

**Key changes from current:**
- All methods now resolve through `event->project` instead of `event->organization`
- `markAttendance`, `trackGearPickup`, `scan` no longer check VA/ES roles — Organizer-only until M11
- `create` allows project Organizers (they need to be able to create events in their assigned projects)
- **Eager-load implication:** `Event` must eager-load `project.organization` when accessed by policies. The `project` relationship is already defined. The `project.organization` chain is needed for `cachedProjectRoleFor()` to call `cachedRoleFor()`.

#### 3.3 `OrganizationPolicy` — Unchanged

```php
// No changes needed. Already checks for Organizer role only.
// view() returns cachedRoleFor !== null — after M9, only Organizers exist at org level,
// so this effectively means "is org Organizer"
```

**Wait — this is a subtle issue.** After migration #15 removes VA/ES rows, `cachedRoleFor()` returns `null` for former VA/ES users. This means former VA/ES users can no longer `view` the organization. This is **correct behavior** because:
- VA/ES users no longer have org-level roles
- They only have access via project_user pivot (if assigned)
- Org view should require org membership

However, **project Organizers need to see the organization** (they're working within it). The `view` method needs updating:

```php
public function view(User $user, Organization $organization): bool
{
    return $user->isOrgOrganizerFor($organization)
        || $user->projects()->where('projects.organization_id', $organization->id)->exists();
}
```

And `manageMembers` stays org-Organizer-only:

```php
public function manageMembers(User $user, Organization $organization): bool
{
    return $user->isOrgOrganizerFor($organization);
}
```

---

### Phase 4: Components

#### 4.1 New Component: `ProjectMembers`

**File:** `app/Livewire/Projects/ProjectMembers.php`
**Route:** `GET /admin/projects/{projectId}/members` → `projects.members`
**Template:** `resources/views/livewire/projects/project-members.blade.php`

```php
namespace App\Livewire\Projects;

use App\Actions\InviteProjectMember;
use App\Exceptions\MemberAlreadyExistsException;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Project Members')]
class ProjectMembers extends Component
{
    public Project $project;

    public string $inviteEmail = '';

    public bool $showRemoveModal = false;
    public ?int $removeMemberId = null;

    public function mount(int $projectId): void
    {
        $this->project = currentOrganization()->projects()->findOrFail($projectId);
        Gate::authorize('manageMembers', $this->project);
    }

    #[Computed]
    public function members(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->project->users()->orderBy('name')->get();
    }

    #[Computed]
    public function orgOrganizers(): \Illuminate\Database\Eloquent\Collection
    {
        // Show org Organizers as "inherited" members (read-only display)
        return currentOrganization()->users()
            ->wherePivot('role', 'organizer')
            ->orderBy('name')
            ->get();
    }

    public function inviteMember(): void
    {
        Gate::authorize('manageMembers', $this->project);

        $this->validate([
            'inviteEmail' => ['required', 'email', 'max:255'],
        ]);

        // User must already be an org member (Organizer) to be added to a project
        // OR we invite them as a project-only Organizer
        $user = User::where('email', $this->inviteEmail)->first();

        if (! $user) {
            $this->addError('inviteEmail', 'No user found with this email. They must have an account first.');
            return;
        }

        if ($this->project->users()->where('user_id', $user->id)->exists()) {
            $this->addError('inviteEmail', 'This user is already a project member.');
            return;
        }

        // If user is already an org Organizer, no need for project assignment
        if ($user->isOrgOrganizerFor($this->project->organization)) {
            $this->addError('inviteEmail', 'This user is already an organization Organizer with full access.');
            return;
        }

        $this->project->users()->attach($user, ['role' => 'organizer']);

        // Also ensure user has org-level membership if not already
        if (! $this->project->organization->users()->where('user_id', $user->id)->exists()) {
            $this->project->organization->users()->attach($user, ['role' => 'organizer']);
        }

        $this->reset('inviteEmail');
        unset($this->members);
        $this->dispatch('member-added');
    }

    public function confirmRemoveMember(int $userId): void
    {
        $this->removeMemberId = $userId;
        $this->showRemoveModal = true;
    }

    public function removeMember(): void
    {
        Gate::authorize('manageMembers', $this->project);

        if (! $this->removeMemberId) {
            return;
        }

        $this->project->users()->detach($this->removeMemberId);

        $this->showRemoveModal = false;
        $this->reset('removeMemberId');
        unset($this->members);
    }
}
```

**Design decisions:**
- Only org-level Organizers can manage project members (via `ProjectPolicy::manageMembers`)
- Project members must have existing accounts (no passwordless invite — this is for staff)
- Org Organizers are shown as "inherited" members (cannot be removed from project level)
- The invite is by email lookup, not by name+email creation
- Adding a project member also ensures they have org-level access (attach to org if needed)

**Template structure:**
```
ProjectMembers (full-page)
  ├── Inherited Members section (read-only list of org Organizers)
  ├── Project Members section (add/remove project-level Organizers)
  │     ├── Invite form (email input + button)
  │     └── Member list (email, name, remove button)
  └── Remove confirmation modal
```

#### 4.2 New Action: `AddProjectMember`

**File:** `app/Actions/AddProjectMember.php`

```php
namespace App\Actions;

use App\Enums\StaffRole;
use App\Exceptions\MemberAlreadyExistsException;
use App\Models\Project;
use App\Models\User;

class AddProjectMember
{
    public function execute(Project $project, User $user): void
    {
        if ($project->users()->where('user_id', $user->id)->exists()) {
            throw new MemberAlreadyExistsException;
        }

        $project->users()->attach($user, ['role' => StaffRole::Organizer]);

        // Ensure user has org-level access
        if (! $project->organization->users()->where('user_id', $user->id)->exists()) {
            $project->organization->users()->attach($user, ['role' => StaffRole::Organizer]);
        }
    }
}
```

#### 4.3 New Action: `RemoveProjectMember`

**File:** `app/Actions/RemoveProjectMember.php`

```php
namespace App\Actions;

use App\Models\Project;
use App\Models\User;

class RemoveProjectMember
{
    public function execute(Project $project, User $user): void
    {
        $project->users()->detach($user->id);
    }
}
```

#### 4.4 Update Component: `MemberManagement`

**Changes:**
1. Remove VA/ES from invite role options — only `organizer` allowed
2. Remove role change dropdown — all members are Organizers
3. Simplify invite validation: remove `inviteRole` property
4. Keep remove member functionality unchanged

**Specific code changes in `MemberManagement.php`:**

```php
// REMOVE: public string $inviteRole = 'volunteer_admin';
// REMOVE: public array $memberRoles = [];
// REMOVE: rendering() method (no longer syncing role dropdowns)
// REMOVE: updatedMemberRoles() method
// REMOVE: updateRole() method

// UPDATE inviteMember():
// - Remove inviteRole from validation
// - Always pass StaffRole::Organizer to InviteMember action
// - Remove reset of inviteRole

public function inviteMember(): void
{
    Gate::authorize('manageMembers', $this->organization());

    $this->validate([
        'inviteName' => ['required', 'string', 'max:255'],
        'inviteEmail' => ['required', 'email', 'max:255'],
    ]);

    try {
        app(InviteMember::class)->execute(
            $this->organization(),
            $this->inviteName,
            $this->inviteEmail,
            StaffRole::Organizer,
        );
    } catch (MemberAlreadyExistsException) {
        $this->addError('inviteEmail', 'This user is already a member.');
        return;
    }

    $this->reset('inviteName', 'inviteEmail');
    unset($this->members);
    $this->dispatch('member-invited');
}
```

**Template changes (`member-management.blade.php`):**

1. Remove the role `<flux:select>` from the invite form
2. Replace role change dropdown in member list with a static badge showing "Organizer"
3. Keep remove button and modal

```blade
{{-- Invite form: remove role select, keep name + email + button --}}

{{-- Member list: replace role dropdown with static badge --}}
@if ($member->id === auth()->id())
    <flux:badge size="sm">{{ __('Organizer') }}</flux:badge>
@else
    <flux:badge size="sm">{{ __('Organizer') }}</flux:badge>
    <flux:button variant="danger" size="sm" icon="trash"
        wire:click="confirmRemoveMember({{ $member->id }})" />
@endif
```

#### 4.5 Update Component: `VolunteerDetail` (Promote)

**Changes:**
- Remove VA/ES from promote role options
- Only offer Organizer role for promotion
- Since there's only one choice, simplify: remove role selector, always promote as Organizer

**Code changes in `VolunteerDetail.php`:**
```php
// CHANGE: public string $promoteRole = 'volunteer_admin';
// TO:     public string $promoteRole = 'organizer';

// UPDATE validation in promoteVolunteer():
// 'promoteRole' => ['required', 'string', 'in:organizer'],
```

**Template changes (`volunteer-detail.blade.php`):**
- Remove the role select dropdown from promote modal
- Show fixed text: "This volunteer will be promoted to Organizer"

#### 4.6 Update Component: `ActivityFeed`

**Current:** Checks `wherePivot('role', StaffRole::Organizer)` — **no change needed.** Already Organizer-only.

#### 4.7 Update Component: `Dashboard`

**Current `userRole` computed:**
```php
public function userRole(): ?string
{
    return auth()->user()->cachedRoleFor($this->organization)?->value;
}
```

**This stays functional** — after M9, `cachedRoleFor()` returns `Organizer` or `null`. For project-only Organizers who don't have org-level roles, this returns `null`. The dashboard should still be accessible to project Organizers.

**Decision:** The dashboard currently shows org-wide metrics. Project Organizers should see it (they're staff members). We need to ensure the `dashboard` route doesn't 403 for project-only users. The `resolve-org` middleware handles this — let's check.

Actually, for project-only Organizers, `cachedRoleFor()` returns `null` (they don't have an org_user row). The `resolve-org` middleware uses session to resolve org. As long as they have a `current_organization_id` set and can access the org view, they're fine.

**But wait:** After migration #15, project-only users (newly added via `AddProjectMember`) DO get an org_user row (with Organizer role). See the `AddProjectMember` action — it ensures org-level membership. So `cachedRoleFor()` will return `Organizer`.

**Correction:** The `AddProjectMember` action should NOT add org-level membership. That defeats the purpose of project-scoped access. Let me reconsider.

**Revised approach:** Project-only Organizers need basic org access (view dashboard, see sidebar) but not org-level management access. They should have an `organization_user` row but NOT as Organizer. Since we've removed VA/ES, we need a new lightweight role.

**Actually, let me reconsider the whole model.** The plan says:

> org-level `organization_user` pivot should only store `Organizer` role

This means project-only Organizers should NOT be in `organization_user` at all. But then they can't access the app (middleware resolves org, sidebar needs org context).

**Resolution:** Project-only Organizers DO need `organization_user` rows. We have two options:
1. Add a `Member` role to StaffRole enum (view-only org access)
2. Keep them as Organizers at org level too (defeats project scoping)
3. Resolve org access through project membership (no org_user row needed)

Option 3 is cleanest: update `resolve-org` middleware and `OrganizationPolicy::view` to also check project membership. This way:
- Org Organizers: have `organization_user` row with `organizer` role
- Project-only users: have NO `organization_user` row, only `project_user` rows
- Both can access the app via their respective org

**Updated `cachedRoleFor()` note:** Returns `null` for project-only users. This is correct — they have no org-level role.

**Updated `OrganizationPolicy::view`:** Must also check project membership.

**Updated `resolve-org` middleware:** Must also check project membership when determining available organizations for a user.

Let me check the resolve-org middleware:

#### 4.8 Update: Sidebar Scanner Link

**Current:** Checks for Organizer OR EntranceStaff OR VolunteerAdmin roles.

**After M9:** Only Organizers can access scanner. Simplify to:

```blade
@if ($isOrganizer)
    <flux:sidebar.item icon="qr-code" ...>Scanner</flux:sidebar.item>
@endif
```

Remove the `$canScan` variable entirely. The `$isOrganizer` check already exists.

#### 4.9 Update: Scanner Components (`QrScanner`, `ManualLookup`, `ScannerEventSelect`)

All three have identical access check patterns in `mount()`:

```php
// CURRENT:
$hasAccess = $organization->users()
    ->where('user_id', auth()->id())
    ->wherePivotIn('role', [StaffRole::Organizer, StaffRole::EntranceStaff, StaffRole::VolunteerAdmin])
    ->exists();

// AFTER M9:
$hasAccess = auth()->user()->cachedProjectRoleFor($this->event->project) === StaffRole::Organizer;
// Or for ScannerEventSelect (no event context):
$hasAccess = auth()->user()->isOrgOrganizerFor($organization)
    || auth()->user()->projects()->where('projects.organization_id', $organization->id)->exists();
```

Wait — `ScannerEventSelect` doesn't have an event/project context yet. It lists all published events for the org. After M9, it should:
- For org Organizers: show all published events
- For project Organizers: show only published events from their assigned projects

This filtering belongs in the computed `events()` query, not just the `mount()` check.

**Updated `ScannerEventSelect`:**
```php
public function mount(): void
{
    $organization = currentOrganization();
    $user = auth()->user();

    // Must be org Organizer or have at least one project assignment
    $hasAccess = $user->isOrgOrganizerFor($organization)
        || $user->projects()->where('projects.organization_id', $organization->id)->exists();

    if (! $hasAccess) {
        abort(403);
    }
}

#[Computed]
public function events(): Collection
{
    $user = auth()->user();
    $organization = currentOrganization();

    $query = $organization->events()
        ->published()
        ->orderBy('starts_at')
        ->withVolunteerCount()
        ->withCount('eventArrivals');

    // If not org Organizer, scope to assigned projects
    if (! $user->isOrgOrganizerFor($organization)) {
        $projectIds = $user->projects()->pluck('projects.id');
        $query->whereIn('project_id', $projectIds);
    }

    return $query->get();
}
```

**Updated `QrScanner` and `ManualLookup`:**
```php
public function mount(int $eventId): void
{
    $organization = currentOrganization();
    $this->event = $organization->events()->findOrFail($eventId);
    $this->eventId = $eventId;

    // Organizer-only access (M11 will add scanner-assignee access)
    if (auth()->user()->cachedProjectRoleFor($this->event->project) !== StaffRole::Organizer) {
        abort(403);
    }
}
```

#### 4.10 Update: `ScannerApiController`

The `data()` method resolves `$userRole` using `cachedRoleFor()`. After M9:

```php
$role = $user->cachedProjectRoleFor($event->project);
$userRole = match ($role) {
    StaffRole::Organizer => 'organizer',
    default => null,
};
```

The `sync()` method uses `Gate::authorize('scan', $event)` — this will work with the updated EventPolicy.

The `syncAttendance()` method uses `Gate::authorize('markAttendance', $event)` — also works.

#### 4.11 Update: `InviteMember` Action

No structural changes needed. It still accepts a `StaffRole` parameter. After M9, it will only be called with `StaffRole::Organizer` from the MemberManagement component. The action itself doesn't need to enforce this — the component does.

#### 4.12 Update: `PromoteVolunteer` Action

Same as InviteMember — the action accepts any StaffRole. After M9, the component only offers Organizer. The action still works for historical reasons and future M11 needs.

#### 4.13 Update: `LeaveOrganization` Action

Currently checks for sole Organizer. **No change needed** — the logic already checks for `StaffRole::Organizer` specifically. After M9, all org_user rows are Organizer, so this still correctly prevents the last Organizer from leaving.

**However:** When an org Organizer leaves, should we also remove their project_user rows? **Yes** — add cleanup:

```php
// After detaching from organization, remove all project assignments in this org
$projectIds = $organization->projects()->pluck('id');
DB::table('project_user')
    ->where('user_id', $user->id)
    ->whereIn('project_id', $projectIds)
    ->delete();
```

---

### Phase 5: Middleware & Org Resolution

#### 5.1 Update: `ResolveOrganization` Middleware

**Current behavior:** Resolves organization from `$user->organizations()` which queries the `organization_user` pivot. Project-only users (who have no `organization_user` row) would fail to resolve an organization.

**Critical change:** The middleware must also resolve organizations through project membership.

**File:** `app/Http/Middleware/ResolveOrganization.php`

```php
public function handle(Request $request, Closure $next): Response
{
    $user = $request->user();

    if (! $user) {
        return $next($request);
    }

    $preferredId = session('current_organization_id') ?? $user->current_organization_id;

    if ($preferredId) {
        // Check org membership first, then project membership
        $organization = $user->organizations()
            ->where('organization_id', $preferredId)
            ->first();

        if (! $organization) {
            // Try via project membership
            $organization = Organization::where('id', $preferredId)
                ->whereHas('projects.users', fn ($q) => $q->where('user_id', $user->id))
                ->first();
        }
    }

    if (! isset($organization) || ! $organization) {
        // Fallback: first org membership, then first project org
        $organization = $user->organizations()->first();

        if (! $organization) {
            $organization = Organization::whereHas('projects.users',
                fn ($q) => $q->where('user_id', $user->id)
            )->first();
        }
    }

    if (! $organization) {
        return $next($request);
    }

    session(['current_organization_id' => $organization->id]);

    if ($user->current_organization_id !== $organization->id) {
        $user->updateQuietly(['current_organization_id' => $organization->id]);
    }

    app()->instance(Organization::class, $organization);

    return $next($request);
}
```

#### 5.2 Update: `OrganizationSwitcher` Component

**File:** `app/Livewire/OrganizationSwitcher.php`

The `organizations` computed property must include orgs accessible via project membership:

```php
#[Computed]
public function organizations(): Collection
{
    $user = auth()->user();

    // Orgs from direct membership
    $directOrgs = $user->organizations()->orderBy('name')->get();

    // Orgs from project membership (not already in direct orgs)
    $directOrgIds = $directOrgs->pluck('id');
    $projectOrgs = Organization::whereHas('projects.users',
        fn ($q) => $q->where('user_id', $user->id)
    )
        ->whereNotIn('id', $directOrgIds)
        ->orderBy('name')
        ->get();

    return $directOrgs->merge($projectOrgs)->sortBy('name')->values();
}
```

The `switchOrganization()` method uses `Gate::authorize('view', $organization)` which will work because the updated `OrganizationPolicy::view()` checks project membership.

#### 5.3 User Model: `accessibleOrganizationIds()` Method

```php
use Illuminate\Support\Facades\DB;

/**
 * Get IDs of all organizations this user can access
 * (via direct org membership or project membership).
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
```

This method is used by tests and other code that needs to check multi-org access.

#### 5.4 `AddProjectMember` Action: Set `current_organization_id`

When adding a user to a project, if they don't have a `current_organization_id` set, set it:

```php
// In AddProjectMember::execute(), after attach:
if (! $user->current_organization_id) {
    $user->updateQuietly(['current_organization_id' => $project->organization_id]);
}
```

---

### Phase 6: Route Registration

#### New Route

```php
// In routes/web.php, inside the auth+verified+resolve-org group:
Route::livewire('projects/{projectId}/members', ProjectMembers::class)->name('projects.members');
```

#### ProjectShow Template Update

Add "Members" tab/link for org Organizers:

```blade
@can('manageMembers', $project)
    <flux:button variant="ghost" :href="route('projects.members', $project)" wire:navigate>
        {{ __('Members') }}
    </flux:button>
@endcan
```

---

### Phase 7: Tests

#### 7.1 Tests That BREAK and How to Fix Them

**Category A: Tests that create VA/ES users via `createUserWithOrganization()`**

These tests create users with VA/ES org roles. After migration #15, these roles are deleted. The test helper still works (creates the pivot row), but:
- Policies no longer recognize VA/ES for non-scanner permissions
- VA/ES users can't access components that now require Organizer

**Affected test files (create VA/ES users):**

| File | Creates | Fix Strategy |
|---|---|---|
| `tests/Feature/Policies/EventPolicyTest.php` | VA + ES | Rewrite: VA/ES tests become "project Organizer" tests. Scanner/attendance tests become Organizer-only. |
| `tests/Feature/Policies/ProjectPolicyTest.php` | VA + ES | Rewrite: VA/ES → project Organizer. ES → non-member. |
| `tests/Feature/Policies/OrganizationPolicyTest.php` | VA + ES | Rewrite: VA/ES → project-only user (no org_user row). |
| `tests/Feature/Settings/MemberManagementTest.php` | VA + ES | Update: remove role change tests, update invite tests to Organizer-only. |
| `tests/Feature/Navigation/ScannerNavigationTest.php` | ES + VA | Rewrite: only Organizer sees scanner link. Remove ES/VA assertions. |
| `tests/Feature/Navigation/SidebarNavigationTest.php` | VA | Update: "non-organizer" test uses project-only user instead. |
| `tests/Feature/Scanner/QrScannerPageTest.php` | ES + VA | Remove VA/ES renders tests. Add project Organizer test. |
| `tests/Feature/Scanner/ScannerEventSelectTest.php` | ES + VA | Remove VA/ES renders tests. Add project Organizer test. |
| `tests/Feature/Scanner/ManualLookupTest.php` | ES + VA | Remove VA/ES test cases. |
| `tests/Feature/Scanner/ScannerDataEndpointTest.php` | ES | Replace ES with Organizer. |
| `tests/Feature/Scanner/ScannerSyncEndpointTest.php` | ES | Replace ES with Organizer. |
| `tests/Feature/Scanner/ScannerAttendanceSyncTest.php` | VA | Replace VA with Organizer. |
| `tests/Feature/Scanner/ScannerTicketLifecycleTest.php` | ES | Replace ES with Organizer. |
| `tests/Feature/Livewire/ManualEnrollmentTest.php` | VA | Replace VA with Organizer. |
| `tests/Feature/Livewire/EventAnnouncementsTest.php` | VA | Replace VA with Organizer. |
| `tests/Feature/Livewire/EventGearSetupTest.php` | VA | Replace VA with Organizer. |
| `tests/Feature/Livewire/GearTrackerTest.php` | VA | Replace VA with Organizer. |
| `tests/Feature/Livewire/GearTrackerWrongEventTest.php` | VA | Replace VA with Organizer. |
| `tests/Feature/Livewire/CustomFieldSetupTest.php` | VA | Replace VA with Organizer. |
| `tests/Feature/Events/AttendanceTrackerTest.php` | VA | Replace VA with Organizer. |
| `tests/Feature/Events/EventListTest.php` | VA | Replace VA with Organizer or project Organizer. |
| `tests/Feature/Events/EventShowTest.php` | VA | Replace VA with Organizer or project Organizer. |
| `tests/Feature/Events/VolunteerListTest.php` | VA | Replace VA with Organizer or project Organizer. |
| `tests/Feature/Events/VolunteerDetailTest.php` | VA | Replace VA with Organizer or project Organizer. |
| `tests/Feature/Events/JobsAndShiftsManagerTest.php` | VA | Replace VA with Organizer. |
| `tests/Feature/Events/EmailTemplateEditorTest.php` | VA | Replace VA with Organizer. |
| `tests/Feature/Livewire/EventShowGroupTest.php` | VA | Replace VA with Organizer. |
| `tests/Feature/Livewire/EventShowGraceMinutesTest.php` | VA | Replace VA with Organizer. |
| `tests/Feature/Livewire/ProjectListTest.php` | VA | Replace VA with project Organizer. |
| `tests/Feature/Livewire/ProjectShowTest.php` | VA | Replace VA with project Organizer. |
| `tests/Feature/Livewire/ActivityFeedTest.php` | VA | Replace VA with Organizer (already denied). |
| `tests/Feature/DashboardTest.php` | VA | Update role display assertions. |
| `tests/Feature/Actions/LeaveOrganizationTest.php` | VA | Replace VA with Organizer (second member). |
| `tests/Feature/Actions/CreateOrganizationTest.php` | — | No VA/ES, but verify Organizer role. |
| `tests/Feature/Actions/PromoteVolunteerTest.php` | VA/ES | Update: promote always creates Organizer. |
| `tests/Feature/OrganizationSwitcherTest.php` | VA | Replace VA with Organizer or project-only user. |
| `tests/Feature/ResolveOrganizationTest.php` | VA | Update for project-only user access. |
| `tests/Feature/Middleware/ResolveOrganizationTest.php` | VA | Update for project-only user access. |
| `tests/Feature/Console/SetupCommandTest.php` | — | Verify Organizer role. |
| `tests/Feature/Console/InviteOrganizerCommandTest.php` | — | Verify Organizer role. |
| `tests/Feature/Console/CreateAdminCommandTest.php` | — | Verify Organizer role. |
| `tests/Feature/Settings/EmailSettingsTest.php` | VA | Replace VA with Organizer. |
| `tests/Feature/Listeners/RecordActivityListenerTest.php` | VA | Replace VA with Organizer. |

**Category B: Tests that need NEW test cases for project-level roles**

| Test File | New Cases Needed |
|---|---|
| `tests/Feature/Policies/ProjectPolicyTest.php` | Project Organizer can view/update. Cannot create/delete. Non-member denied. |
| `tests/Feature/Policies/EventPolicyTest.php` | Project Organizer can view/update/publish events in their project. Cannot access events in other projects. |
| `tests/Feature/Policies/OrganizationPolicyTest.php` | Project-only user can view org. Cannot update or manage members. |
| NEW: `tests/Feature/Livewire/ProjectMembersTest.php` | Full CRUD tests for ProjectMembers component. |
| NEW: `tests/Feature/Actions/AddProjectMemberTest.php` | Add member, duplicate prevention, org-Organizer rejection. |
| NEW: `tests/Feature/Actions/RemoveProjectMemberTest.php` | Remove member. |

#### 7.2 Test Helper Update

```php
// tests/Pest.php — update createUserWithOrganization:
// Keep existing function but add a new helper:

function createUserWithProjectOrganization(
    \App\Models\Organization $organization = null,
): array {
    $user = \App\Models\User::factory()->create();
    $organization ??= \App\Models\Organization::factory()->create();
    $project = \App\Models\Project::factory()->for($organization)->create();

    $project->users()->attach($user, ['role' => \App\Enums\StaffRole::Organizer]);

    return ['user' => $user, 'organization' => $organization, 'project' => $project];
}
```

#### 7.3 New Test: `ProjectMembersTest.php`

```
- renders for org Organizer
- denies access to project-only Organizer
- denies access to non-member
- lists project members
- shows inherited org Organizers as read-only
- adds a project member by email
- rejects non-existent email
- rejects duplicate member
- rejects org Organizer (already has full access)
- removes a project member
```

#### 7.4 New Test: `ProjectPolicyTest.php` (rewritten)

```
- org Organizer can viewAny, view, create, update, delete all projects
- project Organizer can view their project
- project Organizer can update their project
- project Organizer cannot create projects
- project Organizer cannot delete projects
- project Organizer cannot view other projects
- non-member cannot view, update, or delete
- org Organizer can manageMembers
- project Organizer cannot manageMembers
```

#### 7.5 New Test: `EventPolicyTest.php` (rewritten)

```
- org Organizer: full access (create, view, update, publish, archive, manageJobs, markAttendance, scan, manageGear, trackGearPickup, manageCustomFields)
- project Organizer: view, update, publish, archive, manageJobs, markAttendance, scan, manageGear, trackGearPickup, manageCustomFields for events in their project
- project Organizer: denied for events in other projects
- non-member: denied for all
- viewAny: org Organizer or any project member
```

#### 7.6 Database Seeder Update

```php
// DatabaseSeeder.php — no VA/ES users created. Already only creates Organizer.
// No change needed.
```

---

### Implementation Order (with dependencies)

```
Step 1: Migration #14 (create_project_user_table)
  └── No dependencies

Step 2: Migration #15 (remove_non_organizer_roles_from_org_user)
  └── Depends on: Step 1 (run migrations in order)

Step 3: Models (ProjectUser, User updates, Project updates)
  └── Depends on: Step 1 (project_user table must exist)
  └── Includes: cachedProjectRoleFor(), isOrgOrganizerFor(), accessibleOrganizationIds(),
                 Project::users(), User::projects()

Step 4: Policies (ProjectPolicy, EventPolicy, OrganizationPolicy)
  └── Depends on: Step 3 (uses cachedProjectRoleFor, isOrgOrganizerFor)
  └── Includes: ProjectPolicy::manageMembers (new method)

Step 5: Middleware & Org Resolution
  └── Depends on: Step 3 (uses accessibleOrganizationIds, project relationships)
  └── Includes: ResolveOrganization middleware, OrganizationSwitcher component
  └── Critical: project-only users must resolve an org successfully

Step 6: Actions (AddProjectMember, RemoveProjectMember, LeaveOrganization update)
  └── Depends on: Step 3 (model relationships)
  └── Includes: LeaveOrganization cleanup of project_user rows

Step 7: Components — scanner (QrScanner, ManualLookup, ScannerEventSelect, sidebar)
  └── Depends on: Steps 4, 5 (policy changes + middleware)

Step 8: Components — MemberManagement update
  └── Depends on: Step 4 (Organizer-only)

Step 9: Components — ProjectMembers (NEW) + route registration
  └── Depends on: Steps 4, 5, 6 (policy + middleware + actions)

Step 10: Components — VolunteerDetail promote update, ScannerApiController
  └── Depends on: Step 4

Step 11: Fix existing tests (Category A — all ~40 files)
  └── Depends on: Steps 3-10 (all code changes done)
  └── Fix strategy: replace VA/ES with Organizer or project Organizer

Step 12: Write new tests (Category B)
  └── Depends on: Step 11
  └── New test files: ProjectMembersTest, AddProjectMemberTest, RemoveProjectMemberTest
  └── Rewritten tests: ProjectPolicyTest, EventPolicyTest, OrganizationPolicyTest

Step 13: Run full test suite, fix failures
  └── Depends on: Step 12

Step 14: Run pint --dirty
  └── Depends on: Step 13
```

**Parallel opportunities:**
- Steps 7, 8, 9, 10 can be done in parallel (independent component changes)
- Steps 11 and 12 should be done together per test file
- Steps 4 and 5 could be parallelized (policies don't depend on middleware)

---

### Data Migration Strategy

**No data migration needed.** Per the plan constraints:
- App is not in production
- All data is test data
- `migrate:fresh` is always available

Migration #15 simply deletes non-Organizer rows from `organization_user`. Any existing VA/ES users in test data lose access. This is intentional — the app structure has changed.

**For the seeder:** Already creates only Organizer users. No update needed.

---

### Summary of File Changes

| File | Change Type | Description |
|---|---|---|
| `database/migrations/xxxx_create_project_user_table.php` | NEW | Create project_user pivot table |
| `database/migrations/xxxx_remove_non_organizer_roles.php` | NEW | Delete VA/ES rows from organization_user |
| `app/Models/ProjectUser.php` | NEW | Pivot model |
| `app/Models/User.php` | MODIFY | Add projects(), cachedProjectRoleFor(), isOrgOrganizerFor(), accessibleOrganizationIds() |
| `app/Models/Project.php` | MODIFY | Add users() relationship |
| `app/Policies/ProjectPolicy.php` | MODIFY | Two-tier role resolution, add manageMembers |
| `app/Policies/EventPolicy.php` | MODIFY | Project-based resolution, Organizer-only for scanner/attendance |
| `app/Policies/OrganizationPolicy.php` | MODIFY | view() includes project membership |
| `app/Actions/AddProjectMember.php` | NEW | Add user to project |
| `app/Actions/RemoveProjectMember.php` | NEW | Remove user from project |
| `app/Actions/LeaveOrganization.php` | MODIFY | Clean up project_user rows on leave |
| `app/Livewire/Projects/ProjectMembers.php` | NEW | Project member management component |
| `resources/views/livewire/projects/project-members.blade.php` | NEW | ProjectMembers template |
| `app/Livewire/Settings/MemberManagement.php` | MODIFY | Remove VA/ES options, Organizer-only |
| `resources/views/livewire/settings/member-management.blade.php` | MODIFY | Remove role selects |
| `app/Livewire/Events/VolunteerDetail.php` | MODIFY | Promote as Organizer only |
| `resources/views/livewire/events/volunteer-detail.blade.php` | MODIFY | Remove VA/ES from promote modal |
| `app/Livewire/Scanner/QrScanner.php` | MODIFY | Organizer-only access check |
| `app/Livewire/Scanner/ManualLookup.php` | MODIFY | Organizer-only access check |
| `app/Livewire/Scanner/ScannerEventSelect.php` | MODIFY | Organizer + project member check, scoped events |
| `app/Http/Controllers/ScannerApiController.php` | MODIFY | Project-based role resolution |
| `resources/views/layouts/app/sidebar.blade.php` | MODIFY | Simplify scanner link to Organizer-only |
| `app/Livewire/Dashboard.php` | MINOR | userRole uses project-aware resolution (optional) |
| `tests/Pest.php` | MODIFY | Add createUserWithProjectOrganization helper |
| ~40 test files | MODIFY | Replace VA/ES with Organizer or project Organizer |
| `tests/Feature/Livewire/ProjectMembersTest.php` | NEW | ProjectMembers component tests |
| `tests/Feature/Actions/AddProjectMemberTest.php` | NEW | AddProjectMember action tests |
| `tests/Feature/Actions/RemoveProjectMemberTest.php` | NEW | RemoveProjectMember action tests |

### Open Questions for Implementation

1. **Event.project eager loading:** Policies calling `$event->project` will trigger lazy loads. Need to ensure `project` is eager-loaded on events accessed through policies. Components that load events should add `->with('project.organization')` or rely on the relationship already being loaded. Audit during implementation.
2. **Dashboard for project-only users:** Should they see org-wide metrics or project-scoped metrics? For M9, org-wide is acceptable (they're Organizers of specific projects but can see aggregate data). Can scope in M13 if needed.
3. **`currentOrganization()` helper:** This calls `app(Organization::class)`. After the middleware update, project-only users will have an org bound via project membership. Verify all components that call `currentOrganization()` still work correctly.
4. **Test performance:** Many tests use `createUserWithOrganization()`. The new `createUserWithProjectOrganization()` helper creates additional models (Organization, Project, ProjectUser). May add test runtime. Monitor.
