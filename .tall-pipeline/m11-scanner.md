# Milestone: m11-scanner — Scanner Rewrite

**Features:** Project scanners, temp auth via email link + one-time code, dual scanner types (Entry Staff + Volunteer Admin), time windows, scanner management admin UI, project-scoped JWT (already done in M8), TypeScript rewrite for project scope, rename volunteer tab to "Volunteers"
**Issues:** #75, #58, #56, #57, #71, #72, #73, #48
**Dependencies:** m8-project-scoped (complete), m9-roles (complete)

## Plan
- **Status:** complete
- **Gate summary:** 2 migrations, 2 new models, 2 new enums, 5 new actions, 1 job, 1 mailable, 1 command, 2 middleware, 3 new Livewire components, 1 new API controller, 7 TS module updates, SW update. 4-pass self-review passed. 12 decisions recorded.

## Implement
- **Status:** complete
- **Iteration:** 1
- **Gate summary:** 1046 tests green (2293 assertions), 31 TS tests green. 27 new files, ~15 modified, 11 deleted. Pint clean. migrate:fresh --seed clean. 14 review concerns fixed (13 accepted, 1 rejected). Rate limiting on PIN auth, IDOR fix on gear pickup, #[Locked] hardening, aria-live regions, dead code cleanup.
- **Tasks (bug fix #49 — overlap detection):**
  - [x] RED: 4 failing tests for overlap detection (existing signup, intra-batch, adjacent boundary, reactivation)
  - [x] Add `skippedOverlap` to `ShiftSignupResult`
  - [x] Implement overlap detection in `SignUpVolunteerForShifts` (committedShifts pre-load, predicate, null guards, lockForUpdate, return array, constructor)
  - [x] Update `EventSignup` submitSignup() for overlap feedback
  - [x] All 1046 tests green, pint clean
- **Tasks (M11 Scanner Rewrite):**
  - **Phase 1: Migrations + Models + Enums + Factories**
    - [x] Create ScannerType and ScannerMode enums
    - [x] Create create_project_scanners_table migration
    - [x] Create create_project_scanner_assignees_table migration
    - [x] Run migrations
    - [x] Create ProjectScanner model with factory, scopes, casts
    - [x] Create ProjectScannerAssignee model with factory
    - [x] Add scanners() relationship to Project model
    - [x] RED: Write ProjectScanner model unit tests
    - [x] GREEN: All 12 model tests pass
  - **Phase 2: Actions + Policy**
    - [x] RED: Write CreateProjectScanner action tests (5 tests)
    - [x] GREEN: Implement CreateProjectScanner action
    - [x] RED: Write AuthenticateScanner action tests (4 tests)
    - [x] GREEN: Implement AuthenticateScanner action
    - [x] RED: Write SendScannerLinks action tests (3 tests)
    - [x] GREEN: Implement SendScannerLinks action + SendScannerLinksJob stub
    - [x] Implement UpdateProjectScanner action
    - [x] Implement DeleteProjectScanner action
    - [x] Add manageScanners() to ProjectPolicy
  - **Phase 3: Middleware + Auth Flow**
    - [x] Create ScannerAuthMiddleware + ScannerApiMiddleware
    - [x] Register middleware aliases in bootstrap/app.php
    - [x] RED: Write ScannerAuth Livewire component tests (6 tests)
    - [x] GREEN: Implement ScannerAuth component + blade
    - [x] Register scanner auth + app routes
  - **Phase 4: ScannerApp Livewire Component**
    - [x] RED: Write ScannerApp component tests (6 tests)
    - [x] GREEN: Implement ScannerApp component + blade
    - [x] All 6 tests pass
  - **Phase 5: Scanner API Controller**
    - [x] RED: Write ScannerApiMiddleware tests (5 tests)
    - [x] GREEN: ScannerApiMiddleware passes
    - [x] RED: Write ScannerDataController tests (7 tests)
    - [x] GREEN: Implement ScannerDataController (data/sync/gearPickup)
    - [x] Register API routes + nullable scannedBy in RecordArrival/RecordGearPickup
  - **Phase 6: ScannerManagement Admin Component**
    - [x] RED: Write ScannerManagement tests (9 tests)
    - [x] GREEN: Implement ScannerManagement component + blade (Flux UI)
    - [x] Register admin route + navigation
  - **Phase 7: Queue Job + Scheduled Command**
    - [x] Implement SendScannerLinksJob + ScannerLinkMail mailable
    - [x] RED: Write SendScannerLinksCommand tests (4 tests)
    - [x] GREEN: Implement SendScannerLinksCommand
    - [x] Register in routes/console.php
  - **Phase 8: TypeScript Rewrite**
    - [x] Update types.ts (added GearItem, VolunteerGear, ScannerConfig, EventInfo)
    - [x] Update idb-store.ts (DB_VERSION 3, scannerId keys)
    - [x] Update sync.ts (new URLs, X-Scanner-Token header)
    - [x] Rewrite alpine-scanner.ts (new config, gear support, token auth)
    - [x] Create gear-pickup.ts (online-only)
    - [x] Update sw.js (new API path pattern)
    - [x] All 35 TS tests pass + build succeeds
  - **Phase 9: Remove Old Scanner Code**
    - [x] Delete old scanner components (QrScanner, ManualLookup, ScannerEventSelect)
    - [x] Delete old ScannerApiController
    - [x] Remove old scanner routes from web.php
    - [x] Delete old scanner tests (7 files) + SyncArrivalsRequestTest
    - [x] Replace sidebar scanner link with Projects link + add Scanners button to ProjectShow
  - **Phase 10: Issue #48 + Final Cleanup**
    - [x] Verified volunteer tab label already says "Volunteers" (no-op per D12)
    - [x] Updated ScannerNavigationTest for new sidebar layout
    - [x] Run Pint — all clean
    - [x] Full test suite: 1046 passed (2293 assertions)
    - [x] migrate:fresh --seed clean

## Test
- **Status:** complete
- **Gate summary:** 1126 tests green (2466 assertions). 80 new tests across 8 new files + 1 updated file. Coverage gaps filled for: UpdateProjectScanner (7), DeleteProjectScanner (3), SendScannerLinksJob (4), ProjectPolicy::manageScanners (4), ScannerAuth rate limiting + edge cases (12), ScannerAuthMiddleware (7), ScannerDataController IDOR + edge cases (10), ScannerManagement validation + IDOR (18), ProjectScanner model boundaries (13), integration flows (2). Pint clean.

## Security Audit
- **Status:** complete
- **Report:** `.tall-pipeline/m11-security-audit.md`
- **Gate summary:** 0 critical, 2 high, 1 medium, 4 low. All high/medium findings FIXED: (1) session()->regenerate() added, (2) throttle:60,1 on scanner API, (3) Rule::exists eventId validation. Low: non-timing-safe token lookup (accepted risk), volunteer PII to entry staff, missing attendance API endpoint, console.error leaking. 1126 tests green after fixes.

---

## Cross-Milestone Interface

| Category | Items |
|---|---|
| Models | `ProjectScanner` (id, project_id, event_id?, name, type, modes, auth_code, scanner_token, starts_at, ends_at); `ProjectScannerAssignee` (id, project_scanner_id, email, link_sent_at, authenticated_at) |
| Enums | `ScannerType` (EntryStaff, VolunteerAdmin); `ScannerMode` (Checkin, GearPickup) |
| Actions | `CreateProjectScanner::execute(Project, array): ProjectScanner`; `UpdateProjectScanner::execute(ProjectScanner, array): ProjectScanner`; `DeleteProjectScanner::execute(ProjectScanner): void`; `SendScannerLinks::execute(ProjectScanner): void`; `AuthenticateScanner::execute(ProjectScanner, string): bool` |
| Jobs | `SendScannerLinksJob` (queued, per-assignee, sends ScannerLinkMail) |
| Middleware | `scanner-auth` (session-based, web routes); `scanner-api` (X-Scanner-Token header, API routes) |
| Routes (public) | `scanner.auth` → `GET /s/{scannerToken}` (ScannerAuth); `scanner.app` → `GET /s/{scannerToken}/scan` (ScannerApp, scanner-auth middleware) |
| Routes (API) | `scanner-api.data` → `GET /api/scanner/{scannerId}/data`; `scanner-api.sync` → `POST .../sync`; `scanner-api.gear-pickup` → `POST .../gear-pickup` (all scanner-api middleware) |
| Routes (admin) | `projects.scanners` → `GET /projects/{projectId}/scanners` (ScannerManagement) |
| Livewire | `ScannerAuth` (public auth page, 6-digit PIN); `ScannerApp` (scanner UI, both types); `Projects\ScannerManagement` (admin CRUD) |
| Controller | `ScannerDataController` (data, sync, gearPickup endpoints) |
| Policy | `ProjectPolicy::manageScanners()` — Organizer only |
| Key patterns | Scanner token = 64-byte hex; auth code = bcrypt-hashed 6-digit PIN; session-based web auth; header-based API auth; time window enforcement via `isActive()`/`isExpired()` |

## Decisions

| # | Stage | Decision | Rationale | Affects |
|---|---|---|---|---|
| D1 | plan | `auth_code` stored as bcrypt hash, not plaintext | One-time auth codes are credentials; same policy as passwords | ProjectScanner, AuthenticateScanner, ScannerAuth |
| D2 | plan | Scanner session stored in Laravel session (cookie-based) | No JWT for scanner session; session holds `scanner_id` + `authenticated_at`; simpler and more controllable | ScannerAuthMiddleware, ScannerApp, API |
| D3 | plan | Single `ScannerApp` Livewire component handles both scanner types via `$scannerType` | Avoids route duplication; type drives rendering logic; PHP component switches blade sections | ScannerApp, ScannerApp blade |
| D4 | plan | Volunteer Admin scanner: gear pickup is online-only; no outbox buffering | Gear state changes require real-time confirmation (size selection, quantity); offline queueing adds complexity without proportional benefit | GearPickup API endpoint, TS modules |
| D5 | plan | `project_event_scanners` table dropped from scope | M11 scope only includes scanner configs scoped to a project OR a single event via `event_id` nullable FK on `project_scanners`. Full multi-event pivot is M12 territory. | project_scanners schema |
| D6 | plan | Remove old scanner routes + components after new ones ship | Old `scanner/*` routes (QrScanner, ManualLookup, ScannerEventSelect) are replaced entirely. Delete them in Phase 4 to keep the codebase clean. | routes/web.php, old Livewire components |
| D7 | plan | `SendScannerLinks` action dispatches `SendScannerLinksJob` per assignee | One job per email; each is independently retriable | SendScannerLinks, SendScannerLinksJob |
| D8 | plan | Auth code is 6-digit numeric, displayed inline on auth page after generation | Simple to communicate verbally; expires with window; bcrypt stored | ProjectScanner, ScannerAuth blade |
| D9 | plan | Scanner API uses `X-Scanner-Token` header (hashed token) for auth instead of session | API calls from Alpine (fetch) must authenticate without Fortify session; scanner token in header is simple and testable | ScannerApiMiddleware, API controller |
| D10 | plan | Gear pickup for Volunteer Admin type is online-only — no IndexedDB buffering | Gear state changes need confirmation; offline queue would leave unclear state on pickup items | TS modules, GearPickup endpoint |
| D11 | plan | IDB stores bumped to DB_VERSION 3 — new compound keys use `scannerId` not `eventId` | Project-scoped scanner; scanner covers one project (or event); scannerId is the natural IDB scope key | idb-store.ts |
| D12 | plan | Rename `/admin/events/{eventId}/volunteers` tab label from "Volunteers" to "Volunteers" (no-op on UI — it already says "Volunteers" per #48 check) | Issue #48 asked to rename this — verify it's already correct before touching it | sidebar/tabs |

## Reviews

### implement — 2026-03-31

| # | Perspective | Concern | Severity | Resolution | Rationale |
|---|---|---|---|---|---|
| S1 | Simplicity Zealot | `link_sent_at` double-write in action + job; failed sends invisible | high | accepted | Removed eager update from action |
| S2 | Simplicity Zealot | Attendance outbox dead path; entries accumulate, block clear | high | accepted | Changed to online-only direct API call |
| P1 | Security Paranoid | No brute-force protection on 6-digit PIN | high | accepted | Added RateLimiter (5/min, token+IP key) |
| P2 | Security Paranoid | IDOR on gear pickup — no project scope check | high | accepted | Scoped query through project relationship |
| A1 | Accessibility Champion | ScannerApp result panel has no aria-live | high | accepted | Added role="alert" aria-live="assertive" |
| A2 | Accessibility Champion | ScannerAuth error message has no role="alert" | high | accepted | Added role="alert" aria-live="assertive" |
| S3 | Simplicity Zealot | gear-pickup.ts dead code, unused exports | medium | accepted | Deleted file, removed unused exports |
| P3 | Security Paranoid | scannerToken unlocked on ScannerApp + ScannerAuth | medium | accepted | Added #[Locked] |
| P4 | Security Paranoid | modes and eventId unlocked on ScannerApp | medium | accepted | Added #[Locked] |
| P5 | Security Paranoid | rawAuthCode persists in Livewire snapshot | medium | accepted | Replaced with session()->flash() |
| A3 | Accessibility Champion | Remove-assignee × buttons lack aria-label | medium | accepted | Added aria-label with email context |
| A4 | Accessibility Champion | Add-assignee email input has no label | medium | accepted | Added aria-label |
| S4 | Simplicity Zealot | DeleteProjectScanner is a one-line action | low | rejected | Project convention: all domain logic in actions |
| S5 | Simplicity Zealot | ScannerAuthMiddleware checks isExpired but not isActive | low | accepted | Changed to !isActive() |
| A5 | Accessibility Champion | Video viewfinder has no ARIA label | low | accepted | Added aria-label |

## Feedback Loops

| # | Date | Direction | Trigger | Fix | Resolution |
|---|---|---|---|---|---|

---

## 1. Database Schema

### 1.1 Migration #19: create_project_scanners_table

```php
Schema::create('project_scanners', function (Blueprint $table) {
    $table->id();
    $table->foreignId('project_id')->constrained()->cascadeOnDelete();
    $table->foreignId('event_id')->nullable()->constrained()->nullOnDelete();
    $table->string('name');
    $table->string('type'); // 'entry_staff' | 'volunteer_admin'
    $table->json('modes')->nullable(); // ['checkin', 'gear_pickup']
    $table->json('gear_item_ids')->nullable(); // array of project_gear_item ids
    $table->text('hint_text')->nullable();
    $table->dateTime('starts_at');
    $table->dateTime('ends_at');
    $table->string('auth_code'); // bcrypt hash of 6-digit code
    $table->string('scanner_token', 64)->unique(); // for URL + API header auth
    $table->timestamps();

    $table->index('project_id');
    $table->index('scanner_token');
});
```

**Columns:**

| Column | Type | Nullable | Default | Classification | Notes |
|---|---|---|---|---|---|
| id | bigint PK | no | auto | internal | |
| project_id | FK → projects | no | — | internal | Owner project |
| event_id | FK → events | yes | null | internal | Optional: scopes scanner to single event; null = project-wide |
| name | string | no | — | internal | Friendly label e.g. "Eingang Süd" |
| type | string | no | — | internal | `entry_staff` or `volunteer_admin` |
| modes | json | yes | null | internal | `['checkin']`, `['gear_pickup']`, or `['checkin', 'gear_pickup']` |
| gear_item_ids | json | yes | null | internal | Which gear items this VA scanner handles |
| hint_text | text | yes | null | internal | Shown on scanner UI |
| starts_at | datetime | no | — | internal | Window open |
| ends_at | datetime | no | — | internal | Window close |
| auth_code | string | no | — | confidential | bcrypt hash of 6-digit numeric code |
| scanner_token | string(64) | no | — | confidential | Used in URL `/s/{token}` and API `X-Scanner-Token` header |
| created_at | timestamp | no | auto | internal | |
| updated_at | timestamp | no | auto | internal | |

**Data classification:** `auth_code` is `confidential` (hashed credential). `scanner_token` is `confidential` (bearer token — never log). All other columns are `internal`.

**Soft deletes:** No. Scanners are fully deleted by organizers. No audit trail requirement.

**Casts (in model):**
```php
protected function casts(): array
{
    return [
        'modes' => 'array',
        'gear_item_ids' => 'array',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];
}
```

**Computed status:** No `status` column — derived at runtime from `starts_at`/`ends_at`/`now()`:
- `scheduled`: `now() < starts_at`
- `active`: `starts_at <= now() <= ends_at`
- `expired`: `now() > ends_at`

**Query scopes:**
- `scopeActive($query)`: within time window
- `scopeScheduled($query)`: `starts_at > now()`
- `scopeWindowOpensSoon($query, int $minutes)`: `starts_at` between now and now+$minutes (for `scanner-links:send`)

### 1.2 Migration #20: create_project_scanner_assignees_table

```php
Schema::create('project_scanner_assignees', function (Blueprint $table) {
    $table->id();
    $table->foreignId('project_scanner_id')
        ->constrained('project_scanners')
        ->cascadeOnDelete();
    $table->string('email');
    $table->dateTime('link_sent_at')->nullable();
    $table->dateTime('authenticated_at')->nullable();
    $table->timestamps();

    $table->index('project_scanner_id');
    $table->unique(['project_scanner_id', 'email']);
});
```

**Columns:**

| Column | Type | Nullable | Default | Classification | Notes |
|---|---|---|---|---|---|
| id | bigint PK | no | auto | internal | |
| project_scanner_id | FK → project_scanners | no | — | internal | |
| email | string | no | — | confidential | PII — email address of assignee |
| link_sent_at | datetime | yes | null | internal | When the email link was last sent |
| authenticated_at | datetime | yes | null | internal | When the assignee last authenticated |
| created_at | timestamp | no | auto | internal | |
| updated_at | timestamp | no | auto | internal | |

**Data classification:** `email` is `confidential` (PII). Excluded from API responses. Not logged.

**Soft deletes:** No. Assignees are removed via admin UI.

**Unique constraint:** `[project_scanner_id, email]` — one row per email per scanner.

### 1.3 Migration Order

```
18. (reserved for any M10 addendum)
19. create_project_scanners_table
20. create_project_scanner_assignees_table
```

These two migrations must run in order (#19 before #20) due to the FK dependency.

---

## 2. Models

### 2.1 ProjectScanner (NEW)

**File:** `app/Models/ProjectScanner.php`

**Relationships:**
- `belongsTo(Project::class)` — owner project
- `belongsTo(Event::class)` — optional event scope (nullable)
- `hasMany(ProjectScannerAssignee::class)` — email assignees

**Key methods:**
- `getStatusAttribute(): string` — computed `scheduled`|`active`|`expired` based on `starts_at`/`ends_at` vs `now()`
- `isActive(): bool` — `now()->between($this->starts_at, $this->ends_at)`
- `isExpired(): bool` — `now()->gt($this->ends_at)`
- `hasMode(string $mode): bool` — checks `modes` array

**Scopes:**
- `scopeActive(Builder $query): Builder`
- `scopeScheduled(Builder $query): Builder`
- `scopeWindowOpensSoon(Builder $query, int $minutes): Builder`

**Data classification:** `auth_code` and `scanner_token` must never appear in JSON serialization — add to `$hidden`.

**Factory:** `ProjectScannerFactory` — generates a random 6-digit code (stored hashed), a random `scanner_token`, sets `starts_at` to now, `ends_at` to now+2h by default.

**No soft deletes.**

### 2.2 ProjectScannerAssignee (NEW)

**File:** `app/Models/ProjectScannerAssignee.php`

**Relationships:**
- `belongsTo(ProjectScanner::class)`

**Key methods:** none; simple data record

**Data classification:** `email` is PII — add to `$hidden` for JSON serialization (excluded from API responses).

**Factory:** `ProjectScannerAssigneeFactory` — uses `fake()->safeEmail()`, nullable timestamps.

**No soft deletes.**

---

## 3. Enums

### 3.1 ScannerType (NEW)

**File:** `app/Enums/ScannerType.php`

```php
enum ScannerType: string
{
    case EntryStaff = 'entry_staff';
    case VolunteerAdmin = 'volunteer_admin';
}
```

**Used in:** `ProjectScanner.$type` cast, `ScannerApp` component logic.

### 3.2 ScannerMode (NEW)

**File:** `app/Enums/ScannerMode.php`

```php
enum ScannerMode: string
{
    case Checkin = 'checkin';
    case GearPickup = 'gear_pickup';
}
```

**Used in:** `ProjectScanner.$modes` cast (as array of ScannerMode), `ScannerApp` rendering.

---

## 4. Authorization Model

### 4.1 Scanner Authorization

Scanner operators are **not** Laravel Users. They authenticate via:
1. Follow email link → `/s/{scannerToken}` (ScannerAuth page)
2. Enter 6-digit auth code on that page
3. Laravel session records `scanner_id` and `authenticated_at`
4. `ScannerAuthMiddleware` protects `/s/{scannerToken}/scan`
5. API calls use `X-Scanner-Token` header + `ScannerApiMiddleware`

**No policy** for scanner access. Authorization is: valid `scanner_token` + valid `auth_code` + time window active.

### 4.2 ProjectPolicy: `manageScanners`

Add to `ProjectPolicy`:
```php
/**
 * Project Organizers (direct or inherited from org) can manage scanners.
 */
public function manageScanners(User $user, Project $project): bool
{
    return $user->projectRoleFor($project) === StaffRole::Organizer;
}
```

### 4.3 Middleware

**`ScannerAuthMiddleware`** — for web routes at `/s/{scannerToken}/scan`:
- Reads `scanner_id` from session
- Verifies `ProjectScanner` exists and time window is active
- If expired: redirect to ScannerAuth with error
- If not authenticated: redirect to ScannerAuth

**`ScannerApiMiddleware`** — for API routes at `/api/scanner/{scannerId}/*`:
- Reads `X-Scanner-Token` header
- Validates token matches `ProjectScanner.scanner_token`
- Verifies time window is active
- Sets `request->attributes->set('scanner', $scanner)` for controller use
- Returns 401 JSON if invalid

**Alias registration in `bootstrap/app.php`:**
```php
$middleware->alias([
    'resolve-org' => ResolveOrganization::class,
    'scanner-auth' => ScannerAuthMiddleware::class,
    'scanner-api'  => ScannerApiMiddleware::class,
]);
```

---

## 5. Actions Inventory

### 5.1 New Actions

**`CreateProjectScanner`**
- Signature: `execute(Project $project, array $data): ProjectScanner`
- Generates random 6-digit auth code, bcrypt-hashes it, stores both hash and raw code in return (raw code only shown once in UI)
- Generates `scanner_token` via `bin2hex(random_bytes(32))`
- Validates `$data`: name (required), type (enum), modes (array), event_id (nullable FK), gear_item_ids (nullable array), hint_text (nullable), starts_at (datetime), ends_at (datetime, after starts_at)
- Returns `ProjectScanner` with `raw_auth_code` virtual attribute set (transient, not persisted)

**`UpdateProjectScanner`**
- Signature: `execute(ProjectScanner $scanner, array $data): ProjectScanner`
- Updates name, type, modes, event_id, gear_item_ids, hint_text, starts_at, ends_at
- Does NOT regenerate auth_code or scanner_token (immutable after creation)
- If new auth code requested (separate action or flag): `RegenerateAuthCode` considered — defer to M13

**`DeleteProjectScanner`**
- Signature: `execute(ProjectScanner $scanner): void`
- Deletes scanner; assignees cascade via FK

**`SendScannerLinks`**
- Signature: `execute(ProjectScanner $scanner): void`
- For each `ProjectScannerAssignee`, dispatch `SendScannerLinksJob`
- Updates `link_sent_at` on each assignee record after dispatching

**`AuthenticateScanner`**
- Signature: `execute(ProjectScanner $scanner, string $plainCode): bool`
- Verifies: `Hash::check($plainCode, $scanner->auth_code)`
- Verifies: time window is active (`$scanner->isActive()`)
- Returns `bool` — caller (ScannerAuth component) writes to session on success
- Does NOT update `authenticated_at` directly — caller is responsible

### 5.2 Unchanged Actions (no M11 changes)

`CreateProjectScanner`, `UpdateProjectScanner`, `DeleteProjectScanner`, `SendScannerLinks`, `AuthenticateScanner` are all NEW. All existing actions from M10 remain unchanged for M11.

---

## 6. Jobs

### 6.1 SendScannerLinksJob (NEW)

**File:** `app/Jobs/SendScannerLinksJob.php`

| Property | Value |
|---|---|
| Queue | `mail` |
| Timeout | 30s |
| Tries | 3 |
| Backoff | 10, 30, 60 |
| Idempotent? | Yes — checks `link_sent_at` not older than 1 hour before resending |

**Logic:**
1. Load `ProjectScannerAssignee` with `projectScanner`
2. Check scanner not expired
3. Send `ScannerLinkMail` to assignee email
4. Update `link_sent_at` on assignee

**`ScannerLinkMail`** — Mailable with URL `/s/{scannerToken}` and window times.

### 6.2 Scheduled Command: `scanner-links:send`

**File:** `app/Console/Commands/SendScannerLinksCommand.php`

- Schedule: every 5 minutes
- Logic: query `ProjectScanner::windowOpensSoon(minutes: 30)->whereDoesntHave('assignees', fn($q) => $q->whereNotNull('link_sent_at'))`
- For each: dispatch `SendScannerLinksJob` per assignee
- Registered in `routes/console.php`: `Schedule::command('scanner-links:send')->everyFiveMinutes()`

---

## 7. Routes

### 7.1 Public Scanner Routes (no auth)

```php
// routes/web.php — outside auth middleware group
Route::get('/s/{scannerToken}', ScannerAuth::class)->name('scanner.auth');
Route::middleware('scanner-auth')->group(function () {
    Route::livewire('/s/{scannerToken}/scan', ScannerApp::class)->name('scanner.app');
});
```

Note: `{scannerToken}` is the `scanner_token` column value (opaque 64-char hex string), not the `id`.

### 7.2 Admin Scanner Management Routes

```php
// Inside auth + resolve-org group
Route::livewire('projects/{projectId}/scanners', ScannerManagement::class)->name('projects.scanners');
```

### 7.3 Scanner API Routes

```php
// Outside auth middleware — protected by ScannerApiMiddleware
Route::prefix('api/scanner')->middleware('scanner-api')->group(function () {
    Route::get('/{scannerId}/data', [ScannerDataController::class, 'data'])->name('scanner-api.data');
    Route::post('/{scannerId}/sync', [ScannerDataController::class, 'sync'])->name('scanner-api.sync');
    Route::post('/{scannerId}/gear-pickup', [ScannerDataController::class, 'gearPickup'])->name('scanner-api.gear-pickup');
});
```

Note: The new API controller is `ScannerDataController` (separate from existing `ScannerApiController` which is deleted in Phase 6).

### 7.4 Remove Old Scanner Routes

After Phase 4 (new components shipped), remove:
```php
// REMOVE in Phase 6:
Route::livewire('scanner', ScannerEventSelect::class)->name('scanner.index');
Route::livewire('scanner/{eventId}', QrScanner::class)->name('scanner.scan');
Route::livewire('scanner/{eventId}/lookup', ManualLookup::class)->name('scanner.lookup');
Route::get('scanner/api/events/{eventId}/data', [ScannerApiController::class, 'data'])->name('scanner.data');
Route::post('scanner/api/events/{eventId}/sync', [ScannerApiController::class, 'sync'])->name('scanner.sync');
Route::post('scanner/api/events/{eventId}/attendance-sync', [ScannerApiController::class, 'syncAttendance'])->name('scanner.attendance-sync');
```

---

## 8. Livewire Components

### 8.1 ScannerAuth (NEW — full-page, public)

**File:** `app/Livewire/ScannerAuth.php`
**Route:** `GET /s/{scannerToken}` — no auth middleware
**Layout:** `layouts.scanner` (existing fullscreen dark layout)

**Properties:**
```php
public string $scannerToken;       // from route
public string $authCode = '';      // wire:model.live
public bool $authenticated = false;
public string $errorMessage = '';
```

**`#[Locked]`:** none — `scannerToken` comes from route, not user input

**`mount(string $scannerToken): void`**
- Find `ProjectScanner` by `scanner_token` — abort 404 if not found
- If already authenticated (session has `scanner_id` matching this scanner), set `$authenticated = true`
- If scanner expired, show "window closed" error

**`authenticate(): void`**
- Validate: `authCode` is 6 digits
- Call `AuthenticateScanner::execute($scanner, $this->authCode)`
- On success: write `scanner_id` and `authenticated_at` to session, redirect to `scanner.app`
- On failure: set `$errorMessage`, clear `$authCode`

**Blade template:** `resources/views/livewire/scanner-auth.blade.php`
- Scanner layout, dark bg
- Show scanner name + time window
- Single 6-digit PIN input (`wire:model.live`)
- Error state (wrong code)
- Expired state (window closed)

### 8.2 ScannerApp (NEW — full-page, scanner-auth protected)

**File:** `app/Livewire/ScannerApp.php`
**Route:** `GET /s/{scannerToken}/scan` — `scanner-auth` middleware
**Layout:** `layouts.scanner`

**Properties:**
```php
public string $scannerToken;           // from route, #[Locked]
public ?int $scannerId = null;         // loaded from session in mount()
public string $scannerType = '';       // 'entry_staff' | 'volunteer_admin'
public array $modes = [];              // ['checkin', 'gear_pickup']
public ?int $eventId = null;           // event_id from scanner config (nullable)
public int $projectId;                 // always set
public string $scannerName = '';       // display name
public ?string $hintText = null;
```

**`#[Locked]`:** `$scannerId`, `$projectId`, `$scannerType`

**`mount(string $scannerToken): void`**
- Load `ProjectScanner` by `scanner_token`; abort 403 if not active
- Load scanner properties into public properties
- Verify session `scanner_id` matches — middleware already did this, but double-check for safety

**`#[Computed]`:**
- `dataUrl(): string` — returns `/api/scanner/{scannerId}/data`
- `syncUrl(): string` — returns `/api/scanner/{scannerId}/sync`
- `gearPickupUrl(): string` — returns `/api/scanner/{scannerId}/gear-pickup`

**Blade template:** `resources/views/livewire/scanner-app.blade.php`
- Single template; Alpine `x-data="scannerApp({...})"` drives everything
- Uses `@if($scannerType === 'entry_staff')` to toggle entry-staff vs volunteer-admin sections
- Entry Staff section: QR viewfinder + result panel + guest list tab
- Volunteer Admin section: QR viewfinder + volunteer panel + shift list tab + gear pickup

### 8.3 ScannerManagement (NEW — full-page, admin, auth required)

**File:** `app/Livewire/Projects/ScannerManagement.php`
**Route:** `GET /admin/projects/{projectId}/scanners`
**Layout:** `layouts.app` (standard admin layout)

**Properties:**
```php
public int $projectId;                    // from route, #[Locked]
public ?Project $project = null;
public bool $showCreateModal = false;
public bool $showDeleteConfirm = false;
public ?int $deletingScannerId = null;
public array $rawAuthCode = [];           // [scannerId => code] transient after create
// Form fields
public string $name = '';
public string $type = 'entry_staff';
public array $modes = ['checkin'];
public ?int $eventId = null;
public array $gearItemIds = [];
public string $hintText = '';
public string $startsAt = '';
public string $endsAt = '';
public ?int $editingScannerId = null;
```

**`#[Locked]`:** `$projectId`

**`mount(int $projectId): void`**
- Authorize: `Gate::authorize('manageScanners', $project)` (new `ProjectPolicy::manageScanners`)
- Load project with events and gear items

**`#[Computed]`:**
- `scanners(): Collection` — `ProjectScanner::where('project_id', $projectId)->with('assignees')->orderBy('starts_at')->get()`
- `events(): Collection` — project events for event scoping dropdown
- `gearItems(): Collection` — project gear items for VA scanner config

**Actions:**
- `createScanner(): void` — validate → `CreateProjectScanner::execute($project, $data)` → store raw code in `$rawAuthCode[id]` → reset form
- `updateScanner(): void` — validate → `UpdateProjectScanner::execute($scanner, $data)` → reset
- `deleteScanner(): void` — `DeleteProjectScanner::execute($scanner)` → reset
- `sendLinks(int $scannerId): void` — `SendScannerLinks::execute($scanner)` → flash success
- `addAssignee(int $scannerId, string $email): void` — validate email → create `ProjectScannerAssignee`
- `removeAssignee(int $assigneeId): void` — delete assignee

**Blade template:** `resources/views/livewire/projects/scanner-management.blade.php`
- Flux UI table of scanners with status badge (scheduled/active/expired)
- Per-scanner: name, type chip, time window, assignees list, actions (edit, send links, delete)
- Raw auth code shown inline after creation (one-time reveal with copy button)
- Create/edit modal (Flux modal)
- Assignee management (inline add email + list of assignees)

---

## 9. API Controller

### 9.1 ScannerDataController (NEW)

**File:** `app/Http/Controllers/ScannerDataController.php`

Replaces `ScannerApiController` for the new scanner architecture. Does NOT use Fortify session auth — uses `ScannerApiMiddleware`.

**`data(int $scannerId, JwtKeyService $jwtKeyService): JsonResponse`**
- Load scanner from `request()->attributes->get('scanner')` (set by middleware)
- Determine scope: if `$scanner->event_id` set → single event; else → all project events
- Load volunteers scoped to project (or event)
- Load existing arrivals and attendance records
- Return volunteers with tickets, shift_signups, keys
- Add `scanner_type` and `modes` to response

**`sync(int $scannerId, SyncArrivalsRequest $request, RecordArrival $recordArrival): JsonResponse`**
- Entry Staff type only (validate `$scanner->type === ScannerType::EntryStaff`)
- Process arrival records from outbox
- No `scannedBy` user — set `scanned_by` to null or a system sentinel
- Return updated arrivals

**`gearPickup(int $scannerId, Request $request, RecordGearPickup $recordGearPickup): JsonResponse`**
- Volunteer Admin type only
- Online-only endpoint (no buffering)
- Validates: `volunteer_gear_id`, `state` (or `quantity` for Typ 2)
- Calls `RecordGearPickup::execute(...)`
- Returns updated gear state

**Authorization in all methods:** Scanner is already validated by `ScannerApiMiddleware`. Additional check: verify the requested data is within the scanner's project scope.

---

## 10. TypeScript Modules

All existing TS files in `resources/js/scanner/` are updated in-place. No new files except where noted.

### 10.1 `types.ts` — Updated

Changes from current:
- `Volunteer.name` → `Volunteer.first_name + last_name` (add both fields; keep `name` computed as `${first_name} ${last_name}`)
- Add `GearItem` interface:
  ```ts
  export interface GearItem {
      id: number;
      name: string;
      type: 'size_selection' | 'quantity';
      states: string[] | null; // for size_selection
  }
  export interface VolunteerGear {
      id: number;
      project_gear_item_id: number;
      state: string | null;
      quantity: number;
      picked_up_at: string | null;
  }
  ```
- `ScannerData`: replace `event.id` with `scanner.id`, `scanner.type`, `scanner.modes`; add `gear_items` array
- `ArrivalRecord.event_id` → `ArrivalRecord.project_id` (scanner is project-scoped)
- `OutboxEntry`: add `type: 'arrival' | 'attendance'` (already exists), no change needed here

### 10.2 `idb-store.ts` — Updated

Changes from current:
- **DB_VERSION bumped to 3** — schema upgrade required
- All stores switch from `eventId` key to `scannerId` key:
  - `volunteers` store: keyPath `['scannerId', 'id']`, index `byScanner`
  - `outbox` store: index `byScanner` (was `byEvent`)
  - `keys` store: keyPath `scannerId` (was `eventId`)
  - `attendance` store: keyPath `['scannerId', 'id']`, index `byScanner`
- All exported functions: replace `eventId: number` parameter with `scannerId: number`
- Add `onupgradeneeded` migration path: if upgrading from v2, drop all old stores and recreate (data will reload from server on next open)

### 10.3 `jwt-validator.ts` — No changes needed

The JWT validation logic (EdDSA via `@noble/ed25519`, dual-key fallback) is unchanged. Project-scoped keys are already in use from M8's `JwtKeyService` update. No changes required.

### 10.4 `camera.ts` — No changes needed

Camera module is framework-agnostic. No changes required.

### 10.5 `shift-context.ts` — No changes needed

Shift classification logic is data-driven. No changes required.

### 10.6 `sync.ts` — Updated

Changes from current:
- Replace old URL patterns (`/admin/scanner/api/events/{eventId}/sync`) with new (`/api/scanner/{scannerId}/sync`)
- Update `syncOutbox(scannerId: number, syncUrl: string, attendanceSyncUrl?: string)` — parameter rename only
- Remove `credentials: 'same-origin'` (new API uses token header, not cookie session)
- Add `X-Scanner-Token: {token}` header from config (passed in at init time)

### 10.7 `alpine-scanner.ts` — Rewritten

Key changes from current:
- Config parameter changes: `{ scannerId: number, scannerType: 'entry_staff' | 'volunteer_admin', modes: string[], scannerToken: string }`
- API URLs derived from `scannerId` not `eventId`
- Remove `_userRole` (no longer needed — scanner type IS the role)
- Add `_gearItems: GearItem[]` for Volunteer Admin
- Add `_volunteerGear: Record<number, VolunteerGear[]>` keyed by volunteer ID
- `canConfirmArrival`: `scannerType === 'entry_staff'` OR modes includes `'checkin'`
- `canPickupGear`: modes includes `'gear_pickup'`
- New method: `selectGearState(volunteerGearId: number, state: string)` — online-only fetch to gear-pickup endpoint
- Remove CSRF token usage (API uses Bearer token, not session cookies)
- Add `_scannerToken: string` — sent as `X-Scanner-Token` header on all API calls
- IDB calls: replace `eventId` with `scannerId` throughout

### 10.8 New: `gear-pickup.ts`

**File:** `resources/js/scanner/gear-pickup.ts`

Handles the online-only gear pickup flow for Volunteer Admin scanner:

```ts
export interface GearPickupRequest {
    volunteer_gear_id: number;
    state?: string;        // for size_selection type
    quantity?: number;     // for quantity type
}

export async function recordGearPickup(
    scannerId: number,
    scannerToken: string,
    request: GearPickupRequest
): Promise<{ success: boolean; error?: string }>;
```

Sends `POST /api/scanner/{scannerId}/gear-pickup` with `X-Scanner-Token` header.

---

## 11. Service Worker (`public/sw.js`) — Updated

Changes from current:
- API pattern match: replace `/scanner/api/` with `/api/scanner/`
- This is the only change needed; strategy (network-first for API, cache-first for static) remains identical

```js
// Line 38 change:
if (url.pathname.startsWith('/api/scanner/')) {
    event.respondWith(networkFirst(event.request));
    return;
}
```

---

## 12. Issue #48: Rename Volunteer Tab

**Before implementing:** Check the current volunteer tab label in the sidebar and event navigation. Based on M9 implementation notes, the sidebar link was updated to use project-based access checks. Verify the actual displayed text:

- `resources/views/components/sidebar.blade.php` (or equivalent) — check the scanner/volunteer nav link text
- `resources/views/livewire/events/` — check tab labels for the volunteer list page

**If already "Volunteers":** Issue #48 is already resolved as a side-effect of prior work. Close with a note.
**If still "Shift Attendees" or old label:** Update the Blade text in the navigation component.

---

## 13. Tests

### 13.1 Unit Tests

**`ProjectScannerTest`** — `tests/Unit/Models/ProjectScannerTest.php`
- `isActive()` returns true when within window
- `isActive()` returns false before window starts
- `isActive()` returns false after window ends
- `getStatusAttribute()` returns `scheduled`, `active`, `expired` correctly
- `hasMode()` returns true/false for mode presence
- `scopeActive()` filters correctly
- `scopeWindowOpensSoon()` returns only scanners opening within the given window

**`CreateProjectScannerTest`** — `tests/Unit/Actions/CreateProjectScannerTest.php`
- Creates scanner with correct columns
- Generates bcrypt-hashed `auth_code`
- Generates unique `scanner_token` (64 chars hex)
- Returns raw auth code as virtual attribute (not persisted as plaintext)
- `ends_at` must be after `starts_at`

**`AuthenticateScannerTest`** — `tests/Unit/Actions/AuthenticateScannerTest.php`
- Returns `true` for correct code within window
- Returns `false` for wrong code
- Returns `false` for expired window
- Returns `false` for scheduled (not yet started) window

**`SendScannerLinksTest`** — `tests/Unit/Actions/SendScannerLinksTest.php`
- Dispatches one `SendScannerLinksJob` per assignee
- Updates `link_sent_at` on assignees
- Uses `Queue::fake()`

### 13.2 Feature Tests

**`ScannerAuthTest`** — `tests/Feature/Livewire/ScannerAuthTest.php`
- Renders ScannerAuth page for valid `scanner_token`
- Returns 404 for unknown `scanner_token`
- Shows "window closed" when scanner is expired
- Successful auth code → session contains `scanner_id` → redirects to scanner app
- Wrong code → shows error, stays on auth page
- Already authenticated (session set) → skips to app

**`ScannerAppTest`** — `tests/Feature/Livewire/ScannerAppTest.php`
- Renders ScannerApp for authenticated scanner session
- Redirects unauthenticated request to ScannerAuth
- Redirects expired scanner to ScannerAuth with error
- Correct `dataUrl`, `syncUrl`, `gearPickupUrl` computed values
- Entry Staff type: no gear pickup URL exposed
- Volunteer Admin type: gear pickup URL exposed

**`ScannerManagementTest`** — `tests/Feature/Livewire/Projects/ScannerManagementTest.php`
- Org Organizer can view the page
- Project Organizer can view the page
- Non-member gets 403
- Create scanner → scanner appears in list, raw auth code shown
- Edit scanner → updates fields
- Delete scanner → scanner removed
- Send links → `SendScannerLinksJob` dispatched per assignee
- Add assignee → appears in list
- Remove assignee → removed from list

**`ScannerDataControllerTest`** — `tests/Feature/Http/ScannerDataControllerTest.php`
- `GET /api/scanner/{id}/data` with valid token → 200 + scanner data
- `GET /api/scanner/{id}/data` with expired scanner token → 401
- `GET /api/scanner/{id}/data` without token → 401
- `POST /api/scanner/{id}/sync` with valid token → processes arrivals → 200
- `POST /api/scanner/{id}/sync` for VA scanner type → 403 (entry staff only)
- `POST /api/scanner/{id}/gear-pickup` with valid VA token → records gear state → 200
- `POST /api/scanner/{id}/gear-pickup` for entry staff scanner → 403

**`ScannerApiMiddlewareTest`** — `tests/Feature/Http/Middleware/ScannerApiMiddlewareTest.php`
- Valid token within window → passes through
- Valid token but window not started → 401
- Valid token but window expired → 401
- Missing token header → 401
- Non-existent token → 401

**`SendScannerLinksCommandTest`** — `tests/Feature/Console/SendScannerLinksCommandTest.php`
- Dispatches jobs for scanners opening within 30 minutes
- Does not dispatch for scanners already sent
- Does not dispatch for expired scanners

### 13.3 TypeScript Tests (Vitest)

**`tests/js/scanner/idb-store.test.ts`** — Updated
- All existing tests: update `eventId` → `scannerId` parameter names
- Add: DB_VERSION 3 upgrade path from v2 (mock onupgradeneeded)

**`tests/js/scanner/alpine-scanner.test.ts`** — Updated
- Update config object: `scannerId` instead of `eventId`
- Update API URL assertions: `/api/scanner/{id}/` prefix
- Update `canConfirmArrival` and `canPickupGear` assertions (type-based, not role-based)

**`tests/js/scanner/gear-pickup.test.ts`** — New
- Sends correct headers including `X-Scanner-Token`
- Returns success on 200 response
- Returns error on non-200 response

### 13.4 Update Existing Tests

- `ScannerApiControllerTest` — **delete** (replaced by `ScannerDataControllerTest`)
- All tests referencing `scanner.data`, `scanner.sync`, `scanner.attendance-sync` route names → update to `scanner-api.data`, `scanner-api.sync`, `scanner-api.gear-pickup`
- Tests for `QrScanner`, `ManualLookup`, `ScannerEventSelect` Livewire components — **delete** (replaced by `ScannerApp`, `ScannerAuth`)

---

## 14. Implementation Phases

### Phase 1: Migrations + Models + Enums

1. `vendor/bin/sail artisan make:migration --no-down create_project_scanners_table`
2. Fill migration #19 as specified above
3. `vendor/bin/sail artisan make:migration --no-down create_project_scanner_assignees_table`
4. Fill migration #20
5. `vendor/bin/sail artisan migrate`
6. `vendor/bin/sail artisan make:model ProjectScanner` — fill model, factory, scopes, casts
7. `vendor/bin/sail artisan make:model ProjectScannerAssignee` — fill model, factory
8. Create `app/Enums/ScannerType.php` and `app/Enums/ScannerMode.php`
9. Add `scanners(): HasMany` to `Project` model
10. Run unit tests for models: `vendor/bin/sail artisan test --compact --filter=ProjectScannerTest`

**Deliverable:** Migrations run, models exist, basic unit tests pass.

### Phase 2: Actions

1. `vendor/bin/sail artisan make:class App/Actions/CreateProjectScanner`
2. `vendor/bin/sail artisan make:class App/Actions/UpdateProjectScanner`
3. `vendor/bin/sail artisan make:class App/Actions/DeleteProjectScanner`
4. `vendor/bin/sail artisan make:class App/Actions/SendScannerLinks`
5. `vendor/bin/sail artisan make:class App/Actions/AuthenticateScanner`
6. Add `manageScanners()` method to `ProjectPolicy`
7. Run action unit tests: `vendor/bin/sail artisan test --compact --filter=CreateProjectScannerTest,AuthenticateScannerTest,SendScannerLinksTest`

**Deliverable:** All 5 actions implemented and tested. Policy updated.

### Phase 3: Middleware + Auth Flow

1. `vendor/bin/sail artisan make:middleware ScannerAuthMiddleware`
2. `vendor/bin/sail artisan make:middleware ScannerApiMiddleware`
3. Register both in `bootstrap/app.php` alias map
4. Create `ScannerAuth` Livewire component + blade template
5. Register `GET /s/{scannerToken}` route
6. Run ScannerAuth feature tests: `vendor/bin/sail artisan test --compact --filter=ScannerAuthTest`

**Deliverable:** Auth flow works end-to-end. Auth code validates, session written, redirect to app.

### Phase 4: ScannerApp Livewire Component

1. `vendor/bin/sail artisan make:livewire ScannerApp`
2. Fill PHP component (mount, computed URLs, locked properties)
3. Register `GET /s/{scannerToken}/scan` route with `scanner-auth` middleware
4. Create blade template (dual type rendering, Alpine hooks)
5. Run ScannerApp feature tests: `vendor/bin/sail artisan test --compact --filter=ScannerAppTest`

**Deliverable:** ScannerApp renders correctly for both scanner types (no TS yet).

### Phase 5: Scanner API Controller

1. `vendor/bin/sail artisan make:controller ScannerDataController`
2. Implement `data()`, `sync()`, `gearPickup()` methods
3. Register API routes with `scanner-api` middleware
4. Create `SyncArrivalsRequest` for new API (or reuse/update existing)
5. Run API controller tests: `vendor/bin/sail artisan test --compact --filter=ScannerDataControllerTest,ScannerApiMiddlewareTest`

**Deliverable:** API endpoints work with scanner token auth.

### Phase 6: ScannerManagement Admin Component

1. `vendor/bin/sail artisan make:livewire Projects/ScannerManagement`
2. Fill PHP + blade with CRUD, assignee management, auth code reveal
3. Register admin route
4. Add "Scanners" tab/link to project navigation (alongside Gear, Members, etc.)
5. Run ScannerManagement tests: `vendor/bin/sail artisan test --compact --filter=ScannerManagementTest`

**Deliverable:** Admins can create, configure, and send links for scanners.

### Phase 7: Queue Job + Scheduled Command

1. `vendor/bin/sail artisan make:job SendScannerLinksJob`
2. Create `ScannerLinkMail` mailable
3. `vendor/bin/sail artisan make:command SendScannerLinksCommand`
4. Register in `routes/console.php`
5. Test: `vendor/bin/sail artisan test --compact --filter=SendScannerLinksCommandTest,SendScannerLinksTest`

**Deliverable:** Links sent automatically 30min before window opens.

### Phase 8: TypeScript Rewrite

1. Update `types.ts` — add gear types, update `ScannerData` shape
2. Update `idb-store.ts` — bump DB_VERSION to 3, switch to `scannerId` keys
3. Update `sync.ts` — new URLs, scanner token header
4. Rewrite `alpine-scanner.ts` — new config, gear support, token auth
5. Create `gear-pickup.ts` — online-only gear endpoint
6. Update `public/sw.js` — new API path pattern
7. Run TS tests: `vendor/bin/sail npm run test` (Vitest)
8. Run build: `vendor/bin/sail npm run build`

**Deliverable:** TypeScript compiles, Vitest passes, scanner works end-to-end in browser.

### Phase 9: Remove Old Scanner Code

1. Delete `app/Livewire/Scanner/QrScanner.php`
2. Delete `app/Livewire/Scanner/ManualLookup.php`
3. Delete `app/Livewire/Scanner/ScannerEventSelect.php`
4. Delete `app/Http/Controllers/ScannerApiController.php`
5. Remove old scanner routes from `routes/web.php`
6. Delete old scanner tests (ScannerApiControllerTest, QrScannerTest, ManualLookupTest, ScannerEventSelectTest)
7. Check and fix sidebar scanner link (remove old route reference)

**Deliverable:** Codebase clean. No dead code. All tests pass.

### Phase 10: Issue #48 + Final Cleanup

1. Check volunteer tab label per section 12 above
2. Fix if needed (likely a one-line Blade change)
3. Run full test suite: `vendor/bin/sail artisan test --compact`
4. Run Pint: `vendor/bin/sail bin pint --dirty --format agent`
5. Verify `migrate:fresh --seed` clean

**Deliverable:** All tests pass, Pint passes, seeder works, #48 resolved.

---

## 15. Self-Review Checklist

### Pass 1: Laravel Best Practices
- [x] `casts()` method (not property) specified for both new models
- [x] Business logic in Actions (`CreateProjectScanner`, `AuthenticateScanner`, `SendScannerLinks`) — not in Livewire
- [x] `ScannerManagement` form validation happens in Livewire action methods via `$this->validate()`
- [x] Policies cover access: `ProjectPolicy::manageScanners` for admin UI; middleware for scanner access
- [x] Routes use middleware: `scanner-auth` for ScannerApp, `scanner-api` for API routes

### Pass 2: Livewire v4 Correctness
- [x] No `.defer` syntax anywhere
- [x] `wire:model.live` only on auth code input (real-time feedback for 6-digit input)
- [x] No deep Livewire nesting — `ScannerApp` uses Alpine exclusively for interactive state
- [x] `#[Locked]` on `$scannerId`, `$projectId`, `$scannerType` in ScannerApp
- [x] `#[Locked]` on `$projectId` in ScannerManagement
- [x] Route uses `Route::livewire()` for full-page components
- [x] `@island` not needed — Alpine drives all scanner interactivity

### Pass 3: Clean Architecture
- [x] Architecture right-sized — standard Laravel structure, no DDD
- [x] Single responsibility: `AuthenticateScanner` validates only; session writing is caller's responsibility
- [x] Props down, events up — Alpine dispatches in scanner, Livewire handles admin CRUD
- [x] Data classification assigned to every column (`auth_code` and `scanner_token` as confidential, `email` as PII)
- [x] `auth_code` is bcrypt-hashed (never stored plaintext); `scanner_token` is in `$hidden` on model

### Pass 4: Testability
- [x] All Actions unit-testable without HTTP (`AuthenticateScanner` just needs a `ProjectScanner` instance)
- [x] Livewire components testable with `Livewire::test()`
- [x] No static calls preventing mocking — all actions are injected
- [x] Factories defined for `ProjectScanner` and `ProjectScannerAssignee`
- [x] `ScannerApiMiddleware` sets scanner on request attributes — testable via `actingAs` equivalent for scanner token
