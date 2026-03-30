# Product Owner Session — 2026-03-30

Zusammenfassung aller Entscheidungen und Ergebnisse aus der PO-Session mit Olaf Kammler.

---

## Architekturentscheidungen (F1–F10)

| # | Frage | Entscheidung | Issue |
|---|---|---|---|
| F1 | Magic Link Routing | Direkt zurück in den Signup-Flow für das angeforderte Event | #69 |
| F2 | QR-Code Scope | Ein QR-Code pro Projekt-Mitgliedschaft — gilt für alle Events | #51 |
| F3 | Gear Typ-1 Reversibilität | Alle Zustände jederzeit änderbar — kein Einfrieren nach Zeitfenster | #53 |
| F4 | Dashboard Gear Favoriten | Organizer wählt pro Projekt, welche Gear-Items im Dashboard erscheinen | #76 |
| F5 | Zeitzone Digest-Mail | Server-Zeitzone — keine separate Konfiguration | #85 |
| F6 | Event ohne Schichten veröffentlichen | Blockiert — Veröffentlichen erst möglich wenn mind. eine Schicht existiert | #45 |
| F7 | Signup Reihenfolge | Daten zuerst (Vorname, Nachname, Telefon), dann Schichtauswahl | #69 |
| F8 | Gear-only Scanner, Volunteer ohne Gear | Neutrale Meldung „Kein Gear zugewiesen" — kein Fehlerstatus | #56 |
| F9 | Gear-Ausgabe nach Ablauf Zeitfenster | Organizer kann Zeitfenster nachträglich verlängern | #56, #72 |
| F10 | Projektwebsite vor erstem Publish | Preview für Organizer, 404 für alle anderen | #83 |

## Neue Features entschieden

### Announcements (Sek. 16, Issue #87)

- **Empfänger:** Filterbasiert (Event → Job → Schicht), kombinierbar
- **Kanal:** Nur E-Mail (kein Portal-Banner)
- **Zeitpunkt:** Sofort oder geplant
- **Inhalt:** Freitext (Subject + Body) oder gespeicherte Announcement-Templates (wiederverwendbar)
- **Häufigkeit:** Keine Begrenzung (Organizer-only)
- **Ebene:** Projekt

### Volunteer Management durch Organizer (Sek. 17, Issue #88)

- **Volunteer ohne Schicht:** Valider Zustand — Eligibility-Check schützt Scanner-Zugriff
- **Manuelles Anlegen:** Nur E-Mail Pflicht, alle anderen Felder optional
- **Nach Anlage:** E-Mail `volunteer_added_by_organizer` mit Portal-Link
- **Fehlende Typ-1-Auswahl:** Scanner zeigt „Auswahl ausstehend" (generisch, nicht größen-spezifisch)
- **Custom Fields ohne Antwort:** „Keine Angabe" im Organizer-UI
- **Organizer-Aktionen:** Schichten zuweisen/ändern, Anmeldung stornieren, Stammdaten bearbeiten
- **E-Mail nach Änderung:** Dieselbe Standard-E-Mail wie bei Volunteer-eigener Änderung
- **Ebene:** Projekt

### Weitere Entscheidungen

- **Gear Typ-1 Zustände:** Konfigurierbar per Liste, nicht hardcoded (#53)
- **Scanner:** Kein Auto-Dismiss — manueller „Nächsten scannen"-Button (#71)
- **SMTP:** Organisation-Level als Default, Projekt-Level als Override (#47)
- **Neue E-Mail-Templates:** `volunteer_welcome` und `volunteer_added_by_organizer` (#81)
- **Signup-Flow:** Nach Verifizierung separate Willkommens-E-Mail mit Portal-Link

## GitHub-Aktionen

### Issue-Kommentare hinzugefügt
- #53 — Konfigurierbare Typ-1 Zustände (status_options)
- #71 — Kein Auto-Dismiss, manueller Reactivation-Button
- #47 — Org-Level SMTP-Konfiguration
- #81 — Neue Templates: volunteer_welcome, volunteer_added_by_organizer
- #83 — Pre-Publish URL: Preview für Organizer, 404 für Rest
- #72 — F9: Zeitfenster nachträglich verlängerbar
- #87 — Klarstellung: Projektebene
- #88 — Klarstellung: Projektebene

### Neue Issues erstellt
- **#87** — Announcements: manual organizer emails to filtered volunteer groups
- **#88** — Volunteer Management: manual creation and organizer actions on existing volunteers

### Pull Request
- **#89** — docs: rewrite all documentation after product owner feedback

## Dokumentation überarbeitet

20 Dateien aktualisiert (21 im Commit inkl. managing-projects.md als neue Datei):

**Komplett neu geschrieben:**
- README.md, docs/README.md
- docs/ubiquitous-language.md, docs/getting-started.md, docs/roles-and-permissions.md
- docs/recruiting-volunteers.md, docs/managing-volunteers.md, docs/checking-in-volunteers.md
- docs/creating-events.md, docs/managing-projects.md (neu), docs/managing-your-team.md
- docs/settings-and-account.md, docs/tracking-attendance.md
- planning/specs/project.md, planning/specs/status.md

**Revision Notes:**
- planning/design/app-design-spec.md, planning/design/app-concept.md, planning/design/domain-landscape.md

**Deprecated:**
- docs/managing-event-groups.md → Verweis auf managing-projects.md

**Stil:** Alle Docs enthalten Beispiele ("> Example:") und Begründungen ("> Why ...?") bei Architekturentscheidungen.
