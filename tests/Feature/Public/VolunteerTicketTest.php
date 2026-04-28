<?php

use App\Livewire\Public\VolunteerTicket;
use App\Models\Event;
use App\Models\MagicLinkToken;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Shift;
use App\Models\ShiftSignup;
use App\Models\Ticket;
use App\Models\Volunteer;
use App\Models\VolunteerJob;
use App\ValueObjects\HashedToken;
use Livewire\Livewire;

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->project = Project::factory()->for($this->org)->create();
    $this->event = Event::factory()->for($this->org)->for($this->project)->published()->create([
        'name' => 'Summer Festival',
    ]);
    $this->volunteer = Volunteer::factory()->for($this->project)->create(['first_name' => 'Alice', 'last_name' => 'Smith']);
    $this->ticket = Ticket::factory()->for($this->volunteer)->for($this->project, 'project')->create();

    $this->plainToken = 'valid-magic-token-123';
    $this->magicLink = MagicLinkToken::factory()->create([
        'volunteer_id' => $this->volunteer->id,
        'token_hash' => HashedToken::fromPlaintext($this->plainToken)->hash,
        'expires_at' => null,
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

it('renders for a backfilled non-expiring magic link', function () {
    $this->magicLink->forceFill([
        'created_at' => now()->subYear(),
        'updated_at' => now()->subYear(),
        'expires_at' => null,
    ])->save();

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

it('does not show cancelled shift signups', function () {
    $cancelledJob = VolunteerJob::factory()
        ->for($this->event)
        ->create(['name' => 'Cancelled Duty']);
    $cancelledShift = Shift::factory()
        ->for($cancelledJob, 'volunteerJob')
        ->create();
    ShiftSignup::factory()->create([
        'volunteer_id' => $this->volunteer->id,
        'shift_id' => $cancelledShift->id,
        'cancelled_at' => now(),
    ]);

    Livewire::test(VolunteerTicket::class, ['magicToken' => $this->plainToken])
        ->assertSee('Gate Security')
        ->assertDontSee('Cancelled Duty');
});

it('renders without errors when all signups are cancelled', function () {
    ShiftSignup::query()->update(['cancelled_at' => now()]);

    Livewire::test(VolunteerTicket::class, ['magicToken' => $this->plainToken])
        ->assertOk()
        ->assertDontSee('Gate Security');
});

it('returns 404 for nonexistent token', function () {
    $this->get(route('volunteer.ticket', 'nonexistent-token'))
        ->assertNotFound();
});
