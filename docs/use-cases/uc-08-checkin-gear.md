# UC-08: Volunteer Admin checkt Schicht ein und gibt Gear aus (Attendance)

**Akteur:** Volunteer Admin
**Ziel:** Volunteer für Schicht einchecken (Attendance) und Gear ausgeben

## Vorbedingungen

- Volunteer Admin Scanner ist konfiguriert (Modus: Check-in + Gear Pickup, oder einzeln)
- Scanner ist aktiv (innerhalb Zeitfenster)
- Volunteer hat mindestens eine Schicht

## Ablauf

1. Volunteer Admin scannt QR-Code oder sucht manuell
2. **Volunteer-Info:** Name, Telefon, Schichten, Gear angezeigt
3. **Attendance pro Schicht** (wenn Check-in-Modus aktiv):
   - Jede Schicht zeigt den aktuellen Status (Default-Status aus Projekteinstellungen)
   - Tap auf Schicht → Status aus konfigurierten Stadien wählen
   - Automatische Erkennung: vor Schichtbeginn + Grace Period = "Eingecheckt (pünktlich)", danach = "Verspätet"
4. **Gear-Ausgabe** (wenn Gear-Pickup-Modus aktiv):
   - Typ-1 (T-Shirt): aktueller Status → Tap → neuer Status aus Organizer-konfigurierter Liste
   - Typ-2 (Getränkemarken): "1/3 abgeholt" → Tap [+1] → "2/3 abgeholt" (2s Cooldown nach jedem Tap)
5. **"Nächsten scannen"** → bereit für nächsten Volunteer

## Ergebnis

- **Attendance** (Schicht-Check-in) ist pro Schicht erfasst
- Gear-Status aktualisiert
- Kein Event Arrival — das ist Aufgabe des Entry Staff Scanners

## Modi

| Modus | Anzeige nach Scan |
|---|---|
| Check-in only | Schichten mit Attendance-Status — kein Gear |
| Gear Pickup only | Gear-Items — keine Schichten |
| Beide | Schichten + Gear |

## Sonderfälle

- **Kein Gear zugewiesen:** Neutrale Meldung "Kein Gear zugewiesen" (kein Rot)
- **Volunteer ohne Schicht:** Eligibility-Check schlägt fehl → kein Check-in möglich
- **Typ-2 offline:** Nicht möglich — Typ-2 braucht Internetverbindung (Race-Condition-Schutz)
- **Typ-2 Cooldown:** Nach jedem Pickup 2 Sekunden Sperre, damit Operator sieht, dass verbucht wurde (#135)

## Referenz

- Gesamtübersicht Sek. 8.7 (Volunteer Admin Scanner)
- Decision: PO-Session 2026-04-09 (Arrival vs. Attendance, konfigurierbare Stadien)
- Issues: #56, #53, #130, #135, #138
