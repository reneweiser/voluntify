<?php

use App\Actions\AddGuestEntry;
use App\Actions\AddGuestGroup;
use App\Actions\CheckInGuest;
use App\Actions\ConfirmGuestList;
use App\Actions\CreateGuestList;
use App\Actions\RecordGuestGearPickup;
use App\Actions\RemoveGuestEntry;
use App\Enums\GuestListStatus;
use App\Enums\ScannerType;
use App\Jobs\ConfirmGuestListJob;
use App\Jobs\SendGuestInvitationsJob;
use App\Mail\GuestInvitationMail;
use App\Models\GuestEntry;
use App\Models\GuestEntryGear;
use App\Models\Project;
use App\Models\ProjectGearItem;
use App\Models\ProjectScanner;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

it('completes full lifecycle: create list -> add groups -> add entries with gear -> confirm -> QR generated -> emails grouped -> check-in -> gear pickup', function () {
    // Setup
    $project = Project::factory()->create();
    $scanner = ProjectScanner::factory()->for($project)->create(['type' => ScannerType::EntryStaff]);
    $gearItem = ProjectGearItem::factory()->for($project)->create();

    // 1. Create guest list
    $guestList = (new CreateGuestList)->execute($project, [
        'scanner_id' => $scanner->id,
        'name' => 'Artist Guest List',
        'gear_items' => [$gearItem->id],
    ]);

    expect($guestList->status)->toBe(GuestListStatus::Draft);

    // 2. Add groups
    $group = (new AddGuestGroup)->execute($guestList, 'DJ Soundwave', 2);
    expect($group->entries)->toHaveCount(2);

    // 3. Add entry with gear and email
    $entry = (new AddGuestEntry)->execute($group, 'Manager', 'manager@example.com', [
        ['project_gear_item_id' => $gearItem->id, 'quantity' => 1],
    ]);
    expect($entry->gear)->toHaveCount(1)
        ->and($entry->qr_token)->toBeNull(); // draft list, no QR yet

    // 4. Confirm guest list
    Queue::fake();
    $guestList = (new ConfirmGuestList)->execute($guestList);
    expect($guestList->status)->toBe(GuestListStatus::Confirmed);
    Queue::assertPushed(ConfirmGuestListJob::class);

    // 5. Run the confirmation job to generate QR tokens and dispatch email jobs
    Mail::fake();
    Queue::fake([SendGuestInvitationsJob::class]);
    (new ConfirmGuestListJob($guestList->fresh()))->handle();

    // All 3 entries (2 from group + 1 added) should have QR tokens
    $allEntries = $guestList->entries()->get();
    expect($allEntries)->toHaveCount(3);
    $allEntries->each(fn ($e) => expect($e->qr_token)->not->toBeNull());

    // Only 1 unique email, so 1 invitation job
    Queue::assertPushed(SendGuestInvitationsJob::class, 1);

    expect($guestList->entries()->where('email', 'manager@example.com')->whereNull('invitation_queued_at')->count())->toBe(0);

    // 6. Run the invitation job to send email
    Queue::fake();
    (new SendGuestInvitationsJob(
        $guestList,
        'manager@example.com',
        $guestList->entries()->where('email', 'manager@example.com')->pluck('guest_entries.id')->all(),
    ))->handle();
    Mail::assertSent(GuestInvitationMail::class, function (GuestInvitationMail $mail) {
        $mail->assertSeeInHtml(__('Open Guest Pass'));

        return $mail->hasTo('manager@example.com')
            && $mail->entries->count() === 1;
    });

    // 7. Check in a guest
    $entryToCheckIn = $allEntries->first();
    $staff = User::factory()->create();
    $result = (new CheckInGuest)->execute($entryToCheckIn, $staff->id);
    expect($result->checked_in_at)->not->toBeNull()
        ->and($result->checked_in_by)->toBe($staff->id);

    // 8. Record gear pickup
    $gearRecord = GuestEntryGear::where('guest_entry_id', $entry->id)->first();
    $pickupResult = (new RecordGuestGearPickup)->execute($gearRecord, ['selection' => 'M', 'status' => 'issued']);
    expect($pickupResult->selection)->toBe('M')
        ->and($pickupResult->status)->toBe('issued');
});

it('post-confirm: adding entry generates QR immediately and dispatches email', function () {
    Queue::fake();

    $project = Project::factory()->create();
    $scanner = ProjectScanner::factory()->for($project)->create(['type' => ScannerType::EntryStaff]);

    $guestList = (new CreateGuestList)->execute($project, [
        'scanner_id' => $scanner->id,
        'name' => 'VIP List',
    ]);

    $group = (new AddGuestGroup)->execute($guestList, 'VIP', 1);
    (new ConfirmGuestList)->execute($guestList);

    // Run confirmation job
    (new ConfirmGuestListJob($guestList->fresh()))->handle();

    // Now add a new entry to the confirmed list
    Queue::fake();
    $newEntry = (new AddGuestEntry)->execute($group, 'Late Addition', 'late@example.com');

    expect($newEntry->qr_token)->not->toBeNull()
        ->and(strlen($newEntry->qr_token))->toBe(64);

    Queue::assertPushed(SendGuestInvitationsJob::class, function ($job) {
        return $job->email === 'late@example.com';
    });

    expect($newEntry->fresh()->invitation_queued_at)->not->toBeNull()
        ->and($newEntry->fresh()->invitation_sent_at)->toBeNull();
});

it('post-confirm removal: removed entry QR becomes invalid for check-in via API', function () {
    $project = Project::factory()->create();
    $scanner = ProjectScanner::factory()->active()->create([
        'project_id' => $project->id,
        'type' => ScannerType::EntryStaff,
    ]);
    $guestList = (new CreateGuestList)->execute($project, [
        'scanner_id' => $scanner->id,
        'name' => 'Removal Test',
    ]);
    $group = (new AddGuestGroup)->execute($guestList, 'Test Group', 2);

    Queue::fake();
    (new ConfirmGuestList)->execute($guestList);
    (new ConfirmGuestListJob($guestList->fresh()))->handle();

    $entries = $guestList->entries()->get();
    $entryToRemove = $entries->first();
    $entryId = $entryToRemove->id;

    // Remove the entry
    (new RemoveGuestEntry)->execute($entryToRemove);

    // Entry no longer exists
    expect(GuestEntry::find($entryId))->toBeNull();

    // Remaining entry still works
    $remainingEntry = $entries->last()->fresh();
    expect($remainingEntry->qr_token)->not->toBeNull();
});

it('email grouping: two entries with same email in one list produce one invitation job', function () {
    $project = Project::factory()->create();
    $scanner = ProjectScanner::factory()->for($project)->create(['type' => ScannerType::EntryStaff]);

    $guestList = (new CreateGuestList)->execute($project, [
        'scanner_id' => $scanner->id,
        'name' => 'Grouping Test',
    ]);
    $group = (new AddGuestGroup)->execute($guestList, 'Band', 1);

    // Add two more entries with same email
    (new AddGuestEntry)->execute($group, 'Member 1', 'band@example.com');
    (new AddGuestEntry)->execute($group, 'Member 2', 'band@example.com');

    // Confirm (fake only the job it dispatches)
    Queue::fake([ConfirmGuestListJob::class]);
    (new ConfirmGuestList)->execute($guestList);
    Queue::assertPushed(ConfirmGuestListJob::class);

    // Now manually run the confirm job with SendGuestInvitationsJob faked
    Queue::fake([SendGuestInvitationsJob::class]);
    (new ConfirmGuestListJob($guestList->fresh()))->handle();

    // Only one invitation job for the shared email
    Queue::assertPushed(SendGuestInvitationsJob::class, 1);
    Queue::assertPushed(SendGuestInvitationsJob::class, function ($job) {
        return $job->email === 'band@example.com';
    });
});

it('GuestEntry qr_token is hidden from serialization', function () {
    $entry = GuestEntry::factory()->withQrToken()->create();

    $array = $entry->toArray();

    expect($array)->not->toHaveKey('qr_token')
        ->and($entry->qr_token)->not->toBeNull();
});
