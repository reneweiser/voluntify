# Product Owner Session — 2026-04-09

Zusammenfassung aller Entscheidungen und Ergebnisse aus der Testing- und PO-Session mit Olaf Kammler.

---

## Architekturentscheidungen

### Arrival vs. Attendance — Getrennte Konzepte (#130)

| Konzept | Scanner-Typ | Bezug | Bedeutung |
|---|---|---|---|
| **Arrival** | Entry Staff | Pro Event | Volunteer hat Zutritt zum Event-Gelände |
| **Attendance** | Volunteer Admin / Organizer | Pro Schicht | Volunteer hat sich zur Schicht gemeldet |

- Arrival kann zeitlich versetzt für verschiedene Events stattfinden (z.B. Konzert am 3.5. und am 5.5. als Volunteer-Benefit)
- Attendance hat konfigurierbare Stadien pro Projekt mit Default-Status
- Kein globaler Arrival-Button im VA Scanner (#138)
- Entry Staff Scanner: QR-Scan + Volunteer-Suche + Gastliste

### Konfigurierbare Attendance-Stadien (#130)

Default-Stadien (deutsch, anpassbar durch Organizer in Projekteinstellungen):
- **Eingecheckt (pünktlich)** — core, nicht löschbar
- **Verspätet**
- **Entschuldigt** — abgemeldete Abwesenheit
- **Nicht erschienen** — core, nicht löschbar, auto-markiert

Kein separater "Unmarked"-Zustand — stattdessen konfigurierbarer **Default-Status** (z.B. "Offen" oder "Auf dem Weg"). Organizer entscheidet, was "noch nicht eingecheckt" bedeutet.

### Gear + Custom Fields Scoping (#110, #139)

Einheitliches Scoping für beide — auf **Projekt-Ebene** definiert:

| Feld | Typ |
|---|---|
| `event_ids` | Nullable JSON, Mehrfachauswahl |
| `job_ids` | Nullable JSON, Mehrfachauswahl |

- Wenn beides null → projekt-weit
- Gear brauchte `event_ids` (nur `job_ids` war vorhanden)
- Custom Fields von Event-Ebene auf Projekt-Ebene gehoben

### Signup-Flow Rework (#134)

| Step | Inhalt |
|---|---|
| **Step 1** | E-Mail + Verifikation (ganz am Anfang). Neuer und bekannter User: immer Verifikations-Link per E-Mail |
| **Step 2** | Schichten auswählen (Reservierung startet hier, 20 Min). Bekannter Volunteer: bestehende Schichten read-only |
| **Step 3** | Gear Typ 1 + Custom Fields — nur für neu gewählte Schichten, überspringbar wenn nicht vorhanden |
| **Step 4** | Zusammenfassung + **"Verbindlich anmelden"**. Bestätigungs-E-Mail nach Klick |

- Existierende Volunteers können weitere Schichten hinzubuchen
- Bestehendes Gear/Schichten sind read-only im Signup
- Gear-Änderung nur durch Organizer (#117)

### Navigation (#144, #146)

Event- und Projekt-Menü gruppiert mit Zwischenüberschriften:

**Projekt-Navigation:**
- Allgemein: Overview, Website, Events, Members
- Vorbereitung: Gear, Custom Fields, Scanners, Gästelisten
- Kommunikation: Ankündigungen
- Einstellungen: Anwesenheit, Hinweistexte

**Event-Navigation:**
- Allgemein: Overview, Jobs & Shifts, Volunteers
- Vorbereitung: Enroll, Ankündigungen
- Durchführung: Anwesenheit
- Konfiguration: E-Mail-Templates, Einstellungen

Gear, Gear Pickup, Custom Fields → aus Event-Menü raus, auf Projekt-Ebene.

**Publish-Button:** In die Projekt-Overview (nicht hinter "Website" versteckt).

### Profil-Löschung (#143)

- Blockiert wenn nicht-stornierbare Schichten existieren
- Transparenter Hinweis: Leistungsverpflichtung, Team baut auf Zuverlässigkeit
- "Abgeschlossen" = `ends_at` der Schicht liegt in der Vergangenheit
- Erlaubt wenn alle Schichten abgeschlossen oder stornierbar

### Custom Fields Erweiterung (#139)

- Checkbox: definierbare Optionen (wie bei Dropdown)
- Single vs. Multi Choice für Dropdown und Checkbox
- `allow_multiple` Flag auf CustomRegistrationField

### Scanner Modes (#132, #133)

- Modes (Check-in, Gear Pickup) nur für Volunteer Admin auswählbar
- Entry Staff braucht keine Mode-Auswahl
- Gear-only Mode: keine Schicht-Information anzeigen
- Gear-Item-Auswahl pro Scanner (welche Items dieser Scanner ausgeben darf)

---

## Bugs gefunden und gemeldet

| # | Bug |
|---|---|
| #101 | Event erstellen — Project-ID nicht übermittelt (wire:model.live) |
| #116 | Manual Enrollment — Enroll-Button bleibt disabled |
| #121 | Timezone-Dropdown Dark Mode |
| #128 | Scanner löschen → 500 statt Fehlermeldung |
| #133 | VA gear-only zeigt Schichten |
| #141 | Custom Field Dropdown ohne Placeholder |
| #142 | 403/500 beim Stornieren von Schichten |

## Neue Feature-Tickets

| # | Feature |
|---|---|
| #102 | Clone/Duplicate für Jobs |
| #104 | Stornierungsbestätigung per Mail |
| #105 | Volunteer Profil löschen (DSGVO) |
| #106 | Scanner Auth-Code in Mail + Admin-Panel |
| #110 | Gear Typ 2 Quantity Pickup |
| #115 | Ticket-Zugang erneut anfordern |
| #117 | Arrival + Gear im Event Volunteer Detail |
| #130 | Konfigurierbare Attendance-Stadien |
| #132 | Scanner Modes nur für VA |
| #134 | Signup-Flow Rework |
| #135 | Quantity Gear Pickup Counter + Cooldown |
| #136 | Scanner Kamera Auto-Pause |
| #138 | Arrival-Button raus aus VA Scanner |
| #139 | Custom Fields Erweiterung + Projekt-Scoping |
| #140 | Zurück-Navigation nach Signup |
| #143 | Profil-Löschung blockieren |
| #144 | Event-Navigation reorganisieren |
| #145 | Gear Pickup nach Gear gruppiert + Filter |
| #146 | Projekt-Navigation reorganisieren |

## Dokumentation aktualisiert

**Markdown-Docs:**
- `ubiquitous-language.md` — Arrival multi-event, Attendance konfigurierbar + Default-Status, Gear event_ids, Scanner-Types
- `tracking-attendance.md` — Konfigurierbare Stadien, Default-Status, Auto-NoShow-Regeln
- `checking-in-volunteers.md` — Entry Staff = Arrival, VA = Attendance, Gear-only Modus
- `review_voluntify-gesamtuebersicht.md` — Architektur-Hierarchie, Rollen inkl. Gast, Signup-Journey, Scanner-Workflows

**SVG-Figuren:**
- `role-hierarchy.svg` — Gast-Rolle hinzugefügt
- `arrival-vs-attendance.svg` — Multi-Arrival, konfigurierbare States, Default-Status
- `volunteer-journey.svg` — E-Mail-Verifikation als Step 1, "Verbindlich anmelden"
- `event-lifecycle.svg` — Draft ↔ Published Open bidirektional, Public/Private
- `scanner-workflow.svg` — Dual-Diagramm Entry Staff + Volunteer Admin

## Skill erstellt

- `.claude/skills/create-ticket/SKILL.md` — Ticket-Erstellungsrichtlinien mit Endpoint-Pflichtfeld
