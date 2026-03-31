<?php

use App\Actions\CreateVolunteerManually;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Volunteer;

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->project = Project::factory()->for($this->org)->create();
    $this->action = new CreateVolunteerManually;
});

it('creates new volunteer with auto-verified email', function () {
    $volunteer = $this->action->execute($this->project, [
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'email' => 'jane@example.com',
        'phone' => '+4915112345678',
    ]);

    expect($volunteer)->toBeInstanceOf(Volunteer::class)
        ->and($volunteer->first_name)->toBe('Jane')
        ->and($volunteer->last_name)->toBe('Doe')
        ->and($volunteer->email)->toBe('jane@example.com')
        ->and($volunteer->phone)->toBe('+4915112345678')
        ->and($volunteer->project_id)->toBe($this->project->id)
        ->and($volunteer->isEmailVerified())->toBeTrue();
});

it('returns existing volunteer if email and project match already exists', function () {
    $existing = Volunteer::factory()->for($this->project)->verified()->create([
        'email' => 'existing@example.com',
        'first_name' => 'Old',
        'last_name' => 'Name',
    ]);

    $volunteer = $this->action->execute($this->project, [
        'first_name' => 'New',
        'last_name' => 'Name',
        'email' => 'existing@example.com',
    ]);

    expect($volunteer->id)->toBe($existing->id)
        ->and(Volunteer::where('email', 'existing@example.com')->count())->toBe(1);
});

it('updates name and phone on existing volunteer', function () {
    Volunteer::factory()->for($this->project)->verified()->create([
        'email' => 'update@example.com',
        'first_name' => 'Original',
        'last_name' => 'Name',
        'phone' => null,
    ]);

    $volunteer = $this->action->execute($this->project, [
        'first_name' => 'Updated',
        'last_name' => 'Last',
        'email' => 'update@example.com',
        'phone' => '+491234',
    ]);

    expect($volunteer->first_name)->toBe('Updated')
        ->and($volunteer->last_name)->toBe('Last')
        ->and($volunteer->phone)->toBe('+491234');
});

it('auto-verifies existing unverified volunteer', function () {
    $unverified = Volunteer::factory()->for($this->project)->create([
        'email' => 'unverified@example.com',
        'email_verified_at' => null,
    ]);

    $volunteer = $this->action->execute($this->project, [
        'first_name' => $unverified->first_name,
        'last_name' => $unverified->last_name,
        'email' => 'unverified@example.com',
    ]);

    expect($volunteer->id)->toBe($unverified->id)
        ->and($volunteer->isEmailVerified())->toBeTrue();
});

it('creates volunteer without phone when phone is omitted', function () {
    $volunteer = $this->action->execute($this->project, [
        'first_name' => 'No',
        'last_name' => 'Phone',
        'email' => 'nophone@example.com',
    ]);

    expect($volunteer->phone)->toBeNull()
        ->and($volunteer->isEmailVerified())->toBeTrue();
});
