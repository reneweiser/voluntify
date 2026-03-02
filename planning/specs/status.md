# Voluntify -- Feature Status Dashboard

> **Amended**: See [amendments/001-status-sync-m1-m2-m21-m3p1.md](amendments/001-status-sync-m1-m2-m21-m3p1.md) -- Sync status with implemented milestones

**Last updated**: 2026-03-02

## Summary

| Status | Count |
|---|---|
| Done | 12 |
| In Progress | 2 |
| Not Started | 10 |

## Features

### Milestone 1: Foundation

| # | Feature | Type | Priority | Status | Spec |
|---|---|---|---|---|---|
| 01 | Database schema, models & factories | backend | Must Have | Done | -- |
| 02 | Auth, roles & middleware | backend | Must Have | Done | -- |
| 03 | Organization management | fullstack | Must Have | Done | -- |
| 04 | App layout & navigation | frontend | Must Have | Done | -- |

### Milestone 2: Event Setup & Volunteer Signup

| # | Feature | Type | Priority | Status | Spec |
|---|---|---|---|---|---|
| 05 | Event CRUD | fullstack | Must Have | Done | -- |
| 06 | Jobs & shifts management | fullstack | Must Have | Done | -- |
| 07 | Public event page & volunteer signup | fullstack | Must Have | Done | -- |

### Milestone 2.1: Enhanced Event Setup

| # | Feature | Type | Priority | Status | Spec |
|---|---|---|---|---|---|
| 22 | Optional volunteer phone number | fullstack | Should Have | Done | -- |
| 23 | Event title image upload | fullstack | Should Have | Done | -- |
| 24 | Customizable email templates | fullstack | Should Have | Done | -- |

### Milestone 3: Tickets & QR Scanner

| # | Feature | Type | Priority | Status | Notes |
|---|---|---|---|---|---|
| 09 | Ticket generation & email | backend | Must Have | Done | GenerateTicket action, JWT via JwtKeyService |
| 10 | Magic links & ticket page | fullstack | Must Have | Done | VerifyMagicLink, VolunteerTicket component, QR SVG display |
| 11 | QR scanner PWA | fullstack | Must Have | In Progress | Backend complete (ScannerApiController, RecordArrival, JwtKeyService.validateToken). Frontend PWA not started. |
| 12 | Manual lookup | fullstack | Must Have | In Progress | RecordArrival action supports manual_lookup method. Lookup UI not started. |

### Milestone 4: Attendance & Notifications

| # | Feature | Type | Priority | Status | Spec |
|---|---|---|---|---|---|
| 13 | Attendance tracker | fullstack | Should Have | Not Started | -- |
| 14 | Pre-shift notifications | backend | Should Have | Not Started | -- |

### Milestone 5: Dashboard & Volunteer Management

| # | Feature | Type | Priority | Status | Spec |
|---|---|---|---|---|---|
| 15 | Dashboard | fullstack | Should Have | Not Started | -- |
| 16 | Volunteer list & detail | fullstack | Should Have | Not Started | -- |

### Milestone 6: Polish

| # | Feature | Type | Priority | Status | Spec |
|---|---|---|---|---|---|
| 17 | Event cloning | fullstack | Could Have | Not Started | -- |
| 18 | Volunteer promotion | fullstack | Could Have | Not Started | -- |
| 19 | CSV export | backend | Could Have | Not Started | -- |
| 20 | Dashboard analytics | fullstack | Could Have | Not Started | -- |
| 21 | Browser integration tests | testing | Should Have | Not Started | -- |

### Post-MVP: AI Event Creation

| # | Feature | Type | Priority | Status | Notes |
|---|---|---|---|---|---|
| 08 | AI-powered event creation | fullstack | Should Have | Not Started | BYOK storage infrastructure already built (ai_api_key on organizations, TeamManagement UI) |
