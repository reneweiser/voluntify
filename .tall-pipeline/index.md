---
pipeline_status: in_progress
current_stage: complete
current_focus: "M7 Foundation complete — ready for M8"
current_milestone: m7-foundation
entry_point: plan
stages_to_run:
  - plan
  - implement
  - test
  - security-audit
completed_stages:
  - plan
  - implement
  - test
  - security-audit
project_summary: "Voluntify Phase 2 restructure — Projects replace EventGroups as mandatory top-level entity"
quality_bar: "domain ~100% coverage, components 80%+, no critical/high security findings"
started_at: "2026-03-31"
last_updated: "2026-03-31T10:00:00"
---

# TALL Pipeline — Index

## Milestones

| ID | Name | Features | Dependencies | Status |
|---|---|---|---|---|
| m7-foundation | Foundation | EventGroup->Project, volunteer name split, EventStatus 4-state, phone_required | none | complete |
| m8-project-scoped | Project-Scoped Data | Volunteer project scope, gear remodel, ticket scope, custom fields, Private Events (#91) | m7 | not_started |
| m9-roles | Roles & Team | project_user pivot, role hierarchy, scanner roles | m7 | not_started |
| m10-signup | Signup Flow Rewrite | Multi-step wizard, shift reservations, manual enrollment | m8 | not_started |
| m11-scanner | Scanner Rewrite | Project scanners, temp auth, dual scanner types, rename volunteer tab to "Volunteers" | m8, m9 | not_started |
| m12-guest-lists | Guest Lists (#90) | Guest list CRUD, QR generation, grouped emails, scanner integration | m8, m11 | not_started |
| m13-polish | Communication & Polish | Announcements, email templates, remaining features | m8, m9, m11 | not_started |

## Artifacts

| File | Stage | Milestone | Purpose | Status |
|---|---|---|---|---|
| `.tall-plan.md` | plan | global | Full architecture plan (schema, components, actions, tests) | complete |

## Conceive
- **Status:** n/a (Phase 1 design complete, Phase 2 design from PO feedback PR #89)

## Maintain
- **Status:** pending
