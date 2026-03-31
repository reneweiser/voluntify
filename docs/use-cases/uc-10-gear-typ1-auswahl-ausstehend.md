# UC-10: Gear Typ-1 Ausgabe mit fehlender Auswahl

**Akteur:** Volunteer Admin
**Ziel:** Gear ausgeben, obwohl der Volunteer keine Auswahl getroffen hat

## Vorbedingungen

- Volunteer wurde manuell vom Organizer angelegt (ohne Typ-1-Auswahl)
- ODER: Gast auf Gästeliste ohne Typ-1-Auswahl

## Ablauf — Volunteer (manuell angelegt)

1. Volunteer Admin scannt QR-Code
2. Gear-Screen: T-Shirt zeigt **"Auswahl ausstehend"**
3. **Ausgabe blockiert** — Volunteer muss Auswahl selbst im Portal treffen
4. Volunteer Admin informiert den Volunteer: "Bitte wähle deine Größe im Portal"

## Ablauf — Gast (kein Portal)

1. Volunteer Admin scannt Gast-QR
2. Gear-Screen: T-Shirt zeigt **"Auswahl ausstehend"**
3. Operator tippt auf "Auswahl ausstehend"
4. **Dropdown öffnet sich:** XS / S / M / L / XL
5. Operator fragt mündlich → wählt "L"
6. System speichert: Auswahl = "L", Status → "Abgeholt"

## Ergebnis

- **Volunteer:** Muss selbst im Portal auswählen → dann Scanner-Ausgabe möglich
- **Gast:** Auswahl wird direkt im Scanner getroffen → Statistik korrekt

## Warum der Unterschied?

Volunteers haben ein Portal für Self-Service. Die Auswahl gehört zum Volunteer — der Organizer soll nicht raten müssen. Gäste haben kein Portal. Mündliche Abfrage + Scanner-Eingabe ist der einzige Weg.

## Referenz

- Gesamtübersicht Sek. 7 (Gear), Sek. 17.3 ("Auswahl ausstehend"), Sek. 18.4 (Gäste)
- Issues: #53, #88, #90
