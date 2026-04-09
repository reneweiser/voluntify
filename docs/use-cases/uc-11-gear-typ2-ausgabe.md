# UC-11: Gear Typ-2 Ausgabe (Getränkemarken)

**Akteur:** Volunteer Admin
**Ziel:** Mengenbasiertes Gear ausgeben (z.B. Getränkemarken, Essensbons)

## Vorbedingungen

- Volunteer oder Gast hat Typ-2 Gear zugewiesen
- Volunteer Admin Scanner mit Gear-Pickup-Modus aktiv
- **Internetverbindung erforderlich** (Typ-2 braucht Online-Sync)

## Ablauf (Scanner)

1. Scan oder manuelle Suche → Volunteer/Gast gefunden
2. Gear-Screen: "Getränkemarken: 0/3 abgeholt"
3. Operator tippt **[+1]** → "1/3 abgeholt"
4. **2 Sekunden Cooldown** — Button deaktiviert, visuelles Feedback
5. Button reaktiviert → nächster Tap möglich
6. Kontingent erreicht (3/3) → Button verschwindet

## Ablauf (Admin-Panel — Gear Pickup Ansicht)

1. Organizer öffnet Projekt → Gear Pickup
2. **Gruppiert nach Gear-Item** (z.B. Sektion "Getränkemarken")
3. Filter nach Gear-Item möglich
4. Pro Volunteer: "2/3 abgeholt" → [+1] / [-1] Buttons
5. Volunteer-Suche filtert übergreifend

## Ergebnis

- Jede einzelne Ausgabe wird als `VolunteerGearPickup` protokolliert (Audit Trail)
- Echtzeit-Sync verhindert Doppelausgabe bei mehreren Scannern
- Verbleibend: `quantity_entitled - COUNT(pickups)`

## Warum Online-Pflicht?

Typ-2 zählt Mengen. Wenn zwei Scanner offline arbeiten und beide 3 Marken ausgeben, hat der Volunteer 6 statt 3. Online-Sync mit Server-seitigem Counter verhindert diese Race Condition.

## Warum 2 Sekunden Cooldown?

Am Ausgabe-Punkt (z.B. Bar) muss der Operator eindeutig sehen, dass ein Getränk verbucht wurde. Ohne Cooldown kann versehentliches Doppeltippen zu falschen Zählern führen.

## Referenz

- Gesamtübersicht Sek. 7 (Gear Typ-2)
- Decision: PO-Session 2026-04-09 (Gear Pickup Counter + Cooldown)
- Issues: #53, #110, #135, #145
