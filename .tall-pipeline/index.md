---
pipeline_status: complete
current_stage: complete
current_focus: "Phase 4 signup conflict UX and coverage is complete; overlap guidance, cancelled-shift reactivation coverage, Playwright browser verification, and local-only E2E fixture handling are in place with no unresolved critical/high security findings"
current_milestone: phase-4-signup-conflict-ux-coverage
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
started_at: "2026-04-01"
last_updated: "2026-05-08T07:55:01+02:00"
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
| m13-polish | Communication & Polish | Announcements, email templates, remaining features | m8, m9, m11 | complete (all stages) |
| m14-audit-remediation | Code Quality Audit Remediation | H0-H3: test gaps, Form Objects, auth refactor | m13 | complete |
| m15-volunteer-portal-enhancements | Volunteer Portal Enhancements | #117 arrival+gear, #125 banners, #115 magic link re-request, #126 QR+resend, #127 attendance, #105 self-deletion | m14 | complete |
| m16-bugfixes | Bug Fixes | #132 scanner modes, #133 gear-only shifts, #138 VA arrival button, #141 dropdown placeholder, #142 portal cancel errors | m15 | complete |
| m17-reliability-quick-wins | Reliability & Quick Wins | #112 immed. cancel notify, #113 retry strategy, #114 idempotency, #122 tz picker, #135 gear counter, #136 camera pause, #140 signup nav, #143 deletion guard | m16 | complete |
| m18-signup-custom-fields | Signup & Custom Fields Rework | #139 checkbox options + single/multi choice, #134 signup step rework | m17 | complete |
| m19-non-expiring-magic-links | Non-Expiring Volunteer Magic Links | #185 make volunteer portal/ticket magic links non-expiring | m15, m17 | complete |
| issue-175-announcement-recipient-resilience | Announcement Recipient Resilience | #175 token-backed verified recipient resolution for announcements | m13, m18, m19 | complete |
| issue-190-portal-announcement-visibility | Portal Announcement Visibility | #190 null-safe volunteer portal announcements + targeted visibility filtering | m15, issue-175-announcement-recipient-resilience | complete |
| issue-193-guest-mail-magic-link | Guest Mail Magic-Link Fallback | #193 signed browser fallback page for guest invitation QR codes | m12, m15, m19 | complete |
| issue-196-scanner-arrival-duplicate-status | Scanner Arrival Duplicate Status | #196 volunteer-admin scanners should skip arrival duplicate status while entry-staff behavior stays unchanged | m11, m16 | complete |
| issue-168-shifts-jobs-active-state | Jobs & Shifts Active State | #168 active/inactive controls for jobs and shifts with public signup filtering | m10-signup | complete |
| issue-203-priority-shift-gate | Priority Shift Gate | #203 event-level priority gating for public signup with organizer override and progress UI | m10-signup, issue-168-shifts-jobs-active-state | complete |
| issue-206-hide-fully-booked-jobs | Hide Fully Booked Jobs | #206 hide fully booked public-signup jobs while keeping partial/full returning-volunteer context | issue-168-shifts-jobs-active-state, issue-203-priority-shift-gate | complete |
| issue-207-signup-empty-state-notifications | Signup Empty-State Notifications | #207 empty-state opt-in, double opt-in verification, unsubscribe flow, and queued availability notices for public signup | issue-168-shifts-jobs-active-state, issue-203-priority-shift-gate, issue-206-hide-fully-booked-jobs | complete |
| issue-201-gear-scanner-type | Gear Scanner Type | #201 dedicated gear scanner for volunteer and guest gear with pool events and guest group filters | m11-scanner, m12-guest-lists, issue-196-scanner-arrival-duplicate-status | complete |
| issue-222-portal-jwt-refresh | Volunteer Portal JWT Refresh | #222 refresh portal QR JWTs so scanner verification no longer fails with stale signatures | m15-volunteer-portal-enhancements, m11-scanner, m19-non-expiring-magic-links | complete |
| issue-221-signup-grace-minutes | Signup Grace Minutes | #221 event-level public signup cutoff with configurable grace minutes | issue-168-shifts-jobs-active-state, issue-203-priority-shift-gate, issue-206-hide-fully-booked-jobs, issue-207-signup-empty-state-notifications | complete |
| phase-3-invitation-reliability-messaging-ux | Phase 3: Invitation Reliability & Messaging UX | #217 failed guest invitation visibility + resend, #218 sending-active guest-list wording, #220 reminder portal links, #219 guest-pass CTA button | m12-guest-lists, m13-polish, m19-non-expiring-magic-links, issue-193-guest-mail-magic-link | complete |
| phase-4-signup-conflict-ux-coverage | Phase 4: Signup Conflict UX & Coverage | #164 cancelled-then-re-signup overlap coverage, #163 specific overlap conflict messaging | issue-168-shifts-jobs-active-state, issue-203-priority-shift-gate, issue-206-hide-fully-booked-jobs, issue-207-signup-empty-state-notifications, issue-221-signup-grace-minutes | complete |

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
| `.tall-pipeline/m13-polish.md` | plan+impl+test | m13-polish | M13 plan (19 features, 5 phases) + implementation + test stage results | complete |
| `.tall-pipeline/m13-security-audit.md` | security-audit | m13-polish | Security audit report (0 crit, 1 high, 4 med, 5 low, 4 info) | complete |

| `.tall-pipeline/m17-reliability-quick-wins.md` | plan+impl | m17 | M17 plan + implementation tracking | complete |
| `.tall-pipeline/m18-signup-custom-fields.md` | all | m18 | M18 plan+implement+test+security: custom field enhancements + signup step rework | complete |
| `.tall-pipeline/m19-non-expiring-magic-links.md` | plan+implement+test | m19 | Issue #185 plan + implementation tracking for volunteer magic link expiry removal | complete |
| `.tall-pipeline/issue-175-announcement-recipient-resilience.md` | plan+implement+test | issue-175 | Issue #175 plan + implementation tracking for resilient announcement recipients | complete |
| `.tall-pipeline/issue-190-portal-announcement-visibility.md` | plan+implement+test | issue-190 | Issue #190 tracking for null-safe volunteer portal announcements and targeted visibility | complete |
| `.tall-pipeline/issue-193-guest-mail-magic-link.md` | plan+implement+test | issue-193 | Issue #193 tracking for guest invitation magic-link QR fallbacks | complete |
| `.tall-pipeline/issue-196-scanner-arrival-duplicate-status.md` | plan+implement+test | issue-196 | Issue #196 tracking for volunteer-admin scanner duplicate-status regression | complete |
| `.tall-pipeline/issue-168-shifts-jobs-active-state.md` | plan+implement+test | issue-168 | Issue #168 tracking for active/inactive jobs and shifts plus signup filtering | complete |
| `.tall-pipeline/issue-203-priority-shift-gate.md` | plan+implement+test | issue-203 | Issue #203 tracking for event-level priority shift gating, organizer override, and signup UI progress | complete |
| `.tall-pipeline/issue-206-hide-fully-booked-jobs.md` | plan+implement+test | issue-206 | Issue #206 tracking for hiding fully booked jobs from public signup while preserving returning-volunteer visibility | complete |
| `.tall-pipeline/issue-207-signup-empty-state-notifications.md` | plan+implement+test | issue-207 | Issue #207 tracking for empty-state notification opt-in, verification, unsubscribe, and queued event availability notifications | complete |
| `.tall-pipeline/issue-201-gear-scanner-type.md` | plan+implement+test+security | issue-201 | Issue #201 tracking for dedicated gear scanners, pool-scoped volunteer pickups, guest filtering, and scanner E2E coverage | complete |
| `.tall-pipeline/issue-222-portal-jwt-refresh.md` | plan+implement+test+security | issue-222 | Issue #222 tracking for volunteer portal ticket JWT refresh and scanner verification | complete |
| `e2e/volunteer-portal-jwt-refresh.spec.ts` | test | issue-222 | Browser coverage for stale portal QR refresh before scanner verification | complete |
| `.tall-pipeline/issue-221-signup-grace-minutes.md` | plan+implement+test+security | issue-221 | Issue #221 tracking for event-level signup grace minutes and booking cutoff enforcement | complete |
| `e2e/signup-grace-minutes.spec.ts` | test | issue-221 | Browser coverage for organizer-managed signup grace minutes and public shift visibility | complete |
| `.tall-pipeline/phase-3-invitation-reliability-messaging-ux.md` | plan+implement+test+security | phase-3-invitation-reliability-messaging-ux | Phase 3 milestone tracking with implementation, verification, and security-audit outcomes for #217, #218, #220, and #219 | complete |
| `.tall-pipeline/phase-3-invitation-reliability-messaging-ux-security-audit.md` | security-audit | phase-3-invitation-reliability-messaging-ux | Security audit report for invitation state transitions, organizer resend recovery, reminder portal links, and guest-pass CTA surfaces | complete |
| `.tall-pipeline/phase-4-signup-conflict-ux-coverage.md` | all | phase-4-signup-conflict-ux-coverage | Phase 4 milestone tracking for signup overlap reactivation coverage and specific conflict messaging | complete |
| `.tall-pipeline/phase-4-signup-conflict-ux-coverage-security-audit.md` | security-audit | phase-4-signup-conflict-ux-coverage | Security audit report for overlap UX hardening, verified-token resume handling, and public E2E fixture exposure | complete |
| `e2e/signup-conflict-ux.spec.ts` | test | phase-4-signup-conflict-ux-coverage | Browser coverage for public signup overlap messaging, returning-volunteer conflicts, and cancelled-shift reactivation outcomes | complete |

## Conceive
- **Status:** n/a (Phase 1 design complete, Phase 2 design from PO feedback PR #89)

## Maintain
- **Status:** pending
