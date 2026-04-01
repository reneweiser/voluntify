<?php

use App\Enums\HintLocation;
use App\Models\HintText;
use App\Models\Project;
use App\Services\HintTextResolver;

beforeEach(function () {
    $this->project = Project::factory()->create();
    $this->resolver = new HintTextResolver;
});

it('returns text for an enabled project hint', function () {
    HintText::factory()->for($this->project)->create([
        'location' => HintLocation::SignupEmail,
        'text' => 'Please use your personal email.',
        'enabled' => true,
    ]);

    $result = $this->resolver->resolve(HintLocation::SignupEmail, $this->project);

    expect($result)->toBe('Please use your personal email.');
});

it('returns null for a disabled hint', function () {
    HintText::factory()->for($this->project)->create([
        'location' => HintLocation::SignupEmail,
        'text' => 'Some hint',
        'enabled' => false,
    ]);

    $result = $this->resolver->resolve(HintLocation::SignupEmail, $this->project);

    expect($result)->toBeNull();
});

it('returns null when no hint exists for location', function () {
    $result = $this->resolver->resolve(HintLocation::PortalTopBanner, $this->project);

    expect($result)->toBeNull();
});

it('only returns hints for the correct project', function () {
    $otherProject = Project::factory()->create();

    HintText::factory()->for($otherProject)->create([
        'location' => HintLocation::SignupEmail,
        'text' => 'Other project hint',
        'enabled' => true,
    ]);

    $result = $this->resolver->resolve(HintLocation::SignupEmail, $this->project);

    expect($result)->toBeNull();
});
