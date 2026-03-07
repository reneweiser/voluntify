# Project Spec: Voluntify

> **Amended**: See [amendments/001-status-sync-m1-m2-m21-m3p1.md](amendments/001-status-sync-m1-m2-m21-m3p1.md) -- Status synced with M1, M2, M2.1, M3 Part 1 implementation
> **Amended**: See [amendments/002-m3p2-gdpr-email-sync.md](amendments/002-m3p2-gdpr-email-sync.md) -- M3 Part 2 completion, GDPR double opt-in, branded email templates
> **Amended**: See [amendments/003-m4-m5-m6-crosscutting-sync.md](amendments/003-m4-m5-m6-crosscutting-sync.md) -- M4, M5, M6 completion & cross-cutting features sync

**Date**: 2026-03-01
**Status**: Active
**Design docs**: `planning/design/` (domain-landscape, pain-points, opportunity-analysis, app-concept, app-design-spec)

---

## Vision

For event organizers who struggle with volunteer coordination, Voluntify is a volunteer management platform that lets volunteers sign up without an account, delivers QR-coded event tickets, and validates them offline at the entrance. Unlike SignUpGenius or VolunteerHub, it combines the entire volunteer lifecycle in one affordable tool.

## Goals

| ID | Goal | Measures | Traces to |
|---|---|---|---|
| G1 | Unified volunteer lifecycle — signup through entrance — in a single tool | All 3 core workflows (signup, ticket, scan) functional without external tools | PR-1, FP-1 |
| G2 | Zero-friction volunteer signup | Signup requires only name + email; no account creation, no password | SP-1, PP-3 |
| G3 | Affordable QR scanning with offline capability | QR ticket generation + offline-capable PWA scanner at $0 infrastructure cost | FP-1, PP-1 |
| G4 | Separate event arrival from shift attendance | Two distinct entities (`event_arrivals`, `attendance_records`) with different actors | PR-2, PR-3 |

## Non-Goals

These are explicitly out of scope (Won't Have — see `planning/design/opportunity-analysis.md`):

- **Mobile wallet integration** (Apple Wallet, Google Wallet) — re-evaluate post-launch based on demand
- **SMS notifications** — email is sufficient for v1; SMS adds cost and compliance complexity
- **Multi-language support** — build English-first, add i18n based on user base
- **Payment collection** — volunteer events are unpaid; paid ticketing is a different product

## Users & Roles

| Role | Auth | Description |
|---|---|---|
| **Organizer** | User account (Fortify) | Full admin: creates events, manages team, configures org settings |
| **Volunteer Admin** | User account (Fortify) | On-the-ground: marks shift attendance (on_time/late/no_show) |
| **Entrance Staff** | User account (Fortify) | Gate operations: scans QR tickets, manual volunteer lookup |
| **Volunteer** | No account (passwordless) | Signs up with name + email, receives QR ticket via magic link |

Roles are per-organization via the `organization_user` pivot table. See `planning/design/domain-landscape.md` for detailed persona descriptions.

## Core User Flows

Seven flows drive the product. Full step-by-step tables are in `planning/design/app-design-spec.md` § Core User Flows.

| # | Flow | Actor | Trigger | Key outcome |
|---|---|---|---|---|
| 1 | Event Setup | Organizer | Creates a new event | Published event with jobs, shifts, and shareable public URL |
| 2 | Volunteer Sign-Up | Volunteer | Opens public event link | Signed up for shift, confirmation email with magic link to QR ticket |
| 3 | Pre-Shift Notifications | System → Volunteer | Scheduled (24h, 4h before shift) | Volunteer receives job-specific instructions by email |
| 4 | Shift Attendance | Volunteer Admin | Shift start time | Each volunteer marked on_time / late / no_show |
| 5 | Entrance QR Scanning | Entrance Staff | Event day at gate | QR validated (offline-capable), arrival recorded |
| 6 | Manual Lookup | Entrance Staff | Volunteer can't present QR | Found by name search, arrival recorded (method=manual_lookup) |
| 7 | Volunteer Promotion | Organizer | Promotes volunteer to staff | User account created with temp password, role assigned |

## Tech Stack

| Layer | Choice | Notes |
|---|---|---|
| Framework | Laravel 12 | |
| Frontend | Livewire 4 + Flux UI (v2.9+) | Server-rendered; minimal JS except scanner |
| Styling | Tailwind CSS 4 | |
| Auth (staff) | Laravel Fortify | Login, password reset, forced password change |
| Auth (volunteers) | Magic links | SHA-256 hashed tokens, no account needed |
| QR generation | `chillerlan/php-qrcode` | Server-side, SVG/PNG |
| QR scanning | `jsQR` | Client-side JS, on-device decoding |
| JWT | `firebase/php-jwt` | HS256, per-event per-period key derived from APP_KEY |
| Offline | Service Worker + IndexedDB | PWA pattern for scanner |
| Testing | Pest | TDD throughout |
| Queue | Laravel Queue (database driver) | Emails, notifications |
| Database | SQLite (dev) / MySQL or PostgreSQL (prod) | |

Full architecture details (QR/JWT, offline sync, middleware) in `planning/design/app-design-spec.md` § Tech Stack.

## Architecture Overview

Extracted from `planning/design/app-design-spec.md` § Architectural Guardrails.

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

- **PHP Enums**: All status/role/method fields use backed string enums with `$casts`. No string comparisons. Cases are PascalCase. Migrations use `->string()` columns.
- **Multi-tenancy**: `ResolveOrganization` middleware binds current org to the container as a typed singleton (`app()->instance(Organization::class, $org)`). Actions receive it via constructor injection (`__construct(private Organization $organization)`), keeping the dependency explicit, type-safe, and testable. All queries scoped through org relationships (`$org->events()`), never unscoped. Routes outside org context (org creation, org switching) bypass this middleware. Queue jobs resolve their org from the job payload — never rely on container state from the web request.
- **Authorization**: Laravel Policies per model, called from Actions. Livewire components contain no auth logic. Route middleware for broad role gating.
- **Action orchestration**: Actions are single-responsibility with `execute()`. Complex flows (e.g., `SignUpVolunteer`) orchestrate sub-Actions via constructor injection. Side effects dispatched as queued jobs/notifications. Actions return Eloquent models (or scalars/void) — no DTO return layer. In a Livewire app, models go directly to Blade templates; wrapping them in response DTOs would be pure ceremony.
- **Validation**: Lives at the adapter boundary (Livewire `#[Validate]` attributes, Form Requests for API). Actions trust their typed inputs.
- **Domain exceptions**: Business rule violations throw domain-specific exceptions extending `App\Exceptions\DomainException`. Livewire components catch and translate to UI feedback.
- **DTOs**: Used when Actions accept >3 parameters. Readonly classes, no behavior.
- **Value Objects**: Used sparingly, only for security-critical primitives where misuse has real consequences. `HashedToken` wraps magic link tokens (hashes on construction, timing-safe comparison). `PublicToken` wraps event public tokens (generates and validates format). Located in `app/ValueObjects/`. General primitives (email, name) remain plain strings — Value Objects are not justified where misuse risk is low.

### Middleware

| Middleware | Purpose |
|---|---|
| `RequirePasswordChange` | Redirects to `/change-password` if `must_change_password` is true |
| `ResolveOrganization` | Resolves current org from user memberships, binds as typed singleton (`Organization::class`) |

### Per-Organization Email

`OrganizationMailerService` dynamically registers a named SMTP mailer (`org-{id}`) from the organization's SMTP settings (host, port, credentials, encryption). Falls back to the app default mailer when no SMTP is configured. Volunteer-facing notifications apply the `UsesOrganizationMailer` trait to route through the correct mailer.

## Feature Breakdown

### Milestone 1: Foundation

| # | Feature | Type | Priority | Pain points |
|---|---|---|---|---|
| 01 | Database schema, models & factories | backend | Must Have | — |
| 02 | Auth, roles & middleware | backend | Must Have | SP-1 |
| 03 | Organization management | fullstack | Must Have | — |
| 04 | App layout & navigation | frontend | Must Have | SP-2 |

**Outcome**: Authenticated staff can log in, belong to an organization, navigate the app shell. All 15 entities exist with factories ready for TDD.

### Milestone 2: Event Setup & Volunteer Signup

| # | Feature | Type | Priority | Pain points |
|---|---|---|---|---|
| 05 | Event CRUD | fullstack | Must Have | PR-1 |
| 06 | Jobs & shifts management | fullstack | Must Have | PR-1 |
| 07 | Public event page & volunteer signup | fullstack | Must Have | SP-1, PP-3, PR-1, FP-1 |

**Outcome**: Organizer creates events with jobs/shifts, publishes them. Volunteers sign up passwordlessly on a public page. Feature 07 is the **tracer bullet** — the `SignUpVolunteer` Action orchestrates ticket generation, magic link creation, and notification dispatch end-to-end.

### Milestone 2.1: Enhanced Event Setup

| # | Feature | Type | Priority | Pain points |
|---|---|---|---|---|
| 22 | Optional volunteer phone number | fullstack | Should Have | PP-3 |
| 23 | Event title image upload | fullstack | Should Have | PP-3, PR-1 |
| 24 | Customizable email templates | fullstack | Should Have | PP-3, FP-1 |

**Outcome**: Volunteer signup collects optional phone number. Organizers can upload hero images for events (displayed on public page and admin overview). Organizers can customize automated email templates (signup confirmation, pre-shift reminders) per event with placeholder variables. Falls back to built-in defaults when no custom template exists.

### Milestone 3: Tickets & QR Scanner

| # | Feature | Type | Priority | Pain points |
|---|---|---|---|---|
| 09 | Ticket generation & email | backend | Must Have | FP-1, PR-1 |
| 10 | Magic links & ticket page | fullstack | Must Have | FP-1 |
| 11 | QR scanner PWA | fullstack | Must Have | PP-1, FP-1, SP-2 |
| 12 | Manual lookup | fullstack | Must Have | PP-1 |

**Outcome**: All Must Have features complete. Volunteers receive QR tickets, view them via magic link, and are scanned at the entrance (online or offline). Manual name lookup as fallback.

Note: Features 09 and 10 provide the backend Actions (`GenerateTicket`, `GenerateMagicLink`) that feature 07 orchestrates. They get their own focused behavior tests first, then integrate into the signup flow.

### Milestone 4: Attendance & Notifications

| # | Feature | Type | Priority | Pain points |
|---|---|---|---|---|
| 13 | Attendance tracker | fullstack | Should Have | PR-2, PR-3 |
| 14 | Pre-shift notifications | backend | Should Have | PR-1, PP-3 |

**Outcome**: Volunteer Admins track shift attendance. Volunteers receive automated reminders with job-specific instructions.

### Milestone 5: Dashboard & Volunteer Management

| # | Feature | Type | Priority | Pain points |
|---|---|---|---|---|
| 15 | Dashboard | fullstack | Should Have | — |
| 16 | Volunteer list & detail | fullstack | Should Have | PR-3 |

**Outcome**: Role-adaptive dashboard with key metrics. Staff can search, filter, and view volunteer details per event.

### Milestone 6: Polish

| # | Feature | Type | Priority | Pain points |
|---|---|---|---|---|
| 17 | Event cloning | fullstack | Could Have | PP-2 |
| 18 | Volunteer promotion | fullstack | Could Have | — |
| 19 | CSV export | backend | Could Have | FP-2 |
| 20 | Dashboard analytics | fullstack | Could Have | PR-3 |
| 21 | Browser integration tests | testing | Should Have | — |

**Outcome**: Event cloning, volunteer promotion flow, CSV export, and dashboard analytics shipped. Playwright MCP runbook covers 4 browser test scenarios.

## Cross-Cutting Concerns

### Testing Strategy (TDD)

Every feature follows **red-green-refactor**. Each feature's `tasks.md` interleaves failing test → minimal implementation → refactor. No horizontal slicing (all tests first, then all code).

**Testing layers**:

| Layer | Test Type | Tool | Mocking |
|---|---|---|---|
| Actions | Integration (real DB) | Pest + `RefreshDatabase` | Mock queue/mail/notifications at boundary only |
| Livewire components | Integration (Livewire helpers) | Pest + Livewire test helpers | Test through public interface |
| ScannerController API | Feature (HTTP) | Pest + `RefreshDatabase` | No internal mocks |
| JWT/QR generation | Unit (pure logic) | Pest | No mocks |
| PWA offline flow | Browser | Playwright or Dusk | Real browser, IndexedDB |

**Mocking rules**:
- Mock at system boundaries only: `Notification::fake()`, `Queue::fake()`, `Mail::fake()`, `Http::fake()` (for Anthropic API)
- Never mock Actions, Policies, or Models — use real instances with test factories
- `QUEUE_CONNECTION=sync` in `phpunit.xml` so queued jobs run synchronously in tests

**Factory requirements**:
- Every model gets a factory when introduced (feature 01)
- Factories define named states for common scenarios: `Event::factory()->published()`, `Shift::factory()->full()`, etc.

**Test naming**: Describe behavior, not implementation:
- "a volunteer can sign up for a published event shift"
- "signing up for a full shift throws ShiftFullException"
- NOT "SignUpVolunteer calls GenerateTicket"

**No feature is marked complete without passing behavior tests.**

**Feature 21 scope** (Browser integration tests):
- QR scanner offline flow (requires real browser for Service Worker + IndexedDB)
- Critical smoke paths (login → create event → signup → scan ticket)
- Everything else is covered by Pest tests written during TDD

### Security

- Volunteer data (name, email) is minimal by design — no passwords stored for volunteers
- Magic link tokens are SHA-256 hashed before storage with expiration
- JWT signing keys are per-event, per-period (rotate at 4 AM local) — limits exposure if IndexedDB is compromised
- Scanner validates current + previous period keys (dual-key fallback for boundary events)
- `public_token` on events prevents ID enumeration
- Multi-tenancy enforced at query level — never unscoped model queries in authenticated contexts

### Email

All emails are dispatched as queued notifications (database queue driver):
- `SignupConfirmation` — event details + magic link to ticket
- `EmailVerification` — GDPR double opt-in verification link
- `PreShiftReminder` — 24h and 4h before shift with job-specific instructions
- `StaffInvitation` — team member invite with temp password
- `VolunteerPromoted` — temp password + login link

Volunteer-facing notifications use the `UsesOrganizationMailer` trait to route through per-org SMTP when configured (see `OrganizationMailerService`).

**Customizable Templates (M2.1)**: Organizers can customize subject and body of automated emails per event via the `email_templates` table. The `EmailTemplateRenderer` service resolves custom templates by `[event_id, type]`, falling back to built-in defaults. Templates support `{{placeholder}}` variables: `volunteer_name`, `event_name`, `job_name`, `shift_date`, `shift_time`, `event_location`. Template types: `signup_confirmation`, `pre_shift_reminder_24h`, `pre_shift_reminder_4h`.

## Constraints

- **Solo developer** — no team parallelization; features are sequential
- **Laravel 12 + Livewire 4 + Flux UI** — tech stack is fixed
- **No paid external services** — no Twilio, no cloud scanning APIs. Self-hostable.
- **Database queue driver** — no Redis dependency

## Domain Model

15 entities — full schema in `planning/design/app-concept.md` § Domain Model. Ubiquitous language glossary in the same section.

```
[organizations] 1───N [events]
[organizations] N───M [users] (via organization_user with role)
[events] 1───N [volunteer_jobs]
[events] 1───N [email_templates]
[volunteer_jobs] 1───N [shifts]
[shifts] N───M [volunteers] (via shift_signups)
[volunteers] 1───N [shift_signups]
[shift_signups] 1───0..1 [attendance_records]
[volunteers] 1───N [tickets] (one per event)
[tickets] 1───N [event_arrivals]
[volunteers] 1───N [event_arrivals]
[volunteers] 0..1───1 [users] (when promoted)
[volunteers] 1───N [magic_link_tokens]
[volunteers] 1───0..1 [volunteer_promotions]
[volunteers] 1───N [email_verification_tokens]
```
