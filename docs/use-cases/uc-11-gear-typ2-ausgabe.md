# UC-11: Gear Typ-2 Ausgabe (Getränkemarken)

**Akteur:** Volunteer Admin
**Ziel:** Mengenbasiertes Gear ausgeben (z.B. Getränkemarken, Essensbons)

## Vorbedingungen

- Volunteer oder Gast hat Typ-2 Gear zugewiesen
- Volunteer Admin Scanner mit Gear-Pickup-Modus aktiv
- **Internetverbindung erforderlich** (Typ-2 braucht Online-Sync)

## Ablauf

1. Scan oder manuelle Suche → Volunteer/Gast gefunden
2. Gear-Screen: "Getränkemarken: 0/3 abgeholt"
3. Operator tippt → "1/3 abgeholt"
4. Nächster Tap → "2/3 abgeholt"
5. Kontingent erreicht → Button deaktiviert

## Ergebnis

- Jede einzelne Ausgabe wird protokolliert
- Echtzeit-Sync verhindert Doppelausgabe bei mehreren Scannern

## Warum Online-Pflicht?

Typ-2 zählt Mengen. Wenn zwei Scanner offline arbeiten und beide 3 Marken ausgeben, hat der Volunteer 6 statt 3. Online-Sync mit Server-seitigem Counter verhindert diese Race Condition.

## Referenz

- Gesamtübersicht Sek. 7 (Gear Typ-2)
- Issue: #53
