<?php

namespace App\Services;

use App\Enums\EmailTemplateType;
use App\Models\Event;

class EmailTemplateRenderer
{
    /** @var array<string, array{subject: string, body: string}> */
    private const DEFAULTS = [
        'signup_confirmation' => [
            'subject' => "You're signed up for {{event_name}}!",
            'body' => "Hello {{volunteer_name}}!\n\nYou've been signed up for **{{event_name}}**.\n\n**Your Shifts:**\n{{shifts_summary}}\n{{event_location}}\nYou will receive your ticket with a QR code via a separate link.\n\nThank you for volunteering!",
        ],
        'pre_shift_reminder_24h' => [
            'subject' => 'Reminder: Your shift for {{event_name}} is tomorrow',
            'body' => "Hello {{volunteer_name}}!\n\nThis is a reminder that your shift for **{{event_name}}** is coming up tomorrow.\n\n**Job:** {{job_name}}\n**Shift:** {{shift_date}} {{shift_time}}\n{{event_location}}\nSee you there!",
        ],
        'pre_shift_reminder_4h' => [
            'subject' => 'Reminder: Your shift for {{event_name}} starts soon',
            'body' => "Hello {{volunteer_name}}!\n\nYour shift for **{{event_name}}** starts in a few hours.\n\n**Job:** {{job_name}}\n**Shift:** {{shift_date}} {{shift_time}}\n{{event_location}}\nSee you soon!",
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
     * @return array<string>
     */
    public function availablePlaceholders(EmailTemplateType $type): array
    {
        return match ($type) {
            EmailTemplateType::SignupConfirmation => [
                'volunteer_name',
                'event_name',
                'shifts_summary',
                'job_name',
                'shift_date',
                'shift_time',
                'event_location',
            ],
            EmailTemplateType::PreShiftReminder24h,
            EmailTemplateType::PreShiftReminder4h => [
                'volunteer_name',
                'event_name',
                'job_name',
                'shift_date',
                'shift_time',
                'event_location',
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
