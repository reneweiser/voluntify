<?php

namespace App\Notifications\Concerns;

use App\Models\Organization;
use App\Services\OrganizationMailerService;
use Illuminate\Notifications\Messages\MailMessage;

trait UsesOrganizationMailer
{
    protected function applyOrgMailer(MailMessage $mail, Organization $organization): MailMessage
    {
        $service = app(OrganizationMailerService::class);
        $mailerName = $service->resolveMailerName($organization);
        $mail->mailer($mailerName);

        if ($organization->smtp_from_address) {
            $mail->from($organization->smtp_from_address, $organization->smtp_from_name);
        }

        return $mail;
    }
}
