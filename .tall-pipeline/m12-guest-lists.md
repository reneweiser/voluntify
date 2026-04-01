# Milestone: m12-guest-lists — Guest Lists (#90)

**Features:** Guest list CRUD, QR generation, grouped emails, scanner integration, guest gear tracking
**Issues:** #90
**Dependencies:** m8-project-scoped (complete), m11-scanner (complete)

## Plan
- **Status:** complete
- **Gate summary:** 4 migrations, 4 new models, 1 new enum, 10 actions, 1 job, 1 mailable, 2 new Livewire components, 1 controller extension, 5 TS type additions, 2 IDB store additions, Alpine scanner extension. 4-pass self-review passed. See Decisions table.

---

## 1. Database Schema

### 1.1 ERD

```
Project 1──* GuestList
                ├── project_id (FK → projects)
                ├── scanner_id (FK → project_scanners, EntryStaff type required)
                └── gear_items (json — array of project_gear_item_ids)

GuestList 1──* GuestGroup
                ├── guest_list_id (FK → guest_lists, cascadeOnDelete)
                └── label, guest_count

GuestGroup 1──* GuestEntry
                ├── guest_group_id (FK → guest_groups, cascadeOnDelete)
                ├── qr_token (nullable, unique — generated on confirmation)
                └── checked_in_at, checked_in_by

GuestEntry 1──* GuestEntryGear
                ├── guest_entry_id (FK → guest_entries, cascadeOnDelete)
                └── project_gear_item_id (FK → project_gear_items)
```

### 1.2 Migration #21: create_guest_lists_table

```php
Schema::create('guest_lists', function (Blueprint $table) {
    $table->id();
    $table->foreignId('project_id')->constrained()->cascadeOnDelete();
    $table->foreignId('scanner_id')->constrained('project_scanners')->restrictOnDelete();
    $table->string('name');
    $table->string('status')->default('draft'); // 'draft' | 'confirmed'
    $table->dateTime('confirmed_at')->nullable();
    $table->json('gear_items')->nullable(); // array of project_gear_item_ids
    $table->timestamps();

    $table->index(['project_id', 'status']);
});
```

### 1.3 Migration #22: create_guest_groups_table

```php
Schema::create('guest_groups', function (Blueprint $table) {
    $table->id();
    $table->foreignId('guest_list_id')->constrained()->cascadeOnDelete();
    $table->string('label');
    $table->unsignedInteger('guest_count');
    $table->timestamps();

    $table->index('guest_list_id');
});
```

### 1.4 Migration #23: create_guest_entries_table

```php
Schema::create('guest_entries', function (Blueprint $table) {
    $table->id();
    $table->foreignId('guest_group_id')->constrained()->cascadeOnDelete();
    $table->unsignedInteger('number');
    $table->string('name')->nullable();
    $table->string('email')->nullable();
    $table->string('qr_token', 64)->nullable()->unique();
    $table->dateTime('checked_in_at')->nullable();
    $table->unsignedBigInteger('checked_in_by')->nullable();
    $table->timestamps();

    $table->index('guest_group_id');
    $table->index('qr_token');
    $table->foreign('checked_in_by')->references('id')->on('users')->nullOnDelete();
});
```

**Data classification:**
- `name`, `email`: **confidential** (PII)
- `qr_token`: **confidential** (authentication credential)
- All other columns: **internal**

### 1.5 Migration #24: create_guest_entry_gear_table

```php
Schema::create('guest_entry_gear', function (Blueprint $table) {
    $table->id();
    $table->foreignId('guest_entry_id')->constrained()->cascadeOnDelete();
    $table->foreignId('project_gear_item_id')->constrained()->cascadeOnDelete();
    $table->unsignedInteger('quantity')->default(1);
    $table->unsignedInteger('picked_up_count')->default(0);
    $table->string('selection')->nullable(); // Typ-1: size choice (e.g. "M", "L")
    $table->string('status')->nullable();    // Typ-1: state from available_states
    $table->timestamps();

    $table->unique(['guest_entry_id', 'project_gear_item_id']);
});
```

### 1.6 Migration Order

All 4 migrations must run in sequence (FK dependencies):
1. `create_guest_lists_table` (depends on: projects, project_scanners)
2. `create_guest_groups_table` (depends on: guest_lists)
3. `create_guest_entries_table` (depends on: guest_groups, users)
4. `create_guest_entry_gear_table` (depends on: guest_entries, project_gear_items)

Forward-only: no `down()` methods.

---

## 2. Models

### 2.1 GuestList

```php
class GuestList extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id', 'scanner_id', 'name', 'status', 'confirmed_at', 'gear_items',
    ];

    protected function casts(): array
    {
        return [
            'status' => GuestListStatus::class,
            'confirmed_at' => 'datetime',
            'gear_items' => 'array',
        ];
    }

    // Relationships
    public function project(): BelongsTo;       // → Project
    public function scanner(): BelongsTo;        // → ProjectScanner (FK: scanner_id)
    public function groups(): HasMany;           // → GuestGroup
    public function entries(): HasManyThrough;   // → GuestEntry through GuestGroup

    // Scopes
    public function scopeConfirmed(Builder $query): Builder;  // WHERE status = 'confirmed'
    public function scopeForProject(Builder $query, int $projectId): Builder;

    // Computed
    public function isConfirmed(): bool;
    public function isDraft(): bool;
}
```

**Soft deletes:** No. Guest lists are explicit entities managed by Organizer. Deletion removes associated groups/entries via cascade.

### 2.2 GuestGroup

```php
class GuestGroup extends Model
{
    use HasFactory;

    protected $fillable = [
        'guest_list_id', 'label', 'guest_count',
    ];

    protected function casts(): array
    {
        return [
            'guest_count' => 'integer',
        ];
    }

    // Relationships
    public function guestList(): BelongsTo;  // → GuestList
    public function entries(): HasMany;       // → GuestEntry

    // Computed
    public function checkedInCount(): int;    // entries where checked_in_at is not null
}
```

### 2.3 GuestEntry

```php
class GuestEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'guest_group_id', 'number', 'name', 'email', 'qr_token',
        'checked_in_at', 'checked_in_by',
    ];

    protected function casts(): array
    {
        return [
            'number' => 'integer',
            'checked_in_at' => 'datetime',
        ];
    }

    // Relationships
    public function group(): BelongsTo;        // → GuestGroup
    public function gear(): HasMany;           // → GuestEntryGear
    public function checkedInByUser(): BelongsTo; // → User (FK: checked_in_by)

    // Computed
    public function isCheckedIn(): bool;       // checked_in_at !== null
    public function displayLabel(): string;    // "GroupLabel N/Total" e.g. "DJ Soundwave 1/3"

    // QR
    public function qrCodeSvg(): string;       // uses QrCodeGenerator with qr_token as data
}
```

### 2.4 GuestEntryGear

```php
class GuestEntryGear extends Model
{
    use HasFactory;

    protected $table = 'guest_entry_gear';

    protected $fillable = [
        'guest_entry_id', 'project_gear_item_id', 'quantity',
        'picked_up_count', 'selection', 'status',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'picked_up_count' => 'integer',
        ];
    }

    // Relationships
    public function entry(): BelongsTo;     // → GuestEntry
    public function gearItem(): BelongsTo;  // → ProjectGearItem
}
```

### 2.5 Model Modifications

**Project** (existing):
- Add `guestLists(): HasMany` relationship.

**ProjectScanner** (existing):
- Add `guestLists(): HasMany` relationship.
- Add `guestEntries()` accessor: returns GuestEntry records for all confirmed guest lists linked to this scanner (for data endpoint).

---

## 3. Enums

### 3.1 GuestListStatus

```php
enum GuestListStatus: string
{
    case Draft = 'draft';
    case Confirmed = 'confirmed';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Confirmed => 'Confirmed',
        };
    }
}
```

No other new enums needed. `GearItemType` (SizeSelection, Quantity) from M8 is reused for guest gear logic.

---

## 4. Authorization Model

### 4.1 Policy Extension

Guest lists are managed through the existing `ProjectPolicy`. Add one new method:

```php
// ProjectPolicy
public function manageGuestLists(User $user, Project $project): bool
{
    return $user->projectRoleFor($project) === StaffRole::Organizer;
}
```

**Rationale:** Only Organizers create/edit/confirm/delete guest lists (matches design spec). Scanner operators interact with guest data through the scanner only, never the admin UI.

### 4.2 Access Rules Summary

| Action | Allowed Roles |
|---|---|
| View guest lists | Organizer (via manageGuestLists) |
| Create/edit/delete guest list | Organizer |
| Confirm guest list | Organizer |
| Add/remove groups/entries | Organizer |
| Scan guest QR | Entry Staff Scanner (via scanner auth) |
| Guest gear pickup | Volunteer Admin Scanner (via scanner auth) |

### 4.3 Data Scoping

- Guest lists are scoped by `project_id` and further scoped by `currentOrganization()` in Livewire components.
- Scanner API scopes guest data by `scanner_id` on the ProjectScanner (confirmed guest lists linked to that scanner).
- No global scopes needed; scoping is explicit in queries.

---

## 5. Component Architecture

### 5.1 Page Map

```
Route                                  Component                Middleware              Gate
GET /projects/{projectId}/guest-lists  GuestListIndex           auth,verified,resolve-org  manageGuestLists
GET /projects/{projectId}/guest-lists/{guestListId}  GuestListShow  auth,verified,resolve-org  manageGuestLists
```

### 5.2 Component Hierarchy

#### GuestListIndex (full-page)

```
GuestListIndex (full-page, #[Locked] projectId)
├── Header: "Guest Lists" + "New Guest List" button
├── GuestList cards (Blade loop — no nesting)
│   └── Each card: name, status badge, scanner name, group count, entry count, checked-in count
└── Create modal (Flux modal — inline in component)
    ├── Name input
    ├── Scanner dropdown (filtered: Entry Staff type only)
    └── Gear items multi-select (from project gear items)
```

**Properties:**
- `#[Locked] public int $projectId`
- `public bool $showCreateModal = false`
- Form fields: `name`, `scannerId`, `gearItemIds[]`
- `#[Computed] guestLists()` — GuestList::forProject with groups count, entries count
- `#[Computed] entryStaffScanners()` — ProjectScanner::where(type, EntryStaff)
- `#[Computed] projectGearItems()` — ProjectGearItem for the project

**Actions:** `createGuestList()`, `deleteGuestList(int $guestListId)`

#### GuestListShow (full-page)

```
GuestListShow (full-page, #[Locked] projectId + guestListId)
├── Header: name, status badge, "Confirm" button (if draft), "Edit" button
├── Summary bar: total groups, total entries, checked-in count
├── Groups section (inline in component)
│   ├── Add group form (label, guest_count)
│   └── Group cards (Blade loop)
│       ├── Group header: label + "X/Y checked in" + delete button
│       └── Entries table (Blade loop)
│           ├── Entry row: #, name, email, gear summary, QR status, check-in status
│           ├── Inline edit (Alpine x-data toggle)
│           └── Add entry button (if count < guest_count or after confirm)
└── Edit modal (Flux modal — name, scanner, gear items)
```

**Properties:**
- `#[Locked] public int $projectId`
- `#[Locked] public int $guestListId`
- `public bool $showEditModal = false`
- Group form: `newGroupLabel`, `newGroupCount`
- Entry form: `editingEntryId`, `entryName`, `entryEmail`, `entryGear[]`
- `#[Computed] guestList()` — with groups.entries.gear
- `#[Computed] entryStaffScanners()`
- `#[Computed] projectGearItems()`

**Actions:**
- `updateGuestList()` — name, scanner, gear items
- `confirmGuestList()` — generates QR tokens, dispatches email job
- `addGroup()` — creates GuestGroup with entries
- `removeGroup(int $groupId)`
- `addEntry(int $groupId)` — creates GuestEntry. If list is confirmed: generates QR + dispatches email (if email present) + shows success toast ("Entry added, invitation sent")
- `removeEntry(int $entryId)` — deletes entry (QR becomes invalid)
- `updateEntry(int $entryId)` — updates name/email/gear (QR stays valid)
- `assignGear(int $entryId, array $gearSelections)` — creates/updates GuestEntryGear records

### 5.3 Communication Patterns

| From | To | Mechanism | Name |
|---|---|---|---|
| GuestListIndex | GuestListShow | Redirect | `route('guest-lists.show')` |
| GuestListShow confirm | SendGuestInvitationsJob | Job dispatch | Queued per email group |

No cross-component events needed. Both components are independent full-page components.

### 5.4 Scanner Extensions (Existing Components)

**ScannerApp Livewire component** — no PHP changes needed. All scanner logic is in Alpine/TS.

**ScannerApp blade** (`scanner-app.blade.php`) — additions:
1. Entry Staff: Add "Gastliste" tab alongside existing volunteer display. Tab shows guest groups with entries, check-in status, and manual check-in buttons.
2. Entry Staff result panel: handle guest QR result states (green/yellow/red) with guest-specific messaging ("Gast -- DJ Soundwave 1/3").
3. Volunteer Admin: Show guest entries with gear in the volunteer/guest list (guests with gear only).

**ScannerDataController** — extension:
- `data()` endpoint: include `guest_entries` array in response (for confirmed guest lists linked to the scanner).
- New `guest-checkin` endpoint: `POST /api/scanner/{scannerId}/guest-checkin` for online guest check-in from scanner.
- New `guest-gear-pickup` endpoint: `POST /api/scanner/{scannerId}/guest-gear-pickup` for guest gear state changes.

---

## 6. Alpine.js State Plan

### 6.1 GuestListShow — Inline Entry Editing

```js
// Per entry row
x-data="{ editing: false }"
// Toggle edit mode, bind inputs to $wire properties
```

Minimal Alpine state. Form data is in Livewire properties; Alpine only controls the edit/view toggle.

### 6.2 Scanner Extensions

**Entry Staff scanner (`alpine-scanner.ts`):**
- New internal state: `_guestEntries: GuestEntry[]` — loaded from data endpoint
- New state: `_guestCheckins: GuestCheckin[]` — tracks which guests are checked in
- New method: `_onGuestQrDetected(qrToken: string)` — lookup by `qr_token` in `_guestEntries`
- New method: `confirmGuestCheckin(guestEntryId: number)` — POST to guest-checkin endpoint, add to outbox for offline
- Tab state: `activeTab: 'scanner' | 'volunteers' | 'guests'` — controls which panel is shown
- Guest search: `guestSearchQuery` string, filters `_guestEntries` by name/group label
- Manual check-in: `manualGuestCheckin(guestEntryId: number)` — same as confirmGuestCheckin but triggered from list

**Volunteer Admin scanner (`alpine-scanner.ts`):**
- `_guestEntries` loaded alongside volunteers
- Guest gear display alongside volunteer gear
- `selectGuestGearState(guestEntryGearId: number, state: string)` — POST to guest-gear-pickup endpoint
- `incrementGuestGearPickup(guestEntryGearId: number)` — POST to guest-gear-pickup for Typ-2 increment
- `selectGuestGearSelection(guestEntryGearId: number, selection: string)` — POST for Typ-1 size selection (direct in scanner, no portal)

### 6.3 $wire Integration

No `$wire.entangle()` needed. Scanner data is fetched via API (fetch), not Livewire properties. Admin Livewire components use standard `wire:model` and `wire:click`.

---

## 7. Actions Inventory

### 7.1 Guest List Management

| # | Action | Signature | Side Effects |
|---|---|---|---|
| 1 | `CreateGuestList` | `execute(Project $project, array $data): GuestList` | Creates guest list in draft status |
| 2 | `UpdateGuestList` | `execute(GuestList $guestList, array $data): GuestList` | Updates name, scanner, gear items |
| 3 | `ConfirmGuestList` | `execute(GuestList $guestList): GuestList` | Sets status=confirmed + confirmed_at, dispatches `ConfirmGuestListJob` (single job handles token generation + email dispatch). Livewire action returns immediately. |
| 4 | `DeleteGuestList` | `execute(GuestList $guestList): void` | Cascading delete (groups + entries + gear via DB cascade) |

**Scanner deletion guard:** The `scanner_id` FK uses `restrictOnDelete`. Attempting to delete a ProjectScanner that has guest lists will throw a DB constraint exception. The `DeleteProjectScanner` action (from M11) does not need modification — the DB constraint prevents the cascade naturally. The ScannerManagement UI should catch this exception and show "Cannot delete scanner — it has guest lists assigned."

### 7.2 Guest Group Management

| # | Action | Signature | Side Effects |
|---|---|---|---|
| 5 | `AddGuestGroup` | `execute(GuestList $guestList, string $label, int $guestCount): GuestGroup` | Creates group + N empty GuestEntry rows (numbered 1..guestCount) |
| 6 | `RemoveGuestGroup` | `execute(GuestGroup $group): void` | Deletes group (cascades entries + gear) |

### 7.3 Guest Entry Management

| # | Action | Signature | Side Effects |
|---|---|---|---|
| 7 | `AddGuestEntry` | `execute(GuestGroup $group, ?string $name, ?string $email, array $gearSelections = []): GuestEntry` | Creates entry with next number. If list is confirmed: generates qr_token + dispatches immediate email (if email present) |
| 8 | `RemoveGuestEntry` | `execute(GuestEntry $entry): void` | Deletes entry (QR becomes invalid). Does NOT renumber remaining entries. |
| 9 | `UpdateGuestEntry` | `execute(GuestEntry $entry, array $data): GuestEntry` | Updates name/email. QR token stays valid. Updates gear via nested array. |

### 7.4 Scanner Operations

| # | Action | Signature | Side Effects |
|---|---|---|---|
| 10 | `CheckInGuest` | `execute(GuestEntry $entry, ?int $checkedInBy = null): GuestEntry` | Sets checked_in_at + checked_in_by. Returns entry. Throws if already checked in. |

| 11 | `RecordGuestGearPickup` | `execute(GuestEntryGear $gear, array $data): GuestEntryGear` | Typ-1: sets selection + status. Typ-2: increments picked_up_count (capped at quantity). Online-only. |

**Why a separate action for guest gear (unlike the single-table model)?** Guest gear uses a simpler single-table model (`GuestEntryGear` with `picked_up_count`, `selection`, `status` inline) rather than the volunteer's two-table pattern (`volunteer_gear` + `volunteer_gear_pickups`). The reason: volunteers self-serve via portal (need audit trail of individual pickup events), while guests have no portal — the operator makes all decisions at the scanner. A single row per gear item with mutable state is sufficient. Despite the simpler model, the action class is extracted to maintain the project convention of all business logic in actions.

### 7.5 Action Method Convention

All actions use `execute()` (existing project convention), not `__invoke()` or `handle()`.

---

## 8. Queue & Events

### 8.1 Job Inventory

| Job | Queue | Timeout | Tries | Backoff | Idempotent? |
|---|---|---|---|---|---|
| `ConfirmGuestListJob` | `default` | 120s | 3 | 10,30,60 | Yes (checks status=confirmed, skips if tokens exist) |
| `SendGuestInvitationsJob` | `mail` | 60s | 3 | 10,30,60 | Yes (grouped by email, checks qr_token exists) |

### 8.2 ConfirmGuestListJob (Orchestrator)

**Trigger:** `ConfirmGuestList` action dispatches this single job. Livewire action returns immediately after dispatch.

**Logic:**
1. Receives: `GuestList $guestList`
2. Generates all `qr_token` values in PHP (`bin2hex(random_bytes(32))` per entry), then bulk-updates entries in a single query via `upsert()` within a DB transaction
3. Collects unique email addresses across all entries
4. Dispatches one `SendGuestInvitationsJob` per unique email

### 8.3 SendGuestInvitationsJob

**Trigger:** `ConfirmGuestListJob` dispatches one per unique email. Also dispatched by `AddGuestEntry` when adding to a confirmed list (single entry, immediate).

**Logic:**
1. Receives: `GuestList $guestList`, `string $email`
2. Loads all GuestEntry records for this list where email matches
3. Generates QR code SVGs for each entry
4. Sends single `GuestInvitationMail` with all QR codes

**Grouping:** Same email across multiple entries in the list receives ONE email with ALL their QR codes.

### 8.3 GuestInvitationMail

```php
class GuestInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public GuestList $guestList,
        /** @var Collection<int, GuestEntry> */
        public Collection $entries,
    ) {}

    public function envelope(): Envelope;  // Subject: "Your Guest Pass — {guestList.name}"
    public function content(): Content;    // markdown: 'mail.guest-invitation'
}
```

**Template:** Shows guest list name, project name, and for each entry: entry number, group label, QR code SVG inline. No per-org SMTP routing (guests are not volunteers — use default mailer).

### 8.4 Event-Listener Map

No domain events needed for M12. Guest list operations are synchronous admin actions. The only async work is email sending via the job.

---

## 9. API Design (Scanner API Extensions)

### 9.1 Existing Endpoint Extension

**`GET /api/scanner/{scannerId}/data`** — add `guest_entries` to response:

```json
{
    "scanner": { ... },
    "events": [ ... ],
    "volunteers": [ ... ],
    "arrivals": [ ... ],
    "keys": { ... },
    "guest_entries": [
        {
            "id": 1,
            "guest_group_id": 1,
            "group_label": "DJ Soundwave",
            "group_guest_count": 3,
            "number": 1,
            "name": "DJ Soundwave",
            "email": "dj@example.com",
            "qr_token": "abc123...",
            "checked_in_at": null,
            "checked_in_by": null,
            "gear": [
                {
                    "id": 1,
                    "guest_entry_id": 1,
                    "project_gear_item_id": 5,
                    "gear_item_name": "T-Shirt",
                    "gear_item_type": "size_selection",
                    "available_sizes": ["S", "M", "L", "XL"],
                    "available_states": ["ordered", "received", "issued"],
                    "quantity": 1,
                    "picked_up_count": 0,
                    "selection": null,
                    "status": null
                }
            ]
        }
    ]
}
```

**Scoping:**
- **Entry Staff:** Only entries from confirmed guest lists where `guest_lists.scanner_id = $scanner->id`. Full payload including `qr_token`.
- **Volunteer Admin:** Guest entries from all confirmed guest lists in the same project, filtered by `whereHas('gear')` (only entries with gear assigned). `qr_token` is **stripped** from the payload (Volunteer Admin never scans QR — they only handle gear pickup by ID).

**Security note (D11):** `qr_token` is a confidential credential. Entry Staff scanners need it for offline QR matching. Volunteer Admin scanners do not — the token is omitted from their payload to reduce exposure. The scanner device is already trusted (authenticated via scanner token), so Entry Staff having tokens in IDB is an accepted risk.

### 9.2 New Endpoint: Guest Check-In

```
POST /api/scanner/{scannerId}/guest-checkin
```

**Request:**
```json
{
    "guest_entry_id": 1
}
```

**Response:**
```json
{
    "success": true,
    "guest_entry": { "id": 1, "checked_in_at": "2026-07-01 19:32:00", ... },
    "already_checked_in": false
}
```

**Auth:** `scanner-api` middleware (X-Scanner-Token). Scanner type must be `EntryStaff`. Guest entry must belong to a confirmed guest list linked to this scanner.

### 9.3 New Endpoint: Guest Gear Pickup

```
POST /api/scanner/{scannerId}/guest-gear-pickup
```

**Request:**
```json
{
    "guest_entry_gear_id": 1,
    "selection": "L",
    "status": "issued",
    "quantity": 1
}
```

**Response:**
```json
{
    "success": true,
    "guest_entry_gear": { ... }
}
```

**Auth:** `scanner-api` middleware. Scanner type must be `VolunteerAdmin`. Guest entry gear must belong to a guest entry in the same project.

**Logic:**
- Typ-1 (SizeSelection): set `selection` and/or `status` on the GuestEntryGear record
- Typ-2 (Quantity): increment `picked_up_count` by `quantity` (capped at `quantity` field)
- Online-only (consistent with D4/D10 from M11)

### 9.4 New Endpoint: Guest Check-In Sync (Offline Support)

```
POST /api/scanner/{scannerId}/guest-sync
```

**Request:**
```json
{
    "guest_checkins": [
        { "guest_entry_id": 1, "checked_in_at": "2026-07-01 19:32:00" }
    ]
}
```

**Response:**
```json
{
    "guest_entries": [ ... ]
}
```

**Rationale:** Entry Staff scanners support offline mode. Guest check-ins must be buffered in outbox just like volunteer arrivals. This endpoint batch-syncs buffered guest check-ins.

---

## 10. TypeScript Changes

### 10.1 New Types (`types.ts`)

```typescript
export interface GuestEntry {
    id: number;
    guest_group_id: number;
    group_label: string;
    group_guest_count: number;
    number: number;
    name: string | null;
    email: string | null;
    qr_token: string | null;
    checked_in_at: string | null;
    checked_in_by: number | null;
    gear: GuestEntryGearItem[];
}

export interface GuestEntryGearItem {
    id: number;
    guest_entry_id: number;
    project_gear_item_id: number;
    gear_item_name: string;
    gear_item_type: 'size_selection' | 'quantity';
    available_sizes: string[] | null;
    available_states: string[] | null;
    quantity: number;
    picked_up_count: number;
    selection: string | null;
    status: string | null;
}
```

### 10.2 OutboxEntry Extension

Add `'guest_checkin'` to the `type` union:

```typescript
export interface OutboxEntry {
    id?: number;
    type: 'arrival' | 'attendance' | 'guest_checkin';
    // ... existing fields ...
    guest_entry_id?: number;  // for guest_checkin type
}
```

### 10.3 IDB Store Changes

**DB_VERSION bump to 4.** The `onupgradeneeded` handler must be refactored to use `event.oldVersion` branching instead of dropping all stores unconditionally:

```typescript
// In onupgradeneeded:
const oldVersion = event.oldVersion;

if (oldVersion < 3) {
    // Full rebuild for versions before 3 (existing behavior)
    for (const name of db.objectStoreNames) {
        db.deleteObjectStore(name);
    }
    // Create all stores: volunteers, outbox, keys, guest_entries
}

if (oldVersion >= 3 && oldVersion < 4) {
    // Additive: only create the new guest_entries store
    const guestStore = db.createObjectStore('guest_entries', { keyPath: ['scannerId', 'id'] });
    guestStore.createIndex('byScanner', 'scannerId', { unique: false });
}
```

New functions:
- `storeGuestEntries(scannerId: number, entries: GuestEntry[]): Promise<void>`
- `getGuestEntries(scannerId: number): Promise<GuestEntry[]>`
- `searchGuestEntries(scannerId: number, query: string): Promise<GuestEntry[]>` — searches name + group_label

### 10.4 Alpine Scanner Extensions (`alpine-scanner.ts`)

**New internal state:**
```typescript
_guestEntries: [] as GuestEntry[],
_guestCheckins: [] as { guest_entry_id: number; checked_in_at: string }[],
activeTab: 'scanner' as 'scanner' | 'volunteers' | 'guests',
guestSearchQuery: '' as string,
```

**New methods:**
```typescript
// Entry Staff only
_onQrDetected(data: string): // Extended to check guest QR tokens (simple string match, not JWT)
confirmGuestCheckin(guestEntryId: number): // POST to guest-checkin or add to outbox
manualGuestCheckin(guestEntryId: number): // Same as confirmGuestCheckin, different trigger

// Volunteer Admin only
selectGuestGearState(guestEntryGearId: number, state: string): // POST to guest-gear-pickup
selectGuestGearSelection(guestEntryGearId: number, selection: string): // POST, Typ-1 direct
incrementGuestGearPickup(guestEntryGearId: number): // POST, Typ-2 increment
```

**QR detection change:** The `_onQrDetected` method currently assumes all QR codes are JWTs. Must be extended:
1. First, try JWT validation (existing volunteer flow).
2. If JWT validation fails, check if the scanned data matches a `qr_token` in `_guestEntries` (simple string lookup).
3. If match found, show guest result instead of volunteer result.

This dual-path detection lets guest QR tokens (random strings) coexist with volunteer JWT tokens.

### 10.5 Sync Module Extension (`sync.ts`)

**Critical: Replace `clearOutbox()` with selective deletion.** The current `syncOutbox()` calls `clearOutbox()` after syncing arrivals, which deletes ALL outbox entries including unsynced guest check-ins. Must change to:
1. Sync arrivals → delete only synced arrival entries by ID
2. Sync guest check-ins → delete only synced guest_checkin entries by ID
3. Never clear the entire outbox store at once

```typescript
// Sync arrivals
const arrivals = entries.filter((e) => e.type === 'arrival');
if (arrivals.length > 0) {
    await postArrivals(arrivals);
    await deleteOutboxEntries(arrivals.map(e => e.id)); // selective delete
}

// Sync guest check-ins
const guestCheckins = entries.filter((e) => e.type === 'guest_checkin');
if (guestCheckins.length > 0) {
    // POST to guest-sync endpoint
    await deleteOutboxEntries(guestCheckins.map(e => e.id)); // selective delete
}
```

### 10.6 Service Worker (`sw.js`)

Add guest API endpoints to network-first pattern:
- `/api/scanner/*/guest-checkin`
- `/api/scanner/*/guest-gear-pickup`
- `/api/scanner/*/guest-sync`

---

## 11. Scanner Blade Changes

### 11.1 Entry Staff Scanner (`scanner-app.blade.php`)

Add tab navigation and guest panel:

```html
{{-- Tab bar (Entry Staff only) --}}
<nav x-show="scannerType === 'entry_staff'" class="flex border-b border-zinc-700">
    <button @click="activeTab = 'scanner'" :class="activeTab === 'scanner' ? 'border-emerald-500' : ''">
        Scanner
    </button>
    <button @click="activeTab = 'volunteers'" :class="...">
        Volunteers
    </button>
    <button @click="activeTab = 'guests'" :class="...">
        Gastliste
    </button>
</nav>

{{-- Guest tab content --}}
<div x-show="activeTab === 'guests'" x-cloak>
    <input type="text" x-model="guestSearchQuery" placeholder="Search guests...">
    <template x-for="group in filteredGuestGroups" :key="group.label">
        {{-- Group header with check-in count --}}
        <template x-for="entry in group.entries" :key="entry.id">
            {{-- Entry row with manual check-in button --}}
        </template>
    </template>
</div>
```

### 11.2 Entry Staff Result Panel

Extend the result panel to handle guest results:

```html
{{-- Guest result (alongside existing volunteer result) --}}
<div x-show="guestResult" x-transition role="alert" aria-live="assertive">
    <p x-text="resultMessage"></p>
    <button @click="confirmGuestCheckin(guestResult.id)">Check In</button>
</div>
```

### 11.3 Volunteer Admin Scanner

Add guest entries to the volunteer/guest list display. Guests with gear appear alongside volunteers. Guest entries are visually distinguished with a "Guest" badge. Typ-1 gear shows a dropdown for direct selection (unlike volunteers who self-select in portal).

---

## 12. Factories

### 12.1 GuestListFactory

```php
public function definition(): array
{
    return [
        'project_id' => Project::factory(),
        'scanner_id' => ProjectScanner::factory(),
        'name' => fake()->words(3, true),
        'status' => GuestListStatus::Draft,
        'confirmed_at' => null,
        'gear_items' => null,
    ];
}

public function confirmed(): static
{
    return $this->state(fn () => [
        'status' => GuestListStatus::Confirmed,
        'confirmed_at' => now(),
    ]);
}
```

### 12.2 GuestGroupFactory

```php
public function definition(): array
{
    return [
        'guest_list_id' => GuestList::factory(),
        'label' => fake()->name(),
        'guest_count' => fake()->numberBetween(1, 5),
    ];
}
```

### 12.3 GuestEntryFactory

```php
public function definition(): array
{
    return [
        'guest_group_id' => GuestGroup::factory(),
        'number' => 1,
        'name' => fake()->optional(0.7)->name(),
        'email' => fake()->optional(0.6)->safeEmail(),
        'qr_token' => null,
        'checked_in_at' => null,
        'checked_in_by' => null,
    ];
}

public function withQrToken(): static
{
    return $this->state(fn () => [
        'qr_token' => bin2hex(random_bytes(32)),
    ]);
}

public function checkedIn(): static
{
    return $this->state(fn () => [
        'checked_in_at' => now(),
    ]);
}
```

### 12.4 GuestEntryGearFactory

```php
public function definition(): array
{
    return [
        'guest_entry_id' => GuestEntry::factory(),
        'project_gear_item_id' => ProjectGearItem::factory(),
        'quantity' => 1,
        'picked_up_count' => 0,
        'selection' => null,
        'status' => null,
    ];
}
```

---

## 13. Testing Strategy

### 13.1 Test Pyramid

**Unit tests (Actions):** ~25 tests
- `CreateGuestListTest` (3): creates draft, validates scanner type is EntryStaff, sets gear_items
- `UpdateGuestListTest` (3): updates name/scanner/gear, rejects invalid scanner type
- `ConfirmGuestListTest` (5): generates qr_tokens for all entries, sets status + confirmed_at, dispatches jobs grouped by email, handles entries without email, rejects already-confirmed list
- `DeleteGuestListTest` (2): cascades, handles empty list
- `AddGuestGroupTest` (3): creates group + N entries, validates guest_count >= 1, auto-numbers entries
- `RemoveGuestGroupTest` (2): cascades entries, works on confirmed list
- `AddGuestEntryTest` (4): adds to draft (no QR), adds to confirmed (generates QR + sends email), handles no-email entry, assigns gear
- `RemoveGuestEntryTest` (2): deletes entry, does not renumber remaining
- `UpdateGuestEntryTest` (3): updates name/email (QR stays), updates gear
- `CheckInGuestTest` (3): sets checked_in_at, rejects already checked in, accepts nullable checked_in_by

**Feature tests (Livewire):** ~20 tests
- `GuestListIndexTest` (5): displays lists, create form, authorization, delete confirmation, empty state
- `GuestListShowTest` (10): displays groups/entries, add group, remove group, add entry, edit entry, remove entry, confirm flow, gear assignment, post-confirm add (immediate email), post-confirm remove
- `ScannerDataControllerGuestTest` (5): includes guest entries for Entry Staff, includes guest gear for Volunteer Admin, excludes draft lists, scopes to scanner, handles no guest lists

**Feature tests (Scanner API):** ~8 tests
- `GuestCheckinApiTest` (3): successful check-in, duplicate check-in returns already_checked_in, rejects wrong scanner
- `GuestGearPickupApiTest` (3): Typ-1 selection + status, Typ-2 increment, rejects exceeding quantity
- `GuestSyncApiTest` (2): batch sync guest check-ins, handles empty array

**TypeScript tests (Vitest):** ~6 tests
- `idb-store.test.ts`: storeGuestEntries, getGuestEntries, searchGuestEntries
- `alpine-scanner.test.ts`: QR dual-path detection (JWT vs guest token), guest check-in flow

**Total:** ~59 new tests

### 13.2 Key Test Scenarios

1. **Full lifecycle:** Create list → add groups → add entries with gear → confirm → QR tokens generated → emails grouped → scan QR → check in → gear pickup
2. **Post-confirm mutation:** Add entry to confirmed list → QR generated immediately → email sent → scan works
3. **Post-confirm removal:** Remove entry from confirmed list → QR becomes invalid → scanner shows red
4. **Email grouping:** Two entries with same email → one SendGuestInvitationsJob → one email with two QR codes
5. **Scanner scoping:** Entry Staff scanner only sees guest lists assigned to it, not other scanners
6. **Gear Typ-1 direct selection:** Volunteer Admin scanner sets selection + status on guest gear (no portal flow)
7. **Offline guest check-in:** Guest check-in added to outbox → synced when online → server records check-in

---

## 14. Implementation Phases

### Phase 1: Schema + Models + Enum + Factories (foundation)

**Tasks:**
- [ ] Create `GuestListStatus` enum
- [ ] Create migration #21: `create_guest_lists_table`
- [ ] Create migration #22: `create_guest_groups_table`
- [ ] Create migration #23: `create_guest_entries_table`
- [ ] Create migration #24: `create_guest_entry_gear_table`
- [ ] Run migrations
- [ ] Create `GuestList` model with factory, relationships, casts, scopes
- [ ] Create `GuestGroup` model with factory, relationships, casts
- [ ] Create `GuestEntry` model with factory, relationships, casts, `qrCodeSvg()`
- [ ] Create `GuestEntryGear` model with factory, relationships, casts
- [ ] Add `guestLists()` to Project model
- [ ] Add `guestLists()` to ProjectScanner model
- [ ] Add `manageGuestLists()` to ProjectPolicy
- [ ] RED: Write model unit tests (relationships, scopes, computed methods)
- [ ] GREEN: All model tests pass

**Deployable gate:** migrate:fresh --seed clean, model tests green.

### Phase 2: Actions (business logic)

**Tasks:**
- [ ] RED: Write CreateGuestList tests (3)
- [ ] GREEN: Implement CreateGuestList
- [ ] RED: Write UpdateGuestList tests (3)
- [ ] GREEN: Implement UpdateGuestList
- [ ] RED: Write DeleteGuestList tests (2)
- [ ] GREEN: Implement DeleteGuestList
- [ ] RED: Write AddGuestGroup tests (3)
- [ ] GREEN: Implement AddGuestGroup
- [ ] RED: Write RemoveGuestGroup tests (2)
- [ ] GREEN: Implement RemoveGuestGroup
- [ ] RED: Write AddGuestEntry tests (4)
- [ ] GREEN: Implement AddGuestEntry
- [ ] RED: Write RemoveGuestEntry tests (2)
- [ ] GREEN: Implement RemoveGuestEntry
- [ ] RED: Write UpdateGuestEntry tests (3)
- [ ] GREEN: Implement UpdateGuestEntry
- [ ] RED: Write CheckInGuest tests (3)
- [ ] GREEN: Implement CheckInGuest
- [ ] RED: Write ConfirmGuestList tests (5) — use `Queue::fake()` to assert `ConfirmGuestListJob` dispatch (job class doesn't exist yet, but Laravel only needs the class name for assertion)
- [ ] GREEN: Implement ConfirmGuestList
- [ ] RED: Write RecordGuestGearPickup tests (3)
- [ ] GREEN: Implement RecordGuestGearPickup

**Deployable gate:** All action tests green (~33 tests).

### Phase 3: Jobs + Mailable (email sending)

**Tasks:**
- [ ] Create `GuestInvitationMail` mailable with markdown template
- [ ] Create `ConfirmGuestListJob` (orchestrator: bulk token generation + dispatches SendGuestInvitationsJob per email)
- [ ] Create `SendGuestInvitationsJob` (queued, per-email grouping)
- [ ] RED: Write ConfirmGuestListJob tests (4: bulk token generation, email grouping, idempotent, skips entries without email)
- [ ] RED: Write SendGuestInvitationsJob tests (3: grouped send, no-email skip, idempotent)
- [ ] GREEN: All job tests pass
- [ ] Verify end-to-end: ConfirmGuestList → ConfirmGuestListJob → SendGuestInvitationsJob (integration)

**Deployable gate:** Full email pipeline works, job tests green.

### Phase 4: Admin Livewire Components (UI)

**Tasks:**
- [ ] Create `GuestListIndex` Livewire component + blade
- [ ] Create `GuestListShow` Livewire component + blade
- [ ] Register routes in `web.php`
- [ ] Add navigation link (Scanners page or Project page sidebar)
- [ ] RED: Write GuestListIndex tests (5)
- [ ] GREEN: All GuestListIndex tests pass
- [ ] RED: Write GuestListShow tests (10)
- [ ] GREEN: All GuestListShow tests pass
- [ ] Run Pint

**Deployable gate:** Full admin CRUD works, component tests green.

### Phase 5: Scanner API Extensions (backend)

**Tasks:**
- [ ] Extend `ScannerDataController::data()` to include guest entries
- [ ] Add `guestCheckin()` method to ScannerDataController
- [ ] Add `guestGearPickup()` method to ScannerDataController
- [ ] Add `guestSync()` method to ScannerDataController
- [ ] Register new API routes in `routes/api.php`
- [ ] RED: Write ScannerDataControllerGuestTest (5)
- [ ] GREEN: Data endpoint tests pass
- [ ] RED: Write GuestCheckinApiTest (3)
- [ ] GREEN: Check-in endpoint tests pass
- [ ] RED: Write GuestGearPickupApiTest (3)
- [ ] GREEN: Gear pickup endpoint tests pass
- [ ] RED: Write GuestSyncApiTest (2)
- [ ] GREEN: Sync endpoint tests pass

**Deployable gate:** Scanner API serves guest data, all API tests green.

### Phase 6: TypeScript + Scanner Blade (frontend)

**Tasks:**
- [ ] Add `GuestEntry` and `GuestEntryGearItem` types to `types.ts`
- [ ] Extend `OutboxEntry` type with `guest_checkin`
- [ ] Bump IDB to DB_VERSION 4, add `guest_entries` store
- [ ] Add `storeGuestEntries`, `getGuestEntries`, `searchGuestEntries` to `idb-store.ts`
- [ ] Extend `sync.ts` for guest check-in sync
- [ ] Extend `alpine-scanner.ts` with guest state + methods
- [ ] Implement dual-path QR detection (JWT vs guest token)
- [ ] Update `scanner-app.blade.php`: Entry Staff tabs, guest panel, guest result display
- [ ] Update `scanner-app.blade.php`: Volunteer Admin guest gear display with Typ-1 direct selection
- [ ] Update `sw.js` with new API paths
- [ ] RED: Write IDB guest store tests (3)
- [ ] GREEN: IDB tests pass
- [ ] RED: Write Alpine scanner guest tests (3)
- [ ] GREEN: All TS tests pass
- [ ] Run `npm run build`

**Deployable gate:** Scanner handles guest QR codes + guest gear, TS tests + build green.

### Phase 7: Polish + Final Verification

**Tasks:**
- [ ] Run Pint on all modified PHP files
- [ ] Run full test suite: `vendor/bin/sail artisan test --compact`
- [ ] Run TS tests: Vitest
- [ ] Verify `migrate:fresh --seed` clean
- [ ] Manual smoke test: create list → confirm → scan QR → check in → gear pickup
- [ ] Review for N+1 queries in data endpoint
- [ ] Update cross-milestone interface table

**Deployable gate:** All tests green, Pint clean, migrate:fresh clean.

---

## 15. Self-Review Results

### Pass 1: Laravel Best Practices
- [x] `casts()` method (not property) on all 4 new models
- [x] Business logic in Actions, not Livewire components
- [x] Validation in Livewire component methods (project convention: inline validation, not Form Request for Livewire)
- [x] Policy covers guest list management (Organizer only)
- [x] Routes use `auth`, `verified`, `resolve-org` middleware

### Pass 2: Livewire v4 Correctness
- [x] No `.defer` syntax used
- [x] `wire:model.live` only for search inputs
- [x] No deep nesting — both components are full-page, entries rendered as Blade loops
- [x] `#[Locked]` on projectId and guestListId
- [x] `Route::livewire()` for full-page components
- [x] Result panel uses `role="alert" aria-live="assertive"` (consistent with M11)

### Pass 3: Clean Architecture
- [x] Default Laravel structure (existing pattern, no DDD)
- [x] Single responsibility per action class
- [x] No cross-component events needed (both pages are independent)
- [x] Data classification assigned: name, email, qr_token are confidential
- [x] `qr_token` not exposed in admin UI beyond QR image

### Pass 4: Testability
- [x] All 10 actions unit-testable without HTTP
- [x] Both Livewire components testable with `Livewire::test()`
- [x] No static calls preventing mocking
- [x] Factories defined for all 4 new models with useful states
- [x] ~59 tests planned across unit, feature, API, and TS layers

---

## Implement
- **Status:** complete
- **Iteration:** 1
- **Gate summary:** 1221 PHP tests green (2668 assertions), 31 TS tests green, Vite build clean, Pint clean, migrate:fresh --seed clean. 65 new PHP tests, ~0 new TS tests (existing tests updated for IDB v4). 7 phases complete.
- **Tasks:**
  - [x] Phase 1: 4 migrations, 4 models, 1 enum, 4 factories, policy extension, 30 model tests GREEN
  - [x] Phase 2: 11 actions, 32 action tests GREEN
  - [x] Phase 3: ConfirmGuestListJob, SendGuestInvitationsJob, GuestInvitationMail, 8 job tests GREEN
  - [x] Phase 4: GuestListIndex + GuestListShow Livewire components, routes, 13 component tests GREEN
  - [x] Phase 5: ScannerDataController extended (guest data, check-in, gear pickup, sync), 13 API tests GREEN
  - [x] Phase 6: TypeScript + Scanner Blade (types, IDB v4, dual-path QR, Alpine scanner, guest tabs/panels, SW)
  - [x] Phase 7: Polish + Final Verification (all green, migrate:fresh clean)

## Test
- **Status:** complete
- **Gate summary:** 37 new tests added (14 gaps identified and filled). Coverage: full lifecycle, post-confirm mutations, email grouping, scanner scoping, authorization, validation, edge cases. Total: 1258 PHP tests (2761 assertions), 31 TS tests. New test files: GuestInvitationMailTest (4), GuestListLifecycleTest (5). Extended: GuestListIndexTest (+6), GuestListShowTest (+8), GuestCheckinApiTest (+3), GuestGearPickupApiTest (+2), GuestSyncApiTest (+3), ScannerDataControllerGuestTest (+1), RecordGuestGearPickupTest (+2), AddGuestEntryTest (+3), UpdateGuestEntryTest (+2).

## Security Audit
- **Status:** complete
- **Gate summary:** 0 critical, 1 high, 2 medium, 3 low findings. All high/medium findings FIXED: (1) entryGear validation with project-scoped Rule::exists, (2) gearItemIds validation on create+update, (3) ShouldBeUnique on ConfirmGuestListJob, (4) lockForUpdate in ConfirmGuestList action. Low: TOCTOU race now mitigated by lockForUpdate, npm audit build-time deps (accepted), no audit logging (deferred). 4 prior findings verified as fixed. 1258 tests green after fixes.

## Decisions

| # | Stage | Decision | Rationale | Affects |
|---|---|---|---|---|
| D1 | plan | Guest QR tokens are 64-char hex random strings (not JWT) | Guests don't have tickets or JWT infrastructure. Simple random token is sufficient — identifies the guest_entry directly. No offline crypto validation needed since guest tokens are looked up by string match in IDB. | GuestEntry, QR generation, scanner TS |
| D2 | plan | Guest check-in supports offline (outbox buffering) like volunteer arrivals | Entry Staff scanner operates offline. Guest check-ins must not be lost when offline. Same outbox pattern as volunteer arrivals. | sync.ts, idb-store.ts, guest-sync API |
| D3 | plan | Guest gear pickup is online-only (consistent with D4/D10 from M11) | Gear state changes require real-time confirmation. Same rationale as volunteer gear. | guest-gear-pickup API, alpine-scanner.ts |
| D4 | plan | No separate GuestListPolicy — use ProjectPolicy::manageGuestLists() | Guest lists are project-scoped resources managed by the same Organizer role. Adding a separate policy adds indirection without benefit. | ProjectPolicy, Livewire components |
| D5 | plan | GuestInvitationMail uses default mailer (not per-org SMTP) | Guests are not volunteers. The UsesOrganizationMailer trait is for volunteer-facing notifications. Guest emails use the platform default. | SendGuestInvitationsJob, GuestInvitationMail |
| D6 | plan | RemoveGuestEntry does NOT renumber remaining entries | Renumbering would change the displayed "X/Y" label for existing entries. The number field is immutable after creation. | RemoveGuestEntry action |
| D7 | plan | Volunteer Admin scanner loads guest entries from ALL confirmed lists in the project (not just its linked scanner) | Volunteer Admin scanners handle gear across the whole project. They are not Entry Staff scanners and don't have a guest_lists.scanner_id link. Gear scope is project-wide. | ScannerDataController, types.ts |
| D8 | plan | Dual-path QR detection: try JWT first, then string-match against guest tokens | Guest tokens and volunteer JWTs coexist. JWT validation is tried first (existing flow). If it fails, the scanned string is checked against the guest_entries IDB store. No ambiguity since JWTs have a specific format (header.payload.signature). | alpine-scanner.ts |
| D9 | plan | AddGuestGroup creates N empty GuestEntry rows upfront | The group's guest_count defines expected entries. Pre-creating numbered entries (1..N) simplifies the UI — each entry is immediately editable. Entries beyond guest_count can be added post-confirmation. | AddGuestGroup action |
| D10 | plan | IDB DB_VERSION bumped from 3 to 4 to add guest_entries store | New IDB object store requires version bump. The onupgradeneeded handler must use `event.oldVersion` branching for additive upgrade (not drop-all). | idb-store.ts |
| D11 | plan-review | `qr_token` stripped from Volunteer Admin payload, kept for Entry Staff | VA never scans QR (only gear pickup by ID). Reduces credential exposure. Entry Staff needs tokens for offline QR matching — accepted risk since device is already trusted. | ScannerDataController, types.ts |
| D12 | plan-review | `scanner_id` FK uses `restrictOnDelete` (not cascade) | Prevents accidental destruction of confirmed guest lists with issued QR codes when deleting a scanner. DB constraint is the guard. | Migration #21, DeleteProjectScanner |
| D13 | plan-review | `syncOutbox` uses selective per-type deletion (not clear-all) | Prevents data loss when arrival sync succeeds but guest check-in sync has not yet run. Each type's entries are deleted only after successful sync. | sync.ts |
| D14 | plan-review | `ConfirmGuestList` dispatches single `ConfirmGuestListJob` orchestrator | Avoids queue burst (N emails dispatched synchronously in Livewire action). Orchestrator handles bulk token generation + email dispatch. Livewire returns immediately. | ConfirmGuestList, ConfirmGuestListJob |
| D15 | plan-review | Guest gear uses simpler single-table model + dedicated action | Guests have no portal for self-service — operator decides everything at scanner. No audit trail of individual pickups needed (unlike volunteers). `RecordGuestGearPickup` action extracted for convention consistency. | GuestEntryGear, RecordGuestGearPickup |

## Cross-Milestone Interface

| Category | Items |
|---|---|
| Models (new) | `GuestList` (project_id, scanner_id, name, status, confirmed_at, gear_items); `GuestGroup` (guest_list_id, label, guest_count); `GuestEntry` (guest_group_id, number, name?, email?, qr_token?, checked_in_at?, checked_in_by?); `GuestEntryGear` (guest_entry_id, project_gear_item_id, quantity, picked_up_count, selection?, status?) |
| Models (modified) | `Project` (+guestLists()); `ProjectScanner` (+guestLists()) |
| Enums (new) | `GuestListStatus` (Draft, Confirmed) |
| Actions (new) | `CreateGuestList`, `UpdateGuestList`, `DeleteGuestList`, `ConfirmGuestList`, `AddGuestGroup`, `RemoveGuestGroup`, `AddGuestEntry`, `RemoveGuestEntry`, `UpdateGuestEntry`, `CheckInGuest`, `RecordGuestGearPickup` |
| Jobs (new) | `ConfirmGuestListJob` (orchestrator: bulk QR tokens + email dispatch); `SendGuestInvitationsJob` (grouped emails per unique address) |
| Mailables (new) | `GuestInvitationMail` (markdown: mail.guest-invitation) |
| Policy | `ProjectPolicy::manageGuestLists()` — Organizer only |
| Routes (admin) | `guest-lists.index` → `GET /admin/projects/{projectId}/guest-lists`; `guest-lists.show` → `GET /admin/projects/{projectId}/guest-lists/{guestListId}` |
| Routes (API) | `scanner-api.guest-checkin` → `POST /api/scanner/{scannerId}/guest-checkin`; `scanner-api.guest-gear-pickup` → `POST .../guest-gear-pickup`; `scanner-api.guest-sync` → `POST .../guest-sync` |
| Livewire | `Projects\GuestListIndex` (list + create modal); `Projects\GuestListShow` (group/entry management, confirm flow) |
| Controller | `ScannerDataController` extended: `data()` includes guest_entries, +`guestCheckin()`, `guestGearPickup()`, `guestSync()` |
| TypeScript | `GuestEntry`/`GuestEntryGearItem` types; IDB v4 with `guest_entries` store; dual-path QR detection (JWT then guest token); selective outbox deletion; guest sync path |
| Blade | `scanner-app.blade.php`: 3-tab Entry Staff UI (Scanner/Volunteers/Gastliste), guest result panels, manual guest check-in |
| Key patterns | Guest QR = 64-char hex random string (not JWT); scanner_id FK uses restrictOnDelete; ConfirmGuestListJob is single orchestrator; VA payload excludes qr_token; syncOutbox uses selective per-type deletion |

## Reviews

### plan — 2026-04-01

| # | Perspective | Concern | Severity | Resolution | Rationale |
|---|---|---|---|---|---|
| DA-1 | Devil's Advocate | syncOutbox clear-all will discard guest check-ins | high | accepted | Selective per-type deletion (D13) |
| DA-2 | Devil's Advocate | cascadeOnDelete on scanner_id destroys confirmed guest lists | high | accepted | Changed to restrictOnDelete (D12) |
| SS-1 | Scalability Skeptic | VA data endpoint loads ALL guest entries unbounded | high | accepted | Filter whereHas('gear') for VA scanners |
| SS-2 | Scalability Skeptic | GuestListShow eager-loads full tree without pagination | high | rejected | Guest lists are for VIPs/artists (typically <50 entries). Pagination adds disproportionate UI complexity. |
| JD-1 | Junior Dev Lens | Guest gear model diverges from volunteer gear without explanation | high | accepted | Added inline rationale in section 7.4 (D15) |
| DA-3 | Devil's Advocate | IDB onupgradeneeded drops all stores unconditionally | medium | accepted | Specified event.oldVersion branching in section 10.3 |
| DA-4 | Devil's Advocate | qr_token exposed in IDB to scanner device | medium | accepted | Stripped from VA payload; accepted risk for Entry Staff (D11) |
| SS-3 | Scalability Skeptic | Queue burst on confirmation (N jobs synchronous) | medium | accepted | Single ConfirmGuestListJob orchestrator (D14) |
| SS-4 | Scalability Skeptic | VA receives qr_token it never needs | medium | accepted | Merged with DA-4 (D11) |
| JD-2 | Junior Dev Lens | Test descriptions list what not why | medium | deferred | Will write proper it('...') descriptions with business reasons during implementation |
| JD-4 | Junior Dev Lens | ConfirmGuestList in Phase 2 depends on Phase 3 job | medium | accepted | Added Queue::fake() note to Phase 2 task |
| DA-5 | Devil's Advocate | Guest gear pickup inline in controller breaks action convention | low | accepted | Extracted RecordGuestGearPickup action (D15) |
| SS-5 | Scalability Skeptic | N+1 in ConfirmGuestList token generation | low | accepted | Bulk generation + upsert in ConfirmGuestListJob |
| JD-3 | Junior Dev Lens | "Gastliste" tab label is German, rest is English | low | rejected | Design spec uses "Gastliste". App targets German-speaking organizations. |
| JD-5 | Junior Dev Lens | addEntry on confirmed list has unannounced side effects | low | accepted | Added success toast note in component section |

### implement — 2026-04-01

| # | Perspective | Concern | Severity | Resolution | Rationale |
|---|---|---|---|---|---|
| S-2 | Simplicity Zealot | Three near-identical guest gear TS methods + fragile URL string-replace | high | accepted | Extracted _postGuestGear helper, added guestGearPickupUrl to config |
| P-1 | Security Paranoid | qr_token leaks in guestCheckin JSON response — no $hidden | high | accepted | Added protected $hidden = ['qr_token'] to GuestEntry |
| A-1 | Accessibility Champion | Scanner tabs lack ARIA tab pattern | high | accepted | Added role=tablist/tab/tabpanel, aria-selected, aria-controls, id/aria-labelledby |
| A-2 | Accessibility Champion | Guest search input has no accessible label | high | accepted | Added aria-label |
| S-1 | Simplicity Zealot | Delete actions are single-line wrappers | medium | rejected | Project convention: all domain logic in actions |
| S-4 | Simplicity Zealot | ConfirmGuestList uses raw string instead of enum | medium | accepted | Changed to GuestListStatus::Confirmed |
| P-2 | Security Paranoid | $editingEntryId not locked | medium | accepted | Added #[Locked] |
| A-3 | Accessibility Champion | Check In buttons lack identifying context | medium | accepted | Added dynamic aria-label with entry number/name |
| A-4 | Accessibility Champion | Manual check-in success has no live announcement | medium | accepted | Added aria-live="polite" to entry rows |
| S-3 | Simplicity Zealot | loadGuestEntries has duplicated map branches | low | deferred | Minor DRY, not worth refactoring now |
| S-5 | Simplicity Zealot | manualGuestCheckin is a pass-through | low | accepted | Removed, calling confirmGuestCheckin directly |
| P-3 | Security Paranoid | guestGearPickup scopes by project not scanner | low | rejected | Intentional per D7 — VA handles gear project-wide |
| P-4 | Security Paranoid | status/selection accept arbitrary strings | low | accepted | Added max:255 validation |
| P-5 | Security Paranoid | $project is public nullable, not computed | low | deferred | Not exploitable since queries use locked $projectId |
| A-5 | Accessibility Champion | Admin table headers missing scope="col" | low | accepted | Added scope="col" and sr-only Actions header |

## Feedback Loops

| # | Date | Direction | Trigger | Fix | Resolution |
|---|---|---|---|---|---|
