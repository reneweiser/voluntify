<?php

namespace App\Actions;

use App\Models\Project;
use App\Models\Volunteer;
use App\Notifications\PortalAccessLink;

class RequestPortalAccessLink
{
    public function __construct(private GenerateMagicLink $generateMagicLink) {}

    public function execute(string $email, Project $project): void
    {
        $volunteer = Volunteer::where('email', $email)
            ->where('project_id', $project->id)
            ->first();

        if (! $volunteer) {
            return;
        }

        $result = $this->generateMagicLink->execute($volunteer);
        $volunteer->notify(new PortalAccessLink($project, $result['plainToken']));
    }
}
