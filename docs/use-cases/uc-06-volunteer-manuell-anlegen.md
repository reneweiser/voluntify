# UC-06: Organizer legt Volunteer manuell an

**Akteur:** Organizer
**Ziel:** Volunteer ins System aufnehmen, der nicht den Signup-Flow durchlaufen hat

## Vorbedingungen

- Projekt existiert
- Organizer kennt mindestens die E-Mail-Adresse des Volunteers

## Ablauf

1. Projekt → Volunteers → **Volunteer hinzufügen**
2. Formular ausfüllen:
   - E-Mail (Pflicht)
   - Vorname, Nachname, Telefon (optional)
   - Schichten zuweisen (optional)
   - Custom Fields eintragen (optional)
   - Gear-Auswahl (optional)
3. **Speichern**
4. System sendet `volunteer_added_by_organizer`-E-Mail mit Portal-Link

## Ergebnis

- Volunteer existiert im Projekt
- Portal-Link in der E-Mail: Volunteer kann fehlende Daten vervollständigen
- Fehlende Typ-1-Gear-Auswahl: Scanner zeigt "Auswahl ausstehend"
- Fehlende Custom Fields: "Keine Angabe" im Organizer-UI
- Fehlender Name: Portal zeigt Banner "Bitte vervollständige deine Registrierung"

## Beispiel

Organizer trifft jemanden auf einer Vereinsveranstaltung, der beim Hochschulball helfen möchte. Er erstellt den Volunteer mit nur der E-Mail. Der Volunteer erhält eine Mail, öffnet das Portal und trägt selbst Name, Schichten und T-Shirt-Größe ein.

## Referenz

- Gesamtübersicht Sek. 17.3 (Manuelles Anlegen)
- Issue: #88
