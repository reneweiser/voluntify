<?php

namespace App\Enums;

enum EmailTemplateType: string
{
    case SignupConfirmation = 'signup_confirmation';
    case PreShiftReminder24h = 'pre_shift_reminder_24h';
    case PreShiftReminder4h = 'pre_shift_reminder_4h';
    case EmailVerification = 'email_verification';

    // #81 - New template types for German defaults
    case StaffInvitation = 'staff_invitation';
    case VolunteerPromoted = 'volunteer_promoted';
    case AddedToOrganization = 'added_to_organization';
    case EventAnnouncement = 'event_announcement';
    case EventUpdated = 'event_updated';

    /**
     * Human-readable German label for the template type.
     */
    public function label(): string
    {
        return match ($this) {
            self::SignupConfirmation => 'Anmeldebestätigung',
            self::PreShiftReminder24h => 'Schicht-Erinnerung (24h)',
            self::PreShiftReminder4h => 'Schicht-Erinnerung (4h)',
            self::EmailVerification => 'E-Mail-Bestätigung',
            self::StaffInvitation => 'Team-Einladung',
            self::VolunteerPromoted => 'Helfer-Beförderung',
            self::AddedToOrganization => 'Zur Organisation hinzugefügt',
            self::EventAnnouncement => 'Event-Ankündigung',
            self::EventUpdated => 'Event-Aktualisierung',
        };
    }
}
