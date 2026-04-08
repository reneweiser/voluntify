<?php

use App\Livewire\Public\ProjectWebsite;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Volunteer;
use App\Notifications\PortalAccessLink;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->project = Project::factory()->for($this->org)->create([
        'website_published' => true,
    ]);
});

it('shows access link form on project website', function () {
    Livewire::test(ProjectWebsite::class, ['publicToken' => $this->project->public_token])
        ->assertSee('Zugang erhalten')
        ->assertSeeHtml('wire:model="requestEmail"');
});

it('shows neutral success message for known email', function () {
    Notification::fake();

    Volunteer::factory()->for($this->project)->create([
        'email' => 'known@example.com',
    ]);

    Livewire::test(ProjectWebsite::class, ['publicToken' => $this->project->public_token])
        ->set('requestEmail', 'known@example.com')
        ->call('requestAccessLink')
        ->assertSee('Falls ein Konto mit dieser E-Mail existiert, wurde ein Zugangslink versendet.');

    Notification::assertSentTo(
        Volunteer::where('email', 'known@example.com')->first(),
        PortalAccessLink::class,
    );
});

it('shows same neutral message for unknown email', function () {
    Notification::fake();

    Livewire::test(ProjectWebsite::class, ['publicToken' => $this->project->public_token])
        ->set('requestEmail', 'unknown@example.com')
        ->call('requestAccessLink')
        ->assertSee('Falls ein Konto mit dieser E-Mail existiert, wurde ein Zugangslink versendet.');

    Notification::assertNothingSent();
});

it('validates email format', function () {
    Livewire::test(ProjectWebsite::class, ['publicToken' => $this->project->public_token])
        ->set('requestEmail', 'not-an-email')
        ->call('requestAccessLink')
        ->assertHasErrors(['requestEmail' => 'email']);
});

it('rate limits requests per email', function () {
    Notification::fake();

    Volunteer::factory()->for($this->project)->create([
        'email' => 'ratelimit@example.com',
    ]);

    $component = Livewire::test(ProjectWebsite::class, ['publicToken' => $this->project->public_token]);

    // First 3 requests should succeed
    for ($i = 0; $i < 3; $i++) {
        $component
            ->set('requestEmail', 'ratelimit@example.com')
            ->call('requestAccessLink')
            ->assertHasNoErrors();
    }

    // 4th request should be rate limited
    $component
        ->set('requestEmail', 'ratelimit@example.com')
        ->call('requestAccessLink')
        ->assertSee('Zu viele Anfragen');
});
