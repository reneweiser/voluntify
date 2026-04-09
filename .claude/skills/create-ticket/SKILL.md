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

## Checklist Before Submitting

- [ ] Endpoint/Location included
- [ ] Clear title with type prefix
- [ ] Related issues referenced
- [ ] Code references where applicable
