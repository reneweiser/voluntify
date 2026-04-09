# UC-05: Rückkehrender Volunteer meldet sich für weiteres Event an

**Akteur:** Volunteer (bereits im Projekt registriert)
**Ziel:** Für Schichten eines weiteren Events im selben Projekt anmelden

## Vorbedingungen

- Volunteer hat sich zuvor für ein Event im Projekt angemeldet
- E-Mail ist bereits verifiziert

## Ablauf

1. Volunteer öffnet Projektwebsite → sieht ein neues Event
2. Klickt "Anmelden" → **Schritt 1: E-Mail + Verifikation**
   - E-Mail eingeben
   - System erkennt: bekannte E-Mail → sendet **Verifikations-Link** per E-Mail
   - Volunteer klickt Link → Identität bestätigt
   - Persönliche Daten vorausgefüllt (read-only oder editierbar)
3. **Schritt 2: Schichtauswahl**
   - 20-Minuten-Timer startet
   - **Bestehende Schichten** aus früheren Events werden als **read-only** angezeigt (nicht änderbar, nicht stornierbar — Stornierung nur über Volunteer Portal)
   - **Bestehendes Gear** ist read-only (Änderung nur durch Organizer)
   - Neue Schichten für das aktuelle Event auswählen
4. **Schritt 3: Gear + Custom Fields** (nur wenn vorhanden für neue Schichten)
   - Nur Gear und Custom Fields, die für die **neu gewählten** Events/Jobs gelten (Scoping)
   - Projektfelder: bereits ausgefüllt (vorbelegt, nur einmal pro Projekt)
   - Eventfelder: neu abfragen (pro Event separat)
   - Typ-1 Gear: nur für neue Schichten/Jobs
   - **Überspringen wenn keine neuen Gear-Items/Fields relevant**
5. **Schritt 4: Zusammenfassung** → **"Verbindlich anmelden"**
6. Bestätigungs-E-Mail mit aktualisierter Zusammenfassung aller aktiven Schichten

## Ergebnis

- Neue Schichten zum bestehenden Projekt-Volunteer hinzugefügt
- Gleicher QR-Code (projektweit) — kein neuer Code
- Bestätigungs-E-Mail mit allen aktiven Schichten (alt + neu)

## Sonderfälle

- **Volunteer hat alle Schichten abgesagt:** System erkennt die E-Mail trotzdem → Verifikation, normaler Flow
- **Volunteer kommt über Direktlink eines privaten Events:** Identischer Ablauf
- **Gear-Änderung gewünscht:** Nicht im Signup möglich — Organizer muss Gear manuell anpassen (#117)
- **Schicht aus früherem Event stornieren:** Nicht im Signup möglich — über Volunteer Portal (#14)

## Referenz

- Gesamtübersicht Sek. 5.2 (Signup-Flow, Rückkehrender Volunteer)
- Decision: PO-Session 2026-04-09 (Signup-Flow Rework)
- Issues: #69, #134
