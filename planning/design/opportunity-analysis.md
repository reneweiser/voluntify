# Opportunity Analysis: Volunteer Management for Events

**Date**: 2026-02-28
**Domain**: Volunteer management software for small/mid-sized event-running organizations

## Impact-Feasibility Scores

| ID | Pain Point | Impact (1-5) | Feasibility (1-5) | Combined | Quadrant |
|---|---|---|---|---|---|
| PR-1 | No unified signup → ticket → entrance workflow | 5 | 4 | 20 | Quick Win |
| FP-1 | Affordable tools lack QR/scanning | 5 | 4 | 20 | Quick Win |
| SP-1 | Account-creation friction (passwordless) | 5 | 5 | 25 | Quick Win |
| PP-1 | Manual paper check-in at entrance | 4 | 4 | 16 | Quick Win |
| FP-2 | Paying for enterprise features | 4 | 5 | 20 | Quick Win |
| PR-2 | Conflating event arrival vs shift attendance | 4 | 5 | 20 | Quick Win |
| PP-3 | Coordinator tech support burden | 3 | 4 | 12 | Fill-in |
| PR-3 | No-show detection gaps | 4 | 4 | 16 | Quick Win |
| SP-2 | Poor mobile experience | 4 | 3 | 12 | Strategic Bet |
| PP-2 | Rebuilding events from scratch | 3 | 5 | 15 | Fill-in |

## Impact-Effort Matrix

```
        High Impact (5)
             |
     SP-2    |  PR-1  FP-1  SP-1
   Strategic |        FP-2  PR-2
   Bets      |  PP-1  PR-3
             |
        (3)  |
-------------|-------------
             |
   Avoid     |  PP-3  PP-2
             |
             |
        Low Impact (1)
   Low Feasibility (1)    High Feasibility (5)
```

## Opportunity Clusters

### Cluster A: Frictionless Volunteer Lifecycle

**Pain points**: PR-1, FP-1, SP-1, PP-1
**Unifying theme**: The complete journey from volunteer signup to entrance arrival should be seamless, passwordless, and digitally tracked — in a single tool priced for small organizations.
**Product angle**: A unified platform where volunteers sign up without an account, automatically receive QR-coded event tickets, and are scanned at the entrance with offline-capable validation. One tool replaces SignUpGenius + a QR generator + paper check-in lists.
**Combined impact**: Very High — addresses the four highest-scored pain points and fills a gap no competitor occupies at this price point.

### Cluster B: Operational Intelligence

**Pain points**: PR-2, PR-3, PP-2
**Unifying theme**: Better data capture and operational tooling for organizers — distinguishing arrival types, detecting no-shows, and reducing setup repetition.
**Product angle**: Rich attendance tracking (event arrival vs. shift attendance as separate entities), automated no-show flagging, and event cloning to reduce setup time.
**Combined impact**: Medium-High — important for repeat organizers but secondary to the core lifecycle.

### Cluster C: Accessible Experience

**Pain points**: PP-3, SP-2, FP-2
**Unifying theme**: The tool itself shouldn't be a burden — simple enough that volunteers need no help, mobile-optimized for field staff, and priced for the org's actual scale.
**Product angle**: Design simplicity and mobile-first field tools as differentiators against enterprise incumbents.
**Combined impact**: Medium — these are quality attributes that improve the core product rather than standalone features.

## Recommended Focus

**Selected cluster**: Cluster A — Frictionless Volunteer Lifecycle

**Rationale**: This cluster addresses the four highest-impact pain points, occupies a clear competitive gap (no affordable tool combines all three capabilities), and forms a tight, coherent product narrative: "sign up without an account, get a QR ticket, scan at the door." The technical feasibility is high across all four pain points (scores of 4–5). Clusters B and C are natural extensions that enhance Cluster A — they become Should Have and Could Have features rather than separate product concepts.

## MoSCoW Prioritization

### Must Have (MVP-critical)

- **Passwordless volunteer signup with public event pages**: Volunteers browse jobs/shifts on a public page and sign up with only name + email. No account creation. Returning volunteers are recognized by email. — addresses SP-1, PP-3, PR-1
- **QR ticket generation and email distribution**: Upon signup, volunteers receive an event ticket with a unique QR code via email. One ticket per volunteer per event (covering all their shifts). Magic link allows re-accessing the ticket without an account. — addresses FP-1, PR-1
- **Offline QR scanning at entrance**: Entrance Staff scan QR codes on a phone/tablet using a PWA that works without internet. JWT-based tickets are validated client-side. Arrivals are queued in IndexedDB and synced when connectivity returns. Manual name lookup as fallback. — addresses PP-1, FP-1, PR-1, SP-2

### Should Have (v1.0)

- **Pre-shift notifications with job-specific info**: Automated email notifications sent 24h and 4h before each shift, containing job-specific instructions (dress code, parking, check-in location). — addresses PR-1, PP-3
- **Shift attendance verification by admin**: Volunteer Admin marks volunteers as on-time, late, or no-show at their shift station. Separate from entrance arrival. — addresses PR-2, PR-3

### Could Have (post-launch)

- **Event cloning**: Duplicate a previous event's structure (jobs, shifts, capacities) to speed up setup for recurring events. — addresses PP-2
- **Volunteer promotion to admin**: Promote a volunteer to Volunteer Admin role, creating a user account with a temporary password and forced password change on first login. — addresses operational need
- **CSV export**: Export volunteer lists, attendance records, and arrival data for external reporting. — addresses FP-2
- **Dashboard analytics**: Event-level and organization-level summaries — volunteer counts, fill rates, no-show rates, attendance trends. — addresses PR-3

### Won't Have (this time)

- **Mobile wallet integration (Apple Wallet, Google Wallet)**: Adding QR tickets to mobile wallets improves convenience but adds significant complexity (pass signing, wallet API integration). Re-evaluate after launch based on user demand. — addresses SP-2
- **SMS notifications**: Email is sufficient for v1. SMS adds cost (per-message pricing) and complexity (carrier integration, opt-in compliance). — addresses PP-3
- **Multi-language support**: Important for international events but a significant i18n effort. Build English-first, add languages based on user base. — addresses SP-1
- **Payment collection**: Volunteer events are unpaid by definition. Some orgs charge attendees but that's a different product (Eventbrite). — not applicable

## MVP Feature Summary

| # | Feature | Priority | Addresses | Impact | Feasibility |
|---|---|---|---|---|---|
| 1 | Passwordless volunteer signup + public event pages | Must Have | SP-1, PP-3, PR-1 | 5 | 5 |
| 2 | QR ticket generation + email distribution | Must Have | FP-1, PR-1 | 5 | 4 |
| 3 | Offline QR scanning at entrance + manual lookup | Must Have | PP-1, FP-1, PR-1, SP-2 | 5 | 4 |
| 4 | Pre-shift notifications with job-specific info | Should Have | PR-1, PP-3 | 4 | 4 |
| 5 | Shift attendance verification | Should Have | PR-2, PR-3 | 4 | 5 |

## User Validation Notes

The MoSCoW prioritization directly reflects the user's requirements specification, which emphasized three core workflows in order of importance: (1) volunteer recruiting with passwordless signup, (2) QR ticket distribution, and (3) entrance validation with offline scanning. The user explicitly specified the distinction between event arrival and shift attendance, confirming PR-2 as a real concern. Pre-shift notifications with job-specific info and attendance verification were described as important but secondary to the core lifecycle.
