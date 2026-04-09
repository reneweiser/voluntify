# UC-07: Entry Staff scannt Volunteer am Einlass (Event Arrival)

**Akteur:** Entry Staff
**Ziel:** Volunteer am Einlass per QR-Code validieren und Event Arrival erfassen

## Vorbedingungen

- Entry Staff Scanner ist konfiguriert und aktiv (innerhalb Zeitfenster)
- Entry Staff hat Scanner-Link und Auth-Code erhalten und geöffnet
- Scanner ist einem bestimmten Event zugeordnet

## Ablauf

1. Entry Staff öffnet Scanner-Link → Kamera-Viewfinder
2. Volunteer zeigt QR-Code auf dem Handy
3. Scanner erkennt QR automatisch → Ergebnis-Screen:
   - 🟢 **Grün:** "Zugriff erlaubt" + Name des Volunteers → Tap "Confirm Arrival"
   - 🟡 **Gelb:** "Bereits eingecheckt" + Name + letzter Scan-Zeitpunkt
   - 🔴 **Rot:** "Kein Zugriff" + Grund (z.B. "Keine aktive Schicht")
4. Entry Staff liest das Ergebnis
5. Tippt **"Nächsten scannen"** → Kamera-Viewfinder für nächsten Volunteer

## Ergebnis

- **Event Arrival** des Volunteers ist erfasst (pro Event, nicht pro Schicht)
- Kein Auto-Dismiss — jedes Ergebnis muss bewusst bestätigt werden
- Arrival ≠ Attendance: Schicht-Check-in passiert separat über den Volunteer Admin Scanner

## Mehrere Events

Ein Volunteer kann Arrivals an verschiedenen Events haben (z.B. als Benefit für Volunteering):
- Arrival A: Konzert Abend 1 (03.05., 15:00) — Entry Scanner "Eingang Konzert"
- Arrival B: Konzert Abend 2 (05.05., 22:00) — anderer Entry Scanner

Jeder Entry Staff Scanner ist einem Event zugeordnet und erfasst Arrivals für dieses Event.

## Sonderfälle

- **Volunteer ohne Schicht:** 🔴 Rot — "Keine aktive Schicht"
- **QR-Code eines entfernten Gastes:** 🔴 Rot — "QR-Code ungültig"
- **Offline:** Scanner validiert lokal, Arrival wird bei Reconnect gesynct
- **Volunteer nicht in Cache:** QR validiert trotzdem (JWT ist self-contained), Hinweis "Nicht in lokaler Liste"

## Alternativ: Volunteer-Suche und Gastliste

Wenn Volunteer keinen QR-Code hat:
1. Tab **Volunteers** → Suche nach Name
2. Volunteer auswählen → Arrival bestätigen

Für Gäste:
1. Tab **Gastliste** → Suche nach Name oder Gruppe
2. Gast auswählen → Check-in bestätigen

## Referenz

- Gesamtübersicht Sek. 8.6 (Entry Staff Scanner)
- Decision: PO-Session 2026-04-09 (Arrival vs. Attendance)
- Issues: #58, #71, #73, #130
