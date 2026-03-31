# UC-09: Gast von Gästeliste am Einlass

**Akteur:** Entry Staff
**Ziel:** Nicht-Volunteer-Gast (VIP, Künstler, Begleitperson) am Einlass einchecken

## Vorbedingungen

- Gästeliste ist bestätigt und dem Entry Staff Scanner zugeordnet
- Gast hat QR-Code (per E-Mail erhalten) oder wird manuell gesucht

## Ablauf — mit QR-Code

1. Entry Staff scannt QR-Code des Gastes
2. Ergebnis-Screen:
   - 🟢 **Grün:** "Gast — DJ Soundwave 1/3" + Name (wenn vorhanden)
   - 🟡 **Gelb:** "Bereits eingecheckt um 19:32"
   - 🔴 **Rot:** "QR-Code ungültig" (Gast wurde entfernt)
3. **"Nächsten scannen"**

## Ablauf — ohne QR-Code

1. Tab **Gastliste** → Suche "Soundwave"
2. Gästegruppe erscheint: DJ Soundwave (0/3 eingecheckt)
3. Entry Staff tippt auf den entsprechenden Eintrag → Check-in bestätigen

## Ergebnis

- Gast ist als eingecheckt markiert
- Organizer sieht: "DJ Soundwave: 1/3 eingecheckt"

## Ablauf — Gear-Ausgabe (Volunteer Admin Scanner)

Wenn der Gast Gear hat, erscheint er auch im Volunteer Admin Scanner:

1. Scan oder Suche → Gast gefunden
2. **Typ-2:** "0/3 Getränkemarken" → Tap → +1
3. **Typ-1 mit Auswahl:** Normaler Zustandswechsel
4. **Typ-1 ohne Auswahl:** "Auswahl ausstehend" → Operator fragt mündlich → wählt Größe im Dropdown → gespeichert für Statistik

## Sonderfälle

- **Gast ohne E-Mail (kein QR):** Nur über manuelle Suche in der Gastliste auffindbar
- **Gast nachträglich entfernt:** QR zeigt Rot, in Gastliste nicht mehr sichtbar
- **Gast hat kein Gear:** Erscheint nicht im Volunteer Admin Scanner

## Referenz

- Gesamtübersicht Sek. 18 (Gästelisten)
- Issue: #90
