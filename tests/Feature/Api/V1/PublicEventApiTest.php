<?php

use App\Models\Event;
use App\Models\Organization;
use App\Models\Shift;
use App\Models\ShiftSignup;
use App\Models\VolunteerJob;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('returns full JSON structure for a published event', function () {
    $org = Organization::factory()->create(['name' => 'Community Helpers']);
    $event = Event::factory()->published()->create([
        'organization_id' => $org->id,
        'name' => 'Summer Festival 2026',
        'title_image_path' => null,
    ]);
    $job = VolunteerJob::factory()->create([
        'event_id' => $event->id,
        'name' => 'Registration Desk',
        'description' => 'Greet and check in attendees',
        'instructions' => 'Arrive 15 minutes early',
    ]);
    $shift = Shift::factory()->create([
        'volunteer_job_id' => $job->id,
        'capacity' => 5,
    ]);

    $response = $this->getJson("/api/v1/events/{$event->public_token}");

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                'name',
                'slug',
                'description',
                'location',
                'starts_at',
                'ends_at',
                'title_image_url',
                'organization',
                'signup_url',
                'volunteer_jobs' => [
                    '*' => [
                        'name',
                        'description',
                        'instructions',
                        'shifts' => [
                            '*' => [
                                'starts_at',
                                'ends_at',
                                'capacity',
                                'spots_remaining',
                            ],
                        ],
                    ],
                ],
            ],
        ])
        ->assertJsonPath('data.name', 'Summer Festival 2026')
        ->assertJsonPath('data.organization', 'Community Helpers')
        ->assertJsonPath('data.volunteer_jobs.0.name', 'Registration Desk')
        ->assertJsonPath('data.volunteer_jobs.0.instructions', 'Arrive 15 minutes early')
        ->assertJsonPath('data.volunteer_jobs.0.shifts.0.capacity', 5);
});

it('returns 404 for a draft event', function () {
    $event = Event::factory()->create();

    $this->getJson("/api/v1/events/{$event->public_token}")
        ->assertNotFound();
});

it('returns 404 for an archived event', function () {
    $event = Event::factory()->archived()->create();

    $this->getJson("/api/v1/events/{$event->public_token}")
        ->assertNotFound();
});

it('returns 404 for a nonexistent token', function () {
    $this->getJson('/api/v1/events/nonexistent-token-abc123')
        ->assertNotFound()
        ->assertJsonStructure(['message']);
});

it('calculates spots remaining accurately', function () {
    $event = Event::factory()->published()->create();
    $job = VolunteerJob::factory()->create(['event_id' => $event->id]);
    $shift = Shift::factory()->create([
        'volunteer_job_id' => $job->id,
        'capacity' => 5,
    ]);
    ShiftSignup::factory()->count(2)->create(['shift_id' => $shift->id]);

    $response = $this->getJson("/api/v1/events/{$event->public_token}");

    $response->assertOk()
        ->assertJsonPath('data.volunteer_jobs.0.shifts.0.spots_remaining', 3);
});

it('returns null title_image_url when no image is set', function () {
    $event = Event::factory()->published()->create(['title_image_path' => null]);

    $this->getJson("/api/v1/events/{$event->public_token}")
        ->assertOk()
        ->assertJsonPath('data.title_image_url', null);
});

it('excludes internal fields from the response', function () {
    $event = Event::factory()->published()->create();
    VolunteerJob::factory()->create([
        'event_id' => $event->id,
        'name' => 'Test Job',
    ]);

    $response = $this->getJson("/api/v1/events/{$event->public_token}");

    $data = $response->json('data');
    expect($data)->not->toHaveKeys(['id', 'organization_id', 'public_token', 'status', 'created_at', 'updated_at']);

    $job = $data['volunteer_jobs'][0];
    expect($job)->not->toHaveKeys(['id', 'event_id', 'created_at', 'updated_at']);
});

it('returns 429 when rate limit is exceeded', function () {
    $event = Event::factory()->published()->create();

    for ($i = 0; $i < 60; $i++) {
        $this->getJson("/api/v1/events/{$event->public_token}");
    }

    $this->getJson("/api/v1/events/{$event->public_token}")
        ->assertStatus(429);
});

it('includes Cache-Control header in the response', function () {
    $event = Event::factory()->published()->create();

    $response = $this->getJson("/api/v1/events/{$event->public_token}");

    $response->assertOk();
    $cacheControl = $response->headers->get('Cache-Control');
    expect($cacheControl)->toContain('public')
        ->and($cacheControl)->toContain('max-age=60');
});

it('includes CORS headers when Origin is present', function () {
    $event = Event::factory()->published()->create();

    $response = $this->getJson("/api/v1/events/{$event->public_token}", [
        'Origin' => 'https://external-site.com',
    ]);

    $response->assertOk();
    expect($response->headers->has('Access-Control-Allow-Origin'))->toBeTrue();
});
