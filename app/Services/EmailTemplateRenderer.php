<?php

namespace App\Services;

use App\Enums\EmailTemplateType;
use App\Models\Event;

class EmailTemplateRenderer
{
    /**
     * German default templates for all email types.
     * #81 - Converted to German with new placeholders.
     *
     * @var array<string, array{subject: string, body: string}>
     */
    private const DEFAULTS = [
        'signup_confirmation' => [
            'subject' => 'Anmeldebestätigung für {{event_name}}',
            'body' => "Hallo {{vorname}}!\n\nDu bist für **{{event_name}}** angemeldet.\n\n**Deine Schichten:**\n{{shifts_summary}}\n{{event_location}}\n\nDu erhältst dein Ticket mit QR-Code über einen separaten Link.\n\nVielen Dank für deine Unterstützung!",
        ],
        'pre_shift_reminder_24h' => [
            'subject' => 'Erinnerung: Deine Schicht bei {{event_name}} ist morgen',
            'body' => "Hallo {{vorname}}!\n\nDies ist eine Erinnerung, dass deine Schicht bei **{{event_name}}** morgen stattfindet.\n\n**Aufgabe:** {{job_name}}\n**Schicht:** {{shift_date}} {{shift_time}}\n{{event_location}}\n{{cheat_sheet_url}}\n\nBis morgen!",
        ],
        'pre_shift_reminder_4h' => [
            'subject' => 'Erinnerung: Deine Schicht bei {{event_name}} beginnt bald',
            'body' => "Hallo {{vorname}}!\n\nDeine Schicht bei **{{event_name}}** beginnt in wenigen Stunden.\n\n**Aufgabe:** {{job_name}}\n**Schicht:** {{shift_date}} {{shift_time}}\n{{event_location}}\n{{cheat_sheet_url}}\n\nBis gleich!",
        ],
        'email_verification' => [
            'subject' => 'Bestätige deine E-Mail für {{event_name}}',
            'body' => "Hallo {{vorname}}!\n\nBitte bestätige deine E-Mail-Adresse, um deine Anmeldung als Helfer:in bei **{{event_name}}** abzuschließen.\n\nDieser Link ist 24 Stunden gültig. Deine Schichtauswahl ist erst nach der Bestätigung reserviert.",
        ],
        'staff_invitation' => [
            'subject' => 'Einladung zu {{organization_name}}',
            'body' => "Hallo {{name}}!\n\nDu wurdest zu **{{organization_name}}** auf Voluntify eingeladen.\n\nDein temporäres Passwort lautet: **{{temporary_password}}**\n\nBeim ersten Login wirst du aufgefordert, dein Passwort zu ändern.\n\nVielen Dank, dass du dabei bist!",
        ],
        'volunteer_promoted' => [
            'subject' => 'Du wurdest bei {{organization_name}} befördert',
            'body' => "Hallo {{name}}!\n\nDu wurdest bei **{{organization_name}}** auf Voluntify zu **{{role_name}}** befördert.\n\nDein temporäres Passwort lautet: **{{temporary_password}}**\n\nBeim ersten Login wirst du aufgefordert, dein Passwort zu ändern.\n\nVielen Dank für dein Engagement!",
        ],
        'added_to_organization' => [
            'subject' => 'Du wurdest zu {{organization_name}} hinzugefügt',
            'body' => "Hallo {{name}}!\n\nDu wurdest als **{{role_name}}** zu **{{organization_name}}** hinzugefügt.\n\nVielen Dank, dass du dabei bist!",
        ],
        'event_announcement' => [
            'subject' => '{{subject}}',
            'body' => '{{body}}',
        ],
        'event_updated' => [
            'subject' => 'Aktualisierung zu {{event_name}}',
            'body' => "Hallo {{vorname}}!\n\nEs gibt Neuigkeiten zu **{{event_name}}**:\n\n{{organizer_note}}\n\nBesuche dein Portal für weitere Details: {{portal_link}}",
        ],
        'cancellation_confirmation' => [
            'subject' => 'Stornierungsbestätigung für {{event_name}}',
            'body' => "Hallo {{vorname}}!\n\nDeine folgende Schicht bei **{{event_name}}** wurde storniert:\n{{cancelled_shift_summary}}\n\n{{remaining_shifts_section}}\n\nDu kannst dein Ticket jederzeit über den folgenden Link einsehen: {{portal_link}}\n\nVielen Dank!",
        ],
        'profile_deletion' => [
            'subject' => 'Dein Profil wurde gelöscht',
            'body' => "Hallo {{vorname}}!\n\nDein Volunteer-Profil und alle zugehörigen Daten wurden auf deine Anfrage hin gelöscht.\n\n**Folgende Daten wurden unwiderruflich entfernt:**\n- Alle Schicht-Anmeldungen\n- Eventuelle Tickets und QR-Codes\n- Gear-Zuweisungen\n- Persönliche Daten (Name, E-Mail, Telefon)\n\nDieser Vorgang kann nicht rückgängig gemacht werden. Falls du erneut als Volunteer teilnehmen möchtest, musst du dich neu registrieren.\n\nVielen Dank für dein Engagement!",
        ],
    ];

    /**
     * @param  array<string, string>  $variables
     * @return array{subject: string, body: string}
     */
    public function render(EmailTemplateType $type, Event $event, array $variables): array
    {
        $template = $event->emailTemplates()
            ->where('type', $type)
            ->first();

        if ($template) {
            $subject = $template->subject;
            $body = $template->body;
        } else {
            $defaults = self::DEFAULTS[$type->value];
            $subject = $defaults['subject'];
            $body = $defaults['body'];
        }

        return [
            'subject' => $this->replacePlaceholders($subject, $variables),
            'body' => $this->replacePlaceholders($body, $variables),
        ];
    }

    /**
     * @return array{subject: string, body: string}
     */
    public function getDefaults(EmailTemplateType $type): array
    {
        return self::DEFAULTS[$type->value];
    }

    /**
     * Returns available placeholders for a template type.
     * #81 - Added new German placeholders (vorname, nachname, telefon, etc.)
     *
     * @return array<string>
     */
    public function availablePlaceholders(EmailTemplateType $type): array
    {
        return match ($type) {
            EmailTemplateType::SignupConfirmation => [
                'vorname',
                'nachname',
                'telefon',
                'volunteer_name', // Legacy - kept for backwards compatibility
                'event_name',
                'shifts_summary',
                'job_name',
                'shift_date',
                'shift_time',
                'event_location',
                'portal_link',
                'kontakt_email',
                'project_name',
            ],
            EmailTemplateType::PreShiftReminder24h,
            EmailTemplateType::PreShiftReminder4h => [
                'vorname',
                'nachname',
                'volunteer_name', // Legacy
                'event_name',
                'job_name',
                'shift_date',
                'shift_time',
                'event_location',
                'cheat_sheet_url',
                'portal_link',
                'kontakt_email',
                'project_name',
            ],
            EmailTemplateType::EmailVerification => [
                'vorname',
                'nachname',
                'volunteer_name', // Legacy
                'event_name',
            ],
            EmailTemplateType::StaffInvitation => [
                'name',
                'organization_name',
                'temporary_password',
                'login_url',
            ],
            EmailTemplateType::VolunteerPromoted => [
                'name',
                'organization_name',
                'role_name',
                'temporary_password',
                'login_url',
            ],
            EmailTemplateType::AddedToOrganization => [
                'name',
                'organization_name',
                'role_name',
                'login_url',
            ],
            EmailTemplateType::EventAnnouncement => [
                'subject',
                'body',
            ],
            EmailTemplateType::EventUpdated => [
                'vorname',
                'nachname',
                'event_name',
                'organizer_note',
                'portal_link',
            ],
            EmailTemplateType::CancellationConfirmation => [
                'vorname',
                'nachname',
                'event_name',
                'cancelled_shift_summary',
                'remaining_shifts_section',
                'portal_link',
                'kontakt_email',
                'project_name',
            ],
            EmailTemplateType::ProfileDeletion => [
                'vorname',
                'nachname',
                'project_name',
            ],
        };
    }

    /**
     * @param  array<string, string>  $variables
     */
    private function replacePlaceholders(string $text, array $variables): string
    {
        foreach ($variables as $key => $value) {
            $text = str_replace("{{{$key}}}", $value, $text);
        }

        return $text;
    }
}
