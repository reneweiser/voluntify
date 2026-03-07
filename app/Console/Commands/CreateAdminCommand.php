<?php

namespace App\Console\Commands;

use App\Actions\CreateAdminWithOrganization;
use App\Exceptions\DomainException;
use Illuminate\Console\Command;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

class CreateAdminCommand extends Command
{
    protected $signature = 'app:create-admin
        {--name= : The admin user\'s name}
        {--email= : The admin user\'s email}
        {--password= : The admin user\'s password}
        {--organization= : The organization name}';

    protected $description = 'Create an admin user and attach to an organization as Organizer';

    public function handle(CreateAdminWithOrganization $action): int
    {
        $name = $this->option('name') ?: text('Name', required: true);
        $email = $this->option('email') ?: text('Email', required: true);
        $pw = $this->option('password') ?: password('Password', required: true);
        $orgName = $this->option('organization') ?: text('Organization name', required: true);

        try {
            $user = $action->execute($name, $email, $pw, $orgName);
        } catch (DomainException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Admin user [{$user->email}] created and attached to organization [{$orgName}] as Organizer.");

        return self::SUCCESS;
    }
}
