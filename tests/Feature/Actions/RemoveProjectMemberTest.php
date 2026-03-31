<?php

use App\Actions\RemoveProjectMember;
use App\Enums\StaffRole;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->project = Project::factory()->for($this->org)->create();
    $this->action = app(RemoveProjectMember::class);
});

it('removes a user from a project', function () {
    $user = User::factory()->create();
    $this->project->users()->attach($user, ['role' => StaffRole::Organizer]);

    $this->action->execute($this->project, $user);

    expect($this->project->users()->where('user_id', $user->id)->exists())->toBeFalse();
});

it('does nothing when user is not a member', function () {
    $user = User::factory()->create();

    // Should not throw
    $this->action->execute($this->project, $user);

    expect($this->project->users()->count())->toBe(0);
});
