<?php

use App\Livewire\Public\EventSignup;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Shift;
use App\Models\VolunteerJob;
use Livewire\Livewire;

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->event = Event::factory()->for($this->org)->published()->create();
    $this->jobWithInstructions = VolunteerJob::factory()->for($this->event)->create([
        'name' => 'Stage Crew',
        'instructions' => 'Wear steel-toe boots. Report to loading dock at 7am.',
    ]);
    $this->jobWithoutInstructions = VolunteerJob::factory()->for($this->event)->create([
        'name' => 'Greeter',
        'instructions' => null,
    ]);
});

it('renders cheat sheet page for job with instructions', function () {
    $this->get(route('events.jobs.cheat-sheet', [
        'publicToken' => $this->event->public_token,
        'jobId' => $this->jobWithInstructions->id,
    ]))
        ->assertOk()
        ->assertSee('Stage Crew')
        ->assertSee('Wear steel-toe boots. Report to loading dock at 7am.');
});

it('returns 404 when job has no instructions', function () {
    $this->get(route('events.jobs.cheat-sheet', [
        'publicToken' => $this->event->public_token,
        'jobId' => $this->jobWithoutInstructions->id,
    ]))
        ->assertNotFound();
});

it('returns 404 for invalid public token', function () {
    $this->get(route('events.jobs.cheat-sheet', [
        'publicToken' => 'invalid-token',
        'jobId' => $this->jobWithInstructions->id,
    ]))
        ->assertNotFound();
});

it('returns 404 when job does not belong to event', function () {
    $otherEvent = Event::factory()->for($this->org)->published()->create();

    $this->get(route('events.jobs.cheat-sheet', [
        'publicToken' => $otherEvent->public_token,
        'jobId' => $this->jobWithInstructions->id,
    ]))
        ->assertNotFound();
});

it('shows instruction link on public event page for jobs with instructions', function () {
    Shift::factory()->for($this->jobWithInstructions, 'volunteerJob')->create();
    Shift::factory()->for($this->jobWithoutInstructions, 'volunteerJob')->create();

    $cheatSheetUrl = route('events.jobs.cheat-sheet', [
        'publicToken' => $this->event->public_token,
        'jobId' => $this->jobWithInstructions->id,
    ]);

    $noInstructionsUrl = route('events.jobs.cheat-sheet', [
        'publicToken' => $this->event->public_token,
        'jobId' => $this->jobWithoutInstructions->id,
    ]);

    Livewire::test(EventSignup::class, ['publicToken' => $this->event->public_token])
        ->assertSeeHtml($cheatSheetUrl)
        ->assertDontSeeHtml($noInstructionsUrl);
});

it('returns 404 for draft event', function () {
    $draftEvent = Event::factory()->for($this->org)->create();
    $job = VolunteerJob::factory()->for($draftEvent)->create([
        'instructions' => 'Some instructions',
    ]);

    $this->get(route('events.jobs.cheat-sheet', [
        'publicToken' => $draftEvent->public_token,
        'jobId' => $job->id,
    ]))
        ->assertNotFound();
});

it('returns 404 when instructions are empty string', function () {
    $job = VolunteerJob::factory()->for($this->event)->create([
        'instructions' => '',
    ]);

    $this->get(route('events.jobs.cheat-sheet', [
        'publicToken' => $this->event->public_token,
        'jobId' => $job->id,
    ]))
        ->assertNotFound();
});
