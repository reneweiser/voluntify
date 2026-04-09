# UC-17: Volunteer löscht eigenes Profil

**Akteur:** Volunteer
**Ziel:** Profil und alle persönlichen Daten unwiderruflich löschen

## Vorbedingungen

- Volunteer ist über Portal eingeloggt (Magic Link)
- Alle Schichten sind entweder abgeschlossen (`ends_at` in der Vergangenheit) oder stornierbar

## Ablauf

1. Volunteer öffnet Helfer-Portal → "Profil löschen"
2. System prüft: Hat der Volunteer nicht-stornierbare Schichten?
   - **Ja:** Löschung blockiert, Hinweistext:
     > "Dein Profil kann gerade nicht gelöscht werden. Du hast dich verbindlich für Schichten angemeldet, bei denen der Stornierungszeitraum bereits abgelaufen ist. Dein Team baut darauf, dass alle Helfer:innen zuverlässig erscheinen — deine Unterstützung macht den Unterschied! Sobald alle deine Schichten abgeschlossen oder innerhalb der Frist storniert sind, kannst du dein Profil jederzeit löschen."
   - **Nein:** Weiter mit Bestätigung
3. Warnhinweis anzeigen:
   - Alle Schicht-Anmeldungen werden unwiderruflich storniert
   - Eventuelle Tickets verlieren ihre Gültigkeit
   - Nicht abgeholte Gear-Artikel (z.B. T-Shirts) verfallen unwiderruflich
4. Volunteer bestätigt explizit (z.B. Checkbox)
5. Bestätigungs-E-Mail (`profile_deletion_confirmation`) wird versendet
6. Profil wird gelöscht (echte Löschung, kein Soft-Delete — DSGVO)
7. Organizer wird benachrichtigt

## Ergebnis

- Volunteer-Record und alle persönlichen Daten gelöscht
- Schicht-Signups storniert, Kapazität freigegeben
- QR-Code ungültig
- Gear-Zuweisungen entfernt
- Organizer erhält Benachrichtigung

## Sonderfälle

- **Nicht-stornierbare Schicht vorhanden:** Löschung blockiert bis Schicht abgeschlossen
- **"Abgeschlossen" Definition:** `ends_at` der Schicht liegt in der Vergangenheit. Ohne definierte Endzeit: `shift_date` + Tagesende (23:59)

## Referenz

- Decision: PO-Session 2026-04-09 (Profil-Löschung)
- Issues: #105, #143
