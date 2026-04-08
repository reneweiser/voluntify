<?php

use App\Jobs\SendScannerLinksJob;
use App\Mail\ScannerLinkMail;
use App\Models\ProjectScanner;
use App\Models\ProjectScannerAssignee;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;

it('sends mail and updates link_sent_at on assignee', function () {
    Mail::fake();
    Carbon::setTestNow('2026-07-01 12:00:00');

    $scanner = ProjectScanner::factory()->active()->create();
    $assignee = ProjectScannerAssignee::factory()->for($scanner, 'projectScanner')->create([
        'email' => 'staff@example.com',
    ]);

    $job = new SendScannerLinksJob($assignee);
    $job->handle();

    Mail::assertSent(ScannerLinkMail::class, function ($mail) {
        return $mail->hasTo('staff@example.com');
    });

    $assignee->refresh();
    expect($assignee->link_sent_at)->not->toBeNull();

    Carbon::setTestNow();
});

it('skips sending when scanner is expired', function () {
    Mail::fake();
    Carbon::setTestNow('2026-07-01 12:00:00');

    $scanner = ProjectScanner::factory()->expired()->create();
    $assignee = ProjectScannerAssignee::factory()->for($scanner, 'projectScanner')->create();

    $job = new SendScannerLinksJob($assignee);
    $job->handle();

    Mail::assertNothingSent();

    $assignee->refresh();
    expect($assignee->link_sent_at)->toBeNull();

    Carbon::setTestNow();
});

it('includes correct scanner auth URL in mail', function () {
    Mail::fake();
    Carbon::setTestNow('2026-07-01 12:00:00');

    $scanner = ProjectScanner::factory()->active()->create();
    $assignee = ProjectScannerAssignee::factory()->for($scanner, 'projectScanner')->create();

    $job = new SendScannerLinksJob($assignee);
    $job->handle();

    Mail::assertSent(ScannerLinkMail::class, function ($mail) use ($scanner) {
        return str_contains($mail->url, $scanner->scanner_token);
    });

    Carbon::setTestNow();
});

it('passes auth code to ScannerLinkMail when provided', function () {
    Mail::fake();
    Carbon::setTestNow('2026-07-01 12:00:00');

    $scanner = ProjectScanner::factory()->active()->create();
    $assignee = ProjectScannerAssignee::factory()->for($scanner, 'projectScanner')->create([
        'email' => 'staff@example.com',
    ]);

    $job = new SendScannerLinksJob($assignee, '654321');
    $job->handle();

    Mail::assertSent(ScannerLinkMail::class, function ($mail) {
        return $mail->hasTo('staff@example.com')
            && $mail->authCode === '654321';
    });

    Carbon::setTestNow();
});

it('sends mail without auth code when none provided', function () {
    Mail::fake();
    Carbon::setTestNow('2026-07-01 12:00:00');

    $scanner = ProjectScanner::factory()->active()->create();
    $assignee = ProjectScannerAssignee::factory()->for($scanner, 'projectScanner')->create();

    $job = new SendScannerLinksJob($assignee);
    $job->handle();

    Mail::assertSent(ScannerLinkMail::class, function ($mail) {
        return $mail->authCode === null;
    });

    Carbon::setTestNow();
});

it('is configured as retryable with backoff', function () {
    $assignee = ProjectScannerAssignee::factory()->create();
    $job = new SendScannerLinksJob($assignee);

    expect($job->tries)->toBe(3)
        ->and($job->backoff)->toBe([10, 30, 60])
        ->and($job->timeout)->toBe(30);
});
