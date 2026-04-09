# UC-04: Neuer Volunteer meldet sich an

**Akteur:** Volunteer (neu, nicht im System)
**Ziel:** Für Schichten anmelden und QR-Code erhalten

## Vorbedingungen

- Mindestens ein Event ist Published Open
- Volunteer hat den Projektwebsite-Link oder einen Direktlink

## Ablauf

1. Volunteer öffnet Projektwebsite → sieht Events mit "Anmelden"-Button
2. Klickt auf ein Event → **Schritt 1: E-Mail + Verifikation**
   - E-Mail eingeben
   - System erkennt: neue E-Mail → sendet **Verifizierungs-E-Mail** (Code oder Link, 24h gültig)
   - Volunteer verifiziert → Willkommens-E-Mail (`volunteer_welcome`) mit Portal-Link
   - Vorname, Nachname, Telefon eingeben
3. **Schritt 2: Schichtauswahl**
   - 20-Minuten-Timer startet
   - Schichten auswählen (Kapazitätsanzeige, Überschneidungsprüfung)
4. **Schritt 3: Gear + Custom Fields** (nur wenn vorhanden, sonst überspringen)
   - Nur Gear und Custom Fields, die für die gewählten Events/Jobs gelten (Scoping via `event_ids`/`job_ids`)
   - Typ-1 Gear wählen (z.B. T-Shirt-Größe)
   - Custom Fields beantworten (z.B. "Diätanforderungen")
   - Typ-2 Gear wird automatisch im Backend zugewiesen (nicht angezeigt)
5. **Schritt 4: Zusammenfassung** → **"Verbindlich anmelden"**
6. Bestätigungs-E-Mail (`signup_confirmation`) mit Zusammenfassung: Schichtdetails, Gear, Portal-Link

## Ergebnis

- Volunteer ist im Projekt registriert
- Schichten sind verbindlich gebucht
- QR-Code (projektweiter) ist im Helfer-Portal verfügbar
- 24h- und 4h-Reminder werden geplant

## Sonderfälle

- **Timer läuft ab:** Reservierungen werden freigegeben, Volunteer kann neu starten
- **Schicht voll:** "Voll"-Badge, Auswahl blockiert
- **Überschneidende Schichten:** Warnung + Server-Validierung, auch eventübergreifend
- **Schicht ohne Zeiten:** Überschneidungsprüfung wird übersprungen
- **Kein Gear / keine Custom Fields:** Schritt 3 wird übersprungen
- **Nach Abschluss:** Link zurück zur Projektseite (weitere Events ansehen)

## Referenz

- Gesamtübersicht Sek. 5.2 (Signup-Flow)
- Decision: PO-Session 2026-04-09 (Signup-Flow Rework)
- Issues: #69, #134
