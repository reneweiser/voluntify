<?php

namespace App\Actions;

use App\Models\Organization;
use App\Services\OrganizationMailerService;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Mail;

class SendTestEmail
{
    public function __construct(private OrganizationMailerService $mailerService) {}

    public function execute(Organization $organization, string $recipientEmail): void
    {
        $mailerName = $this->mailerService
            ->resolveMailerName($organization);

        $fromAddress = $organization->smtp_from_address ?: config('mail.from.address');
        $fromName = $organization->smtp_from_name ?: config('mail.from.name');
        $orgName = $organization->name;

        Mail::mailer($mailerName)->raw(
            "This is a test email to verify your SMTP settings are working correctly.\n\nOrganization: {$orgName}",
            function (Message $message) use ($recipientEmail, $fromAddress, $fromName, $orgName) {
                $message->to($recipientEmail)
                    ->subject("Test email from {$orgName}")
                    ->from($fromAddress, $fromName);
            },
        );
    }
}
