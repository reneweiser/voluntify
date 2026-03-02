<?php

use App\Livewire\Public\VolunteerTicket;
use App\Models\Event;
use App\Models\MagicLinkToken;
use App\Models\Organization;
use App\Models\Shift;
use App\Models\ShiftSignup;
use App\Models\Ticket;
use App\Models\Volunteer;
use App\Models\VolunteerJob;
use App\ValueObjects\HashedToken;
use Livewire\Livewire;

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->event = Event::factory()->for($this->org)->published()->create([
        'name' => 'Summer Festival',
    ]);
    $this->volunteer = Volunteer::factory()->create(['name' => 'Alice Smith']);
    $this->ticket = Ticket::factory()->for($this->volunteer)->for($this->event)->create();

    $this->plainToken = 'valid-magic-token-123';
    $this->magicLink = MagicLinkToken::factory()->create([
        'volunteer_id' => $this->volunteer->id,
        'token_hash' => HashedToken::fromPlaintext($this->plainToken)->hash,
        'expires_at' => now()->addHours(24),
    ]);

    $this->job = VolunteerJob::factory()->for($this->event)->create(['name' => 'Gate Security']);
    $this->shift = Shift::factory()->for($this->job, 'volunteerJob')->create();
    ShiftSignup::factory()->create([
        'volunteer_id' => $this->volunteer->id,
        'shift_id' => $this->shift->id,
    ]);
});

it('renders for valid magic link', function () {
    $this->get(route('volunteer.ticket', $this->plainToken))
        ->assertOk()
        ->assertSeeLivewire(VolunteerTicket::class);
});

it('shows volunteer name and event name', function () {
    Livewire::test(VolunteerTicket::class, ['magicToken' => $this->plainToken])
        ->assertSee('Alice Smith')
        ->assertSee('Summer Festival');
});

it('shows QR SVG', function () {
    Livewire::test(VolunteerTicket::class, ['magicToken' => $this->plainToken])
        ->assertSee('<svg', escape: false);
});

it('shows shift assignments', function () {
    Livewire::test(VolunteerTicket::class, ['magicToken' => $this->plainToken])
        ->assertSee('Gate Security');
});

// B3: Error States

it('shows expired message for expired magic link', function () {
    $this->magicLink->update(['expires_at' => now()->subMinute()]);

    Livewire::test(VolunteerTicket::class, ['magicToken' => $this->plainToken])
        ->assertSee('expired');
});

it('returns 404 for nonexistent token', function () {
    $this->get(route('volunteer.ticket', 'nonexistent-token'))
        ->assertNotFound();
});
