# Settings and Account

Access settings by clicking **Settings** in the user menu (sidebar). The settings pages let you manage your profile, security, and organization preferences.

## Profile

**Path**: Settings > Profile

Update your name and email address. All team members can edit their own profile.

## Password

**Path**: Settings > Password

Change your password. You'll need to enter your current password and then your new password twice to confirm.

## Two-Factor Authentication

**Path**: Settings > Two-Factor Authentication

Add an extra layer of security to your account with time-based one-time passwords (TOTP).

### Set Up 2FA

1. Go to Settings > Two-Factor Authentication.
2. You may be asked to confirm your password.
3. Scan the QR code with an authenticator app (e.g., Google Authenticator, Authy, 1Password).
4. Enter the code from your authenticator app to confirm setup.

### Recovery Codes

After enabling 2FA, you'll be shown recovery codes. **Save these somewhere safe** -- they're the only way to access your account if you lose your authenticator device.

Each recovery code can only be used once. You can regenerate new codes from the 2FA settings page if needed.

### Disable 2FA

To turn off two-factor authentication, go to Settings > Two-Factor Authentication and disable it. You may need to confirm your password.

## Email / SMTP Configuration

**Path**: Settings > Email
**Who can access**: Organizer only.

Configure email delivery settings for your organization. This controls how Voluntify sends emails to volunteers (signup confirmations, pre-shift reminders, ticket links).

## Appearance

**Path**: Settings > Appearance

Customize the visual appearance of the application.

## Log Viewer

**Path**: Logs (in the sidebar)
**Who can access**: Organizer only.

View structured application logs. The log viewer shows log entries with:
- Timestamp
- Log level (info, warning, error, etc.)
- Message content
- Collapsible context data

Use this to troubleshoot issues like failed email deliveries or sync errors.
