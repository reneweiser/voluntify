# Milestone: m15-volunteer-portal-enhancements — Volunteer Portal Enhancements

**Features:** #117 arrival+gear admin, #125 banners, #115 magic link re-request, #126 QR+resend, #127 attendance status, #105 self-deletion
**Dependencies:** m14-audit-remediation
**Branch:** feat/volunteer-portal-enhancements-m1
**Plan:** `/home/rweiser/.claude/plans/declarative-mixing-puffin.md`

## Plan
- **Status:** complete (expert-reviewed, 12 issues resolved in Cycle 1)
- **Gate summary:** 6 issues, 0 migrations, ~8 new files, ~50 tests. TDD approach per issue.

## Implement
- **Status:** complete
- **Iteration:** 1
- **Gate summary:** 6 issues implemented via TDD. 1718 tests pass (3729 assertions), 0 failures. Pint clean.
- **Tasks:**
  - [x] #117: Admin Manual Arrival + Gear Management
  - [x] #125: Next Shift Banner + Maintenance Banner
  - [x] #115: Magic Link Re-request from Project Website
  - [x] #126: Inline QR Code Display + Resend
  - [x] #127: Attendance Status per Shift
  - [x] #105: Volunteer Self-Deletion (GDPR)

## Test
- **Status:** complete (TDD inline — 42 new tests written during implement)

## Security Audit
- **Status:** pending

## Decisions

| # | Stage | Decision | Rationale | Affects |
|---|---|---|---|---|
| 1 | plan | Inline gear undo (no new action) | Pickups are simple records with no side effects | #117 implement |
| 2 | plan | Synchronous confirmation email before deletion | GDPR right to erasure takes precedence over courtesy email | #105 implement |
| 3 | plan | Dual rate limiting (per-email + per-IP) | Matches existing EventSignup pattern; prevents enumeration | #115, #126 implement |
| 4 | plan | DB::transaction for volunteer delete | Atomicity of cascade; notifications outside transaction | #105 implement |

## Cross-Milestone Interface

| Category | Items |
|---|---|
| Actions | `RequestPortalAccessLink`, `DeleteVolunteerProfile` |
| Notifications | `PortalAccessLink`, `TicketResendNotification`, `ProfileDeletionConfirmation`, `VolunteerProfileDeletedNotification` |
| EmailTemplateType | `ProfileDeletion` (new enum case) |
| Routes | No new routes (all features added to existing portal/detail/project pages) |

## Reviews

## Feedback Loops

| # | Date | Direction | Trigger | Fix | Resolution |
|---|---|---|---|---|---|
