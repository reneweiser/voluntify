# UC-13: Organizer ändert Schichten eines Volunteers

**Akteur:** Organizer
**Ziel:** Schichten eines bestehenden Volunteers hinzufügen, ändern oder entfernen

## Vorbedingungen

- Volunteer existiert im Projekt

## Ablauf

1. Projekt → Volunteers → Volunteer auswählen
2. **Schichten bearbeiten**
3. Schichten hinzufügen oder entfernen (eventübergreifend innerhalb des Projekts)
4. **Speichern**
5. Volunteer erhält dieselbe Bestätigungs-/Änderungs-E-Mail wie bei eigener Änderung

## Ergebnis

- Schichtzuweisung aktualisiert
- Volunteer wird per E-Mail informiert (gleiche Templates wie Self-Service)
- Kapazitäten aktualisiert

## Sonderfälle

- **Alle Schichten entfernt:** Volunteer bleibt im Projekt (Status: "Keine Schicht") — kein Löschen
- **Volle Schicht:** Organizer kann trotzdem zuweisen (Override der Kapazitätsprüfung)

## Referenz

- Gesamtübersicht Sek. 17.4 (Organizer-Aktionen)
- Issue: #88
