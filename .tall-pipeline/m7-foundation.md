# Milestone: m7-foundation — Foundation

**Features:** EventGroup->Project rename, volunteer name split, EventStatus 4-state expansion, phone_required on events
**Dependencies:** none

## Plan
- **Status:** complete
- **Gate summary:** `.tall-plan.md` sections 3.2, 5, 7, 11, 12 cover M7 scope. 4 migrations (edit existing) + 1 new, ~16 files deleted, ~16 created, ~35 edited. Total ~67 files touched.

## Implement
- **Status:** complete
- **Iteration:** 1
- **Gate summary:** 882 tests green, 0 failures. migrate:fresh --seed clean. Pint passes. Grep sweep clean: zero references to EventGroup, event_group, EventStatus::Published, or event-groups routes.
- **Tasks:**
  - [x] Phase 1: Migrations (rename tables, split volunteer name, FULLTEXT index, phone_required)
  - [x] Phase 2: Enums (EventStatus 4-state, ActivityCategory Project)
  - [x] Phase 3: Models + Factory + Seeder (Project model, factories, seeder)
  - [x] Phase 4: Activity Events + Listener (4 new events, deleted 5 old, updated listener)
  - [x] Phase 5: Policy (ProjectPolicy replaces EventGroupPolicy)
  - [x] Phase 6: Actions (4 Project actions, CloseRegistration, updated PublishEvent/ArchiveEvent/etc)
  - [x] Phase 7: Livewire Components (ProjectList/Show/Website, updated EventShow/EventSignup/etc)
  - [x] Phase 8: Routes (projects.* routes replace event-groups.*)
  - [x] Phase 9: Tests (10 deleted, 10 created, 28 updated)
  - [x] Phase 10: Cleanup (Pint, grep sweep, final verification)

## Test
- **Status:** complete
- **Gate summary:** 914 tests (882 existing + 32 new), 0 failures. Coverage gaps filled: EventStatus enum (8 tests), 4-state lifecycle flow (10 tests), phone_required validation (2 tests), Volunteer full_name accessor (2 tests), Project publishedEvents (2 tests), Event published scope + factory (5 tests), search by last_name (1 test), PublishEvent/ArchiveEvent edge cases (2 tests).

## Security Audit
- **Status:** complete
- **Gate summary:** 0 critical, 1 high (cascadeOnDelete — by design, deferred), 3 medium (2 fixed: project_id removed from $fillable + CSV sanitization; 1 deferred: status in $fillable needed by actions), 4 low (1 fixed: phone label; 3 deferred). 8 info findings all confirmed secure. Fixes: removed project_id from Event $fillable, added CSV formula-prefix sanitization, fixed phone_required label conditional, updated CreateEvent to use direct attribute assignment.

## Decisions

| # | Stage | Decision | Rationale | Affects |
|---|---|---|---|---|
| 1 | plan | Delete RemoveEventFromGroup entirely | project_id is NOT NULL; AssignEventsToProject handles reassignment | implement (actions) |
| 2 | plan | Add full_name accessor on Volunteer | Minimizes blast radius of name split — 23+ views can use accessor | implement (model, views) |
| 3 | plan | Add isPublished() helper on EventStatus | Published->PublishedOpen/PublishedClosed; helper avoids repeating whereIn | implement (enum, scopes) |
| 4 | plan | Edit existing migrations in-place | App not in production, migrate:fresh always available | implement (migrations) |
| 5 | plan-review | Atomic EventStatus enum change | Removing Published case causes PHP fatal; do enum + all references in single pass | implement (phases 2-7 status refs) |
| 6 | plan-review | Drop global unique on volunteers.email in M7 | M8 needs composite unique [email, project_id]; dropping now avoids in-place edit chain | implement (migration) |
| 7 | plan-review | Rename CloseEventSignup to CloseRegistration | Symmetry with PublishEvent/ArchiveEvent lifecycle actions | implement (action naming) |

## Reviews

### plan — 2026-03-31

| # | Perspective | Concern | Severity | Resolution | Rationale |
|---|---|---|---|---|---|
| 1 | Devil's Advocate | EventStatus::Published removal is big bang — app un-bootable during transition | high | accepted | Do enum + all references atomically in single pass |
| 2 | Scalability Skeptic | FULLTEXT index on 3 columns degrades email search; fix #67 deferred to M12 | high | deferred | Pre-existing issue (#67); current search already broken for emails |
| 3 | Junior Dev | Missing CloseEventSignup action in .tall-plan.md | high | rejected | Already in implementation plan Phase 6c; gap only in source doc |
| 4 | Devil's Advocate | scopeSearch LIKE fallback uses deleted name column | medium | accepted | Add explicit checklist item for LIKE fallback verification |
| 5 | Devil's Advocate | No undo path for bulk reassignment after RemoveEventFromGroup deletion | medium | rejected | AssignEventsToProject already handles reassignment; UI has dropdown |
| 6 | Devil's Advocate | volunteers.email global unique index — cross-milestone edit chain risk | medium | accepted | Drop global unique in M7 now (no production data) |
| 7 | Scalability Skeptic | Unbounded get() in EventList/ProjectList | medium | deferred | Pre-existing issue, not introduced by M7; address in M12 |
| 8 | Junior Dev | Enum case naming unspecified | medium | rejected | Already specified in implementation plan: PublishedOpen, PublishedClosed |
| 9 | Junior Dev | full_name accessor has no precedent in codebase | medium | rejected | Standard Laravel pattern; minor concern |
| 10 | Scalability Skeptic | No index on events.status column | low | deferred | Address in M8 with project_id composite index |
| 11 | Junior Dev | CloseEventSignup vs CloseRegistration naming | low | accepted | Rename to CloseRegistration for lifecycle symmetry |
| 12 | Devil's Advocate | Seeder sequencing documentation | low | accepted | Phase 1 verify = migrate:fresh only; Phase 3 verify = with --seed |

### implement — 2026-03-31

| # | Perspective | Concern | Severity | Resolution | Rationale |
|---|---|---|---|---|---|
| 1 | Security Paranoid | phone_required flag never enforced in validation | high | accepted | Wired into EventSignup validation |
| 2 | Accessibility | Icon-only back buttons lack aria-label | high | accepted | Added aria-label to 14 icon-only buttons |
| 3 | Accessibility | Tab bars lack ARIA tab semantics | high | accepted | Added nav wrapper + aria-current="page" |
| 4 | Simplicity | publishedEvents() and ScannerEventSelect duplicate scopePublished | medium | accepted | Replaced with ->published() scope calls |
| 5 | Simplicity | EventFactory configure() auto-creates Project via hidden afterMaking | medium | deferred | Works correctly; revisit in M8 |
| 6 | Simplicity | CreateEvent silently creates Project when none passed | medium | deferred | Revisit in M8 when project scoping established |
| 7 | Security | CloseRegistration has no internal authorization | medium | deferred | Consistent with action pattern; caller-enforced |
| 8 | Accessibility | No aria-live regions for Livewire re-renders | medium | deferred | Address in M12 polish |
| 9 | Accessibility | Shift checkboxes lack context for disabled state | medium | deferred | Address in M12 polish |
| 10 | Simplicity | ArchiveEvent has redundant third guard (dead code) | low | accepted | Removed dead guard |
| 11 | Simplicity | RecordActivityListener is 530-line god listener | low | deferred | Future refactor |
| 12 | Security | project_id in Event fillable — mass assignment risk | low | deferred | Low risk given action architecture |
| 13 | Security | Public ProjectWebsite exposes volunteer counts | low | deferred | Intentional for social proof |
| 14 | Accessibility | Flux headings may render as div not semantic h1/h2 | low | deferred | Address in M12 polish |

### security-audit — 2026-03-31

| # | Concern | Severity | Resolution | Rationale |
|---|---|---|---|---|
| 1 | status in Event $fillable — could bypass state machine | medium | deferred | Needed by 3 action classes that use $event->update(); removing breaks them |
| 2 | project_id in Event $fillable — cross-org mass assignment | medium | accepted | Removed from $fillable; CreateEvent uses direct assignment |
| 3 | CSV injection — formula prefixes unsanitized | medium | accepted | Added sanitizeCsvValue() helper |
| 4 | org_id in Project/Event $fillable | low | deferred | Auto-set by relationships; low risk |
| 5 | user_id in Volunteer $fillable | low | deferred | Only set by PromoteVolunteer; low risk |
| 6 | No #[Locked] on model properties | info | rejected | Livewire 4 re-queries models; mount() scopes to org |
| 7 | cascadeOnDelete on project_id | high | deferred | By design — projects own events |
| 8 | No closeRegistration policy gate | low | deferred | update gate sufficient |
| 9 | Phone label always shows optional | low | accepted | Fixed with conditional @unless |

## Feedback Loops

## Cross-Milestone Interface

| Category | Items |
|---|---|
| Models | Project (id, organization_id, name, public_token, description, title_image_path) |
| Models (modified) | Event (+project_id NOT NULL, +phone_required, status now 4-state), Volunteer (first_name, last_name replacing name, +full_name accessor), Organization (projects() replaces eventGroups()) |
| Actions | CreateProject, UpdateProject, DeleteProject, AssignEventsToProject, CloseRegistration |
| Actions (modified) | PublishEvent (→PublishedOpen), ArchiveEvent (accepts PublishedOpen/Closed), ProcessVolunteerSignup (firstName/lastName params), ExportVolunteersCsv (first_name/last_name + CSV sanitization), CloneEvent (keeps project_id), CreateEvent (+optional project param) |
| Actions (deleted) | CreateEventGroup, UpdateEventGroup, DeleteEventGroup, AssignEventsToGroup, RemoveEventFromGroup |
| Enums | EventStatus: Draft, PublishedOpen, PublishedClosed, Archived + isPublished() + label(). ActivityCategory: Project replaces EventGroup |
| Routes | projects.index (/admin/projects), projects.show (/admin/projects/{projectId}), projects.public (/p/{publicToken}) |
| Named Routes (deleted) | event-groups.index, event-groups.show, event-groups.public |
| Components | ProjectList, ProjectShow, ProjectWebsite (new). EventShow, EventSignup, ScannerEventSelect (modified) |
| Events | ProjectCreated, ProjectUpdated, ProjectDeleted, EventAssignedToProject (new). EventGroupCreated/Updated/Deleted, EventAssignedToGroup, EventRemovedFromGroup (deleted) |
| Policy | ProjectPolicy (viewAny, view, create, update, delete) |
| Factory | ProjectFactory, EventFactory (auto-creates project in same org via configure()), VolunteerFactory (first_name/last_name) |
| Notes for M8 | PR #92 adds Private Events (#91) — visibility toggle on events. Add to M8 scope. |
| Notes for M11 | PR #92 requires renaming scanner volunteer tab from "Gastliste" to "Volunteers" to avoid collision with Guest Lists (#90). |
| Notes for M12 | PR #92 adds Guest Lists (#90) — new M12 milestone. 4 tables, scanner integration, QR generation. |
