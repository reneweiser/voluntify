# UC-07: Entry Staff scannt Volunteer am Einlass

**Akteur:** Entry Staff
**Ziel:** Volunteer am Einlass per QR-Code validieren

## Vorbedingungen

- Entry Staff Scanner ist konfiguriert und aktiv (innerhalb Zeitfenster)
- Entry Staff hat Scanner-Link erhalten und geöffnet

## Ablauf

1. Entry Staff öffnet Scanner-Link → Kamera-Viewfinder
2. Volunteer zeigt QR-Code auf dem Handy
3. Scanner erkennt QR automatisch → Ergebnis-Screen:
   - 🟢 **Grün:** "Zugriff erlaubt" + Name des Volunteers
   - 🟡 **Gelb:** "Bereits eingecheckt" + Name + letzter Scan-Zeitpunkt
   - 🔴 **Rot:** "Kein Zugriff" + Grund (z.B. "Keine aktive Schicht")
4. Entry Staff liest das Ergebnis
5. Tippt **"Nächsten scannen"** → Kamera-Viewfinder für nächsten Volunteer

## Ergebnis

- Ankunft des Volunteers ist im System erfasst
- Kein Auto-Dismiss — jedes Ergebnis muss bewusst bestätigt werden

## Sonderfälle

- **Volunteer ohne Schicht:** 🔴 Rot — "Keine aktive Schicht"
- **QR-Code eines entfernten Gastes:** 🔴 Rot — "QR-Code ungültig"
- **Offline:** Scanner validiert lokal, Check-in wird bei Reconnect gesynct
- **Volunteer nicht in Cache:** QR validiert trotzdem (JWT ist self-contained), Hinweis "Nicht in lokaler Liste"

## Alternativ: Manuelle Suche

Wenn Volunteer keinen QR-Code hat:
1. Tab **Gastliste** → Suche nach Name
2. Volunteer auswählen → Check-in bestätigen

## Referenz

- Gesamtübersicht Sek. 8.6 (Entry Staff Scanner)
- Issues: #58, #71, #73
