# Milestone: m18-signup-custom-fields — Signup & Custom Fields Rework

**Features:** Custom field checkbox options + single/multi choice (#139), Signup step rework (#134)
**Dependencies:** m17-reliability-quick-wins
**GitHub Milestone:** [Milestone 3](https://github.com/reneweiser/voluntify/milestone/3)

## Plan
- **Status:** complete
- **Gate summary:** 1 migration, 2 enum changes, 3 component updates, 2 action updates, ~30 tests

### Phase 1: Custom Field Enhancements (#139)

#### Schema Change

**Migration: `add_allow_multiple_to_custom_registration_fields_table`**
```
custom_registration_fields:
  + allow_multiple  boolean  default(false)  after(required)
```

#### Behavior Matrix

| Type | Has Choices | allow_multiple | Renders As | Storage Format |
|---|---|---|---|---|
| Text | No | n/a | Input / Textarea | string |
| Select | Yes | false | Dropdown (single) | string |
| Select | Yes | true | Multi-select checkboxes | JSON array string |
| Checkbox | No | false | Single checkbox (yes/no) | '1' / '0' |
| Checkbox | Yes | false | Radio buttons (single) | string |
| Checkbox | Yes | true | Checkboxes (multi) | JSON array string |

**Design decision:** Select + allow_multiple renders as a checkbox group (not a native multi-select dropdown) for better UX on mobile. Radio buttons for single-choice checkbox with options.

**Storage:** Multi-choice values stored as JSON-encoded array string in `custom_field_responses.value` (text column). This avoids schema changes to the responses table. `displayValue()` decodes and joins with ", ".

#### Files to Change

1. **Migration** — add `allow_multiple` column
2. **`CustomRegistrationField` model** — add `allow_multiple` to `$fillable` and `casts()`
3. **`CustomFieldType` enum:**
   - `validationRules()` — accept `$allowMultiple` param; multi-choice: `['required'/'nullable', 'array']` + per-item rules
   - `validationItemRules()` — new method for `.*` wildcard rules on multi-choice arrays
   - `validateOptions()` — also validate checkbox fields with choices; enforce max 30 choices per field
   - `castToStorage()` — array values → `json_encode()`
   - `displayValue()` — JSON strings → decode + comma-join; checkbox with choices (single) → display value directly
4. **`CustomFieldSetup` component:**
   - Show options input when type is `select` OR `checkbox`
   - Show "Allow Multiple" toggle when options are present (select or checkbox with choices)
   - `buildOptions()` — build choices for checkbox type too
   - `validateAndBuildOptions()` — validate options for checkbox with choices
   - New property: `$newFieldAllowMultiple`
   - `saveField()` — persist `allow_multiple`
   - `applyTemplate()` — handle `allow_multiple` in templates
5. **`CustomFieldSetup` blade:**
   - Options input visible for `select` and `checkbox`
   - "Allow Multiple" checkbox when options are present
   - Description hint when allow_multiple toggled: "Will render as checkboxes for volunteers"
   - Display "allow_multiple" badge in field list
6. **`EventSignup` blade (Step 2 custom fields section):**
   - Checkbox + choices + allow_multiple → checkbox group
   - Checkbox + choices + !allow_multiple → radio buttons
   - Select + allow_multiple → checkbox group
   - Select + !allow_multiple → dropdown (unchanged)
7. **`EventSignup` component:**
   - `validateGearAndCustomFields()` — use `validationRules()` with `$field->allow_multiple`, add `.*` item rules for multi-choice
8. **`RecordCustomFieldResponses` action:**
   - Handle array values (multi-choice): `json_encode()` before storage
   - `validateResponse()` — branch on `$field->allow_multiple` (model already loaded in loop): decode array, validate each item against choices, enforce max 50 items per response
   - Keep re-fetch of fields (needed for email verification flow where component state isn't available)
9. **`ExportVolunteersCsv`** — `displayValue()` already called, will work if displayValue handles JSON strings
10. **Volunteer portal + detail views** — `displayValue()` already called, will work automatically
11. **`CustomFieldTemplates`** — add `allow_multiple` key to templates (default false); update dietary_restrictions to use checkbox + allow_multiple
12. **`EmailVerificationToken`** — no change needed (custom_field_responses stored as JSON, supports any structure)

#### Tests for Phase 1

- Unit: `CustomFieldTypeTest` — new cases for multi-choice validation, castToStorage with arrays, displayValue with JSON
- Unit: `CustomFieldTemplatesTest` — verify templates have valid `allow_multiple` values
- Feature: `CustomFieldSetupTest` — add field with checkbox+options, add field with allow_multiple
- Feature: `RecordCustomFieldResponsesTest` — multi-choice storage and validation
- Feature: `EventSignupTest` — signup with multi-choice fields

### Phase 2: Signup Step Rework (#134)

#### New Wizard Flow

```
Current:  SelectingShifts → GearAndFields → PersonalInfo → Confirming
New:      PersonalInfo → SelectingShifts → GearAndFields → Confirming
```

**WizardState enum:** No rename needed — reuse existing values, change transition logic only.

#### Step 1: Personal Info (New Position)
- Collects: first_name, last_name, email, phone
- **Volunteer lookup** on email blur:
  - Query: exact `where('email', $email)->where('project_id', ...)` (not LIKE) — verify DB index exists
  - Match found: silently pre-fill first_name, last_name, phone + generic flash "Details pre-filled from your previous signup"
  - No match: silent (no error, no message)
  - Email changes: clear pre-filled data, reset flash
  - No public `$volunteerFound` bool — pre-fill is the only signal (mitigates GDPR enumeration risk)
- Rate limit lookup: 10 per IP per minute (prevent automated enumeration)
- No reservation timer (timer starts at Step 2)
- Advance method: `advanceToShifts()` — validates personal info, transitions to SelectingShifts

#### Step 2: Shift Selection (Was Step 1)
- Same as current `reserveAndAdvance()` — creates reservations, starts timer
- On advance: go to GearAndFields if `hasGearOrFields`, else to Confirming
- **`hasGearOrFields` must evaluate against filtered gear list** (SizeSelection + job-matched only) + custom fields — prevents empty step 3 for Quantity-only events

#### Step 3: Gear & Custom Fields (Was Step 2)
- **Gear filtering:** Only show SizeSelection items applicable to selected jobs
  - `selectedJobIds` — `#[Computed]` property resolving job IDs from `$this->jobs` (reuses cached computed, no extra query)
  - Filter `gearItems` by `job_ids` intersection (null = all jobs) + SizeSelection type only
  - Hide Quantity items entirely (auto-assigned in backend)
- Custom fields: shown as before (with #139 enhancements)
- Advance: `advanceToConfirmation()` — validates gear + fields, transitions to Confirming

#### Step 4: Confirmation (Unchanged position)
- Summary of all data
- Submit: `submitSignup()` — validates everything, processes signup

#### Files to Change

1. **`EventSignup` component:**
   - Initial state: `PersonalInfo` (was `SelectingShifts`)
   - New method: `advanceToShifts()` — validates personal info, advances to `SelectingShifts`
   - New method: `lookupVolunteer()` — exact email+project query with rate limiting, no public bool
   - **DELETE** `advanceToPersonalInfo()` — no longer needed (was Gear→PersonalInfo)
   - **REPURPOSE** `advanceToConfirmation()` — now Gear→Confirming (incorporate reservation check from old `advanceToPersonalInfo`)
   - Update `goBack()`:
     - SelectingShifts → PersonalInfo
     - GearAndFields → SelectingShifts
     - Confirming → PersonalInfo (skip gear — can't edit shifts without re-reserving)
   - Update `reserveAndAdvance()` — destination after reserving: GearAndFields or Confirming
   - Update `hasGearOrFields` — evaluate against filtered gear list (SizeSelection + job-matched) + custom fields
   - Update `gearItems` computed — filter by job_ids + SizeSelection type only
   - New `#[Computed]` property: `selectedJobIds` — resolves job IDs from `$this->jobs` (cached, no extra query)
   - `restartSignup()` — reset to PersonalInfo (not SelectingShifts)
2. **`EventSignup` blade:**
   - Reorder step sections: PersonalInfo first, then SelectingShifts, GearAndFields, Confirming
   - Update step progress bar labels/order
   - Update aria announcements for new step order
   - Add volunteer lookup UI (hint message when found)
   - Gear section: only show SizeSelection items
3. **`WizardState` enum** — no changes needed

#### Transition Map (New)

```
PersonalInfo --advanceToShifts()--> SelectingShifts
SelectingShifts --reserveAndAdvance()--> GearAndFields | Confirming
GearAndFields --advanceToConfirmation()--> Confirming
Confirming --submitSignup()--> PendingVerification | Complete

goBack():
  SelectingShifts --> PersonalInfo
  GearAndFields --> SelectingShifts
  Confirming --> PersonalInfo (skip gear — can't edit shifts without re-reserving)
  
restartSignup() --> PersonalInfo
```

**Decision: Confirming → goBack():** Goes to PersonalInfo (not GearAndFields) because going back to shifts would require releasing reservations. User can edit their info and re-confirm. If they need different shifts, they restart.

#### Tests for Phase 2

- Feature: `EventSignupTest` — full wizard flow with new step order
- Feature: `EventSignupTest` — volunteer lookup pre-fills data
- Feature: `EventSignupTest` — gear filtered by selected jobs (only SizeSelection shown)
- Feature: `EventSignupTest` — back navigation in new order
- Feature: `EventSignupTest` — restart returns to PersonalInfo

## Implement
- **Status:** pending

## Test
- **Status:** pending

## Security Audit
- **Status:** pending

## Decisions

| # | Stage | Decision | Rationale | Affects |
|---|---|---|---|---|
| 1 | plan | Multi-choice stored as JSON string in text column | Avoids schema change to responses table; displayValue handles decoding | implement, test |
| 2 | plan | Select+allow_multiple renders as checkbox group, not native multi-select | Better mobile UX, consistent with checkbox+allow_multiple rendering | implement |
| 3 | plan | Confirming→goBack() goes to PersonalInfo, not GearAndFields | Shift changes require new reservations; going back to shifts is confusing | implement |
| 4 | plan | WizardState enum values unchanged — only transition logic changes | Avoids breaking existing state references, less migration risk | implement |
| 5 | plan | Quantity gear hidden in signup UI, only SizeSelection shown | #134 spec: "Gear Typ 2 (Quantity) wird hier nicht angezeigt — Zuweisung erfolgt automatisch im Backend" | implement |
| 6 | plan-review | `hasGearOrFields` must use filtered gear list (SizeSelection + job-matched) | DA-1/JD-2: empty step 3 for Quantity-only events or job-mismatched gear | implement |
| 7 | plan-review | No public `$volunteerFound` Livewire property — pre-fill silently | DA-2: GDPR enumeration risk; rate limiting is primary defense | implement |
| 8 | plan-review | Delete `advanceToPersonalInfo()`, repurpose `advanceToConfirmation()` | JD-1: avoid method confusion during implementation | implement |
| 9 | plan-review | Enforce max 30 choices per field in `validateOptions()` | SS-5: prevent validation/rendering degradation at scale | implement |
| 10 | plan-review | Max 50 items per multi-choice response in `validateResponse()` | SS-1: bound attacker-injected array sizes | implement |
| 11 | plan-review | `selectedJobIds` as `#[Computed]` reusing `$this->jobs` cache | SS-2/DA-5: avoid extra queries on step transitions | implement |

## Cross-Milestone Interface

| Category | Items |
|---|---|
| Schema | `custom_registration_fields.allow_multiple` (boolean, default false) |
| Enum | `CustomFieldType::validationRules()` now accepts `$allowMultiple` param |
| Enum | `CustomFieldType::validationItemRules()` new method for array item validation |
| Behavior | Wizard starts at PersonalInfo, not SelectingShifts |
| Behavior | Gear items filtered by selected jobs in signup; Quantity type hidden in UI |

## Reviews

### plan — 2026-04-09

| # | Perspective | Concern | Severity | Resolution | Rationale |
|---|---|---|---|---|---|
| 1 | Devil's Advocate | `hasGearOrFields` evaluates unfiltered gear — empty step 3 for Quantity-only events | high | accepted | Real bug; use filtered gear list |
| 2 | Junior Dev | `advanceToPersonalInfo()` rename not flagged | high | accepted | Delete old, repurpose `advanceToConfirmation()`, add `advanceToShifts()` |
| 3 | Junior Dev | Same `hasGearOrFields` issue (Quantity-only) | high | accepted | Merged with #1 |
| 4 | Devil's Advocate | `$volunteerFound` exposes volunteer existence (GDPR) | medium | accepted | No public bool; pre-fill silently + generic flash |
| 5 | Devil's Advocate | `validateResponse()` data flow unclear for multi-choice | medium | accepted | Branch on `$field->allow_multiple` explicitly |
| 6 | Scalability Skeptic | Action re-fetches fields; no array bound | medium | partial | Keep re-fetch (verification flow needs it); add max-items guard |
| 7 | Scalability Skeptic | `selectedJobIds` needs caching | medium | accepted | `#[Computed]` reusing `$this->jobs` |
| 8 | Scalability Skeptic | Volunteer lookup query unspecified — could table-scan | medium | accepted | Exact `where()` + verify/add DB index |
| 9 | Junior Dev | Lookup UX undefined (no-match, email change) | medium | accepted | Spec added: silent on miss, clear on change |
| 10 | Devil's Advocate | Confirming→goBack skips gear silently | low | rejected | Confirmation shows all data + restart button available |
| 11 | Devil's Advocate | `selectedJobIds` computed lifecycle | low | accepted | Merged with #7 |
| 12 | Scalability Skeptic | `hasGearOrFields` extra queries per render | low | accepted | Merged with #1; Livewire memoizes `#[Computed]` |
| 13 | Scalability Skeptic | No cap on choices per field | low | accepted | Max 30 in `validateOptions()` |
| 14 | Junior Dev | Vague test descriptions | low | accepted | Behavior-driven names during implementation |
| 15 | Junior Dev | Admin UI no hint for Select+allow_multiple rendering | low | accepted | Add description text in CustomFieldSetup |

## Feedback Loops
