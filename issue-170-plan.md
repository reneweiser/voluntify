# Issue 170 Plan

## Goal
Fix `#170` so the organizer event volunteer list no longer treats cancelled signups as active shifts, while still surfacing cancellation information.

## Current State
- `app/Livewire/Events/VolunteerList.php` loads all signups for the event.
- `resources/views/livewire/events/volunteer-list.blade.php` uses `shiftSignups->count()` directly.
- Attendance also uses all signups as the denominator.
- Result: cancelled signups inflate shift counts and attendance counts.

## Product Direction
Per `docs/decisions/2026-04-09-po-session.md`:
- Organizer/Admin view should still retain visibility into cancellations.
- Cancelled signups should be marked, not treated as active.

## Smallest Safe Fix
Keep loading all event signups, but split them in the Blade view:
- `activeSignups`: `shiftSignups` where `cancelled_at` is null
- `cancelledCount`: count of cancelled signups

Render:
- `Shifts` column: active count as primary value
- if cancellations exist, show secondary muted marker like `1 storniert`
- `Attendance` column: compute denominator and marked count from `activeSignups` only

## Files to Change
- `resources/views/livewire/events/volunteer-list.blade.php`
- `tests/Feature/Events/VolunteerListTest.php`

## Tests to Add
1. Volunteer with 2 active + 1 cancelled signup:
   - list shows active count `2`
   - list also shows cancelled marker `1 storniert`
2. Attendance denominator excludes cancelled signups:
   - if 1 active signup has attendance and 1 cancelled signup exists, badge shows `1/1`, not `1/2`
3. Optional:
   - volunteer with only cancelled signups still appears with `0` active and cancellation marker

## Verification
- `vendor/bin/sail artisan test --compact tests/Feature/Events/VolunteerListTest.php`
- `vendor/bin/sail bin pint --dirty --format agent`

## Notes
- Do not filter cancelled signups out at query level for this issue.
- Do not redesign the table into a per-shift detail view in this fix.
- A richer cancelled-shift display can be a follow-up enhancement.
