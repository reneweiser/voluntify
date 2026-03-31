# UC-08: Volunteer Admin checkt Schicht ein und gibt Gear aus

**Akteur:** Volunteer Admin
**Ziel:** Volunteer für Schicht einchecken und Gear ausgeben

## Vorbedingungen

- Volunteer Admin Scanner ist konfiguriert (Modus: Beides)
- Scanner ist aktiv (innerhalb Zeitfenster)
- Volunteer hat mindestens eine Schicht

## Ablauf

1. Volunteer Admin scannt QR-Code oder sucht manuell
2. **Eligibility-Check:** Hat der Volunteer eine Schicht? → Ja
3. **Check-in-Screen:** Schichten des Volunteers angezeigt
   - Schicht auswählen → Anwesenheit markieren (On Time / Late)
4. **Gear-Screen:** Gear-Items angezeigt
   - Typ-1 (T-Shirt): Status "Ausstehend" → Tap → "Abgeholt"
   - Typ-2 (Getränkemarken): "0/3 abgeholt" → Tap → "1/3 abgeholt"
5. **"Nächsten scannen"** → bereit für nächsten Volunteer

## Ergebnis

- Anwesenheit für die Schicht erfasst
- Gear-Status aktualisiert
- Alles über den Scanner — kein Web-UI nötig

## Sonderfälle

- **Kein Gear zugewiesen:** Neutrale Meldung "Kein Gear zugewiesen" (kein Rot)
- **Volunteer ohne Schicht:** Eligibility-Check schlägt fehl → kein Check-in möglich
- **Typ-2 offline:** Nicht möglich — Typ-2 braucht Internetverbindung (Race-Condition-Schutz)

## Referenz

- Gesamtübersicht Sek. 8.7 (Volunteer Admin Scanner)
- Issues: #56, #53
