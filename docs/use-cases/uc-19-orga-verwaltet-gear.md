# UC-19: Organizer verwaltet Volunteer-Gear im Event

**Akteur:** Organizer
**Ziel:** Gear-Zuweisungen eines Volunteers anpassen (Größe ändern, ausgeben, entfernen)

## Vorbedingungen

- Volunteer ist im Projekt registriert und hat Gear zugewiesen
- Organizer hat Zugriff auf Event → Volunteer Detail

## Ablauf

1. Organizer öffnet Event → Volunteers → Volunteer auswählen
2. Volunteer-Detailansicht zeigt:
   - Persönliche Daten
   - Schichten mit Attendance-Status
   - **Gear-Zuweisungen** mit aktuellem Status
3. **Typ-1 Gear (z.B. T-Shirt):**
   - Aktuelle Größe angezeigt → Dropdown zum Ändern (z.B. L → XL)
   - Status ändern (z.B. "Ausstehend" → "Abgeholt")
4. **Typ-2 Gear (z.B. Getränkemarken):**
   - Aktueller Zähler angezeigt (z.B. "2/3 abgeholt")
   - [+1] / [-1] Buttons zum manuellen Anpassen
5. **Arrival-Status** anzeigen (read-only — wird durch Entry Staff Scanner gesetzt)
6. **Attendance pro Schicht** manuell markieren (z.B. Volunteer als "Eingecheckt" markieren ohne Scanner)

## Ergebnis

- Gear-Zuweisung aktualisiert
- Änderungen im Activity Log erfasst
- Organizer kann ohne Scanner agieren (Fallback wenn Scanner ausfällt)

## Warum?

Volunteers können ihre Gear-Auswahl (z.B. T-Shirt-Größe) nicht selbst ändern. Der Organizer ist der einzige, der Gear manuell anpassen kann. Außerdem dient die Ansicht als Fallback wenn Scanner nicht verfügbar sind.

## Referenz

- Decision: PO-Session 2026-04-09
- Issue: #117
