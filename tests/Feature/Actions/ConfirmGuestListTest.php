<?php

use App\Actions\ConfirmGuestList;
use App\Enums\GuestListStatus;
use App\Exceptions\DomainException;
use App\Jobs\ConfirmGuestListJob;
use App\Models\GuestList;
use Illuminate\Support\Facades\Queue;

it('sets status to confirmed and confirmed_at', function () {
    Queue::fake();

    $guestList = GuestList::factory()->create(['status' => GuestListStatus::Draft]);

    $action = new ConfirmGuestList;
    $result = $action->execute($guestList);

    expect($result->status)->toBe(GuestListStatus::Confirmed)
        ->and($result->confirmed_at)->not->toBeNull();
});

it('dispatches ConfirmGuestListJob', function () {
    Queue::fake();

    $guestList = GuestList::factory()->create();

    $action = new ConfirmGuestList;
    $action->execute($guestList);

    Queue::assertPushed(ConfirmGuestListJob::class, function ($job) use ($guestList) {
        return $job->guestList->id === $guestList->id;
    });
});

it('rejects already-confirmed list to prevent duplicate invitation emails', function () {
    $guestList = GuestList::factory()->confirmed()->create();

    $action = new ConfirmGuestList;

    expect(fn () => $action->execute($guestList))->toThrow(DomainException::class, 'Guest list is already confirmed.');
});

it('dispatches exactly one job regardless of entry count', function () {
    Queue::fake();

    $guestList = GuestList::factory()->create();

    $action = new ConfirmGuestList;
    $action->execute($guestList);

    Queue::assertPushed(ConfirmGuestListJob::class, 1);
});
