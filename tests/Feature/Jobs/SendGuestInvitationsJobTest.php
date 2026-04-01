<?php

use App\Jobs\SendGuestInvitationsJob;
use App\Mail\GuestInvitationMail;
use App\Models\GuestEntry;
use App\Models\GuestGroup;
use App\Models\GuestList;
use Illuminate\Support\Facades\Mail;

it('sends grouped email with all QR codes for matching entries', function () {
    Mail::fake();

    $guestList = GuestList::factory()->confirmed()->create();
    $group = GuestGroup::factory()->for($guestList)->create(['guest_count' => 3]);
    GuestEntry::factory()->for($group, 'group')->withQrToken()->create(['number' => 1, 'email' => 'dj@example.com']);
    GuestEntry::factory()->for($group, 'group')->withQrToken()->create(['number' => 2, 'email' => 'dj@example.com']);
    GuestEntry::factory()->for($group, 'group')->withQrToken()->create(['number' => 3, 'email' => null]);

    $job = new SendGuestInvitationsJob($guestList, 'dj@example.com');
    $job->handle();

    Mail::assertSent(GuestInvitationMail::class, function (GuestInvitationMail $mail) {
        return $mail->hasTo('dj@example.com')
            && $mail->entries->count() === 2;
    });
});

it('skips when no entries match the email', function () {
    Mail::fake();

    $guestList = GuestList::factory()->confirmed()->create();

    $job = new SendGuestInvitationsJob($guestList, 'nobody@example.com');
    $job->handle();

    Mail::assertNothingSent();
});

it('skips entries without qr_token', function () {
    Mail::fake();

    $guestList = GuestList::factory()->confirmed()->create();
    $group = GuestGroup::factory()->for($guestList)->create(['guest_count' => 1]);
    GuestEntry::factory()->for($group, 'group')->create(['number' => 1, 'email' => 'test@example.com', 'qr_token' => null]);

    $job = new SendGuestInvitationsJob($guestList, 'test@example.com');
    $job->handle();

    Mail::assertNothingSent();
});
