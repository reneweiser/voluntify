# UC-20: Volunteer erhält Einlass zu mehreren Events (Volunteer-Benefit)

**Akteur:** Entry Staff, Volunteer
**Ziel:** Volunteer nutzt QR-Code für Einlass zu verschiedenen Events als Benefit

## Vorbedingungen

- Volunteer hat sich für Schichten im Projekt angemeldet
- Projekt hat mehrere Events (z.B. Volunteer-Event + Publikums-Events)
- Entry Staff Scanner sind für die jeweiligen Events konfiguriert

## Beispiel

```
Projekt: SpaceKidHeadCup 2026
├── Event: Aufbautag (01.05.) → Volunteer arbeitet hier
├── Event: Konzert Abend 1 (03.05., 15:00) → kostenloser Eintritt als Benefit
└── Event: Konzert Abend 2 (05.05., 22:00) → kostenloser Eintritt als Benefit
```

## Ablauf

1. **Aufbautag:** Volunteer wird per Entry Staff Scanner eingescannt → Arrival A (01.05.)
2. **Konzert Abend 1:** Volunteer zeigt gleichen QR-Code → Entry Staff scannt → Arrival B (03.05., 15:00)
3. **Konzert Abend 2:** Volunteer zeigt gleichen QR-Code → Entry Staff scannt → Arrival C (05.05., 22:00)

## Ergebnis

- Drei separate Arrival-Records, je einem Event zugeordnet
- Ein einziger QR-Code (projektweit) für alle Events
- Eligibility-Check: Volunteer hat Schichten im Projekt → QR ist für alle Events gültig
- Jeder Entry Staff Scanner ist einem Event zugeordnet und erfasst nur Arrivals für dieses Event

## Warum ein QR-Code für mehrere Events?

QR-Codes sind projekt-scoped, nicht event-scoped. Das bedeutet:
- Volunteer braucht nur einen Code, egal wie viele Events
- Entry Staff Scanner filtert automatisch nach seinem zugeordneten Event
- "Bereits eingecheckt" (Gelb) erscheint nur, wenn der Volunteer **am selben Event** bereits gescannt wurde

## Sonderfälle

- **Alle Schichten storniert:** QR ist ungültig → 🔴 Rot bei jedem Event
- **Volunteer nur für ein Event angemeldet:** Trotzdem Arrival an allen Events möglich (Benefit)
- **Privates Event mit Entry Scanner:** Gleicher QR-Code funktioniert auch für private Events im Projekt

## Referenz

- Decision: PO-Session 2026-04-09 (Arrival = Event-Level, multi-event)
- Issue: #130
