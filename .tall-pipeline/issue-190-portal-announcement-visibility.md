# Milestone: issue-190-portal-announcement-visibility — Portal Announcement Visibility

**GitHub Issue:** [#190](https://github.com/reneweiser/voluntify/issues/190)
**Features:** #190
**Dependencies:** m15-volunteer-portal-enhancements, issue-175-announcement-recipient-resilience
**Branch:** fix/portal-announcement-visibility

## Plan
- **Status:** complete
- **Gate summary:** make portal announcement metadata null-safe, align portal visibility with project/event/job/shift targeting, and prevent future deleted targets from broadening into project-wide announcements

### Scope
- Add a persisted `is_project_wide` marker to distinguish true project-wide announcements from targeted announcements whose foreign keys may later be nulled
- Scope volunteer portal announcements to the volunteer's non-cancelled signup targets
- Render timestamp-only metadata when an announcement has no event relation
- Add regression coverage for project-wide, matching targeted, mismatched targeted, and orphaned-target portal scenarios

## Implement
- **Status:** complete
- **Iteration:** 1
- **Tasks:**
  - [x] AC-1: Write failing portal tests for project-wide timestamp-only rendering and targeted visibility filtering
  - [x] AC-2: Write failing composer tests for persisted `is_project_wide` behavior
  - [x] AC-3: Add the `is_project_wide` migration with backfill for true project-wide announcements
  - [x] AC-4: Update `Announcement` creation defaults and portal visibility query to use the persisted marker plus signup target matching
  - [x] AC-5: Make portal announcement metadata null-safe in Blade
  - [x] Verify: run focused Sail tests, Pint, and the full Sail test suite
- **Gate summary:** portal announcement visibility now mirrors target semantics, null-event announcements no longer crash the portal, and future orphaned targeted announcements remain hidden. Full Sail suite green (`1933` tests).

## Test
- **Status:** complete
- **Gate summary:** RED confirmed on missing `is_project_wide` persistence and portal visibility gaps; GREEN verified with `VolunteerPortalTest`, `AnnouncementComposerTest`, `SendAnnouncementTest`, then full-suite regression coverage

## Cross-Milestone Interface

| Category | Items |
|---|---|
| Livewire | Public\VolunteerPortal, Projects\AnnouncementComposer |
| Models | Announcement, Volunteer, ShiftSignup |
| Views | `resources/views/livewire/public/volunteer-portal.blade.php` |
| Tests | VolunteerPortalTest, AnnouncementComposerTest, SendAnnouncementTest |
