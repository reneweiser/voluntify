# UC-18: Organizer konfiguriert Attendance-Stadien

**Akteur:** Organizer
**Ziel:** Anwesenheitsstadien für das Projekt anpassen

## Vorbedingungen

- Organizer hat Zugriff auf Projekteinstellungen

## Ablauf

1. Organizer öffnet Projekt → Einstellungen → **Anwesenheit**
2. Übersicht der konfigurierten Stadien:
   - **Eingecheckt (pünktlich)** — Core, nicht löschbar
   - **Verspätet** — optional, löschbar
   - **Entschuldigt** — optional, löschbar
   - **Nicht erschienen** — Core, nicht löschbar
3. Organizer kann:
   - Labels umbenennen (z.B. "Eingecheckt" → "Anwesend")
   - Neue Stadien hinzufügen (z.B. "Auf dem Weg", "Krank")
   - Optionale Stadien deaktivieren/löschen
   - **Default-Status** festlegen (z.B. "Offen" oder "Auf dem Weg")
4. Speichern

## Ergebnis

- Volunteer Admin Scanner zeigt die konfigurierten Stadien pro Schicht
- Default-Status wird für alle Volunteers angezeigt, bevor ein Check-in stattfindet
- Auto-NoShow markiert nur Volunteers, die noch den Default-Status haben
- Dashboard-Statistiken verwenden die konfigurierten Stadien

## Einschränkungen

- Core-Stadien ("Eingecheckt", "Nicht erschienen") können umbenannt, aber nicht gelöscht werden
- Mindestens ein Default-Status muss definiert sein

## Referenz

- Decision: PO-Session 2026-04-09 (Konfigurierbare Attendance-Stadien)
- Issue: #130
