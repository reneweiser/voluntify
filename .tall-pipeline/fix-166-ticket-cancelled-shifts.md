# Fix #166: Ticket page shows cancelled shifts

- **Status:** implemented
- **Type:** bugfix
- **Issue:** [#166](https://github.com/reneweiser/voluntify/issues/166)
- **Branch:** `fix/ticket-shows-cancelled-shifts-166`

## Problem

The `VolunteerTicket` Livewire component at `app/Livewire/Public/VolunteerTicket.php:61` loads shift signups via `$this->volunteer->shiftSignups()` **without** the `.active()` scope. This means cancelled signups (those with `cancelled_at` set) still appear under "YOUR SHIFTS" on the ticket page.

The `VolunteerPortal` component correctly applies `.active()` at lines 113 and 132 — this fix aligns `VolunteerTicket` with that pattern.

## Root Cause

`getShiftSignupsProperty()` (line 55-64) queries all signups:

```php
return $this->volunteer->shiftSignups()
    ->with('shift.volunteerJob.event.project')
    ->get();
```

The `ShiftSignup` model already has `scopeActive()` at line 36-39 that filters `whereNull('cancelled_at')`, but it's not called here.

## Files to Change

| File | Change |
|---|---|
| `tests/Feature/Public/VolunteerTicketTest.php` | Add test: cancelled signups excluded from ticket |
| `app/Livewire/Public/VolunteerTicket.php` | Add `.active()` scope to query (line 61) |

No view changes needed — the Blade template renders whatever the component provides.

**Note:** The Blade template derives the event name header from `$this->shiftSignups->first()?->shift?->volunteerJob?->event?->name` with a fallback to `$ticket->project->name`. After this fix, if a volunteer cancels all their signups, the header falls back to the project name. This is a pre-existing UX edge case (not introduced by this fix) — the fallback path already exists and handles it gracefully.

## TDD Plan

### RED — Write Failing Tests

Add two tests to `tests/Feature/Public/VolunteerTicketTest.php`:

**Test 1: Cancelled signups are excluded**

```php
it('does not show cancelled shift signups', function () {
    $cancelledJob = VolunteerJob::factory()
        ->for($this->event)
        ->create(['name' => 'Cancelled Duty']);
    $cancelledShift = Shift::factory()
        ->for($cancelledJob, 'volunteerJob')
        ->create();
    ShiftSignup::factory()->create([
        'volunteer_id' => $this->volunteer->id,
        'shift_id' => $cancelledShift->id,
        'cancelled_at' => now(),
    ]);

    Livewire::test(VolunteerTicket::class, ['magicToken' => $this->plainToken])
        ->assertSee('Gate Security')        // active signup visible
        ->assertDontSee('Cancelled Duty');   // cancelled signup hidden
});
```

**Test 2: Page renders correctly when all signups are cancelled**

```php
it('renders without errors when all signups are cancelled', function () {
    // Cancel the only signup from beforeEach
    ShiftSignup::query()->update(['cancelled_at' => now()]);

    Livewire::test(VolunteerTicket::class, ['magicToken' => $this->plainToken])
        ->assertOk()
        ->assertDontSee('Gate Security')
        ->assertDontSee('Your Shifts');
});
```

**Expected RED result:** Test 1 fails because `getShiftSignupsProperty()` returns all signups including the cancelled one. Test 2 may also fail depending on header rendering.

### GREEN — One-Line Fix

In `app/Livewire/Public/VolunteerTicket.php`, change `getShiftSignupsProperty()`:

```php
// Before (line 61-63):
return $this->volunteer->shiftSignups()
    ->with('shift.volunteerJob.event.project')
    ->get();

// After:
return $this->volunteer->shiftSignups()
    ->active()
    ->with('shift.volunteerJob.event.project')
    ->get();
```

**Expected GREEN result:** Test passes — only active signups are returned.

### REFACTOR — None Needed

This is a one-line scope addition. The architecture is already correct:
- `scopeActive()` exists and is well-tested (`tests/Feature/Models/ShiftSignupScopeTest.php`)
- The same pattern is used in `VolunteerPortal` — consistency is restored
- No new abstractions or helpers required

## Verification

1. Run the specific test: `vendor/bin/sail artisan test --compact --filter=VolunteerTicketTest`
2. Run related tests: `vendor/bin/sail artisan test --compact --filter=ShiftSignup`
3. Run Pint: `vendor/bin/sail bin pint --dirty --format agent`

## Risk Assessment

- **Blast radius:** Minimal — single computed property on one public page
- **Regression risk:** Low — all behavioral changes are intentional (cancelled signups no longer rendered). The event name fallback on line 13 of the Blade template will trigger more frequently for volunteers with all signups cancelled, but falls back gracefully to `$ticket->project->name`
- **Data integrity:** No schema or data changes

## Related Call Sites (out of scope — separate issues)

A codebase audit found other locations that query `shiftSignups()` without `.active()`. These are **not fixed in this PR** but should be evaluated separately:

| File | Line(s) | Context | Needs `.active()`? |
|---|---|---|---|
| `app/Livewire/Admin/VolunteerDetail.php` | ~69 | Admin volunteer detail page | Likely yes — admin should see active signups by default |
| `app/Http/Controllers/ScannerDataController.php` | ~77, ~133 | Scanner offline data sync | Likely yes — entrance staff shouldn't see stale cancelled shifts |
| `app/Actions/ExportVolunteersCsv.php` | ~46, ~83, ~89 | CSV export metrics | Evaluate — cancelled signups may be relevant for audit/reporting |
| `app/Models/Volunteer.php` `scopeForEvent()` | ~119-122 | `whereHas('shiftSignups')` subquery | Evaluate — volunteers with only cancelled signups may still be valid for lookup |

These should be triaged as follow-up issues after this fix lands.

## Review Log

### Reviewers
| Slot | Name | Background | Cognitive Style | Focus |
|---|---|---|---|---|
| A | Dr. Elara Voss | Senior Architect | Analytical / Rigorous | Correctness & Internal Logic |
| B | Marcus Chen | Product Engineer | Creative / Lateral | Completeness, Gaps & Alternatives |
| C | Sgt. Kai Novak | Production SRE | Adversarial / Skeptical | Practical Viability & Failure Modes |

### Cycle 1 — Independent Evaluation
**Verdicts:** Voss=APPROVE, Chen=REVISE, Novak=REVISE

**Applied changes:**
1. Added Test 2 (all-signups-cancelled edge case) — raised by Chen (B) + Novak (C), consensus
2. Documented event name header fallback as pre-existing UX edge case — raised by Chen (B) + Novak (C), consensus
3. Reworded risk assessment from "cannot break" to "Low — intentional changes" — raised by Novak (C), solo high-confidence
4. Added "Related Call Sites" section documenting other `shiftSignups()` calls without `.active()` — raised by Novak (C) + Voss (A), consensus
5. Added `scopeForEvent()` to related call sites — raised by Novak (C), solo high-confidence

**Deferred:**
- Test brittleness concern (Chen, Minor, Medium confidence) — `assertDontSee` is standard Livewire testing practice; the concern about future template changes is speculative

**Progress:** 5 resolved, 0 regressed → IMPROVING
