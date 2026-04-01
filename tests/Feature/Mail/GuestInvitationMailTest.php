<?php

use App\Mail\GuestInvitationMail;
use App\Models\GuestEntry;
use App\Models\GuestGroup;
use App\Models\GuestList;
use App\Models\Project;
use Illuminate\Database\Eloquent\Collection;

beforeEach(function () {
    $this->project = Project::factory()->create(['name' => 'Summer Festival']);
    $this->guestList = GuestList::factory()->confirmed()->create([
        'project_id' => $this->project->id,
        'name' => 'Artist Guest List',
    ]);
    $this->group = GuestGroup::factory()->for($this->guestList)->create([
        'label' => 'DJ Soundwave',
        'guest_count' => 2,
    ]);
});

it('has correct subject line', function () {
    $entry = GuestEntry::factory()->for($this->group, 'group')->withQrToken()->create([
        'number' => 1,
        'name' => 'DJ Soundwave',
        'email' => 'dj@example.com',
    ]);

    $mail = new GuestInvitationMail($this->guestList, new Collection([$entry]));

    expect($mail->envelope()->subject)->toBe('Your Guest Pass — Artist Guest List');
});

it('renders markdown with project name and entry labels', function () {
    $entry1 = GuestEntry::factory()->for($this->group, 'group')->withQrToken()->create([
        'number' => 1,
        'name' => 'DJ Soundwave',
        'email' => 'dj@example.com',
    ]);
    $entry2 = GuestEntry::factory()->for($this->group, 'group')->withQrToken()->create([
        'number' => 2,
        'email' => 'dj@example.com',
    ]);

    $entries = new Collection([$entry1, $entry2]);
    $mail = new GuestInvitationMail($this->guestList, $entries);

    $mail->assertSeeInHtml('Summer Festival')
        ->assertSeeInHtml('DJ Soundwave 1/2')
        ->assertSeeInHtml('DJ Soundwave 2/2')
        ->assertSeeInHtml('present your QR code');
});

it('renders entry name when present', function () {
    $entry = GuestEntry::factory()->for($this->group, 'group')->withQrToken()->create([
        'number' => 1,
        'name' => 'John Doe',
        'email' => 'john@example.com',
    ]);

    $mail = new GuestInvitationMail($this->guestList, new Collection([$entry]));

    $mail->assertSeeInHtml('John Doe');
});

it('is sendable with valid data', function () {
    $entry = GuestEntry::factory()->for($this->group, 'group')->withQrToken()->create([
        'number' => 1,
        'email' => 'test@example.com',
    ]);

    $mail = new GuestInvitationMail($this->guestList, new Collection([$entry]));

    expect($mail)->not->toBeNull();
    $mail->assertHasSubject('Your Guest Pass — Artist Guest List');
});
