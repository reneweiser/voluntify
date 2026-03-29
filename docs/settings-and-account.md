# Settings and Account

Access settings by clicking **Settings** in the user menu (sidebar). The settings pages let you manage your profile, security, and organization preferences.

## Profile

**Path**: Settings > Profile

Update your name and email address. All team members can edit their own profile.

## Password

**Path**: Settings > Password

Change your password. You'll need to enter your current password and then your new password twice to confirm. A real-time requirements checklist updates as you type, showing which rules your new password satisfies (length, mixed case, numbers, symbols).

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

## Organization Switching

The organization switcher is located at the top of the sidebar. Click it to see all organizations you belong to, and select one to switch your active context. All pages (Dashboard, Events, Settings, etc.) reflect the currently selected organization. Your selection is persisted across sessions -- when you log back in, you'll return to the last organization you were viewing.

To create a new organization directly from the switcher, click **Create new organization**. Enter a name and the slug will be auto-generated. Click **Create** to finish.

To leave an organization, see [Managing Your Members > Leave an Organization](managing-your-team.md#leave-an-organization).

## Activity Log

**Path**: Activity Log (in the sidebar)
**Who can access**: Organizer only.

View an audit trail of actions taken across your organization. The activity log tracks 20 event types across 8 categories, showing who did what and when.

### Filters

- **Event** -- Filter by a specific event.
- **Category** -- Filter by activity category.
- **Actor** -- Filter by the user who performed the action.
- **Date range** -- Filter by time period.

### Categories

| Category | What It Tracks |
|---|---|
| **Event** | Event creation, updates, publishing, archiving, cloning |
| **Job** | Job creation, updates, deletion |
| **Shift** | Shift creation, updates, deletion |
| **Volunteer** | Volunteer signups, promotions |
| **Attendance** | Attendance status changes |
| **Member** | Member invitations, role changes, removals, departures |
| **Email** | Email template changes, SMTP configuration |
| **System** | Organization-level changes |

## Delete Account

**Path**: Settings > Profile (bottom of the page)

To permanently delete your account:

1. Go to Settings > Profile.
2. Scroll to the **Delete account** section.
3. Click **Delete account**.
4. Enter your password to confirm.
5. Click **Delete account** in the confirmation dialog.

Your account and all associated data are permanently deleted and you are logged out immediately. Organizations you belonged to and their data (events, volunteers, etc.) are not deleted, but may become inaccessible if you were the sole Organizer.

This action cannot be undone. If you are the only Organizer in an organization, [transfer the Organizer role](managing-your-team.md#change-a-members-role) to another member before deleting your account.

The delete account option only appears for users with a verified email address.
