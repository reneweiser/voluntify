# Milestone: m8-project-scoped — Project-Scoped Data

**Features:** Volunteer project scope, gear remodel (Typ-1/Typ-2), ticket scope, custom fields dual-level, JwtKeyService project scope, Private Events (#91)
**Dependencies:** m7-foundation (complete)

## Plan
- **Status:** complete
- **Gate summary:** 7 phases, ~8 migrations (edit in-place + 1 new), ~20 models/factories, ~10 actions, ~15 Livewire components, ~25 test files. Private Events (#91) is standalone Phase 1. Phases 2-6 are the core project-scoping changes. Phase 7 is test updates.

## Implement
- **Status:** complete
- **Iteration:** 1
- **Gate summary:** 947 tests green (920 after Phase 1 + 28 new - 1 deleted). migrate:fresh --seed clean. Pint passes. Grep sweep clean: zero references to EventGearItem, event_gear_item, ToggleGearPickup.
- **Tasks:**
  - [x] Phase 1: Private Events (#91) — visibility enum, ProjectWebsite filter, EventShow toggle
  - [x] Phase 2: Migrations — volunteer project_id, tickets project_id, project_gear_items, volunteer_gear_pickups, dual-level custom fields
  - [x] Phase 3: Models + Factories — ProjectGearItem, VolunteerGearPickup, GearItemType enum, updated Volunteer/Ticket/VolunteerGear/CustomRegistrationField/Event/Project
  - [x] Phase 4: Actions — ProcessVolunteerSignup project scoping, GenerateTicket project JWT, RecordGearPickup (new), JwtKeyService projectId, gear/export/clone updates
  - [x] Phase 5: Livewire Components — EventGearSetup/GearTracker/EventSignup/VolunteerPortal/VolunteerTicket/ManualLookup/VolunteerList/Dashboard + withVolunteerCount scope
  - [x] Phase 6: TypeScript — Ticket interface project_id, test fixtures
  - [x] Phase 7: Tests — 28 files updated, 5 new test files (29 tests), 1 deleted

## Test
- **Status:** complete
- **Gate summary:** 974 tests (947 + 27 new), 0 failures. Coverage gaps filled: scopeForEvent with cancelled signups (5), scopeWithVolunteerCount (5), volunteer uniqueness per project (3), dual-level custom field constraints (5), GearTracker wrong-event validation (5), gear item states (2), visibility factory states (2).

## Security Audit
- **Status:** complete
- **Gate summary:** Quick scan clean. No $request->all() mass assignment. Zero EventGearItem/ToggleGearPickup references. project_id not in Event $fillable (M7 fix intact). GearTracker volunteer validation confirmed.

## Decisions

| # | Stage | Decision | Rationale | Affects |
|---|---|---|---|---|
| 1 | plan-review | Add scopeWithVolunteerCount on Event using subquery | Event::volunteers() hasManyThrough removed; withCount breaks at 7 call sites | implement (Event model, views) |
| 2 | plan-review | scopeForEvent includes eventArrivals OR clause | Cancelled-but-arrived volunteers would vanish from per-event views | implement (Volunteer model) |
| 3 | plan-review | Scanner endpoint filters by event_id (event-scoped until M11) | Project-scoped payload too large for mobile PWA | implement (ScannerApiController) |
| 4 | plan-review | Gear pickup undo = delete the pickup record | Simple delete reversal; no separate ReverseGearPickup action needed | implement (GearTracker) |
| 5 | plan-review | Add CHECK constraint for custom field dual-FK in migration | Model-only enforcement can be bypassed; MySQL 8+/SQLite 3.25+ support CHECK | implement (migration) |
| 6 | plan-review | Flip unique index to ['project_id', 'email'] on volunteers | scopeForProject queries WHERE project_id=? needs leading column | implement (migration) |
| 7 | plan-review | Rename states → available_states on project_gear_items | Disambiguate from volunteer_gear_pickups.state (actual value) | implement (migration, model) |
| 8 | plan-review | VolunteerFactory requires explicit ->for($project), no auto-create | Hidden factory magic flagged in M7 review; don't carry forward | implement (factory, tests) |
| 9 | plan-review | Explicitly delete ToggleGearPickup action and all references | Replaced by RecordGearPickup; must not coexist | implement (actions) |

## Reviews

### plan — 2026-03-31

| # | Perspective | Concern | Severity | Resolution | Rationale |
|---|---|---|---|---|---|
| 1 | Devil's Advocate | Event::volunteers() removal breaks 7 withCount call sites | high | accepted | Add scopeWithVolunteerCount subquery on Event |
| 2 | Devil's Advocate | scopeForEvent loses cancelled-but-arrived volunteers | high | accepted | Add eventArrivals OR clause to scope |
| 3 | Scalability | Scanner endpoint loads all project volunteers | high | accepted | Filter by event_id; full project scope deferred to M11 |
| 4 | Devil's Advocate | Gear pickup undo mechanism undefined | medium | accepted | Delete pickup record to reverse; documented |
| 5 | Devil's Advocate | Custom field CHECK constraint should be DB-level | medium | accepted | Add CHECK in migration + model validation |
| 6 | Devil's Advocate | VolunteerFactory auto-create pattern carried forward from M7 | medium | accepted | Require explicit ->for($project); no configure() |
| 7 | Scalability | Unique index order ['email', 'project_id'] suboptimal | medium | accepted | Flip to ['project_id', 'email'] |
| 8 | Junior Dev | states vs state column naming confusing | medium | accepted | Rename to available_states |
| 9 | Junior Dev | GearItemType Typ1/Typ2 alias undocumented | low | deferred | Add PHPDoc during implementation |
| 10 | Junior Dev | ToggleGearPickup deletion not explicit in plan | low | accepted | Noted in decisions |
| 11 | Scalability | JWT key scope change is intentional | low | rejected | Correct by design; matches ticket scope |

### implement — 2026-03-31

| # | Perspective | Concern | Severity | Resolution | Rationale |
|---|---|---|---|---|---|
| 1 | Simplicity | isPickedUp() N+1 bug | high | accepted | Check relationLoaded before querying |
| 2 | Security | GearTracker assignAndPickup accepts arbitrary volunteerId | high | accepted | Added Volunteer::forEvent() validation |
| 3 | Accessibility | Gear tracker search input has no label | high | accepted | Added aria-label |
| 4 | Security | Private events accessible via public_token | medium | deferred | By design: private = unlisted, not access-controlled |
| 5 | Simplicity | RecordGearPickup is thin wrapper | medium | deferred | Consistent with action pattern |
| 6 | Accessibility | Gear buttons use title instead of aria-label | medium | accepted | Replaced with aria-label |
| 7 | Accessibility | Dual-level custom fields not grouped in signup | medium | deferred | M13 polish |
| 8 | Security | Missing visibility validation in EventShow | medium | accepted | Added Rule::in validation |
| 9-14 | Various | Low severity items | low | deferred | Documented for future |

## Feedback Loops

## Cross-Milestone Interface

| Category | Items |
|---|---|
| Models (new) | ProjectGearItem (project_id, name, type, requires_size, available_sizes, available_states, sort_order), VolunteerGearPickup (volunteer_gear_id, picked_up_by, picked_up_at, state, quantity) |
| Models (modified) | Volunteer (+project_id FK, scopeForProject, scopeForEvent rewrite via shiftSignups+arrivals), Ticket (project_id replaces event_id), VolunteerGear (project_gear_item_id, pickups(), isPickedUp()), Event (+visibility, scopeWithVolunteerCount, removed tickets/volunteers/gearItems relations), CustomRegistrationField (+project_id dual-FK), Project (+volunteers, gearItems, tickets, customRegistrationFields relations) |
| Models (deleted) | EventGearItem |
| Enums (new) | EventVisibility (Public, Private), GearItemType (SizeSelection, Quantity) |
| Actions (new) | RecordGearPickup |
| Actions (modified) | ProcessVolunteerSignup (firstOrCreate scoped by project_id), GenerateTicket (project-scoped JWT), AssignGearToVolunteer (project gear items), ExportVolunteersCsv (project gear queries), CloneEvent (no longer clones gear), RecordArrival (+Event param) |
| Actions (deleted) | ToggleGearPickup |
| Services (modified) | JwtKeyService (projectId replaces eventId in all methods) |
| Scopes | Event::scopeWithVolunteerCount (subquery), Event::scopePubliclyVisible, Volunteer::scopeForProject, CustomRegistrationField::scopeProjectLevel/scopeEventLevel |
| Factory | ProjectGearItemFactory (sized/quantity states), VolunteerGearPickupFactory, EventFactory (+visibility, private() state), VolunteerFactory (+project_id), TicketFactory (project_id) |
| Notes for M9 | StaffRole changes: VA/ES become scanner-assignees. org_user pivot keeps only Organizer. |
| Notes for M10 | ProcessVolunteerSignup now scopes by project. Signup wizard must pass project context for shift reservations. |
| Notes for M11 | JwtKeyService uses projectId. Scanner API already filters by event_id for volunteer loading. Rename volunteer tab "Gastliste" → "Volunteers". |
