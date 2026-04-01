---
pipeline_status: in_progress
current_stage: implement
current_focus: "M13 Phase C complete — ready for Phase D"
current_milestone: m13-polish
entry_point: plan
stages_to_run:
  - plan
  - implement
  - test
  - security-audit
completed_stages:
  - plan
project_summary: "Voluntify Phase 2 restructure — Projects replace EventGroups as mandatory top-level entity"
quality_bar: "domain ~100% coverage, components 80%+, no critical/high security findings"
started_at: "2026-04-01"
last_updated: "2026-04-01"
---

# TALL Pipeline — Index

## Milestones

| ID | Name | Features | Dependencies | Status |
|---|---|---|---|---|
| m7-foundation | Foundation | EventGroup->Project, volunteer name split, EventStatus 4-state, phone_required | none | complete |
| m8-project-scoped | Project-Scoped Data | Volunteer project scope, gear remodel, ticket scope, custom fields, Private Events (#91) | m7 | complete |
| m9-roles | Roles & Team | project_user pivot, role hierarchy, scanner roles | m7 | complete |
| m10-signup | Signup Flow Rewrite | Multi-step wizard, shift reservations, manual enrollment | m8 | complete |
| m11-scanner | Scanner Rewrite | Project scanners, temp auth, dual scanner types, rename volunteer tab to "Volunteers" | m8, m9 | complete |
| m12-guest-lists | Guest Lists (#90) | Guest list CRUD, QR generation, grouped emails, scanner integration | m8, m11 | complete |
| m13-polish | Communication & Polish | Announcements, email templates, remaining features | m8, m9, m11 | in_progress |

## Artifacts

| File | Stage | Milestone | Purpose | Status |
|---|---|---|---|---|
| `.tall-plan.md` | plan | global | Full architecture plan (schema, components, actions, tests) | complete |
| `.tall-pipeline/m9-roles.md` | plan | m9-roles | Detailed M9 implementation plan (migrations, models, policies, components, tests) | complete |
| `.tall-pipeline/m10-signup.md` | plan | m10-signup | Detailed M10 implementation plan (schema, actions, wizard, reservations, manual enrollment, tests) | complete |
| `.tall-pipeline/m11-scanner.md` | plan | m11-scanner | Detailed M11 implementation plan (scanner schema, temp auth, dual scanner types, TS rewrite, API, tests) | complete |
| `.tall-pipeline/m11-security-audit.md` | security-audit | m11-scanner | Security audit report (0 crit, 2 high, 1 med, 4 low) | complete |
| `.tall-pipeline/m12-guest-lists.md` | plan | m12-guest-lists | Detailed M12 plan (4 migrations, 4 models, 10 actions, 2 Livewire components, scanner extensions, ~59 tests) | complete |
| `.tall-pipeline/m12-security-audit.md` | security-audit | m12-guest-lists | Security audit report (0 crit, 1 high, 2 med, 3 low) | complete |
| `.tall-pipeline/m13-polish.md` | plan | m13-polish | Detailed M13 plan (19 features, 5 phases, ~14 migrations, 3 new models, ~18 actions, ~180+ tests) | complete |

## Conceive
- **Status:** n/a (Phase 1 design complete, Phase 2 design from PO feedback PR #89)

## Maintain
- **Status:** pending
