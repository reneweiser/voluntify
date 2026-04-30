<?php

use App\Models\GuestEntry;
use App\Models\GuestGroup;
use App\Models\GuestList;
use App\Models\Project;
use App\Models\ProjectScanner;
use Illuminate\Support\Facades\URL;

beforeEach(function () {
    $this->project = Project::factory()->create(['name' => 'Summer Festival']);
    $this->scanner = ProjectScanner::factory()->for($this->project)->create([
        'ends_at' => now()->addHours(4),
    ]);
    $this->guestList = GuestList::factory()
        ->for($this->project)
        ->for($this->scanner, 'scanner')
        ->confirmed()
        ->create([
            'name' => 'Artist Guest List',
        ]);
    $this->group = GuestGroup::factory()->for($this->guestList)->create([
        'label' => 'DJ Soundwave',
        'guest_count' => 1,
    ]);
});

it('renders the guest pass for a valid signed link', function () {
    $entry = GuestEntry::factory()->for($this->group, 'group')->withQrToken()->create([
        'number' => 1,
        'name' => 'DJ Soundwave',
    ]);

    $this->get($entry->guestPassUrl())
        ->assertOk()
        ->assertSee('Summer Festival')
        ->assertSee('Artist Guest List')
        ->assertSee('DJ Soundwave 1/1')
        ->assertSee('<svg', escape: false)
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
});

it('shows a guest-friendly message for an invalid signature', function () {
    $entry = GuestEntry::factory()->for($this->group, 'group')->withQrToken()->create();

    $this->get($entry->guestPassUrl().'&tampered=1')
        ->assertForbidden()
        ->assertSee('This guest pass link is invalid or has expired.');
});

it('shows a guest-friendly message for an expired signature', function () {
    $entry = GuestEntry::factory()->for($this->group, 'group')->withQrToken()->create();
    $url = URL::temporarySignedRoute('guest.pass.show', now()->subMinute(), ['entry' => $entry->id]);

    $this->get($url)
        ->assertForbidden()
        ->assertSee('This guest pass link is invalid or has expired.');
});

it('returns 404 when the guest entry has no qr token', function () {
    $entry = GuestEntry::factory()->for($this->group, 'group')->create([
        'qr_token' => null,
    ]);
    $url = URL::temporarySignedRoute('guest.pass.show', now()->addMinutes(5), ['entry' => $entry->id]);

    $this->get($url)->assertNotFound();
});

it('returns 404 when the guest list is still a draft', function () {
    $project = Project::factory()->create();
    $scanner = ProjectScanner::factory()->for($project)->create();
    $draftGuestList = GuestList::factory()
        ->for($project)
        ->for($scanner, 'scanner')
        ->create();
    $group = GuestGroup::factory()->for($draftGuestList)->create([
        'guest_count' => 1,
    ]);
    $entry = GuestEntry::factory()->for($group, 'group')->withQrToken()->create();
    $url = URL::temporarySignedRoute('guest.pass.show', now()->addMinutes(5), ['entry' => $entry->id]);

    $this->get($url)->assertNotFound();
});
