<?php

use App\Jobs\SendGuestInvitationsJob;
use App\Mail\GuestInvitationMail;
use App\Models\GuestEntry;
use App\Models\GuestGroup;
use App\Models\GuestList;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

it('sends grouped email with all QR codes for matching entries', function () {
    Mail::fake();

    $guestList = GuestList::factory()->confirmed()->create();
    $group = GuestGroup::factory()->for($guestList)->create(['guest_count' => 3]);
    GuestEntry::factory()->for($group, 'group')->withQrToken()->create(['number' => 1, 'email' => 'dj@example.com', 'invitation_queued_at' => now()->subMinute()]);
    GuestEntry::factory()->for($group, 'group')->withQrToken()->create(['number' => 2, 'email' => 'dj@example.com', 'invitation_queued_at' => now()->subMinute()]);
    GuestEntry::factory()->for($group, 'group')->withQrToken()->create(['number' => 3, 'email' => null]);

    $claimedEntries = GuestEntry::where('email', 'dj@example.com')->pluck('id')->all();

    $job = new SendGuestInvitationsJob($guestList, 'dj@example.com', $claimedEntries);
    $job->handle();

    Mail::assertSent(GuestInvitationMail::class, function (GuestInvitationMail $mail) {
        $mail->assertSeeInHtml(__('If the QR code is not visible in your email app, open this pass in your browser.'))
            ->assertSeeInHtml(__('Open Guest Pass'));

        return $mail->hasTo('dj@example.com')
            && $mail->entries->count() === 2;
    });

    GuestEntry::where('email', 'dj@example.com')->each(function (GuestEntry $entry) {
        expect($entry->fresh()->invitation_sent_at)->not->toBeNull()
            ->and($entry->fresh()->invitation_queued_at)->toBeNull()
            ->and($entry->fresh()->invitation_failed_at)->toBeNull();
    });
});

it('skips when no entries match the email', function () {
    Mail::fake();

    $guestList = GuestList::factory()->confirmed()->create();

    $job = new SendGuestInvitationsJob($guestList, 'nobody@example.com', []);
    $job->handle();

    Mail::assertNothingSent();
});

it('marks the full recipient sibling set as failed after retries are exhausted', function () {
    Carbon::setTestNow(now());

    $guestList = GuestList::factory()->confirmed()->create();
    $group = GuestGroup::factory()->for($guestList)->create(['guest_count' => 2]);

    GuestEntry::factory()->for($group, 'group')->withQrToken()->create([
        'number' => 1,
        'email' => 'failed@example.com',
        'invitation_queued_at' => now()->subMinute(),
    ]);
    GuestEntry::factory()->for($group, 'group')->withQrToken()->create([
        'number' => 2,
        'email' => 'failed@example.com',
        'invitation_queued_at' => now()->subMinute(),
    ]);

    $job = new SendGuestInvitationsJob($guestList, 'failed@example.com', GuestEntry::where('email', 'failed@example.com')->pluck('id')->all());
    $job->failed(new RuntimeException('SMTP down'));

    GuestEntry::where('email', 'failed@example.com')->each(function (GuestEntry $entry) {
        expect($entry->fresh()->invitation_failed_at)->not->toBeNull()
            ->and($entry->fresh()->invitation_queued_at)->toBeNull()
            ->and($entry->fresh()->invitation_sent_at)->toBeNull();
    });
});

it('skips entries without qr_token', function () {
    Mail::fake();

    $guestList = GuestList::factory()->confirmed()->create();
    $group = GuestGroup::factory()->for($guestList)->create(['guest_count' => 1]);
    GuestEntry::factory()->for($group, 'group')->create(['number' => 1, 'email' => 'test@example.com', 'qr_token' => null, 'invitation_queued_at' => now()->subMinute()]);

    $job = new SendGuestInvitationsJob($guestList, 'test@example.com', GuestEntry::where('email', 'test@example.com')->pluck('id')->all());
    $job->handle();

    Mail::assertNothingSent();
});

it('only updates the exact claimed rows when another same-email row was never eligible', function () {
    Mail::fake();

    $guestList = GuestList::factory()->confirmed()->create();
    $group = GuestGroup::factory()->for($guestList)->create(['guest_count' => 2]);
    $claimableEntry = GuestEntry::factory()->for($group, 'group')->withQrToken()->create([
        'number' => 1,
        'email' => 'mixed@example.com',
        'invitation_queued_at' => now()->subMinute(),
    ]);
    $notEligibleEntry = GuestEntry::factory()->for($group, 'group')->create([
        'number' => 2,
        'email' => 'mixed@example.com',
        'qr_token' => null,
    ]);

    $job = new SendGuestInvitationsJob($guestList, 'mixed@example.com', [$claimableEntry->id]);
    $job->handle();

    expect($claimableEntry->fresh()->invitation_sent_at)->not->toBeNull()
        ->and($claimableEntry->fresh()->invitation_queued_at)->toBeNull()
        ->and($notEligibleEntry->fresh()->invitation_sent_at)->toBeNull()
        ->and($notEligibleEntry->fresh()->invitation_failed_at)->toBeNull()
        ->and($notEligibleEntry->fresh()->invitation_queued_at)->toBeNull();
});

it('does not send or mutate rows that moved to a different email after the job was queued', function () {
    Mail::fake();

    $guestList = GuestList::factory()->confirmed()->create();
    $group = GuestGroup::factory()->for($guestList)->create(['guest_count' => 1]);
    $entry = GuestEntry::factory()->for($group, 'group')->withQrToken()->create([
        'email' => 'old@example.com',
        'invitation_queued_at' => now()->subMinute(),
    ]);

    $entry->update([
        'email' => 'new@example.com',
        'invitation_queued_at' => null,
    ]);

    $job = new SendGuestInvitationsJob($guestList, 'old@example.com', [$entry->id]);
    $job->handle();
    $job->failed(new RuntimeException('stale queued job'));

    Mail::assertNothingSent();

    expect($entry->fresh()->email)->toBe('new@example.com')
        ->and($entry->fresh()->invitation_sent_at)->toBeNull()
        ->and($entry->fresh()->invitation_failed_at)->toBeNull();
});
