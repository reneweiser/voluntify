# UC-15: Gästeliste erstellen und bestätigen

**Akteur:** Organizer
**Ziel:** Gästeliste für VIPs/Künstler/Begleitpersonen anlegen und QR-Codes versenden

## Vorbedingungen

- Projekt existiert
- Mindestens ein Entry Staff Scanner ist konfiguriert

## Ablauf

1. Projekt → **Gästelisten** → **Neue Gästeliste**
2. Konfiguration:
   - Name: "Künstler Hauptabend"
   - Entry Staff Scanner zuordnen (Pflicht)
   - Gear Items zuweisen (optional, Dropdown)
3. **Gästegruppen anlegen:**
   - "DJ Soundwave" — 3 Einträge
     - #1: Name "DJ Soundwave", E-Mail "dj@example.com", Gear: 3 Getränkemarken + T-Shirt
     - #2: E-Mail "dj@example.com", Gear: 2 Getränkemarken
     - #3: kein Name, keine E-Mail, Gear: 2 Getränkemarken
   - "Moderatorin Meier" — 2 Einträge
4. Status: **Entwurf** — keine E-Mails werden versendet
5. Organizer prüft die Liste
6. **Gästeliste bestätigen**:
   - QR-Codes generiert (einer pro Eintrag)
   - Gruppierte E-Mails:
     - dj@example.com → 1 Mail mit 2 QR-Codes (#1 + #2)
     - Eintrag #3 ohne E-Mail → kein Versand, QR nur im System
7. Status: **Bestätigt**

## Ergebnis

- Gäste sind im Entry Staff Scanner sichtbar (Gastliste-Tab)
- QR-Codes sind per Scanner validierbar
- Gäste mit Gear erscheinen im Volunteer Admin Scanner

## Nachträgliche Änderungen

- **Gast hinzufügen:** Neuer QR-Code, E-Mail sofort versendet
- **Gast entfernen:** QR wird ungültig (Scanner: Rot)
- **Daten ändern:** QR bleibt gültig, Daten aktualisiert

## Referenz

- Gesamtübersicht Sek. 18 (Gästelisten)
- Issue: #90
