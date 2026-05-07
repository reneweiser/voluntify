<?php

use App\Models\GuestEntry;
use App\Models\GuestEntryGear;
use App\Models\GuestGroup;
use App\Models\GuestList;
use App\Models\Project;
use App\Models\ProjectScanner;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;

use function Pest\Laravel\travelTo;

it('creates a guest entry with correct attributes', function () {
    $group = GuestGroup::factory()->create(['label' => 'DJ Soundwave', 'guest_count' => 3]);
    $entry = GuestEntry::factory()->create([
        'guest_group_id' => $group->id,
        'number' => 1,
        'name' => 'DJ Soundwave',
        'email' => 'dj@example.com',
    ]);

    expect($entry->exists)->toBeTrue()
        ->and($entry->number)->toBe(1)
        ->and($entry->name)->toBe('DJ Soundwave')
        ->and($entry->email)->toBe('dj@example.com');
});

it('belongs to a group', function () {
    $group = GuestGroup::factory()->create();
    $entry = GuestEntry::factory()->for($group, 'group')->create();

    expect($entry->group->id)->toBe($group->id);
});

it('has many gear items', function () {
    $entry = GuestEntry::factory()->create();
    GuestEntryGear::factory()->count(2)->create(['guest_entry_id' => $entry->id]);

    expect($entry->gear)->toHaveCount(2);
});

it('tracks checked-in-by user', function () {
    $user = User::factory()->create();
    $entry = GuestEntry::factory()->checkedIn()->create(['checked_in_by' => $user->id]);

    expect($entry->checkedInByUser->id)->toBe($user->id);
});

it('reports check-in status', function () {
    $notCheckedIn = GuestEntry::factory()->create();
    $checkedIn = GuestEntry::factory()->checkedIn()->create();

    expect($notCheckedIn->isCheckedIn())->toBeFalse()
        ->and($checkedIn->isCheckedIn())->toBeTrue();
});

it('generates display label in GroupLabel N/Total format', function () {
    $group = GuestGroup::factory()->create(['label' => 'DJ Soundwave', 'guest_count' => 3]);
    $entry = GuestEntry::factory()->for($group, 'group')->create(['number' => 2]);

    expect($entry->displayLabel())->toBe('DJ Soundwave 2/3');
});

it('cascades delete from group', function () {
    $group = GuestGroup::factory()->create();
    GuestEntry::factory()->for($group, 'group')->create();

    expect(GuestEntry::count())->toBe(1);

    $group->delete();

    expect(GuestEntry::count())->toBe(0);
});

it('nullifies checked_in_by when user is deleted', function () {
    $user = User::factory()->create();
    $entry = GuestEntry::factory()->checkedIn()->create(['checked_in_by' => $user->id]);

    $user->delete();

    expect($entry->fresh()->checked_in_by)->toBeNull();
});

it('generates a signed guest pass URL using scanner end time plus buffer', function () {
    travelTo(Carbon::parse('2026-04-30 12:00:00'));

    $project = Project::factory()->create();
    $scanner = ProjectScanner::factory()->for($project)->create([
        'ends_at' => Carbon::parse('2026-05-02 01:00:00'),
    ]);
    $guestList = GuestList::factory()
        ->for($project)
        ->for($scanner, 'scanner')
        ->confirmed()
        ->create();
    $group = GuestGroup::factory()->for($guestList)->create(['guest_count' => 1, 'label' => 'Artist']);
    $entry = GuestEntry::factory()->for($group, 'group')->withQrToken()->create(['number' => 1]);

    $url = $entry->guestPassUrl();

    parse_str(parse_url($url, PHP_URL_QUERY) ?? '', $query);

    expect(URL::hasValidSignature(Request::create($url)))->toBeTrue()
        ->and($query['expires'] ?? null)->toBe((string) $scanner->ends_at->addHours(12)->timestamp)
        ->and($url)->toContain('/guest-pass/'.$entry->id);
});

it('falls back to a seven-day guest pass expiry when scanner end time is unavailable', function () {
    travelTo(Carbon::parse('2026-04-30 12:00:00'));

    $project = Project::factory()->create();
    $scanner = ProjectScanner::factory()->for($project)->create();
    $guestList = GuestList::factory()
        ->for($project)
        ->for($scanner, 'scanner')
        ->confirmed()
        ->create();
    $group = GuestGroup::factory()->for($guestList)->create(['guest_count' => 1, 'label' => 'Artist']);
    $entry = GuestEntry::factory()->for($group, 'group')->withQrToken()->create(['number' => 1]);

    $entry->load('group.guestList.scanner');
    $entry->group->guestList->scanner->forceFill(['ends_at' => null]);

    $url = $entry->guestPassUrl();

    parse_str(parse_url($url, PHP_URL_QUERY) ?? '', $query);

    expect(URL::hasValidSignature(Request::create($url)))->toBeTrue()
        ->and($query['expires'] ?? null)->toBe((string) now()->addDays(7)->timestamp);
});

it('treats legacy invitation_sent_at rows as sent', function () {
    $entry = GuestEntry::factory()->create([
        'email' => 'legacy@example.com',
        'qr_token' => bin2hex(random_bytes(32)),
        'invitation_sent_at' => now()->subMinute(),
        'invitation_queued_at' => null,
        'invitation_failed_at' => null,
    ]);

    expect($entry->isInvitationSent())->toBeTrue()
        ->and($entry->invitationStatus())->toBe('sent')
        ->and($entry->isInvitationPending())->toBeFalse();
});

it('reports queued, failed, and pending invitation states truthfully', function () {
    $queued = GuestEntry::factory()->create([
        'email' => 'queued@example.com',
        'qr_token' => bin2hex(random_bytes(32)),
        'invitation_queued_at' => now()->subMinute(),
    ]);
    $failed = GuestEntry::factory()->create([
        'email' => 'failed@example.com',
        'qr_token' => bin2hex(random_bytes(32)),
        'invitation_failed_at' => now()->subMinute(),
    ]);
    $pending = GuestEntry::factory()->create([
        'email' => 'pending@example.com',
        'qr_token' => bin2hex(random_bytes(32)),
    ]);

    expect($queued->invitationStatus())->toBe('queued')
        ->and($queued->isInvitationQueued())->toBeTrue()
        ->and($failed->invitationStatus())->toBe('failed')
        ->and($failed->isInvitationFailed())->toBeTrue()
        ->and($pending->invitationStatus())->toBe('pending')
        ->and($pending->isInvitationPending())->toBeTrue();
});
