<?php

namespace App\Actions;

use App\Exceptions\DomainException;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class RequestProjectDeletion
{
    public function execute(Project $project, string $password, User $causer): Project
    {
        if (! Hash::check($password, $causer->password)) {
            throw new DomainException('Falsches Passwort.');
        }

        if ($project->isPendingDeletion()) {
            throw new DomainException('Projekt ist bereits zur Löschung vorgemerkt.');
        }

        $project->update(['deletion_requested_at' => now()]);

        return $project->refresh();
    }
}
