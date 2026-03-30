# Settings and Account

Access settings by clicking **Einstellungen** in the user menu (sidebar). Settings are split across organisation, project, and event levels.

## Profile

**Path**: Einstellungen > Profil

Update your Vorname, Nachname, and email address. All Organizers can edit their own profile.

## Password

**Path**: Einstellungen > Passwort

Change your password. Enter your current password and your new password twice. A real-time checklist shows which rules are satisfied.

## Two-Factor Authentication

**Path**: Einstellungen > Zwei-Faktor-Authentifizierung

### Set Up 2FA

1. Go to Einstellungen > Zwei-Faktor-Authentifizierung.
2. Confirm your password.
3. Scan the QR code with an authenticator app (Google Authenticator, Authy, 1Password).
4. Enter the code to confirm.

### Recovery Codes

After enabling 2FA, save your recovery codes somewhere safe -- they're the only way in if you lose your authenticator device. Each code is single-use. Regenerate from the 2FA settings page.

### Disable 2FA

Go to Einstellungen > Zwei-Faktor-Authentifizierung and disable it. Password confirmation required.

## Email / SMTP Configuration

### Organisation Level

**Path**: Organisation > Einstellungen > E-Mail-Server
**Who can access**: Org-Level Organizer only.

Configure the default outgoing mail server for all projects:

- **SMTP Host**
- **SMTP Port**
- **Verschlüsselung** (TLS / SSL)
- **Benutzername**
- **Passwort**
- **Testmail senden** -- Sends a test email to verify the connection

> Example: SKHC configures their mail server once at org level. All projects ("Hochschulball 2026", "Sommerfest 2026") use this server by default.

### Project Level

**Path**: Projekt > Einstellungen > E-Mail
**Who can access**: Project Organizer.

- **Absendername** -- Display name in the "From" field
- **Kontakt-E-Mail** -- Reply-to and `{{kontakt_email}}` placeholder
- **Eigener SMTP-Server** (optional) -- Overrides the organisation default

> Why project-level SMTP override? Different projects may need different sender addresses (e.g. "ball@skhc.de" vs "sommerfest@skhc.de").

**SMTP Hierarchy:**
1. Project SMTP (if configured) -- overrides org default
2. Organisation SMTP -- default for all projects
3. System mailer -- fallback; also used for organizer-facing alerts (e.g. cancellation digest)

### Event Level

**Path**: Event > Einstellungen > E-Mail

- **Benachrichtigungs-E-Mail** -- Address for organizer-facing alerts (cancellation digests, system notifications for this event)
- Must be set before shift cancellation can be enabled for the event

## Organisation Switching

The organisation switcher is at the top of the sidebar. Click to see all organisations you belong to and switch context. Your selection persists across sessions.

To create a new organisation: click **Neue Organisation erstellen** in the switcher.

## Activity Log

**Path**: Activity Log (sidebar)
**Who can access**: Organizer only.

View an audit trail of actions across your organisation.

### Filters

- **Event** -- Filter by event
- **Kategorie** -- Filter by activity category
- **Akteur** -- Filter by user
- **Zeitraum** -- Filter by date range

### Categories

| Category | Tracks |
|---|---|
| **Event** | Creation, updates, publishing, archiving, cloning |
| **Job** | Creation, updates, deletion |
| **Shift** | Creation, updates, deletion |
| **Volunteer** | Signups, promotions, manual creation |
| **Attendance** | Status changes |
| **Member** | Invitations, role changes, removals, departures |
| **Email** | Template changes, SMTP configuration |
| **Security** | Brute-force lockouts (from rate limiting) |
| **System** | Organisation-level changes |

## Delete Account

**Path**: Einstellungen > Profil (bottom of page)

1. Click **Account löschen**.
2. Enter your password.
3. Confirm.

Your account and all associated data are permanently deleted. Organisations and their data are not deleted, but may become inaccessible if you were the sole Organizer.

This action cannot be undone. Transfer the Organizer role to another member first if you're the only one.