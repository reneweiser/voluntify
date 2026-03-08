<?php

namespace App\Console\Commands;

use App\Actions\CreateOrganization;
use App\Models\User;
use Illuminate\Console\Command;

class BackfillPersonalOrganizations extends Command
{
    protected $signature = 'app:backfill-personal-organizations';

    protected $description = 'Create personal organizations for existing users who have none';

    public function handle(CreateOrganization $action): int
    {
        $users = User::doesntHave('organizations')->get();

        if ($users->isEmpty()) {
            $this->info('All users already have organizations.');

            return self::SUCCESS;
        }

        $this->info("Found {$users->count()} user(s) without organizations.");

        $users->each(function (User $user) use ($action) {
            $action->execute($user, $user->name."'s Organization", isPersonal: true);
            $this->line("  Created organization for {$user->email}");
        });

        $this->info('Done.');

        return self::SUCCESS;
    }
}
