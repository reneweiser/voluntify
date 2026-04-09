---
name: create-ticket
description: "Creates GitHub issues for the Voluntify project. Activates when the user asks to create a ticket, issue, bug report, or feature request; or says 'erstelle ein Ticket', 'neues Ticket', 'Ticket für Rene'."
---

# Create Ticket

## When to Apply

Activate this skill when:

- Creating GitHub issues / tickets
- Reporting bugs or requesting features for Rene

## Repository

Always create issues on: `reneweiser/voluntify`

## Required Fields

Every ticket MUST include:

### 1. Endpoint / Location

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

### 2. Title Format

- Prefix with type: `fix:`, `feat:`, `fix(ui):`, `feat(scanner):`, etc.
- Keep under 70 characters
- German or English — match the user's language

### 3. Body Structure

```markdown
**Endpoint:** `METHOD /route` oder **Komponente:** `Class` oder **Ort:** UI-Beschreibung
**Urgency:** 🔴/🟠/🟡/🟢 Level — Begründung

## Bug / Feature Request

Kurze Beschreibung des Problems oder der Anforderung.

## [Für Bugs: Ursache / Für Features: Erwartetes Verhalten]

Details, Code-Referenzen, betroffene Dateien.

## [Optional: Fix / Lösungsvorschlag]

Konkreter Vorschlag, wenn vorhanden.
```

### 4. Code References

- Include file paths and line numbers where relevant
- Reference related issues with `#number`
- Include code snippets for bugs (current vs. expected)

### 5. Screenshots

If the user has provided a screenshot, add a placeholder:
```markdown
<!-- Screenshot einfügen -->
```
And remind the user to attach it.

### 6. Urgency Rating

Every ticket MUST include an urgency rating as both a GitHub label and a line in the issue body.

#### Decision Flowchart

Follow this if/else chain top-to-bottom. Stop at the first match:

1. **Security vulnerability?**
   - Exploitable now → `urgency:critical`
   - Theoretical / needs specific conditions → `urgency:high`
2. **Core feature completely broken, no workaround?** → `urgency:critical`
3. **Core feature broken, but workaround exists?** → `urgency:high`
4. **Affects many/all users of a feature?** → `urgency:high`
5. **Contained bug affecting some users, or feature request that unblocks a core workflow (signup, scanning, attendance, gear tracking)?** → `urgency:medium`
6. **Everything else** (minor bugs, cosmetic issues, nice-to-have features, refactoring, tech debt, docs, tests) → `urgency:low`

#### Special Rules

- **Feature requests** default to `urgency:low` unless they address a gap that blocks a core workflow, in which case assess via the flowchart.
- **Refactoring / tech debt / docs / tests** default to `urgency:low` unless they unblock a higher-urgency item — then inherit that item's urgency.
- **Mixed bug + feature request**: Rate urgency based on the bug portion. Suggest splitting into two tickets.
- **Ambiguous input**: If the description is too vague to assess urgency confidently, ask clarifying questions first. If workaround availability is unclear, ask.

#### Confirmation

Before creating the issue, state the assessed urgency with a one-line reason:

> Urgency: **High** — core signup flow is broken but a workaround exists.

If the user disagrees, adjust accordingly.

#### Body Format

Add the urgency line directly after the Endpoint/Location line:

```
**Urgency:** 🔴 Critical — [one-line reason]
```

Emoji mapping:
- 🔴 `Critical` — system down, security, no workaround
- 🟠 `High` — major breakage, wide impact, workaround exists
- 🟡 `Medium` — contained issue, some users affected
- 🟢 `Low` — minor, cosmetic, nice-to-have

#### Label Application

Apply the urgency label via `--label "urgency:critical"` (etc.) when creating the issue. If the label doesn't exist, create it first:

```bash
gh label create "urgency:critical" --color "b60205" --description "System down, security issue, no workaround" --force
```

Label colors: critical=`b60205`, high=`d93f0b`, medium=`fbca04`, low=`0e8a16`.

## Checklist Before Submitting

- [ ] Endpoint/Location included
- [ ] Clear title with type prefix
- [ ] Related issues referenced
- [ ] Code references where applicable
- [ ] Urgency assessed and label applied
