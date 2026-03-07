# E2E Test Runbook (Playwright MCP)

Tests are executed interactively by Claude using `@playwright/mcp` browser tools + Bash/tinker for setup and verification. No `.spec.ts` files needed.

## Prerequisites

1. Sail is running: `vendor/bin/sail up -d`
2. Setup script has been run: `bash e2e/setup.sh`
3. Playwright MCP server is registered in Claude Code
4. Claude Code has been restarted to pick up the MCP server

## Test Users

| Role | Email | Password |
|------|-------|----------|
| Organizer | test@example.com | password |
| EntranceStaff | entrance@example.com | password |

---

## Scenario 1: Volunteer Signup Flow

**Goal**: Verify end-to-end volunteer signup from public event page through email verification to ticket display.

### Setup

```bash
# Get the public token for "Spring Community Fair"
vendor/bin/sail artisan tinker --execute="echo App\Models\Event::where('name', 'Spring Community Fair')->first()->public_token;"
```

### Steps

1. **Navigate** to `/events/{publicToken}`
2. **Assert** "Spring Community Fair" is visible on the page
3. **Fill** the signup form:
   - Name input (placeholder: `Your full name`) → "Alice Test"
   - Email input (placeholder: `your@email.com`) → "alice@test.com"
   - Phone input (placeholder: `+1 555 123 4567`) → "+1 555 000 1234"
4. **Check** at least one shift checkbox (`input[type=checkbox]`)
5. **Click** the "Sign Up to Volunteer" button
6. **Assert** "Check Your Email" heading appears

### Email Verification

7. **Fetch** the verification email from Mailpit:
   ```bash
   curl -s http://localhost:8025/api/v1/messages | jq '.' | head -50
   ```
8. **Extract** the `/verify-email/{token}` URL from the email body
9. **Navigate** to the verification URL
10. **Assert** the page shows verified/confirmed state

### Ticket Access

11. **Fetch** the signup confirmation email from Mailpit (second email)
12. **Extract** the `/my-ticket/{magicToken}` URL from the email body
13. **Navigate** to the ticket URL
14. **Assert** volunteer name "Alice Test" is visible (as `<flux:heading>`)
15. **Assert** QR code SVG element is present on the page

### Verification

```bash
vendor/bin/sail artisan tinker --execute="
  \$v = App\Models\Volunteer::where('email', 'alice@test.com')->first();
  echo 'Volunteer: ' . \$v->name;
  echo PHP_EOL . 'Has ticket: ' . (\$v->ticket ? 'yes' : 'no');
  echo PHP_EOL . 'Signups: ' . \$v->shiftSignups()->count();
"
```

---

## Scenario 2: Manual Lookup Flow

**Goal**: Verify authenticated manual volunteer lookup and arrival confirmation.

### Setup

Login is performed via the browser.

### Steps

1. **Navigate** to `/login`
2. **Fill** email input (placeholder: `email@example.com`) → "test@example.com"
3. **Fill** password input (placeholder: `Password`) → "password"
4. **Click** the "Log in" button (`data-test="login-button"`)
5. **Assert** redirected to dashboard (authenticated)

### Manual Lookup

6. **Navigate** to `/admin/scanner/lookup`
7. **Select** an event from the dropdown (placeholder: `Select an event...`)
8. **Type** a seeded volunteer name (3+ chars) in the search input (placeholder: `Search by volunteer name...`)
9. **Assert** a volunteer card appears showing name and email
10. **Click** the "Confirm" button (`wire:click="confirmArrival(...)"`)
11. **Assert** success confirmation message appears

### Duplicate Check

12. **Search** for the same volunteer again
13. **Assert** "Already arrived" badge/indicator is visible

### Verification

```bash
vendor/bin/sail artisan tinker --execute="
  \$v = App\Models\Volunteer::where('name', 'LIKE', '%SEARCHED_NAME%')->first();
  echo 'Arrival exists: ' . (App\Models\EventArrival::where('volunteer_id', \$v->id)->exists() ? 'yes' : 'no');
"
```

---

## Scenario 3: QR Scanner Flow

**Goal**: Verify QR scanner with JWT injection, arrival confirmation, and duplicate detection.

### Setup

Must be logged in as Organizer (from Scenario 2, or login fresh).

### Steps

1. **Navigate** to `/admin/scanner`
2. **Select** an event from the dropdown (`wire:model.live="selectedEventId"`)

### Generate JWT

3. **Generate** a valid JWT via tinker. The volunteer must have a ticket (seeded volunteers don't — use a volunteer who signed up via the public form, or create one with `GenerateTicket`). Alternatively, use the JWT from the volunteer's existing ticket:
   ```bash
   vendor/bin/sail artisan tinker --execute="
     \$event = App\Models\Event::where('name', 'Spring Community Fair')->first();
     \$ticket = App\Models\Ticket::where('event_id', \$event->id)->first();
     echo \$ticket->jwt_token;
   "
   ```
   Or generate a fresh JWT (note: payload uses `volunteer_id`, not `sub`):
   ```bash
   vendor/bin/sail artisan tinker --execute="
     \$event = App\Models\Event::where('name', 'Spring Community Fair')->first();
     \$volunteer = App\Models\Volunteer::whereHas('ticket', fn(\$q) => \$q->where('event_id', \$event->id))->first();
     \$service = app(App\Services\JwtKeyService::class);
     \$key = \$service->deriveKey(\$event->id, \Carbon\Carbon::now());
     \$payload = ['volunteer_id' => \$volunteer->id, 'event_id' => \$event->id, 'iat' => time()];
     echo Firebase\JWT\JWT::encode(\$payload, \$key, 'HS256');
   "
   ```

### Inject JWT (Camera Bypass)

4. **Execute** in browser console (via Playwright `browser_evaluate`):
   ```javascript
   // Access the Alpine scanner component and inject the JWT
   const el = document.querySelector('[x-data]');
   const component = el._x_dataStack[0];
   component.state = 'scanning';
   component._processing = false;
   await component._onQrDetected('JWT_STRING_HERE');
   ```
5. **Assert** volunteer name appears in the result panel (`x-text="result?.name"`)
6. **Click** "Confirm Arrival" button
7. **Assert** "Arrival Confirmed" text is visible

### Duplicate Detection

8. **Inject** the same JWT again (repeat step 4)
9. **Assert** "Already Checked In" text is visible

### Offline Test (Optional)

10. **Execute** in browser console to simulate offline:
    ```javascript
    const el = document.querySelector('[x-data]');
    const component = el._x_dataStack[0];
    component.isOnline = false;
    ```
11. **Generate** a new JWT for a different volunteer (via tinker)
12. **Inject** the new JWT
13. **Verify** outbox count incremented (check IndexedDB or Alpine state)

### Verification

```bash
vendor/bin/sail artisan tinker --execute="
  \$event = App\Models\Event::where('name', 'Spring Community Fair')->first();
  echo 'Arrivals: ' . App\Models\EventArrival::where('event_id', \$event->id)->count();
"
```

---

## Scenario 4: Smoke E2E (Cross-Role Journey)

**Goal**: Full cross-role journey — Organizer creates event, volunteer signs up, Organizer scans ticket.

### Step A: Create Event (as Organizer)

1. **Navigate** to `/login`
2. **Fill** email → "test@example.com", password → "password"
3. **Click** "Log in"
4. **Navigate** to the events list page
5. **Click** "Create Event" button (`wire:click="$set('showCreateModal', true)"`)
6. **Fill** event name input (`wire:model="eventName"`, placeholder: `e.g. Summer Carnival`) → "E2E Test Event"
7. **Submit** the modal form
8. **Assert** redirected to the event show page

### Step B: Add Job, Shift & Publish

9. **Add** a volunteer job (use the event show page UI)
10. **Add** a shift to the job
11. **Click** "Publish" button (`wire:click="publishEvent"`)
12. **Confirm** the publish dialog ("Publish this event? It will become publicly accessible.")
13. **Assert** event status changes to Published

### Step C: Extract Public Token

```bash
vendor/bin/sail artisan tinker --execute="
  echo App\Models\Event::where('name', 'E2E Test Event')->first()->public_token;
"
```

### Step D: Volunteer Signup (Anonymous)

14. **Open** a new browser tab or clear session
15. **Navigate** to `/events/{publicToken}`
16. **Fill** signup form:
    - Name → "Bob E2E"
    - Email → "bob@e2e-test.com"
    - Phone → "+1 555 999 0000"
17. **Check** a shift checkbox
18. **Click** "Sign Up to Volunteer"
19. **Assert** "Check Your Email" appears

### Step E: Email Verification & Ticket

20. **Fetch** verification email from Mailpit, extract URL
21. **Navigate** to verification URL
22. **Fetch** confirmation email, extract ticket URL
23. **Navigate** to ticket URL
24. **Assert** QR code SVG is visible

### Step F: Scan Ticket (as Organizer)

25. **Login** as Organizer again
26. **Navigate** to `/admin/scanner`
27. **Select** "E2E Test Event" from the dropdown
28. **Generate** JWT for "Bob E2E" via tinker
29. **Inject** JWT into scanner (camera bypass)
30. **Click** "Confirm Arrival"
31. **Assert** "Arrival Confirmed"

### Final Verification

```bash
vendor/bin/sail artisan tinker --execute="
  \$event = App\Models\Event::where('name', 'E2E Test Event')->first();
  \$volunteer = App\Models\Volunteer::where('email', 'bob@e2e-test.com')->first();
  echo 'Event exists: ' . (\$event ? 'yes' : 'no');
  echo PHP_EOL . 'Volunteer exists: ' . (\$volunteer ? 'yes' : 'no');
  echo PHP_EOL . 'Has ticket: ' . (\$volunteer?->ticket ? 'yes' : 'no');
  echo PHP_EOL . 'Arrival recorded: ' . (App\Models\EventArrival::where('event_id', \$event?->id)->where('volunteer_id', \$volunteer?->id)->exists() ? 'yes' : 'no');
"
```

---

## Key Selectors Reference

| Page | Element | Selector |
|------|---------|----------|
| Login | Email input | `placeholder="email@example.com"` |
| Login | Password input | `placeholder="Password"` |
| Login | Submit | `data-test="login-button"` or text "Log in" |
| Signup | Name | `placeholder="Your full name"` |
| Signup | Email | `placeholder="your@email.com"` |
| Signup | Phone | `placeholder="+1 555 123 4567"` |
| Signup | Shift checkbox | `input[type=checkbox]` per shift |
| Signup | Submit | text "Sign Up to Volunteer" |
| Signup | Pending | heading "Check Your Email" |
| Ticket | QR code | `<svg>` inside ticket card |
| Ticket | Name | volunteer name in `<flux:heading size="lg">` |
| Scanner | Event select | `wire:model.live="selectedEventId"` |
| Scanner | Result name | `x-text="result?.name"` |
| Scanner | Confirm | text "Confirm Arrival" |
| Scanner | Duplicate | text "Already Checked In" |
| Scanner | Confirmed | text "Arrival Confirmed" |
| Lookup | Search | `placeholder="Search by volunteer name..."` |
| Lookup | Confirm | text "Confirm" with `wire:click="confirmArrival(...)"` |
| Event Create | Modal trigger | text "Create Event" |
| Event Create | Name | `wire:model="eventName"`, placeholder "e.g. Summer Carnival" |
| Event Show | Publish | `wire:click="publishEvent"`, text "Publish" |
