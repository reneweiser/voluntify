# UC-16: Volunteer fordert Ticket-Zugang erneut an

**Akteur:** Volunteer
**Ziel:** Neuen Zugangslink zum Helfer-Portal erhalten, wenn die E-Mail verloren ging

## Vorbedingungen

- Volunteer ist im Projekt registriert (= Signup-Flow vollständig abgeschlossen, siehe UC-04 Schritt 5)
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

## Sonderfall: "Verifiziert, aber nicht angemeldet"

Hat ein Interessent zwar die E-Mail-Verifikation durchlaufen (UC-04 Schritt 1), den Signup-Flow danach aber nicht bis Schritt 5 abgeschlossen, existiert **kein Volunteer-Record**. Die Magic-Link-Anfrage greift dann den Privacy-Stillegang in [`RequestPortalAccessLink`](../../app/Actions/RequestPortalAccessLink.php) -- der User sieht die generische Bestätigungsmeldung, bekommt aber keine E-Mail.

Support-Antwort in dem Fall: "Deine Anmeldung wurde nicht abgeschlossen, du bist nicht im System -- bitte den Signup-Flow erneut starten." Es gibt nichts zu löschen und nichts an Schichten anzuzeigen.

## Referenz

- Decision: PO-Session 2026-04-09
- Issue: #115
