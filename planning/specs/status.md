# Voluntify -- Feature Status Dashboard

> **Amended**: See [amendments/001-status-sync-m1-m2-m21-m3p1.md](amendments/001-status-sync-m1-m2-m21-m3p1.md) -- Sync status with implemented milestones
> **Amended**: See [amendments/002-m3p2-gdpr-email-sync.md](amendments/002-m3p2-gdpr-email-sync.md) -- M3 Part 2 completion, GDPR double opt-in, branded email templates
> **Amended**: See [amendments/003-m4-m5-m6-crosscutting-sync.md](amendments/003-m4-m5-m6-crosscutting-sync.md) -- M4, M5, M6 completion & cross-cutting features sync
> **Major revision 2026-03-30**: Added Phase 2 (Architecture Rework) tracked via GitHub Issues #45–#88

**Last updated**: 2026-03-30

## Summary

| Phase | Status | Count |
|---|---|---|
| Phase 1 (Milestones 1–6) | Done | 32 |
| Phase 2 (Architecture Rework) | Not Started | 42 |

---

## Phase 1: Foundation (All Done)

### Milestone 1: Foundation

| # | Feature | Status |
|---|---|---|
| 01 | Database schema, models & factories | Done |
| 02 | Auth, roles & middleware | Done |
| 03 | Organization management | Done |
| 04 | App layout & navigation | Done |

### Milestone 2: Event Setup & Volunteer Signup

| # | Feature | Status |
|---|---|---|
| 05 | Event CRUD | Done |
| 06 | Jobs & shifts management | Done |
| 07 | Public event page & volunteer signup | Done |

### Milestone 2.1: Enhanced Event Setup

| # | Feature | Status |
|---|---|---|
| 22 | Optional volunteer phone number | Done |
| 23 | Event title image upload | Done |
| 24 | Customizable email templates | Done |

### Milestone 3: Tickets & QR Scanner

| # | Feature | Status |
|---|---|---|
| 09 | Ticket generation & email | Done |
| 10 | Magic links & ticket page | Done |
| 11 | QR scanner PWA | Done |
| 12 | Manual lookup | Done |

### Milestone 4: Attendance & Notifications

| # | Feature | Status |
|---|---|---|
| 13 | Attendance tracker | Done |
| 14 | Pre-shift notifications | Done |

### Milestone 5: Dashboard & Volunteer Management

| # | Feature | Status |
|---|---|---|
| 15 | Dashboard | Done |
| 16 | Volunteer list & detail | Done |

### Milestone 6: Polish

| # | Feature | Status |
|---|---|---|
| 17 | Event cloning | Done |
| 18 | Volunteer promotion | Done |
| 19 | CSV export | Done |
| 20 | Dashboard analytics | Done |
| 21 | Browser integration tests | Done |

### Cross-Cutting

| # | Feature | Status |
|---|---|---|
| 25 | GDPR double opt-in email verification | Done |
| 26 | Voluntify-branded email templates | Done |
| 27 | Per-organization SMTP settings | Done |
| 28 | Log viewer | Done |
| 29 | Organization switching | Done |
| 30 | Scanner event select | Done |
| 31 | Delete user account | Done |
| 32 | Two-factor authentication UI | Done |

---

## Phase 2: Architecture Rework (GitHub Issues #45–#88)

All features tracked as GitHub Issues. Ordered by implementation dependency.

### Foundational

| Issue | Feature | Status | Depends On |
|---|---|---|---|
| #52 | Project: rename Event Group, establish mandatory top-level entity | Not Started | -- |

### Core Architecture

| Issue | Feature | Status | Depends On |
|---|---|---|---|
| #65 | Define role model: Organizer (org/proj), Volunteer Admin, Entry Staff | Not Started | -- |
| #82 | Volunteer: replace name with Vorname + Nachname | Not Started | -- |
| #45 | Event lifecycle: 4-stage model (Draft/Open/Closed/Archived) | Not Started | -- |

### Signup & Volunteer

| Issue | Feature | Status | Depends On |
|---|---|---|---|
| #69 | Multi-step signup flow (project-scoped) | Not Started | #49, #50, #52, #54, #80, #82 |
| #51 | Volunteer portal: project context, QR display, attendance | Not Started | #52, #53, #69 |
| #85 | Shift cancellation: volunteer self-cancellation with digest | Not Started | #47, #51, #52 |
| #88 | Volunteer management: manual creation, organizer actions | Not Started | #51, #52, #69 |
| #49 | Prevent overlapping shift signups | Not Started | -- |
| #50 | Configurable required phone number field | Not Started | -- |
| #80 | Rate limiting for magic links, verification, scanner auth | Not Started | -- |

### Scanner

| Issue | Feature | Status | Depends On |
|---|---|---|---|
| #75 | Scanner management: unified setup for both types | Not Started | #52, #65 |
| #56 | Volunteer Admin Scanner: check-in + gear + attendance | Not Started | #52, #53, #65, #75 |
| #58 | Entry Staff Scanner: QR + color results + guest list | Not Started | #52, #65, #71, #72, #73, #75 |
| #71 | Scanner: green/yellow/red results with manual reactivation button | Not Started | -- |
| #72 | Scanner: time window enforcement + 10-min warning | Not Started | #73, #75 |
| #73 | Scanner: offline encryption + auto-expiry | Not Started | -- |
| #57 | Scanner: shift list tab | Not Started | -- |
| #48 | Scanner: improve touch targets on mobile | Not Started | -- |
| #74 | Configurable hint texts for signup, portal, scanner | Not Started | -- |

### Project Infrastructure

| Issue | Feature | Status | Depends On |
|---|---|---|---|
| #53 | Project: shared Gear with configurable Typ-1 states | Not Started | #52 |
| #54 | Project: shared Custom Registration Fields | Not Started | #52 |
| #47 | Project Settings: email sender config (+ org-level SMTP) | Not Started | #52 |
| #46 | Introduce dedicated Settings area for events | Not Started | -- |
| #60 | Project: member management (Organizer assignment) | Not Started | #52 |
| #62 | Organizer inheritance: org-level auto-access to all projects | Not Started | -- |
| #63 | Remove Vol Admin + Entry Staff from org-level member management | Not Started | -- |

### Content & Email

| Issue | Feature | Status | Depends On |
|---|---|---|---|
| #81 | German email defaults (11 templates) | Not Started | #52, #69, #74, #82, #84 |
| #55 | Event: email templates at event level | Not Started | -- |
| #84 | Re-publish notification (event_updated) | Not Started | -- |
| #83 | Project website: content editor + public event listing | Not Started | #52 |
| #87 | Announcements: manual organizer emails to filtered groups | Not Started | #52 |

### Operations

| Issue | Feature | Status | Depends On |
|---|---|---|---|
| #76 | Dashboard: project tiles, quick actions, search | Not Started | -- |
| #77 | Gear: project-level summary, missing gear report | Not Started | #53 |
| #78 | Clone: project or event with optional date offset | Not Started | -- |
| #79 | Data deletion: cascade rules for project + event | Not Started | -- |
| #64 | Activity log: cover project + event membership changes | Not Started | -- |
| #66 | Rework Promote to Staff: scope to event/project | Not Started | -- |
| #86 | Shifts: optional times with custom display text | Not Started | -- |
| #67 | Fix: MATCH AGAINST syntax error for email search | Not Started | -- |
