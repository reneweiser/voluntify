<?php

namespace App\Services;

use App\Enums\SmtpEncryption;
use App\Models\Organization;

class OrganizationMailerService
{
    public function resolveMailerName(Organization $organization): string
    {
        if (! $organization->smtp_host) {
            return config('mail.default');
        }

        $name = "org-{$organization->id}";
        config()->set("mail.mailers.{$name}", $this->buildMailerConfig($organization));

        return $name;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildMailerConfig(Organization $organization): array
    {
        return [
            'transport' => 'smtp',
            'host' => $organization->smtp_host,
            'port' => $organization->smtp_port,
            'username' => $organization->smtp_username,
            'password' => $organization->smtp_password,
            'encryption' => $organization->smtp_encryption === SmtpEncryption::None
                ? null
                : $organization->smtp_encryption->value,
        ];
    }
}
