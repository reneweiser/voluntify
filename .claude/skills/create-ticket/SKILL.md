---
name: create-ticket
description: "Creates GitHub issues for the Voluntify project. Activates when the user asks to create a ticket, issue, bug report, feature request, or chore; or says 'erstelle ein Ticket', 'neues Ticket', 'Ticket für Rene'."
---

# Create Ticket

## When to Apply

Activate this skill when:

- Creating GitHub issues / tickets
- Reporting bugs or requesting features for Rene
- Creating chore/refactor/tech-debt tickets

## Repository

Always create issues on: `reneweiser/voluntify`

## Issue Type Detection

Before selecting a template, classify the issue:

- **Bug (`fix:`)** — Something that worked before is broken, or behavior deviates from what's expected. The user describes an error, unexpected behavior, crash, or regression.
- **Feature (`feat:`)** — New capability, new UI element, new workflow, or enhancement to existing feature. The user describes something that doesn't exist yet.
- **Chore (`chore:`)** — Refactoring, dependency updates, documentation, test coverage, performance optimization, tech debt. No user-facing behavior change.

If ambiguous, ask the user. If the issue contains both a bug and a feature request, suggest splitting into two tickets.

## Common Required Fields

These fields appear in every issue type.

### Endpoint / Location

Start the issue body with the affected endpoint or UI location:

```
**Endpoint:** `GET /projects/{id}/settings`
```

or for Livewire components:

```
**Komponente:** `App\Livewire\Projects\ScannerManagement`
```

or for UI locations:

```
**Ort:** Projekteinstellungen → Zeitzone
```

### Title Format

- Prefix with type: `fix:`, `feat:`, `chore:`, `fix(ui):`, `feat(scanner):`, etc.
- Keep under 70 characters
- German or English — match the user's language

### Labels

Apply both a **type label** and an **urgency label** to every issue:

| Template | Label | Color |
|----------|-------|-------|
| Bug | `bug` | (GitHub default) |
| Feature | `enhancement` | (GitHub default) |
| Chore | `chore` | `c5def5` |

Create the `chore` label if it doesn't exist:
```bash
gh label create "chore" --color "c5def5" --description "Refactoring, tech debt, docs, tests" --force
```

Urgency labels: see [Urgency Rating System](#urgency-rating-system) below.

---

## Template: Bug Report

Use this template when the issue type is `fix:`.

```markdown
**Endpoint:** `METHOD /route` | **Komponente:** `App\Namespace\Class` | **Ort:** UI-Beschreibung
**Urgency:** 🔴/🟠/🟡/🟢 Level — Begründung

## Bug

Kurze Beschreibung (1-3 Sätze): Was ist kaputt? Wann tritt es auf?

## Reproduktion

1. [Schritt 1]
2. [Schritt 2]
3. [Schritt 3]
4. → **Beobachtetes Verhalten:** [Was tatsächlich passiert] ❌

## Erwartetes Verhalten

[Was stattdessen passieren sollte]

## Vermutete Ursache

[Code-Analyse: Datei, Zeile, warum es fehlschlägt. Oder "Unklar — Untersuchung nötig."]

## Betroffene Dateien

- `app/Path/To/File.php` — [was hier geändert werden muss]
- `resources/views/path/to/view.blade.php` — [was hier geändert werden muss]
- `tests/Feature/Path/ToTest.php` — [Test hinzufügen/anpassen]

## Akzeptanzkriterien

- [ ] **Given** [Ausgangszustand], **when** [Aktion], **then** [erwartetes Ergebnis]
- [ ] **Given** [Randbedingung], **when** [Aktion], **then** [erwartetes Ergebnis]
- [ ] Bestehende Tests bleiben grün

## Scope

**In Scope:** [Was geändert werden soll]
**Out of Scope:** [Was NICHT angefasst werden soll]

## Verifikation

```bash
vendor/bin/sail artisan test --compact --filter=TestName
vendor/bin/sail bin pint --dirty --format agent
```

[Optional: Manuelle Schritte zur Verifikation]

## Referenz-Implementierung

[Optional: Verweis auf ähnliche Implementierung im Codebase als Muster, z.B. "Orientierung an `RecordArrival` für Duplikat-Erkennung"]

## Verwandt

- #123 (Beschreibung)

## Screenshots

<!-- Screenshot einfügen -->
```

### Bug Template Notes

- **Reproduktion** — Numbered steps are essential. An agent resolving this issue will use these steps to confirm the bug exists and verify the fix works. Be specific: include URLs, test data, and user roles.
- **Vermutete Ursache** — Root cause analysis saves agents the diagnosis phase. Include file paths, method names, and line numbers when possible. If the cause is unknown, say so — the agent will investigate.
- **Betroffene Dateien** — List every file that needs modification, including test files. Per-file descriptions of what needs changing let agents jump straight to implementation without exploring the codebase first.

---

## Template: Feature Request

Use this template when the issue type is `feat:`.

```markdown
**Endpoint:** `METHOD /route` | **Komponente:** `App\Namespace\Class` | **Ort:** UI-Beschreibung
**Urgency:** 🔴/🟠/🟡/🟢 Level — Begründung

## Feature Request

Kurze Beschreibung (1-3 Sätze): Was soll hinzugefügt/geändert werden? Warum?

## Aktueller Stand

Was existiert heute? Was fehlt? Welche Einschränkung soll behoben werden?

## Erwartetes Verhalten

Detaillierte Beschreibung des gewünschten Verhaltens.

[Optional: Tabelle oder Liste mit Szenarien/Use Cases]

## Betroffene Dateien

- `app/Path/To/File.php` — [neu erstellen / ändern: was]
- `resources/views/path/to/view.blade.php` — [neu erstellen / ändern: was]
- `tests/Feature/Path/ToTest.php` — [neuer Test]

## Akzeptanzkriterien

- [ ] **Given** [Ausgangszustand], **when** [Aktion], **then** [erwartetes Ergebnis]
- [ ] **Given** [Randbedingung], **when** [Aktion], **then** [erwartetes Ergebnis]
- [ ] Neue Tests für alle Akzeptanzkriterien geschrieben
- [ ] Bestehende Tests bleiben grün

## Scope

**In Scope:** [Was implementiert werden soll]
**Out of Scope:** [Was NICHT Teil dieses Tickets ist]

## Verifikation

```bash
vendor/bin/sail artisan test --compact --filter=TestName
vendor/bin/sail bin pint --dirty --format agent
```

[Optional: Manuelle Schritte zur Verifikation]

## Referenz-Implementierung

[Optional: Verweis auf ähnliches Feature im Codebase als Muster, z.B. "Orientierung an `EventGearSetup` für CRUD-Modal-Pattern"]

## Verwandt

- #123 (Beschreibung)

## Screenshots

<!-- Screenshot / Mockup einfügen -->
```

### Feature Template Notes

- **Aktueller Stand** — Tells the agent what already exists so it builds on top of existing code rather than starting from scratch or duplicating functionality.
- **Akzeptanzkriterien** — Every criterion should be independently verifiable and map to a Pest test assertion. Include at least one criterion per use case. Include edge cases.
- **Referenz-Implementierung** — Pointing to a similar feature in the codebase dramatically improves agent output because it has a concrete pattern to follow (e.g., "follow the same CRUD pattern as `EventGearSetup`").

---

## Template: Chore / Refactor

Use this template when the issue type is `chore:`.

```markdown
**Ort:** [Betroffener Bereich / Modul]
**Urgency:** 🟢 Low — [Begründung, oder höher wenn es ein höher priorisiertes Ticket entblockt]

## Chore

Kurze Beschreibung: Was soll aufgeräumt/verbessert/aktualisiert werden? Warum jetzt?

## Aktuelle Situation

[Was ist der Ist-Zustand? Warum ist er suboptimal?]

## Gewünschter Zustand

[Was soll sich ändern? Welches Muster soll eingeführt/eingehalten werden?]

## Betroffene Dateien

- `path/to/file.php` — [was hier geändert werden muss]

## Akzeptanzkriterien

- [ ] [Kriterium 1]
- [ ] [Kriterium 2]
- [ ] Bestehende Tests bleiben grün
- [ ] Lint läuft fehlerfrei

## Scope

**In Scope:** [Was geändert werden soll]
**Out of Scope:** [Was NICHT angefasst werden soll — besonders wichtig bei Refactoring]

## Verifikation

```bash
vendor/bin/sail artisan test --compact
vendor/bin/sail bin pint --dirty --format agent
```

## Verwandt

- #123 (Beschreibung)
```

### Chore Template Notes

- **Scope boundaries are critical** for refactoring tickets — agents tend to "improve" adjacent code when given a refactoring task. Be explicit about what should NOT be touched.
- Chores default to `urgency:low` unless they unblock a higher-urgency ticket — in that case, inherit that ticket's urgency and reference it.

---

## Writing Acceptance Criteria

Acceptance criteria are the most important section for agent-friendly issues. They serve as the contract between the issue author and the resolver — both human and AI. An agent uses these criteria to validate its own work before submitting a PR.

### Format

Use **Given/When/Then** (BDD) format as checkboxes:

```markdown
- [ ] **Given** [precondition/context], **when** [action/event], **then** [expected observable outcome]
```

### Guidelines

- Each criterion should be independently verifiable — an agent can check it off without needing the others.
- Include both the **happy path** and **edge cases**.
- Criteria should map directly to Pest test assertions where possible.
- For bugs: always include one criterion that reproduces the original bug (regression test).
- For features: include at least one criterion per use case.
- Keep criteria behavioral (what the user sees), not implementation-specific (how the code works internally).

### Examples

```markdown
- [ ] **Given** ein Volunteer mit bestehender Schicht "Orgazelt 08:00-23:00",
      **when** er eine überlappende Schicht auswählt,
      **then** wird eine Fehlermeldung mit den überlappenden Zeiten angezeigt

- [ ] **Given** ein EntranceStaff-User mit Event-Scope,
      **when** ein gültiger Volunteer-QR-Code gescannt wird,
      **then** wird der Volunteer korrekt verifiziert und ein `event_arrivals`-Eintrag erstellt

- [ ] **Given** ein Event mit `cancellation_cutoff_hours = 24`,
      **when** ein Volunteer 12 Stunden vor Schichtbeginn versucht abzusagen,
      **then** wird die Absage mit Hinweis auf die 24h-Frist blockiert
```

---

## Duplicate / Closed-Issue Check

Before creating a new issue, search for existing issues (including closed ones) that match the topic:

```bash
gh issue list --repo reneweiser/voluntify --state closed --search "<keywords>" --limit 10
```

- If a **closed issue** covers the same problem or feature, **reopen it** instead of creating a new one:
  ```bash
  gh issue reopen <number> --repo reneweiser/voluntify
  gh issue comment <number> --repo reneweiser/voluntify --body "Reopened: <reason>"
  ```
- Update the issue body or add a comment with any new context (updated endpoint, new reproduction steps, changed urgency, etc.).
- Update labels if the urgency has changed.
- Inform the user that the existing issue was reopened and link to it.
- Only create a new issue if no matching closed issue exists.

---

## Urgency Rating System

Every ticket MUST include an urgency rating as both a GitHub label and a line in the issue body.

### Decision Flowchart

Follow this if/else chain top-to-bottom. Stop at the first match:

1. **Security vulnerability?**
   - Exploitable now → `urgency:critical`
   - Theoretical / needs specific conditions → `urgency:high`
2. **Core feature completely broken, no workaround?** → `urgency:critical`
3. **Core feature broken, but workaround exists?** → `urgency:high`
4. **Affects many/all users of a feature?** → `urgency:high`
5. **Contained bug affecting some users, or feature request that unblocks a core workflow (signup, scanning, attendance, gear tracking)?** → `urgency:medium`
6. **Everything else** (minor bugs, cosmetic issues, nice-to-have features, refactoring, tech debt, docs, tests) → `urgency:low`

### Special Rules

- **Feature requests** default to `urgency:low` unless they address a gap that blocks a core workflow, in which case assess via the flowchart.
- **Refactoring / tech debt / docs / tests** default to `urgency:low` unless they unblock a higher-urgency item — then inherit that item's urgency.
- **Mixed bug + feature request**: Rate urgency based on the bug portion. Suggest splitting into two tickets.
- **Ambiguous input**: If the description is too vague to assess urgency confidently, ask clarifying questions first. If workaround availability is unclear, ask.

### Confirmation

Before creating the issue, state the assessed urgency with a one-line reason:

> Urgency: **High** — core signup flow is broken but a workaround exists.

If the user disagrees, adjust accordingly.

### Body Format

Add the urgency line directly after the Endpoint/Location line:

```
**Urgency:** 🔴 Critical — [one-line reason]
```

Emoji mapping:
- 🔴 `Critical` — system down, security, no workaround
- 🟠 `High` — major breakage, wide impact, workaround exists
- 🟡 `Medium` — contained issue, some users affected
- 🟢 `Low` — minor, cosmetic, nice-to-have

### Label Application

Apply the urgency label via `--label "urgency:critical"` (etc.) when creating the issue. If the label doesn't exist, create it first:

```bash
gh label create "urgency:critical" --color "b60205" --description "System down, security issue, no workaround" --force
```

Label colors: critical=`b60205`, high=`d93f0b`, medium=`fbca04`, low=`0e8a16`.

---

## Checklist Before Submitting

- [ ] Checked for existing closed issues (reopen if found)
- [ ] Correct template selected (Bug / Feature / Chore)
- [ ] Endpoint/Location included
- [ ] Clear title with type prefix (`fix:` / `feat:` / `chore:`)
- [ ] Akzeptanzkriterien written as Given/When/Then checkboxes
- [ ] Betroffene Dateien lists specific file paths with change descriptions
- [ ] Scope boundaries defined (In Scope / Out of Scope)
- [ ] Verification commands included
- [ ] Related issues referenced
- [ ] Urgency assessed and label applied
- [ ] Type label applied (`bug` / `enhancement` / `chore`)
