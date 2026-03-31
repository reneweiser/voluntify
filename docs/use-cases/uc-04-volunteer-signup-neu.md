# UC-04: Neuer Volunteer meldet sich an

**Akteur:** Volunteer (neu, nicht im System)
**Ziel:** Für Schichten anmelden und QR-Code erhalten

## Vorbedingungen

- Mindestens ein Event ist Published Open
- Volunteer hat den Projektwebsite-Link oder einen Direktlink

## Ablauf

1. Volunteer öffnet Projektwebsite → sieht Events mit "Anmelden"-Button
2. Klickt auf ein Event → **Schritt 1: E-Mail eingeben**
3. System erkennt: neue E-Mail → sendet **Verifizierungs-E-Mail** (Code oder Link, 24h gültig)
4. Volunteer verifiziert → System sendet **Willkommens-E-Mail** (`volunteer_welcome`) mit Portal-Link
5. Volunteer klickt Link → landet auf **Schritt 2: Daten + Schichtauswahl**
   - 20-Minuten-Timer startet
   - Vorname, Nachname eingeben
   - Schichten auswählen (Kapazitätsanzeige, Überschneidungsprüfung)
6. **Schritt 3: Custom Fields + Gear**
   - Projektfelder beantworten (z.B. "Diätanforderungen")
   - Typ-1 Gear wählen (z.B. T-Shirt-Größe)
   - Eventfelder beantworten (z.B. "Parkplatz benötigt?")
7. **Schritt 4: Zusammenfassung** → **Verbindlich anmelden**
8. Bestätigungs-E-Mail (`signup_confirmation`) mit Schichtdetails und Portal-Link

## Ergebnis

- Volunteer ist im Projekt registriert
- Schichten sind reserviert
- QR-Code (projektweiter) ist im Helfer-Portal verfügbar
- 24h- und 4h-Reminder werden geplant

## Sonderfälle

- **Timer läuft ab:** Reservierungen werden freigegeben, Volunteer kann neu starten
- **Schicht voll:** "Voll"-Badge, Auswahl blockiert
- **Überschneidende Schichten:** Warnung + Server-Validierung, auch eventübergreifend
- **Schicht ohne Zeiten:** Überschneidungsprüfung wird übersprungen

## Referenz

- Gesamtübersicht Sek. 5.2 (Signup-Flow)
- Issues: #69, #49, #50, #80, #82
