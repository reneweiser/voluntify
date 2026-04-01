<?php

use App\Jobs\ConfirmGuestListJob;
use App\Jobs\SendGuestInvitationsJob;
use App\Models\GuestEntry;
use App\Models\GuestGroup;
use App\Models\GuestList;
use Illuminate\Support\Facades\Queue;

it('generates qr_token for all entries without tokens', function () {
    $guestList = GuestList::factory()->confirmed()->create();
    $group = GuestGroup::factory()->for($guestList)->create(['guest_count' => 3]);
    GuestEntry::factory()->for($group, 'group')->create(['number' => 1, 'qr_token' => null]);
    GuestEntry::factory()->for($group, 'group')->create(['number' => 2, 'qr_token' => null]);
    GuestEntry::factory()->for($group, 'group')->create(['number' => 3, 'qr_token' => null]);

    Queue::fake([SendGuestInvitationsJob::class]);

    $job = new ConfirmGuestListJob($guestList);
    $job->handle();

    $entries = GuestEntry::whereNotNull('qr_token')->get();
    expect($entries)->toHaveCount(3);

    $tokens = $entries->pluck('qr_token')->unique();
    expect($tokens)->toHaveCount(3);
    $tokens->each(fn ($token) => expect(strlen($token))->toBe(64));
});

it('dispatches one SendGuestInvitationsJob per unique email', function () {
    Queue::fake([SendGuestInvitationsJob::class]);

    $guestList = GuestList::factory()->confirmed()->create();
    $group = GuestGroup::factory()->for($guestList)->create(['guest_count' => 3]);
    GuestEntry::factory()->for($group, 'group')->create(['number' => 1, 'email' => 'dj@example.com']);
    GuestEntry::factory()->for($group, 'group')->create(['number' => 2, 'email' => 'dj@example.com']);
    GuestEntry::factory()->for($group, 'group')->create(['number' => 3, 'email' => null]);

    $job = new ConfirmGuestListJob($guestList);
    $job->handle();

    Queue::assertPushed(SendGuestInvitationsJob::class, 1);
    Queue::assertPushed(SendGuestInvitationsJob::class, function ($job) {
        return $job->email === 'dj@example.com';
    });
});

it('is idempotent and skips entries that already have tokens', function () {
    Queue::fake([SendGuestInvitationsJob::class]);

    $guestList = GuestList::factory()->confirmed()->create();
    $group = GuestGroup::factory()->for($guestList)->create(['guest_count' => 2]);
    $existing = GuestEntry::factory()->for($group, 'group')->withQrToken()->create(['number' => 1, 'email' => 'a@example.com']);
    GuestEntry::factory()->for($group, 'group')->create(['number' => 2, 'email' => 'b@example.com']);

    $originalToken = $existing->qr_token;

    $job = new ConfirmGuestListJob($guestList);
    $job->handle();

    expect($existing->fresh()->qr_token)->toBe($originalToken);
    expect(GuestEntry::whereNotNull('qr_token')->count())->toBe(2);
});

it('skips if guest list is not confirmed', function () {
    Queue::fake([SendGuestInvitationsJob::class]);

    $guestList = GuestList::factory()->create();
    $group = GuestGroup::factory()->for($guestList)->create();
    GuestEntry::factory()->for($group, 'group')->create(['number' => 1]);

    $job = new ConfirmGuestListJob($guestList);
    $job->handle();

    expect(GuestEntry::whereNotNull('qr_token')->count())->toBe(0);
    Queue::assertNothingPushed();
});
