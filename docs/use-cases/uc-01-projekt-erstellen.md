# UC-01: Projekt erstellen und erstes Event veröffentlichen

**Akteur:** Organizer
**Ziel:** Neues Projekt mit erstem Event live auf der Projektwebsite

## Vorbedingungen

- Organizer ist eingeloggt
- Organisation existiert

## Ablauf

1. Dashboard → **Neues Projekt**
2. Name eingeben (z.B. "Hochschulball 2026"), optional Beschreibung
3. **Erstellen** → Projekt angelegt
4. Im Projekt → **Neues Event**
5. Name, Datum, Ort eingeben → **Erstellen** → Event im Status Draft
6. **Jobs & Schichten** → Job hinzufügen (z.B. "Einlass")
7. Schicht hinzufügen: Datum, Start/Endzeit, Kapazität
8. Zurück zu **Übersicht** → **Veröffentlichen**
9. Event wechselt zu Published Open
10. Projektwebsite wird aktiviert unter `/p/{token}`
11. Organizer kopiert den Link und teilt ihn

## Ergebnis

- Projektwebsite zeigt das Event mit Anmelde-Button
- Volunteers können sich über den Link anmelden
- Organizer sieht das Event im Dashboard

## Sonderfälle

- **Veröffentlichen ohne Schichten:** Blockiert — Fehlermeldung "Mindestens eine Schicht erforderlich"
- **Mehrere Events:** Zweites Event wird nach Veröffentlichung automatisch auf der bestehenden Projektwebsite angezeigt

## Referenz

- Gesamtübersicht Sek. 4 (Event-Lifecycle), Sek. 5.1 (Projektwebsite)
- Issues: #52, #45, #83
