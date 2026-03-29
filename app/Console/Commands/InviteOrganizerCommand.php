<?php

namespace App\Console\Commands;

use App\Actions\InviteMember;
use App\Enums\StaffRole;
use App\Exceptions\DomainException;
use App\Models\Organization;
use Illuminate\Console\Command;

use function Laravel\Prompts\text;

class InviteOrganizerCommand extends Command
{
    protected $signature = 'app:invite-organizer
        {--name= : The user\'s name}
        {--email= : The user\'s email}
        {--organization= : The organization slug}';

    protected $description = 'Invite a user to an organization as Organizer';

    public function handle(InviteMember $action): int
    {
        $name = $this->option('name') ?: text('Name', required: true);
        $email = $this->option('email') ?: text('Email', required: true);
        $slug = $this->option('organization') ?: text('Organization slug', required: true);

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error("Invalid email address: {$email}");

            return self::FAILURE;
        }

        $organization = Organization::where('slug', $slug)->first();

        if (! $organization) {
            $this->error("Organization not found with slug: {$slug}");

            return self::FAILURE;
        }

        try {
            $action->execute($organization, $name, $email, StaffRole::Organizer);
        } catch (DomainException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("User [{$email}] invited to [{$organization->name}] as Organizer.");

        return self::SUCCESS;
    }
}
