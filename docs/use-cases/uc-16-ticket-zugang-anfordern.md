# UC-16: Volunteer fordert Ticket-Zugang erneut an

**Akteur:** Volunteer
**Ziel:** Neuen Zugangslink zum Helfer-Portal erhalten, wenn die E-Mail verloren ging

## Vorbedingungen

- Volunteer ist im Projekt registriert
- Volunteer hat keinen Zugang mehr (E-Mail gelöscht, Link abgelaufen)

## Ablauf

1. Volunteer öffnet die öffentliche Projektseite
2. Unterhalb der Events: Formular "Zugang zu deinem Volunteer-Ticket erhalten"
3. E-Mail-Adresse eingeben
4. System prüft, ob ein Volunteer mit dieser E-Mail im Projekt existiert
5. Falls ja: neuer Magic-Link-Token generiert, E-Mail mit Portal-Link versendet
6. Falls nein: keine E-Mail
7. **Immer gleiche Rückmeldung:** "Falls ein Konto mit dieser E-Mail existiert, wurde ein Zugangslink versendet."

## Ergebnis

- Volunteer erhält neuen Portal-Zugang per E-Mail
- Bestehende Token bleiben gültig (alter Link funktioniert weiterhin falls doch gefunden)
- Kein Hinweis ob E-Mail existiert (Datenschutz)

## Sicherheit

- Rate-Limiting: max. 3 Anfragen pro E-Mail pro Stunde
- Keine Info-Leaks (immer gleiche Meldung)

## Referenz

- Decision: PO-Session 2026-04-09
- Issue: #115
