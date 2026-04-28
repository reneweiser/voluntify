# Milestone: issue-175-announcement-recipient-resilience — Announcement Recipient Resilience

**GitHub Issue:** [#175](https://github.com/reneweiser/voluntify/issues/175)
**Features:** #175
**Dependencies:** m13-polish, m18-signup-custom-fields, m19-non-expiring-magic-links

## Plan
- **Status:** complete
- **Gate summary:** keep verified-only announcement policy, but resolve recipients from persisted volunteer verification or matching verified email-token history

### Scope
- Extract one shared announcement recipient query for preview and send
- Preserve current event/job/shift filtering semantics
- Treat volunteers as eligible when `email_verified_at` is present or a matching verified `email_verification_tokens` row exists
- Protect counts against duplicate token rows with a correlated existence check
- Add regression tests for preview/send parity and token-backed eligibility

## Implement
- **Status:** complete
- **Iteration:** 1
- **Tasks:**
  - [x] AC-1: Write failing preview-count tests for token-backed eligible volunteers and excluded unverified volunteers
  - [x] AC-2: Write failing send-action tests for token-backed recipients, exclusion of unverified recipients, and preview/send parity
  - [x] AC-3: Implement shared recipient query and reuse it in `AnnouncementComposer` and `SendAnnouncement`
  - [x] AC-4: Add duplicate-token regression coverage to lock in `whereExists` behavior
  - [x] Verify: run targeted Sail tests, Pint, and full Sail test suite
- **Gate summary:** shared token-aware recipient scope shipped, targeted announcement coverage green, full suite green (`1925` tests)

## Test
- **Status:** complete
- **Gate summary:** RED confirmed on preview/send under-targeting, GREEN verified with focused suite, full regression suite green after refactor

## Cross-Milestone Interface

| Category | Items |
|---|---|
| Livewire | Projects\AnnouncementComposer |
| Actions | SendAnnouncement, CreateAnnouncement |
| Models | Volunteer, Announcement, EmailVerificationToken, ShiftSignup |
| Tests | AnnouncementComposerTest, SendAnnouncementTest |
