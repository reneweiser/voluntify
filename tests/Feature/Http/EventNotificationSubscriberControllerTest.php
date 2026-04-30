<?php

use App\Models\Event;
use App\Models\EventNotificationSubscriber;
use App\Models\Organization;
use App\Models\Project;
use App\ValueObjects\HashedToken;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->project = Project::factory()->for($this->org)->create();
    $this->event = Event::factory()->for($this->org)->for($this->project)->published()->create([
        'name' => 'Community Kitchen',
    ]);
});

it('verifies a subscriber from the confirmation link', function () {
    $plainToken = Str::random(64);

    $subscriber = EventNotificationSubscriber::factory()->for($this->event)->create([
        'email' => 'notify@example.com',
        'verification_token_hash' => HashedToken::fromPlaintext($plainToken)->hash,
        'verification_expires_at' => now()->addDays(7),
    ]);

    $this->get(route('events.notifications.verify', $plainToken))
        ->assertOk()
        ->assertSee('You are on the list')
        ->assertSee('notify@example.com');

    expect($subscriber->fresh()->verified_at)->not->toBeNull();
});

it('unsubscribes a subscriber from the mail link', function () {
    $plainToken = Str::random(64);

    $subscriber = EventNotificationSubscriber::factory()->for($this->event)->verified()->create([
        'unsubscribe_token_hash' => HashedToken::fromPlaintext($plainToken)->hash,
    ]);

    $this->get(route('events.notifications.unsubscribe', $plainToken))
        ->assertOk()
        ->assertSee('Notifications turned off')
        ->assertSee('Community Kitchen');

    expect(EventNotificationSubscriber::query()->find($subscriber->id))->toBeNull();
});

it('returns 404 for invalid unsubscribe links', function () {
    $this->get(route('events.notifications.unsubscribe', 'invalid-token'))
        ->assertNotFound();
});
