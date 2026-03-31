# Project Spec: Voluntify

> **Amended**: See [amendments/001-status-sync-m1-m2-m21-m3p1.md](amendments/001-status-sync-m1-m2-m21-m3p1.md) -- Status synced with M1, M2, M2.1, M3 Part 1 implementation
> **Amended**: See [amendments/002-m3p2-gdpr-email-sync.md](amendments/002-m3p2-gdpr-email-sync.md) -- M3 Part 2 completion, GDPR double opt-in, branded email templates
> **Amended**: See [amendments/003-m4-m5-m6-crosscutting-sync.md](amendments/003-m4-m5-m6-crosscutting-sync.md) -- M4, M5, M6 completion & cross-cutting features sync
> **Major revision 2026-03-30**: Architecture reworked — Projects as mandatory top-level entity, scanner-based staff (no accounts), 4-stage event lifecycle, project-scoped QR codes, configurable gear states. See GitHub Issues #45–#88.

**Date**: 2026-03-01 (revised 2026-03-30)
**Status**: Active
**Design docs**: `planning/design/` (domain-landscape, pain-points, opportunity-analysis, app-concept, app-design-spec)

---

## Vision

For event organisers who struggle with volunteer coordination, Voluntify is a project-based volunteer management platform that lets volunteers sign up without an account, delivers project-wide QR codes via magic links, and validates them at the entrance — even offline. Unlike SignUpGenius or VolunteerHub, it combines the entire volunteer lifecycle in one affordable tool.

## Goals

| ID | Goal | Measures | Traces to |
|---|---|---|---|
| G1 | Unified volunteer lifecycle — signup through gear pickup — in a single tool | All core workflows (signup, QR, scan, gear, attendance) without external tools | PR-1, FP-1 |
| G2 | Zero-friction volunteer signup | Multi-step signup with only email required; no account creation | SP-1, PP-3 |
| G3 | Affordable QR scanning with offline capability | Project-wide QR codes + offline-capable PWA scanner at $0 infrastructure cost | FP-1, PP-1 |
| G4 | Separate event arrival from shift attendance | Entry Staff Scanner for arrival, Volunteer Admin Scanner for attendance | PR-2, PR-3 |
| G5 | Project-based resource sharing | Gear, custom fields, volunteers shared across events within a project | #52, #53, #54 |

## Non-Goals

- **Mobile wallet integration** (Apple Wallet, Google Wallet) — re-evaluate post-launch
- **SMS notifications** — email is sufficient; SMS adds cost and compliance complexity
- **Payment collection** — volunteer events are unpaid; paid ticketing is a different product

## Users & Roles

| Role | Auth | Description |
|---|---|---|
| **Organizer** | Permanent account (Fortify) | Full admin. Exists at org-level (all projects) or project-level (specific project) |
| **Volunteer Admin** | No account — temporary scanner link | On-the-ground: check-in, attendance, gear pickup via Volunteer Admin Scanner |
| **Entry Staff** | No account — temporary scanner link | Entrance control: QR scanning, green/yellow/red result screens via Entry Staff Scanner |
| **Volunteer** | No account (passwordless) | Signs up via project website, receives project-wide QR code via magic link |

> Why no accounts for Volunteer Admin and Entry Staff? These roles are typically filled by volunteers or short-term helpers assigned to a single time window. Temporary scanner links eliminate onboarding friction, automatically expire, and use encrypted local storage.

Resolution order: Scanner config > Project membership > Organisation membership.

## Core User Flows

| # | Flow | Actor | Trigger | Key outcome |
|---|---|---|---|---|
| 1 | Project & Event Setup | Organizer | Creates project + events | Published events on project website with jobs, shifts, gear |
| 2 | Volunteer Signup | Volunteer | Opens project website | 4-step signup: email → data + shifts → custom fields + gear → confirm |
| 3 | Email Verification | System → Volunteer | New volunteer enters email | Verification + welcome email with portal magic link |
| 4 | Pre-Shift Notifications | System → Volunteer | Scheduled (24h, 4h before shift) | Reminder with job-specific instructions |
| 5 | Entrance Scanning | Entry Staff | Event day at gate | QR validated (offline-capable), green/yellow/red result, manual button to proceed |
| 6 | Shift Check-in + Gear | Volunteer Admin | Shift time | Check-in, attendance, gear pickup — all via scanner |
| 7 | Manual Volunteer Lookup | Volunteer Admin | Volunteer can't present QR | Found by name/email search, check-in recorded |
| 8 | Manual Volunteer Creation | Organizer | Adds volunteer directly | Email sent with portal link for completion |
| 9 | Announcements | Organizer | Ad-hoc communication | Filtered email to volunteer groups (event/job/shift) |
| 10 | Volunteer Promotion | Organizer | Promotes volunteer to staff | Assigned to scanner (Vol Admin) or account created (Organizer) |

## Tech Stack

| Layer | Choice | Notes |
|---|---|---|
| Framework | Laravel 12 | |
| Frontend | Livewire 4 + Flux UI (v2) | Server-rendered; minimal JS except scanner |
| Styling | Tailwind CSS 4 | |
| Auth (staff) | Laravel Fortify | Login, password reset, 2FA |
| Auth (volunteers) | Magic links | SHA-256 hashed tokens, no account needed |
| Auth (scanner staff) | Temporary scanner links | One-time code, time-window bound |
| QR generation | `chillerlan/php-qrcode` | Server-side, SVG/PNG |
| QR scanning | `jsQR` | Client-side JS, on-device decoding |
| JWT | `firebase/php-jwt` | HS256, per-event per-period key derived from APP_KEY |
| Offline | Service Worker + IndexedDB + Web Crypto API | PWA scanner with encrypted local storage |
| Testing | Pest 4 | TDD throughout |
| Queue | Laravel Queue (database driver) | Emails, notifications |
| Database | SQLite (dev) / MariaDB (prod) | |

## Architecture Overview

### Entity Hierarchy

```
Organisation
└── Projekt (mandatory top-level entity)
    ├── Projektwebsite (/p/{token})
    ├── Projekteinstellungen
    │   ├── Allgemein, E-Mail (SMTP override)
    │   ├── Custom Fields (project-level)
    │   ├── Gear (Typ 1 + Typ 2)
    │   ├── Scanner (Entry Staff + Volunteer Admin)
    │   └── Mitglieder (Organizer only)
    ├── Volunteers (belong to project, not events)
    └── Events
        ├── Jobs → Schichten (times optional, custom display text)
        ├── Anmeldungen (Volunteers → Schichten)
        ├── Event-Einstellungen (name, date, location, deadline, grace period)
        ├── E-Mail-Vorlagen (per-event, fall back to system defaults)
        └── Event-Status (Draft / Published Open / Published Closed / Archived)
```

### Layered Architecture

```
Routes / Middleware
    └── Livewire Components (adapter — validation, UI state)
        └── Actions (domain — business logic, orchestration)
            ├── Models / Eloquent (persistence)
            ├── Policies (authorization)
            ├── Notifications / Jobs (side effects, queued)
            └── Domain Exceptions (business rule violations)
```

### Key Patterns

- **PHP Enums**: All status/role/method fields use backed string enums with `$casts`. No string comparisons.
- **Multi-tenancy**: `ResolveOrganization` middleware binds current org to the container. All queries scoped through org relationships.
- **Authorization**: Laravel Policies per model. Org-level vs project-level Organizer resolved via explicit membership hierarchy.
- **Action orchestration**: Single-responsibility Actions with `execute()`. Complex flows orchestrate sub-Actions via constructor injection.
- **Validation**: At adapter boundary (Livewire `#[Validate]`, Form Requests). Actions trust typed inputs.
- **Domain exceptions**: Business rule violations throw domain-specific exceptions.
- **DTOs**: Used when Actions accept >3 parameters. Readonly classes, no behavior.

### Per-Organisation Email

SMTP configured at organisation level (default for all projects). Projects can override with their own SMTP. System mailer as fallback for organiser-facing alerts.

**Hierarchy:** Project SMTP → Organisation SMTP → System mailer.

### Scanner Architecture

Two scanner types, both PWA-based:

| Scanner | Data Cached | Requires Online |
|---|---|---|
| Entry Staff | Name, ticket auth, check-in status (minimal) | No (offline-capable) |
| Volunteer Admin | Name, shifts, check-in, gear, attendance (extended) | No for check-in; Yes for Typ-2 gear pickup |

Both use encrypted IndexedDB with session keys from the server. Hard expiry: end of time window or 3 days.

## Domain Model (Revised)

```
[organisations] 1───N [projects]
[organisations] N───M [users] (via organisation_user with role)
[projects] 1───N [events]
[projects] 1───N [volunteers] (belong to project, not event)
[projects] 1───N [project_gear_items]
[projects] 1───N [custom_registration_fields] (project-level)
[projects] 1───N [scanners]
[events] 1───N [volunteer_jobs]
[events] 1───N [email_templates]
[events] 1───N [custom_registration_fields] (event-level)
[volunteer_jobs] 1───N [shifts]
[shifts] N───M [volunteers] (via shift_signups)
[volunteers] 1───N [shift_signups]
[shift_signups] 1───0..1 [attendance_records]
[volunteers] 1───1 [tickets] (one per project, not per event)
[volunteers] 1───N [event_arrivals]
[volunteers] 1───N [volunteer_gear] (per gear item)
[volunteer_gear] 1───N [volunteer_gear_pickups]
[volunteers] 1───N [custom_field_responses]
[volunteers] 1───N [magic_link_tokens]
[scanners] 1───N [scanner_assignees]
[projects] 1───N [announcements]
[organisations] 1───N [announcement_templates]
```

### Key Model Changes from v1

| v1 | v2 | Reason |
|---|---|---|
| Events belong to Organisation | Events belong to Project (#52) | Shared resources need a home |
| Event Groups (optional) | Projects (mandatory) (#52) | No orphan events; volunteers belong to project |
| Ticket per event | QR code per project (#51) | One code for all events in the project |
| Volunteer Admin: User account | Scanner link (no account) (#56) | Short-term role, frictionless access |
| Entry Staff: User account | Scanner link (no account) (#58) | Short-term role, frictionless access |
| EventGearItem | ProjectGearItem (#53) | Gear shared across events |
| Hardcoded gear states | Configurable state list (#53) | Different orgs need different workflows |
| 3-stage lifecycle | 4-stage lifecycle (#45) | Separate "visible" from "accepting signups" |
| Event-level custom fields only | Project + event-level (#54) | Project fields asked once, pre-filled on return |

## Email System

11 system email types, configured per event with system defaults:

| Type | Trigger |
|---|---|
| `signup_confirmation` | Signup completed |
| `email_verification` | New user verifies email |
| `volunteer_welcome` | After verification — portal link |
| `volunteer_added_by_organizer` | Organizer manually creates volunteer |
| `pre_shift_reminder_24h` | 24h before shift |
| `pre_shift_reminder_4h` | 4h before shift |
| `event_updated` | Re-publish after maintenance |
| `event_announcement` | Manual announcement by Organizer |
| `staff_invitation` | Invitation as team member |
| `volunteer_promoted` | Volunteer promoted to staff |
| `added_to_org` | User added to organisation |

Placeholders: `{{vorname}}`, `{{nachname}}`, `{{portal_link}}`, `{{kontakt_email}}`, `{{gear_zusammenfassung}}`, `{{organizer_note}}`, etc.

## Feature Breakdown

### Phase 1: Foundation (Milestones 1–6) — DONE

All 32 features from the original spec are implemented. See [status.md](status.md) for details.

### Phase 2: Architecture Rework (GitHub Issues #45–#88)

The v2 architecture introduces projects, scanner-based staff, and project-scoped resources. All features are tracked as GitHub Issues:

**Foundational:**
- #52 Project rename (basis for everything)

**Core Architecture:**
- #65 Role model (Org/Proj Organizer, scanner-based staff)
- #82 Vorname/Nachname (separate fields)
- #45 Event lifecycle (4 stages)

**Signup & Volunteer:**
- #69 Multi-step signup flow
- #51 Volunteer portal (project context, QR display)
- #85 Shift cancellation (self-service)
- #88 Volunteer management (manual creation, organizer actions)

**Scanner:**
- #75 Scanner management (unified setup)
- #56 Volunteer Admin Scanner (check-in + gear)
- #58 Entry Staff Scanner (QR + color results)
- #71 Green/yellow/red results (manual button, no auto-dismiss)
- #72 Time window enforcement
- #73 Offline encryption

**Content & Email:**
- #81 German email defaults (11 templates)
- #55 Email templates (event-level config)
- #84 Re-publish notification
- #83 Project website
- #87 Announcements

**Operations:**
- #76 Dashboard (project tiles, quick actions)
- #77 Gear summary
- #78 Clone (project or event)
- #79 Deletion (cascade rules)

See [status.md](status.md) for the complete list with statuses.

## Constraints

- **Solo developer** — features are sequential
- **Laravel 12 + Livewire 4 + Flux UI** — tech stack is fixed
- **No paid external services** — self-hostable, no Twilio, no cloud APIs
- **Database queue driver** — no Redis dependency

## Testing Strategy

Every feature follows **red-green-refactor**. No feature is marked complete without passing behavior tests.

| Layer | Test Type | Tool |
|---|---|---|
| Actions | Integration (real DB) | Pest + `RefreshDatabase` |
| Livewire components | Integration (Livewire helpers) | Pest + Livewire test helpers |
| Scanner API | Feature (HTTP) | Pest + `RefreshDatabase` |
| JWT/QR generation | Unit (pure logic) | Pest |
| PWA offline flow | Browser | Playwright |

Mock rules: Mock at system boundaries only (`Notification::fake()`, `Queue::fake()`, `Mail::fake()`). Never mock Actions, Policies, or Models.
