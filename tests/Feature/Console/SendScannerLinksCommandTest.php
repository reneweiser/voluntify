<?php

use App\Jobs\SendScannerLinksJob;
use App\Models\ProjectScanner;
use App\Models\ProjectScannerAssignee;
use Carbon\Carbon;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Carbon::setTestNow('2026-07-01 09:45:00');
});

afterEach(function () {
    Carbon::setTestNow();
});

it('dispatches jobs for scanners opening within 30 minutes', function () {
    Queue::fake();

    $scanner = ProjectScanner::factory()->create([
        'starts_at' => Carbon::parse('2026-07-01 10:00:00'),
        'ends_at' => Carbon::parse('2026-07-01 14:00:00'),
    ]);
    ProjectScannerAssignee::factory()->count(2)->for($scanner, 'projectScanner')->create();

    $this->artisan('scanner-links:send')->assertExitCode(0);

    Queue::assertPushed(SendScannerLinksJob::class, 2);
});

it('does not dispatch for scanners already sent', function () {
    Queue::fake();

    $scanner = ProjectScanner::factory()->create([
        'starts_at' => Carbon::parse('2026-07-01 10:00:00'),
        'ends_at' => Carbon::parse('2026-07-01 14:00:00'),
    ]);
    ProjectScannerAssignee::factory()->for($scanner, 'projectScanner')->create([
        'link_sent_at' => now(),
    ]);

    $this->artisan('scanner-links:send')->assertExitCode(0);

    Queue::assertNothingPushed();
});

it('does not dispatch for expired scanners', function () {
    Queue::fake();

    $scanner = ProjectScanner::factory()->expired()->create();
    ProjectScannerAssignee::factory()->for($scanner, 'projectScanner')->create();

    $this->artisan('scanner-links:send')->assertExitCode(0);

    Queue::assertNothingPushed();
});

it('does not dispatch for scanners opening more than 30 minutes away', function () {
    Queue::fake();

    $scanner = ProjectScanner::factory()->create([
        'starts_at' => Carbon::parse('2026-07-01 12:00:00'),
        'ends_at' => Carbon::parse('2026-07-01 16:00:00'),
    ]);
    ProjectScannerAssignee::factory()->for($scanner, 'projectScanner')->create();

    $this->artisan('scanner-links:send')->assertExitCode(0);

    Queue::assertNothingPushed();
});
