# Milestone: issue-207-signup-empty-state-notifications — Signup Empty-State Notifications

**GitHub Issue:** [#207](https://github.com/reneweiser/voluntify/issues/207)
**Features:** #207
**Dependencies:** issue-168-shifts-jobs-active-state, issue-203-priority-shift-gate, issue-206-hide-fully-booked-jobs
**Branch:** current workspace

## Plan
- **Status:** complete
- **Gate summary:** add an event-scoped notification subscription flow to the public signup empty state, verify subscribers via double opt-in, provide unsubscribe links, and queue one availability-notification batch when an event transitions from no public shifts to available shifts.

### Scope
- Show a Flux-based empty-state card in `WizardState::SelectingShifts` whenever `EventSignup` has no public jobs to render.
- Persist event-specific subscribers with verification tokens, verification expiry, unsubscribe tokens, and per-subscriber notification timestamps.
- Verify subscribers through public routes, send queued availability notifications with unsubscribe links, and debounce organizer-side trigger points via the existing shift/job actions.
- Cover subscription UI, verification/unsubscribe routes, queued notification delivery, and action-triggered scheduling behavior with focused Pest coverage.

## Implement
- **Status:** complete
- **Iteration:** 1
- **Tasks:**
  - [x] RED: add focused Pest coverage for the empty-state opt-in flow, verification/unsubscribe routes, queued notifications, and action-triggered scheduling
  - [x] GREEN: add `event_notification_subscribers`, the public signup empty-state form, double opt-in actions/routes, unsubscribe flow, and queued event-availability notifications
  - [x] REFACTOR: move public-signup availability filtering into `Event::publicSignupJobs()` so public signup and notification triggers share one source of truth
  - [x] Verify: run focused Sail Pest coverage and Pint
- **Gate summary:** the public signup now shows an email opt-in empty state when no shifts are available, stores event-specific subscribers with double opt-in verification, sends unsubscribe-enabled availability notifications, and only queues one delayed notification batch when an event becomes publicly available again.

## Test
- **Status:** complete
- **Gate summary:** focused public signup, controller, notification, job, and action Pest coverage passed for the new empty-state subscription flow, per-event delivery, unsubscribe handling, and queue scheduling behavior; Pint also passed on dirty PHP files.

## Cross-Milestone Interface

| Category | Items |
|---|---|
| Models | `EventNotificationSubscriber` with `event_id`, `email`, verification token hash/expiry, unsubscribe token hash, `verified_at`, and `last_notified_at` |
| Actions | `SubscribeToEventNotifications`, `CompleteEventNotificationSubscription`, `ScheduleEventSubscriberNotification` |
| Jobs | `NotifyEventSubscribers` delayed/unique per event |
| Notifications | `EventNotificationSubscriptionVerification`, `EventNewShiftsAvailable` |
| Routes | `events.notifications.verify`, `events.notifications.unsubscribe` |
| Public signup | `EventSignup` empty-state opt-in plus shared `Event::publicSignupJobs()` availability filtering |
