# UC-02: Privates Event für interne Teams

**Akteur:** Organizer
**Ziel:** Internes Event erstellen, das nur über Direktlink zugänglich ist

## Vorbedingungen

- Projekt existiert
- Organizer ist eingeloggt

## Ablauf

1. Im Projekt → **Neues Event**
2. Name eingeben (z.B. "Workshop Hauptorga" oder "Grafikteam")
3. Event-Einstellungen → Sichtbarkeit auf **Privat** setzen
4. Jobs & Schichten anlegen (z.B. Job "Vorbereitung", Schicht "nach Bedarf" ohne feste Zeiten)
5. **Veröffentlichen** → Event ist Published Open
6. **Direktlink kopieren** (`/event/{token}`)
7. Link per E-Mail oder Messenger an die eingeladenen Personen teilen

## Ergebnis

- Event erscheint **nicht** auf der Projektwebsite
- Nur Personen mit dem Direktlink können sich anmelden
- Angemeldete Volunteers sind ganz normale Projekt-Volunteers (gleicher QR-Code, gleiche Gear-Ansprüche)
- Organizer sieht das Event im Dashboard mit "Privat"-Badge

## Beispiele

**Workshop Hauptorga:** 8 Kernorganisatoren organisieren einen internen Workshop. Sie sollen im System sein (Gear, QR-Tickets), aber das Event ist nicht öffentlich — nur eingeladene Personen sollen sich anmelden.

**Grafikteam:** 4 Designer arbeiten wochenlang vor dem Festival. Sie haben keine klassische Schicht, sondern arbeiten "nach Bedarf". Privates Event mit flexibler Schicht erfasst sie im System für Gear und Anwesenheit.

## Sonderfälle

- **Privates Event aktiviert nicht die Projektwebsite:** Erst ein öffentliches Event aktiviert `/p/{token}`
- **Sichtbarkeit wechseln:** Organizer kann jederzeit zwischen Öffentlich und Privat wechseln
- **Signup-Flow:** Identisch zum öffentlichen Event — gleiche Schritte, gleiche Validierung

## Referenz

- Gesamtübersicht Sek. 4 (Event-Sichtbarkeit)
- Issue: #91
