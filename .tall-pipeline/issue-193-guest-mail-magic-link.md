# Milestone: issue-193-guest-mail-magic-link — Guest Mail Magic-Link Fallback

**GitHub Issue:** [#193](https://github.com/reneweiser/voluntify/issues/193)
**Features:** #193
**Dependencies:** m12-guest-lists, m15-volunteer-portal-enhancements, m19-non-expiring-magic-links
**Branch:** fix/193-guest-mail-magic-link

## Plan
- **Status:** complete
- **Gate summary:** replace mail-client-only dependence on inline SVG with a per-entry signed browser fallback page, while keeping the existing SVG mail rendering for clients that support it.

### Scope
- Add a signed public guest pass route and read-only Blade page for a single `GuestEntry`
- Add `GuestEntry::guestPassUrl()` with scanner-based expiry and a defensive fallback TTL
- Update guest invitation mails to include a per-entry browser fallback link under the existing QR block
- Add guest-friendly invalid or expired-link handling plus no-store and noindex protections
- Add regression coverage for helper URL generation, public route behavior, and multi-entry mail rendering

## Implement
- **Status:** complete
- **Iteration:** 1
- **Tasks:**
  - [x] AC-1: Write failing tests for `GuestEntry::guestPassUrl()` signed URL generation and expiry branches
  - [x] AC-2: Write failing HTTP tests for the signed guest pass route, invalid or expired-link handling, and response hardening
  - [x] AC-3: Write failing mail tests for per-entry fallback links in grouped guest invitation emails
  - [x] AC-4: Add the signed route, controller, model helper, and guest pass Blade view
  - [x] AC-5: Update exception rendering for guest-friendly invalid or expired signed links
  - [x] AC-6: Eager load required guest relations in the mail job and update the mail template with per-entry browser links
  - [x] Verify: run focused Sail tests, Pint, and affected regression coverage
- **Gate summary:** signed guest pass fallback links now render per entry in guest invitation emails, open a read-only browser QR page, and return a guest-friendly 403 for invalid or expired links without exposing cacheable or indexable content.

## Test
- **Status:** complete
- **Gate summary:** RED confirmed on missing `guestPassUrl()`, missing `guest.pass.show`, and absent mail fallback links. GREEN verified with focused Sail coverage for `GuestEntryTest`, `GuestInvitationMailTest`, `GuestPassControllerTest`, `SendGuestInvitationsJobTest`, `GuestListLifecycleTest`, `VolunteerTicketTest`, and `EmailVerificationTest`, plus `vendor/bin/sail bin pint --dirty --format agent`.

## Cross-Milestone Interface

| Category | Items |
|---|---|
| Jobs | `ConfirmGuestListJob`, `SendGuestInvitationsJob` |
| Models | `GuestEntry`, `GuestGroup`, `GuestList`, `ProjectScanner`, `Ticket`, `MagicLinkToken` |
| Public UI | `layouts/public.blade.php`, `Public\VolunteerTicket`, volunteer public-route patterns |
| Mail | `GuestInvitationMail`, `resources/views/mail/guest-invitation.blade.php` |
| Tests | `GuestInvitationMailTest`, `GuestListLifecycleTest`, `SendGuestInvitationsJobTest`, `TicketTest`, `EmailVerificationTest` |
