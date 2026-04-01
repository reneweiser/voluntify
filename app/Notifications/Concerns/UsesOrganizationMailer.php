<?php

namespace App\Notifications\Concerns;

use App\Models\Organization;
use App\Models\Project;
use App\Services\OrganizationMailerService;
use Illuminate\Notifications\Messages\MailMessage;

trait UsesOrganizationMailer
{
    protected function applyOrgMailer(MailMessage $mail, Organization $organization, ?Project $project = null): MailMessage
    {
        $service = app(OrganizationMailerService::class);
        $mailerName = $service->resolveMailerName($organization);
        $mail->mailer($mailerName);

        if ($organization->smtp_from_address) {
            $fromName = $project?->sender_name ?? $organization->smtp_from_name;
            $mail->from($organization->smtp_from_address, $fromName);
        }

        if ($project?->contact_email) {
            $mail->replyTo($project->contact_email);
        }

        return $mail;
    }
}
