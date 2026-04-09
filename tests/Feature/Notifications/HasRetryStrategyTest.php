<?php

use App\Notifications\AnnouncementNotification;
use App\Notifications\CancellationConfirmation;
use App\Notifications\CancellationDigestNotification;
use App\Notifications\EmailVerification;
use App\Notifications\EventRepublishedNotification;
use App\Notifications\ImmediateCancellationNotification;
use App\Notifications\PortalAccessLink;
use App\Notifications\PreShiftReminder;
use App\Notifications\SignupConfirmation;
use App\Notifications\TicketResendNotification;
use App\Notifications\VolunteerProfileDeletedNotification;

$queuedNotifications = [
    AnnouncementNotification::class,
    CancellationConfirmation::class,
    CancellationDigestNotification::class,
    EmailVerification::class,
    EventRepublishedNotification::class,
    ImmediateCancellationNotification::class,
    PortalAccessLink::class,
    PreShiftReminder::class,
    SignupConfirmation::class,
    TicketResendNotification::class,
    VolunteerProfileDeletedNotification::class,
];

it('configures retry strategy on all queued notifications [#113]', function (string $class) {
    $reflection = new ReflectionClass($class);

    expect($reflection->getDefaultProperties())
        ->toHaveKey('tries', 3)
        ->toHaveKey('backoff', [30, 60, 300]);
})->with($queuedNotifications);

it('has a failed method on all queued notifications [#113]', function (string $class) {
    $reflection = new ReflectionClass($class);

    expect($reflection->hasMethod('failed'))->toBeTrue();
})->with($queuedNotifications);
