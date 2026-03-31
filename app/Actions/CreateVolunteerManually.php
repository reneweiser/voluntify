<?php

namespace App\Actions;

use App\Models\Project;
use App\Models\Volunteer;

class CreateVolunteerManually
{
    /**
     * Create a new volunteer manually (admin panel).
     * The volunteer is auto-verified (no email verification needed)
     * because the organizer is the trust anchor.
     *
     * @param  array{first_name: string, last_name: string, email: string, phone?: string|null}  $data
     */
    public function execute(Project $project, array $data): Volunteer
    {
        $volunteer = Volunteer::firstOrCreate(
            ['email' => $data['email'], 'project_id' => $project->id],
            [
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'phone' => $data['phone'] ?? null,
                'email_verified_at' => now(),
            ],
        );

        if (! $volunteer->wasRecentlyCreated) {
            $volunteer->update([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'phone' => $data['phone'] ?? $volunteer->phone,
            ]);

            if (! $volunteer->isEmailVerified()) {
                $volunteer->markEmailAsVerified();
            }
        }

        return $volunteer;
    }
}
