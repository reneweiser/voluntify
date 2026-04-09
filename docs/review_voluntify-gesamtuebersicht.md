# Voluntify — Gesamtübersicht (Ausführlich)

**Erstellt:** 2026-03-30
**Quellen:** Architekturentscheidungen, GitHub Issues #45–#86

---

## 1. Projektziel

Voluntify ist eine webbasierte Plattform zur Verwaltung von Freiwilligen bei Veranstaltungen.
Organisationen wie SKHC e.V. können Projekte mit mehreren Events anlegen, Freiwillige zur Schichtanmeldung einladen, den Einlass per QR-Code steuern und die Ausgabe von Ausrüstung tracken — alles ohne dass Freiwillige einen Account benötigen.

---

## 2. Gesamtarchitektur

```text
Organisation
└── Projekt (z.B. "Hochschulball 2026")
    ├── Projektwebsite (öffentlich, permanenter Link /p/{token})
    ├── Projekteinstellungen
    │   ├── Allgemein (Name, Beschreibung, Titelbild, Timezone)
    │   ├── E-Mail (Absendername, Kontakt-E-Mail)
    │   ├── Custom Fields (projektweite Felder, Scoping: event_ids + job_ids)
    │   ├── Gear (Typ 1 + Typ 2, Scoping: event_ids + job_ids)
    │   ├── Scanner (Volunteer Admin → Attendance + Gear, Entry Staff → Arrival)
    │   ├── Gästelisten (VIPs, Künstler, Begleitpersonen — an Scanner gebunden)
    │   ├── Anwesenheit (konfigurierbare Stadien + Default-Status)
    │   └── Mitglieder (nur Organizer-Rolle)
    ├── Volunteers (gehören zum Projekt, nicht zum einzelnen Event)
    ├── Gäste (Guest Lists → Guest Groups → Guest Entries, mit optionalem Gear)
    └── Events (z.B. "Aufbautag", "Hauptabend", "Konzert")
        ├── Jobs (z.B. "Einlass", "Bar", "Bühne")
        │   └── Schichten (Datum, Zeit optional, Kapazität)
        ├── Anmeldungen (Volunteers → Schichten)
        ├── Event-Einstellungen
        │   ├── Allgemein (Name, Beschreibung, Ort, Bild, Datum)
        │   ├── Anmeldung (Anmeldefrist, Telefon-Pflichtfeld)
        │   ├── Anwesenheit (Grace Period)
        │   └── E-Mail (Benachrichtigungs-E-Mail, E-Mail-Vorlagen)
        └── Event-Status (Draft / Published Open / Published Closed / Archived)
```

**Personen & Rollen:**

```text
Organisation
├── Organizer (permanenter Account, Org- oder Projekt-Level)
├── Volunteer Admin (temporärer Scanner-Link, Schicht-Check-in + Gear)
├── Entry Staff (temporärer Scanner-Link, Event-Einlass)
├── Volunteer (kein Account, Magic-Link, gehört zum Projekt)
└── Gast (kein Account, QR-Code via Gästeliste, VIPs/Künstler/Begleitpersonen)
```

**Wichtige Prinzipien:**

- Jedes Event gehört zwingend zu einem Projekt — kein standalone Event (#52)
- Volunteer gehört zum Projekt, nicht zum einzelnen Event (#52, #69)
- Gear, Custom Fields, Scanner → Projektebene (#53, #54, #75)
- E-Mail-Vorlagen, Eventfelder, Benachrichtigungs-E-Mail → Eventebene (#55, #47)
- Alle Gear-Zustände werden ausschließlich über den Scanner gesetzt — kein Web-UI-Override (#56)

---

## 3. Rollen & Berechtigungen

### 3.1 Übersicht der Rollen

| Rolle | Account | Zugang | Zugewiesen über |
|---|---|---|---|
| Organizer | Permanent | Alle Projektdaten | Einladung per E-Mail |
| Volunteer Admin | Kein Account | Scanner-Link (temp.) | Volunteer Admin Scanner |
| Entry Staff | Kein Account | Scanner-Link (temp.) | Entry Staff Scanner |
| Volunteer | Kein Account | Magic Link | Signup-Flow |

### 3.2 Organizer (#65, #62)

- Org-Level Organizer: automatischer Zugriff auf alle Projekte und Events der Organisation
- Project-Level Organizer: Zugriff auf alle Events des Projekts
- Explizite Mitgliedschaft auf niedrigerer Ebene überschreibt geerbte Rechte
- Auflösungsreihenfolge: Scanner → Projekt → Organisation
- Erstellt Projekte, Events, Schichten, Scanner, Gear-Definitionen
- Verwaltet Custom Fields, E-Mail-Vorlagen, Mitglieder
- Sieht Dashboard und alle Projektdaten

### 3.3 Volunteer Admin (#56, #65)

- Kein permanenter Account — Zugriff nur über temporären Scanner-Link
- Wird über Volunteer Admin Scanner zugewiesen (event- oder projektbezogen)
- Scanner-Link wird 30 Minuten vor Zeitfenster automatisch versendet
- Einmaliger Code beim ersten Öffnen des Links
- Berechtigt zu: Check-in, Gear-Ausgabe, Schichtliste, manuelle Suche
- Kein Zugriff auf Einstellungen, Statistiken oder andere Events

### 3.4 Entry Staff (#58, #65)

- Kein permanenter Account — Zugriff nur über temporären Scanner-Link
- Wird über Entry Staff Scanner zugewiesen
- Scanner-Link wird 30 Minuten vor Zeitfenster versendet
- Berechtigt zu: QR-Scanner, Einlasskontrolle (Grün/Gelb/Rot), Gastliste
- Kein Zugriff auf: manuelle Suche, Anwesenheit, Volunteer-Details, Gear

### 3.5 Volunteer (#69, #51)

- Kein Account — nur Magic Links
- Anmeldung über Projektwebsite `/p/{token}` oder Event-Link `/event/{token}`
- QR-Code: permanent, **ein Code pro Projekt-Mitgliedschaft**, gilt für alle Events im Projekt, wird nie neu generiert
- Verwaltung über Helfer-Portal (Magic Link)

### 3.6 Berechtigungsmatrix

| Aktion | Org-Org. | Proj-Org. | Vol. Admin | Entry Staff |
|---|---|---|---|---|
| Organisation verwalten | ✓ | — | — | — |
| Projekt erstellen | ✓ | — | — | — |
| Projekt bearbeiten | ✓ | ✓ | — | — |
| Mitglieder einladen (Org) | ✓ | — | — | — |
| Mitglieder einladen (Proj) | ✓ | ✓ | — | — |
| Event erstellen/bearbeiten | ✓ | ✓ | — | — |
| Event veröffentlichen | ✓ | ✓ | — | — |
| Scanner konfigurieren | ✓ | ✓ | — | — |
| Volunteer-Liste einsehen | ✓ | ✓ | ✓ | — |
| Check-in durchführen | ✓ | ✓ | ✓ | — |
| Gear ausgeben | ✓ | ✓ | ✓ | — |
| QR-Scanner (Einlass) | ✓ | ✓ | — | ✓ |
| Gastliste einsehen | ✓ | ✓ | — | ✓ |
| Anwesenheit markieren | ✓ | ✓ | ✓ | — |
| Dashboard | ✓ | ✓ | — | — |

---

## 4. Event-Lifecycle (#45)

### Vier-Stufen-Modell

```text
Draft ──→ Published Open ⇄ Published Closed ──→ Archived
  ↑              ↓
  └──── (Wartung: zurück zu Draft) ────────────┘
```

### Event-Sichtbarkeit

Events haben eine Sichtbarkeitseigenschaft:

| Typ | Projektwebsite | Zugang |
|---|---|---|
| **Öffentlich** (Standard) | Sichtbar | Jeder mit Projektwebsite-Link |
| **Privat** | Nicht sichtbar | Nur über geheimen Direktlink (`/event/{token}`) |

**Privat** bedeutet: Das Event erscheint nie auf der Projektwebsite — weder als Published Open noch als Published Closed. Volunteers können sich ausschließlich über den geheimen Direktlink anmelden, den der Organizer gezielt teilt (per E-Mail, Messenger etc.).

> Beispiel 1: „Workshop Hauptorga" — die Kernorganisatoren sind selbst Volunteers (wegen Gear, QR-Tickets), aber das Event soll nicht öffentlich sichtbar sein, da es nur für eingeladene Personen ist.
>
> Beispiel 2: „Grafikteam" — Designer arbeiten wochenlang vor dem Event, haben keine klassische Schicht, sollen aber im System erfasst werden (Gear, Anwesenheit). Privates Event mit flexiblen Schichtzeiten (z.B. „nach Bedarf").

Der Organizer setzt die Sichtbarkeit in den **Event-Einstellungen** (Toggle: Öffentlich / Privat). Der Wechsel ist jederzeit möglich — auch nach Veröffentlichung.

### Statusbeschreibungen

| Status | Projektwebsite (öffentlich) | Projektwebsite (privat) | Anmeldung | Organizer-Ansicht |
|---|---|---|---|---|
| **Draft** | Verborgen | Verborgen | Nicht möglich | Sichtbar mit Draft-Badge |
| **Published Open** | Sichtbar mit CTA | **Nicht sichtbar** (nur Direktlink) | Möglich | Aktiv |
| **Published Closed** | Sichtbar mit „Abgelaufen" | **Nicht sichtbar** | Nicht möglich | Geschlossen |
| **Archived** | Entfernt | Entfernt | Nicht möglich | Archiviert |

### Organizer-Controls

- **Veröffentlichen**: Draft → Published Open (aktiviert Projektwebsite beim ersten Mal) — **blockiert wenn keine Schichten angelegt**
- **Anmeldung schließen**: Published Open → Published Closed (manuell)
- **Anmeldefrist**: Automatischer Übergang Published Open → Published Closed
- **Wartungsmodus**: Published → Draft (keine Benachrichtigung an Volunteers)
- **Re-Publish**: Draft → Published Open (sendet Update-E-Mail an alle aktiven Angemeldeten, #84)
- **Archivieren**: Published Closed → Archived (endgültig, vorher Archivierungs-Pflicht)
- **Geplantes Veröffentlichen**: Zeitgesteuerte Aktivierung
- **Sichtbarkeit ändern**: Öffentlich ⇄ Privat (jederzeit)

### Re-Publish-Benachrichtigung (#84)

- Trigger: Draft → Published Open (nur beim Wiederveröffentlichen)
- Organizer kann optionalen Freitext-Hinweis mitgeben (z.B. „Schichtzeiten wurden angepasst")
- System sendet automatisch `event_updated`-E-Mail an alle Volunteers mit aktiven Anmeldungen
- Template: `{{vorname}}`, `{{event_name}}`, `{{portal_link}}`, `{{organizer_note}}`

---

## 5. Öffentliche Seiten

### 5.1 Projektwebsite `/p/{token}` (#83)

**Aktivierung:** Beim ersten Veröffentlichen eines Events permanent aktiviert.

**Content-Editor (Projekteinstellungen):**
- Titel (vorausgefüllt aus Projektname)
- Beschreibung (Rich Text / Markdown)
- Titelbild
- Kontakthinweis (optional)
- Vorschau-Modus für Organizer

**Öffentliche Event-Auflistung:**

- Published Open → mit Anmelde-Button und Frist
- Published Closed → mit „Anmeldung abgelaufen"-Label
- Draft → verborgen
- Archived → entfernt

Event-Karten zeigen: Name, Datum, Ort, Anmeldefrist, Status-Badge.

**URL-Verhalten:**
- Vor erstem Publish: eingeloggte Organizer sehen eine **Preview** der Seite; nicht-eingeloggte Besucher erhalten 404

---

### 5.2 Signup-Flow (#69, #49, #50, #54, #80, #82)

```text
Schritt 1 — E-Mail + Verifikation
    ↓                              ↓
Neuer User                    Bekannter User
    ↓                              ↓
E-Mail-Verifizierung          Verifikations-Link per E-Mail
    ↓                              ↓
Persönliche Daten eingeben    Daten vorbelegt (read-only bestehendes Gear/Schichten)
    ↓
Schritt 2 — Schichtauswahl
    (20-Min-Reservierung startet hier)
    (Bekannter User: bestehende Schichten read-only, nur neue wählbar)
    ↓
Schritt 3 — Gear Typ 1 + Custom Fields (nur wenn vorhanden, sonst überspringen)
    (nur für neu gewählte Schichten, basierend auf event_ids/job_ids Scoping)
    (Countdown läuft weiter)
    ↓
Schritt 4 — Zusammenfassung + "Verbindlich anmelden"
    ↓
Anmeldung abgeschlossen
→ Bestätigungs-E-Mail mit Zusammenfassung + QR-Code (pro Projekt, gilt für alle Events)
```

#### Schritt 1 — E-Mail

- Eingabe E-Mail-Adresse
- Bekannter User → Magic Link per E-Mail
- Neuer User → Verifikations-E-Mail (6-stelliger Code oder Link)

#### E-Mail-Verifizierung

- Kein Timer während der Verifikation
- Link/Code läuft nach 24 Stunden ab
- Schichten sind NICHT reserviert bis Verifizierung abgeschlossen
- 20-Minuten-Reservierung startet erst nach erfolgreicher Verifizierung

#### Schritt 2 — Persönliche Daten + Schichtauswahl

**Reihenfolge: Daten zuerst, dann Schichten.**

- 20-Minuten-Countdown sichtbar (startet mit Beginn des Schritts)
- **Persönliche Daten:** Vorname, Nachname (getrennt, #82), E-Mail (vorausgefüllt)
- Optional: Telefonnummer (wenn im Event-Setting aktiviert, #50)
- **Schichtauswahl** mit Kapazitätsanzeige
- Überschneidungsprüfung bei Auswahl (#49): direkte UX-Rückmeldung, Server-Validierung
  - Direkt aufeinanderfolgende Schichten erlaubt (12–14 Uhr und 14–16 Uhr)
  - Konflikte über verschiedene Jobs hinweg werden geprüft

#### Schritt 3 — Custom Fields + Gear

- Countdown läuft weiter
- Projektfelder (einmalig, vorausgefüllt bei Rückkehr)
- Typ-1-Gear-Auswahl (z.B. T-Shirt-Größe)
- Eventfelder (jedes Mal neu)

#### Schritt 4 — Zusammenfassung

- Übersicht aller Daten
- Finaler Button: **Verbindlich anmelden**

**Rate Limiting (#80):**
- E-Mail-Verifizierung und Magic Links: 3× pro Stunde (konfigurierbar)
- QR/Scanner-Resend: 1× alle 5 Minuten (fest)
- 5 Fehlversuche → 30 Minuten Sperrung; Organizer wird per Activity Log benachrichtigt

---

### 5.3 Helfer-Portal (#51, #85)

Zugang per Magic Link. Zeigt alle Informationen zum Projekt-Volunteer-Konto.

**Sektionen:**
1. **Nächste Schicht** — Banner mit Datum, Job, Zeit
2. **Schichten** — gruppiert nach Event, Abmelde-Button (wenn aktiviert)
3. **Gear** — Typ-1-Status (konfigurierbare Zustände) + Typ-2-Ausgaben
4. **Anmeldedaten** — Vorname, Nachname, E-Mail, Telefon, Custom-Field-Antworten
5. **QR-Code** — mit Resend-Button (1× alle 5 Minuten), Maintenance-Banner bei Draft-Events
6. **Anwesenheitsstatus** — je vergangener Schicht: On Time / Late / No Show

**Abgelaufener Magic Link:** Formular für neuen Magic Link per E-Mail

**Schicht-Absagen (#85):**
- Nur wenn Organizer auf Projektebene aktiviert hat
- Optional: Frist (z.B. „nicht später als 24 Stunden vor Schichtbeginn")
- Bestätigungsdialog vor Absage
- Nach Absage: Kapazität sofort freigegeben

---

## 6. Schichten (#86, #49)

### Zeit-Konfigurationen

| Konfiguration | Beispiel-Anzeige |
|---|---|
| Datum + Start + Ende | „Aufbau · 10:00–14:00 Uhr" |
| Datum + Start | „Aufbau · ab 10:00 Uhr" |
| Datum + Custom Text | „Aufbau · nach Bedarf" |
| Start mit Custom Text (Ende) | „Aufbau · 10:00 Uhr – bis Veranstaltungsende" |

**Datum ist immer Pflicht.** Start- und Endzeit sind optional.

### Custom Display Text

- Startzeit-Override: ersetzt „10:00 Uhr" durch z.B. „nach Einlass-Ende"
- Endzeit-Override: ersetzt „14:00 Uhr" durch z.B. „bis Veranstaltungsende"
- Ohne Zeit: Custom Text ist einzige Zeitanzeige (dann Pflicht)

### Organizer-UX

- Datum: immer Pflicht
- Startzeit: optional (Toggle zum Weglassen)
- Endzeit: optional (Toggle zum Weglassen)
- Custom Text für Start und Ende (optionaler Override)
- **Bestätigung beim Speichern**, wenn Start oder Ende fehlt

### Auswirkungen auf andere Features

| Feature | Verhalten |
|---|---|
| Reminder-E-Mails (#81) | Startzeit vorhanden → 24h/4h-Erinnerung; Keine Startzeit → 03:00 Uhr am Schichttag |
| Scanner-Schichtliste (#57) | Chronologisch sortiert; Schichten ohne Zeit → immer an erster Stelle |
| Überschneidungsprüfung (#49) | Mit Zeiten → normale Prüfung; Ohne Zeiten → stillschweigend übersprungen |
| Volunteer-Portal (#51) | Konfigurierte Zeit oder Custom Text angezeigt |
| CSV-Export | Zeit oder Custom Text; leer wenn beides nicht gesetzt |

---

## 7. Gear-System (#53, #56, #77)

### Übersicht

Gear ist auf Projektebene definiert und gilt für alle Events im Projekt.

**Zwei Typen:**

| Typ | Beschreibung | Beispiel | Zustände |
|---|---|---|---|
| **Typ 1** | Größenauswahl, ein Anspruch | T-Shirt | Konfigurierbare Zustandsliste (vom Organizer definiert) |
| **Typ 2** | Mengenausgabe, mehrfach trackbar | Getränkemarken, Mahlzeit | Anzahl ausgegebener Einheiten |

### Datenmodell

- `ProjectGearItem` — Definition auf Projektebene (ehemals `EventGearItem`)
- `VolunteerGear` — Anspruch des Volunteers (Typ 1)
- `VolunteerGearPickup` — einzelne Ausgabe-Buchung (beide Typen)

### Berechtigung

- Mindestens eine Schicht in Vergangenheit **oder** Zukunft
- Gilt für Entry Staff und Volunteer Admin Scanner gleichermaßen

### Gear-Vergabe im Signup (#53)

- Typ 1: Größenauswahl in Schritt 3 des Signup-Flows
- Typ 2: automatisch zugewiesen (keine Auswahl nötig)

### Gear-Ausgabe (ausschließlich über Scanner, #56)

**Alle Gear-Zustände werden nur über den Scanner gesetzt — kein Web-UI-Override.**

**Typ 1 — Volunteer Admin Scanner:**
1. Aktueller Status angezeigt (aus konfigurierter Zustandsliste)
2. Organizer wählt den nächsten Zustand aus der Liste
3. Status gesetzt, Feedback-Anzeige, Scanner-Reaktivierung per Button
4. Alle Zustände sind jederzeit änderbar — auch nach Ablauf des Zeitfensters nicht eingefroren

**Internet-Verbindung für Typ-2-Ausgabe erforderlich** (Race-Condition-Prävention bei Mengen)

### Gear-Summary (#77)

- Read-only Projektübersicht für Organizer
- Pro Item: Statusanzahl (Typ 1) oder Ausgaben vs. Gesamt (Typ 2)
- Fehlende-Gear-Report: filterbar nach Item/Event/Status
- „Nicht vorrätig"-Zusammenfassung
- CSV-Export
- Gear Tracker Mode im Volunteer Admin Scanner

---

## 8. Scanner-System (#56, #57, #58, #71, #72, #73, #75)

### 8.1 Scanner-Typen

| Typ | Zweck | Funktion | Zuweisung |
|---|---|---|---|
| **Entry Staff Scanner** | **Event Arrival** — Einlasskontrolle | QR-Scan, Volunteer-Suche, Gastliste | per E-Mail, temporärer Link |
| **Volunteer Admin Scanner** | **Attendance** — Schicht-Check-in + Gear | QR-Scan, Volunteer-Suche, Pro-Schicht Status, Gear-Ausgabe | per E-Mail, temporärer Link |

> Arrival und Attendance sind getrennte Konzepte: Arrival = "Volunteer hat Zutritt zum Event-Gelände" (z.B. auch kostenloser Eintritt zu Konzerten als Volunteer-Benefit). Attendance = "Volunteer hat sich zur Schicht gemeldet". Ein Volunteer kann multiple Arrivals haben (verschiedene Events an verschiedenen Tagen).

**Scanner-Workflows:**

```text
Entry Staff Scanner (Arrival)              Volunteer Admin Scanner (Attendance)
──────────────────────────────             ─────────────────────────────────────
QR-Scan / Volunteer-Suche / Gastliste      QR-Scan / Volunteer-Suche
    ↓                                          ↓
Ticket validieren                          Volunteer + Schichten anzeigen
    ↓                                          ↓
🟢 Zugang / 🟡 Bereits da / 🔴 Kein       Pro Schicht: Status setzen
    ↓                                      (Eingecheckt / Verspätet / ...)
"Nächsten scannen" (manuell)                   ↓
                                           Gear anzeigen + ausgeben (wenn Mode aktiv)
                                               ↓
                                           "Nächsten scannen"
```

### 8.2 Scanner-Konfiguration (#75)

Unified Scanner Setup unter Projekt → Scanner:

**Datenmodell:**
- `type`: `entry_staff` oder `volunteer_admin`
- `scope`: event oder project
- `time_window`: Start- und End-Zeitpunkt
- `event_filters` / `job_filters`
- `modes`: `checkin`, `gear_pickup` (mind. einer Pflicht)
- `gear_items`: welche Items dieser Scanner ausgeben darf
- `assignees`: Liste der E-Mail-Adressen
- `hint_text`: optionaler Hinweistext (aus #74)

**Setup-Flow:**
1. Typ auswählen (Entry Staff / Volunteer Admin)
2. Scope konfigurieren (Event / Projekt)
3. Zeitfenster setzen
4. Modi auswählen (Check-in / Gear / beides)
5. Zugewiesene Personen hinzufügen
6. Hinweistext setzen

**Scanner-Status:** Geplant / Aktiv / Abgelaufen

**Zeitfenster nachträglich verlängern:**
Organizer kann das Zeitfenster eines Scanners nach Ablauf verlängern — z.B. für nachträgliche Gear-Ausgaben. Verlängerung über das Scanner-Management im Organizer-Bereich.

### 8.3 Zeitfenster-Enforcement (#72)

- Scanner gesperrt außerhalb des konfigurierten Zeitfensters
- 10-Minuten-Warnungsbanner mit Countdown vor Ablauf
- Bei Ablauf: gesperrt mit Meldung, IndexedDB-Daten gelöscht

### 8.4 Offline-Daten & Sicherheit (#73)

- IndexedDB-Daten verschlüsselt mit Session-Key vom Server
- **Entry Staff**: speichert Name, Ticket-Auth, Check-in-Status (minimal)
- **Volunteer Admin**: speichert Name, Schichten, Check-in, Gear, Anwesenheit (erweitert)
- Hartes Ablaufdatum: Ende Zeitfenster ODER 3 Tage
- Auto-Löschung bei Ablauf
- Auto-Update wenn online
- Offline-Konflikt (Doppelscan) wird im Activity Log markiert

### 8.5 Authentifizierung (#58)

- Link-Versand: 30 Minuten vor Zeitfenster (oder sofort wenn < 30 Min.)
- Einmaliger Code beim ersten Öffnen
- Scanner gesperrt außerhalb Zeitfenster

### 8.6 Entry Staff Scanner (#58, #71)

**Vollbild-Farbresultate (manueller Button zum Reaktivieren):**

| Farbe | Bedeutung | Anzeige |
|---|---|---|
| 🟢 Grün | Zugriff erlaubt | Name |
| 🟡 Gelb | Bereits eingecheckt | Name + wann/wo letzter Scan |
| 🔴 Rot | Kein Zugriff | Grund + „Neu scannen"-Button |

**Eligibility:** Mindestens eine Schicht in Vergangenheit oder Zukunft

**Tabs:**
1. QR-Scanner
2. Gastliste (optional)

### 8.7 Volunteer Admin Scanner (#56, #57)

**Modi:**
- Check-in only
- Gear Pickup only
- Beides (Reihenfolge: Check-in → Gear)

**Scan-Flow:**
1. Eligibility-Check (Schicht vorhanden?)
2. Check-in-Schichten (wenn Modus aktiv)
3. Gear-Pickup-Screen (wenn Modus aktiv)
   - Kein Gear zugewiesen → neutrale Meldung „Kein Gear zugewiesen" (kein Fehlerstatus)

**Gear Pickup Screen — Typ 1:**
- Aktueller Zustand angezeigt (aus konfigurierter Zustandsliste des Projekts)
- Auswahl des nächsten Zustands per Tap
- Zustände jederzeit änderbar (keine Einfrierung nach Ablauf des Zeitfensters)

**Volunteer-Ansicht nach Scan:**
- Name (Vorname + Nachname), maskierte Telefonnummer
- Schichten mit Status
- Gear-Status

**Tabs:**
1. Scanner
2. Schichtliste (#57) — chronologisch, „Jump to current time"-Button, Volunteers pro Schicht

**Touch-Target-Größen (#48):**
Check-in-Schaltflächen müssen komfortabel tippbar sein — volle Breite, besonders bei mehreren Schichten.

### 8.8 Scanner-Reaktivierung

Nach jeder Scanner-Aktion: Feedback-Anzeige bleibt stehen.
Reaktivierung für den nächsten Scan nur per **manuellem Button** (kein Auto-Dismiss).

---

## 9. E-Mail-System (#47, #55, #81, #84)

### 9.1 Sender-Konfiguration (#47)

**Organisationsebene (Organisationseinstellungen → E-Mail-Server):**
- SMTP-Konfiguration für den ausgehenden Mailserver:
  - Host, Port, Verschlüsselung (TLS/SSL)
  - Benutzername, Passwort
  - Verbindungstest (Testmail senden)
- Gilt als Standard für alle Projekte der Organisation

**Projektebene (Projekteinstellungen → E-Mail):**
- `Absendername` — Anzeigename des Absenders
- `Kontakt-E-Mail` — Reply-To / `{{kontakt_email}}`-Platzhalter
- Optional: eigener SMTP-Server (überschreibt Organisations-Standard)

**Eventebene (Event-Einstellungen → E-Mail):**
- `Benachrichtigungs-E-Mail` — für Absage-Digests und System-Alerts
  - Muss gesetzt sein, bevor Schicht-Absagen aktiviert werden können

### 9.2 E-Mail-Vorlagen (#55, #81)

E-Mail-Vorlagen sind auf Event-Ebene konfigurierbar.
Fallback: System-Defaults, falls keine Custom-Vorlage gesetzt.

**9 System-E-Mail-Typen:**

| Typ | Trigger |
|---|---|
| `signup_confirmation` | Anmeldung abgeschlossen |
| `email_verification` | Neuer User verifiziert E-Mail |
| `volunteer_welcome` | Nach Verifizierung — Willkommen + Portal-Link |
| `pre_shift_reminder_24h` | 24h vor Schichtbeginn |
| `pre_shift_reminder_4h` | 4h vor Schichtbeginn |
| `event_updated` | Re-Publish nach Wartung (#84) |
| `staff_invitation` | Einladung als Mitarbeiter |
| `volunteer_promoted` | Volunteer wird zu Staff promoted |
| `added_to_org` | User zu Organisation hinzugefügt |
| `volunteer_added_by_organizer` | Organizer hat Volunteer manuell angelegt — Portal-Link + Aufforderung zur Vervollständigung |
| `event_announcement` | Manuelle Ankündigung durch Organizer (Sek. 16) |

**Wichtige Placeholder-Änderungen:**
- `{{volunteer_name}}` → `{{vorname}}` und `{{nachname}}` (#82)
- Grußformel verwendet `{{vorname}}` (z.B. „Hallo {{vorname}},")
- Neu: `{{portal_link}}` — Link zum Helfer-Portal

**Schicht-Reminder (#81, #86):**
- Startzeit vorhanden → 24h und 4h Reminder
- Keine Startzeit → Reminder gesendet um 03:00 Uhr am Schichttag

### 9.3 Absage-Digest (#85)

- Gesendet alle 6 Stunden an Event-Benachrichtigungs-E-Mail
- Nur wenn mindestens eine Absage im Zeitfenster
- Inhalt: Volunteer-Name, Event, Schicht, Job, Absage-Zeitpunkt
- Versendet über System-Mailer (unabhängig vom konfigurierten Projektmailer)
- Versandzeitpunkt basiert auf **Server-Zeitzone** (keine separate Konfiguration)

---

## 10. Custom Registration Fields (#54)

### Zwei Ebenen

| Ebene | Wann gefragt | Vorausgefüllt? |
|---|---|---|
| **Projektfelder** | Einmal pro Volunteer pro Projekt | Ja (bei Rückkehr) |
| **Eventfelder** | Bei jeder Event-Anmeldung | Nein |

### Feldtypen

- **Text** — Freitextantwort
- **Select** — Dropdown mit vorgegebenen Optionen
- **Checkbox** — Ja/Nein

### Datenmodell

- `CustomRegistrationField` — mit `project_id` oder `event_id`
- `CustomFieldResponse` — mit `volunteer_id`

### Organizer-UI

- Projektfelder: Projekteinstellungen → Custom Fields
- Eventfelder: Event-Einstellungen → Anmeldung

### Export & Archivierung

- Beide Ebenen im CSV-Export enthalten
- Archivierte (gelöschte) Felder werden mit „(archiviert)"-Suffix angezeigt
- Signup Step 3: Projektfelder zuerst, dann Eventfelder

---

## 11. Organizer-Seiten

### 11.1 Dashboard (#76)

**Nur für Organizer zugänglich.**

**Oberer Bereich:**
- Nächstes bevorstehendes Event
- „Neues Projekt"-Button
- Globale Volunteer-Suche

**Widgets:**
- Staff-Übersicht
- Intelligente, ausblendbare Warnungen/Reminders

**Projekt-Kacheln:**

| Information | Details |
|---|---|
| Status-Badge | Draft / Open / Closed / Archived |
| Volunteers | Anzahl aktiver Anmeldungen |
| Schichten | Schichten mit Bedarf |
| No-Show-Rate | % Nicht erschienen |
| Anwesenheits-Aufschlüsselung | On Time / Late / No Show |
| Gear Ausstehend | Konfigurierbare Favoriten-Items (Organizer wählt pro Projekt) |

**Quick Actions pro Kachel:**
- Neues Event anlegen
- Anmelde-Link kopieren
- Event duplizieren
- Scanner öffnen
- Ankündigung senden

**Filtermöglichkeiten:** Nach Zeitraum, nach Projekt

### 11.2 Projekteinstellungen

Tabs:
- **Allgemein** — Name, Beschreibung, Titelbild, Kontakthinweis
- **E-Mail** — Absendername, Kontakt-E-Mail
- **Custom Fields** — Projektweite Registrierungsfelder
- **Gear** — Gear-Items definieren (Typ 1 / Typ 2)
- **Scanner** — Alle Scanner des Projekts
- **Mitglieder** — Nur Organizer-Einladungen

### 11.3 Event-Einstellungen (#46)

Tabs:
- **Übersicht** — Read-only Summary
- **Jobs & Schichten** — Jobs/Schichten erstellen und verwalten
- **Volunteers** — Volunteer-Liste, Suche, Filter, Export
- **Einstellungen**
  - Allgemein (Name, Beschreibung, Ort, Bild, Datum)
  - Anmeldung (Anmeldefrist, Telefon-Pflicht, Custom Fields)
  - Anwesenheit (Grace Period)
  - E-Mail (Benachrichtigungs-E-Mail, E-Mail-Vorlagen)

---

## 12. Datenmanagement

### 12.1 Volunteer-Suche (#67)

- Scope: `Volunteer::query()->search($query)`
- Kurze Queries (< 3 Zeichen): LIKE-Suche auf Name und E-Mail
- Längere Queries: MySQL FULLTEXT MATCH...AGAINST
- **Bug #67:** `@`-Zeichen in E-Mail-Adressen bricht MATCH...AGAINST in Boolean Mode
  - Fix: Regex-Bereinigung aller Nicht-Buchstaben/Nicht-Ziffern/Nicht-Leerzeichen
  - Betroffene Komponenten: ManualEnrollment, ManualLookup, GearTracker, VolunteerList

### 12.2 Volunteer zu Staff promoten (#66)

- Aufgerufen von: Volunteer-Detailseite
- Promote zu **Volunteer Admin** → Auswahl welchem Scanner zugewiesen; Link normal versendet
- Promote zu **Organizer** → Account erstellt (falls nötig), zum Projekt/Organisation hinzugefügt, Einladungs-E-Mail
- Entziehen = aus Scanner-Assignee-Liste entfernen
- Entry Staff wird **nicht** über „Promote to Staff" vergeben — nur über Entry Staff Scanner

### 12.3 Klon (#78)

- Klon von Projekt oder einzelnem Event
- Immer als Draft erstellt
- Optionaler Datums-Offset (verschiebt alle Daten, Events, Schichten, Scanner-Zeitfenster)
- **Geklont:** Struktur, Jobs, Gear-Definitionen, Custom Fields, E-Mail-Vorlagen, Scanner-Konfigurationen (Zugewiesene geleert)
- **Nicht geklont:** Volunteers, Anmeldungen, Ankündigungen, Anwesenheit
- Einstiegspunkte: Dashboard-Kachel, Event-Übersicht

### 12.4 Löschen (#79)

- **Projekt löschen:** Vollständige Löschung aller verknüpften Daten
- **Event löschen:** Pro-Volunteer-Prüfung — Volunteer behalten wenn andere Anmeldungen im Projekt vorhanden
- Passwort-Bestätigung erforderlich
- Veröffentlichte Events müssen zuerst archiviert werden
- 30-Tage Soft-Delete-Gnadenfrist

### 12.5 Activity Log (#64)

Log-Ereignisse für Projekt- und Event-Ebene:
- Einladung gesendet
- Rollenänderung
- Mitglied entfernt
- Mitglied hat verlassen
- Gesperrt bei Brute-Force (via #80)

---

## 13. Konfigurierbare Hinweistexte (#74)

Organizer-konfigurierbare Hinweistexte:

**Ebenen:** Organisation (Standard) → Projekt (Override)

**Positionen:**

| Bereich | Felder |
|---|---|
| Signup-Flow | E-Mail, Nachname, Telefon, Step-4-Zusammenfassung, Bestätigungsseite |
| Volunteer-Portal | Oben (Banner), Gear-Sektion, Schichten-Sektion |
| Entry Staff Scanner | Willkommensbildschirm |

Jeder Hinweis hat: Label, Textfeld, Aktiv-Toggle.

---

## 14. Plausibilitätsprüfung — Offene Fragen

### Entscheidungen (alle getroffen)

**F1 — Magic Link Routing** ✓
Magic Link führt direkt zurück in den Signup-Flow für das Event, bei dem der Volunteer sich anmelden wollte. Timer startet nach dem Klick.

**F2 — QR-Code Scope** ✓
Ein QR-Code pro Projekt-Mitgliedschaft — gilt für alle Events im Projekt. Scanner prüft Schicht-Berechtigung pro Event.

**F3 — Gear Typ-1-Zustände Reversibilität** ✓
Alle Typ-1-Zustände sind jederzeit änderbar — unabhängig vom Scanner-Zeitfenster.

**F4 — Dashboard Gear Favoriten** ✓
Organizer wählt pro Projekt, welche Gear-Items im Dashboard-Widget erscheinen (konfigurierbare Favoriten).

**F5 — Zeitzone Digest-Mail** ✓
Server-Zeitzone — keine separate Konfiguration.

**F6 — Event ohne Schichten veröffentlichen** ✓
Veröffentlichen ist blockiert, solange keine Schicht angelegt ist.

**F7 — Signup Reihenfolge** ✓
Schritt 2: Zuerst Daten (Vorname, Nachname, Telefon), dann Schichtauswahl. 20-Min-Timer läuft ab Beginn von Schritt 2.

**F8 — Gear-only Scanner, Volunteer ohne Gear** ✓
Neutrale Meldung „Kein Gear zugewiesen" — kein Fehlerstatus (kein Rot).

**F9 — Gear-Ausgabe nach Ablauf Zeitfenster** ✓
Organizer kann das Zeitfenster eines Scanners nachträglich verlängern.

**F10 — Projektwebsite vor erstem Publish** ✓
Eingeloggte Organizer sehen eine Preview. Nicht-eingeloggte Besucher erhalten 404.

---

## 15. Implementierungs-Reihenfolge (nach Abhängigkeiten)

```text
Foundational
└── #52 Project rename (Basis für alles)

Core Architecture
├── #65 Rollen-Modell
├── #82 Vorname/Nachname
└── #45 Event-Lifecycle

Signup & Volunteer
├── #69 Signup-Flow (Basis: #49, #50, #54, #80, #82)
├── #51 Volunteer-Portal (Basis: #52, #53, #69)
└── #85 Schicht-Absagen (Basis: #52, #47, #51)

Scanner
├── #75 Scanner Management (Basis: #52, #65)
├── #56 Volunteer Admin Scanner (Basis: #52, #53, #65, #75)
├── #58 Entry Staff Scanner (Basis: #52, #65, #71, #72, #73, #75)
├── #71 Green/Yellow/Red Results
├── #72 Zeitfenster-Enforcement
└── #73 Offline-Verschlüsselung

Content & Email
├── #81 Deutsche E-Mail-Vorlagen
├── #55 E-Mail-Vorlagen (Event-Ebene)
├── #84 Re-Publish Benachrichtigung
└── #83 Projektwebsite

Operations
├── #76 Dashboard
├── #77 Gear Summary
├── #78 Klon
└── #79 Löschen

Bug Fixes
├── #67 MATCH AGAINST @ Symbol
└── #48 Scanner Touch-Targets
```

---

---

## 16. Announcements

Organizer kann manuell E-Mails an eine gefilterte Gruppe von Volunteers senden — unabhängig von automatischen System-E-Mails.

**Use Cases:**
- Schicht-spezifisch: „Schicht Kuchenausgabe: Bitte keine Teller mitbringen"
- Event-weit: „Wegen des Wetters bitte Sonnenschutz mitbringen"

### Empfänger

Filterbasierte Auswahl (kombinierbar): Event → Job → Schicht — wie Ticket-Filter.

### Kanal

Nur E-Mail (kein Portal-Banner).

### Zeitpunkt

- **Sofort senden**
- **Geplant** — Versand zu einem festgelegten Zeitpunkt

### Inhalt

- Freitext (Subject + Body)
- **Oder:** Auswahl aus gespeicherten Announcement-Templates (wiederverwendbar über Projekte/Jahre hinweg)

### Häufigkeit

Keine Begrenzung — Organizer-only.

### Abgrenzung zu System-E-Mails

Announcements sind manuelle, inhaltlich freie Nachrichten. Sie unterscheiden sich von automatischen E-Mails (Bestätigung, Reminder, Re-Publish), die durch System-Events ausgelöst werden.

---

## 17. Volunteer Management durch Organizer

### 17.1 Volunteer ohne Schicht

Volunteers können ohne aktive Schicht existieren — dies ist ein valider Zustand:

- Volunteer hat sich verifiziert, aber noch keine Schicht gewählt
- Volunteer hat alle Schichten abgesagt
- Organizer hat Volunteer manuell angelegt ohne Schichtzuweisung

**Auswirkung:** Eligibility-Check schützt korrekt — ohne Schicht (Vergangenheit oder Zukunft) kein Zugriff auf Gear/Ticket im Scanner. Im Organizer-Dashboard: Badge „Keine Schicht".

### 17.2 Signup-Flow — Verifizierung und Magic Link

**Nach E-Mail-Verifizierung (neuer Volunteer):**
- System sendet eine separate Willkommens-E-Mail mit Magic Link ins Volunteer-Portal
- Template: `volunteer_welcome` — „Du bist jetzt im System. Hier ist dein Portal-Zugang."
- Danach: Magic Link-Klick öffnet Schritt 2 des Signup-Flows (Daten + Schichten)

**Rückkehrender Volunteer (Magic Link aus Signup-Flow):**
- Klick → direkt zu Schritt 2 des Signup-Flows für das angeforderte Event
- Kurzer Hinweis auf bestehende Anmeldungen eingeblendet, kein separater Übersichts-Schritt
- 20-Min-Timer startet mit Beginn von Schritt 2

### 17.3 Manuelles Anlegen durch Organizer

Organizer kann einen Volunteer direkt anlegen — ohne Signup-Flow.

**Flow für den Organizer:**
1. Persönliche Daten eintragen: Vorname, Nachname, E-Mail (Pflicht), Telefon (optional)
2. Schichten zuweisen (optional)
3. Custom Fields eintragen (optional — Organizer trägt ein was er weiß)
4. Gear-Größe eintragen (optional)
5. Speichern → E-Mail an Volunteer

**Pflichtfelder für Organizer:** Nur E-Mail. Alle anderen Felder optional — Organizer trägt ein was bekannt ist.

**E-Mail an Volunteer nach Anlage:**
- Template: `volunteer_added_by_organizer`
- Inhalt: „Du wurdest als Helfer eingetragen. Vervollständige deine Angaben über deinen Portal-Link."
- Enthält: Magic Link ins Helfer-Portal

**Fehlende Pflichtangaben (z.B. Typ-1-Gear-Auswahl):**
- Im Portal: Banner „Bitte vervollständige deine Registrierung" mit direktem Link zu fehlenden Feldern
- Im Scanner (Typ-1-Gear): Status **„Auswahl ausstehend"** — gilt generisch für jede Typ-1-Option (Größe, Farbe, Variante etc.); Ausgabe erst möglich wenn Volunteer die Auswahl selbst eingetragen hat
- Custom Fields ohne Antwort: im Organizer-UI als „Keine Angabe" dargestellt

### 17.4 Organizer-Aktionen auf bestehenden Volunteers

Organizer kann folgende Aktionen durchführen:

- **Schichten zuweisen / ändern** — Schichten hinzufügen oder entfernen
- **Anmeldung stornieren** — einzelne Schicht oder gesamte Anmeldung
- **Stammdaten bearbeiten** — Vorname, Nachname, E-Mail, Telefon

**E-Mail nach Organizer-Änderung:**
Dieselbe Standard-E-Mail wie bei einer Änderung durch den Volunteer selbst — kein separates „Organizer hat geändert"-Template. Volunteer merkt im Ergebnis keinen Unterschied.

---

## 18. Gästelisten (#90)

Organizer können Gästelisten für nicht-registrierte Personen (VIPs, Künstler, Ehrengäste, Begleitpersonen) verwalten. Gäste durchlaufen keinen Signup-Flow, erhalten aber QR-Codes und können Gear erhalten.

### Use Case

Künstler erhalten als Teil ihrer Bezahlung Einlass für sich und Begleitpersonen. Organizer legt fest: „DJ Soundwave — 3 Gäste". Jeder Gast erhält einen eigenen QR-Code. Am Einlass wird per Entry Staff Scanner gescannt. Gear wird über den Volunteer Admin Scanner ausgegeben.

### 18.1 Datenmodell

```
GuestList (Projekt-Level)
├── project_id
├── scanner_id          (FK → Entry Staff Scanner, Pflicht)
├── name                ("Künstler Hauptabend")
├── status              (draft / confirmed)
├── gear_items          (welche Gear Items verfügbar)

GuestGroup
├── guest_list_id
├── label               ("DJ Soundwave")
├── guest_count         (3)

GuestEntry
├── guest_group_id
├── number              (1, 2, 3)
├── name                (nullable)
├── email               (nullable)
├── qr_token            (null bis Bestätigung)
├── checked_in_at       (nullable)

GuestEntryGear
├── guest_entry_id
├── project_gear_item_id    (Typ-1 ODER Typ-2)
├── quantity                 (Typ-2: Kontingent, Typ-1: immer 1)
├── picked_up_count          (Typ-2: 0...quantity)
├── selection                (Typ-1: nullable — "M", "L", etc.)
├── status                   (Typ-1: aus konfigurierbarer Zustandsliste)
```

### 18.2 Lifecycle

**Entwurf:** Organizer erstellt Gästeliste, ordnet Entry Staff Scanner zu, legt Gruppen und Einträge an. Keine E-Mails.

**Bestätigung:** Organizer klickt „Gästeliste bestätigen":
- QR-Codes generiert (ein Code pro Eintrag)
- E-Mails gruppiert versendet: gleiche E-Mail bei mehreren Einträgen → eine Mail mit allen QR-Codes
- Einträge ohne E-Mail: kein Versand, QR-Code nur im System

**Nachträgliche Änderungen (nach Bestätigung):**
- Gast hinzufügen → neuer QR-Code → E-Mail sofort (wenn E-Mail vorhanden)
- Gast entfernen → QR-Code ungültig → Scanner zeigt Rot
- Gast bearbeiten → QR-Code bleibt gültig, Daten aktualisiert

### 18.3 Beispiel

| Gruppe | # | Name | E-Mail | Gear |
|---|---|---|---|---|
| DJ Soundwave | 1 | DJ Soundwave | dj@example.com | 3 Getränkemarken, T-Shirt (–) |
| DJ Soundwave | 2 | – | dj@example.com | 2 Getränkemarken |
| DJ Soundwave | 3 | – | – | 2 Getränkemarken |
| Moderatorin Meier | 1 | Anna Meier | anna@example.com | 3 Getränkemarken, T-Shirt (L) |

E-Mail-Versand bei Bestätigung:
- dj@example.com → 1 Mail mit 2 QR-Codes (#1 + #2)
- anna@example.com → 1 Mail mit 1 QR-Code (#1)
- Eintrag DJ #3 (keine E-Mail) → kein Versand

### 18.4 Scanner-Integration

**Entry Staff Scanner:**
- QR-Scan: 🟢 „Gast — DJ Soundwave 1/3" / 🟡 „Bereits eingecheckt" / 🔴 „Ungültig"
- Gastliste-Tab: Volunteers (oben) + Gäste gruppiert (unten) mit Check-in-Status
- Manuelle Suche: findet Gäste nach Name oder Gruppe
- „Nächsten scannen"-Button wie bei Volunteers

**Volunteer Admin Scanner:**
- Gäste erscheinen nur wenn sie Gear haben
- Typ-2: normaler Flow („2/3 abgeholt" → Tap → +1)
- Typ-1 mit Auswahl: normaler Zustandswechsel
- Typ-1 ohne Auswahl: Operator wählt direkt im Scanner (Dropdown) nach mündlicher Abfrage → Auswahl + Zustand gespeichert → Statistik korrekt

> Unterschied zu Volunteers: Bei Volunteers blockiert „Auswahl ausstehend" die Ausgabe (Portal-Auswahl nötig). Bei Gästen kann der Operator die Auswahl direkt im Scanner treffen — Gäste haben kein Portal.

| Feature | Entry Staff Scanner | Volunteer Admin Scanner |
|---|---|---|
| Gast QR scannen | ✓ | — |
| Gastliste durchsuchen | ✓ | — |
| Check-in (Einlass) | ✓ | — |
| Gear Typ-1 ausgeben | — | ✓ (mit Größen-Abfrage) |
| Gear Typ-2 ausgeben | — | ✓ |

### 18.5 Tracking

**Gästeliste Übersicht (Projekt > Gästelisten):**
- DJ Soundwave: 2/3 eingecheckt
- Moderatorin Meier: 0/2 eingecheckt
- Gesamt: 2/5 eingecheckt

**Gear-Tracking:**
- Getränkemarken: 4/12 abgeholt
- T-Shirt: 1/2 ausgegeben (1× „Auswahl ausstehend")

### 18.6 Begründungen

- **An Entry Staff Scanner gebunden:** Scanner-Offline-Daten müssen die Gäste enthalten. Verschiedene Eingänge können verschiedene Listen haben (VIP-Eingang vs. Haupteingang). Kein Scanner = kein Feature.
- **E-Mails erst bei Bestätigung:** Organizer baut Liste in Ruhe auf. Kein versehentlicher Versand.
- **Typ-1 direkt im Scanner (nur Gäste):** Gäste haben kein Portal. Mündliche Abfrage + Scanner-Eingabe ist der pragmatischste Weg. Statistik bleibt korrekt.

---

*Dokument basiert auf GitHub Issues #45–#90 (Stand: 2026-03-31)*