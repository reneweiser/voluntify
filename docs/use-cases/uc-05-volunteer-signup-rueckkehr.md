# UC-05: Rückkehrender Volunteer meldet sich für weiteres Event an

**Akteur:** Volunteer (bereits im Projekt registriert)
**Ziel:** Für Schichten eines weiteren Events im selben Projekt anmelden

## Vorbedingungen

- Volunteer hat sich zuvor für ein Event im Projekt angemeldet
- E-Mail ist bereits verifiziert

## Ablauf

1. Volunteer öffnet Projektwebsite → sieht ein neues Event
2. Klickt "Anmelden" → **Schritt 1: E-Mail eingeben**
3. System erkennt: bekannte E-Mail → sendet **Magic Link** (kein erneutes Verifizieren)
4. Volunteer klickt Magic Link → landet direkt auf **Schritt 2**
   - Kurzer Hinweis auf bestehende Anmeldungen eingeblendet
   - Persönliche Daten vorausgefüllt
   - 20-Minuten-Timer startet
   - Neue Schichten auswählen
5. **Schritt 3: Custom Fields + Gear**
   - Projektfelder bereits vorausgefüllt (nur einmal pro Projekt)
   - Eventfelder neu (pro Event separat)
6. **Schritt 4: Zusammenfassung** → **Verbindlich anmelden**

## Ergebnis

- Neue Schichten zum bestehenden Projekt-Volunteer hinzugefügt
- Gleicher QR-Code (projektweit) — kein neuer Code
- Bestätigungs-E-Mail mit allen aktiven Schichten

## Sonderfälle

- **Volunteer hat alle Schichten abgesagt:** System erkennt die E-Mail trotzdem → Magic Link, normaler Flow
- **Volunteer kommt über Direktlink eines privaten Events:** Identischer Ablauf

## Referenz

- Gesamtübersicht Sek. 5.2 (Signup-Flow, Rückkehrender Volunteer)
- Issues: #69, #51
