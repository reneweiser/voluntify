<?php

namespace App\Console\Commands;

use App\Actions\CreateOrganization;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

class SetupCommand extends Command
{
    protected $signature = 'app:setup
        {--name= : The user\'s name}
        {--email= : The user\'s email}
        {--password= : The user\'s password}
        {--org= : The organization name}';

    protected $description = 'Create the first user and organization for a fresh instance';

    public function handle(CreateOrganization $createOrganization): int
    {
        if (User::exists()) {
            $this->error('Users already exist. Use app:invite-organizer to add more users.');

            return self::FAILURE;
        }

        $name = $this->option('name') ?: text('Your name', required: true);
        $email = $this->option('email') ?: text('Your email', required: true);
        $pass = $this->option('password') ?: password('Password (min 8 characters)', required: true);
        $orgName = $this->option('org') ?: text('Organization name', required: true);

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error("Invalid email address: {$email}");

            return self::FAILURE;
        }

        if (strlen($pass) < 8) {
            $this->error('Password must be at least 8 characters.');

            return self::FAILURE;
        }

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($pass),
            'email_verified_at' => now(),
        ]);

        $organization = $createOrganization->execute($user, $orgName, isPersonal: true);

        $this->info("User [{$email}] created as Organizer of [{$organization->name}].");
        $this->info('You can now log in at /login.');

        return self::SUCCESS;
    }
}
