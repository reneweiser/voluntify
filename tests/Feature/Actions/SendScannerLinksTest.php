<?php

use App\Actions\SendScannerLinks;
use App\Jobs\SendScannerLinksJob;
use App\Models\ProjectScanner;
use App\Models\ProjectScannerAssignee;
use Illuminate\Support\Facades\Queue;

it('dispatches one job per assignee', function () {
    Queue::fake();

    $scanner = ProjectScanner::factory()->create();
    ProjectScannerAssignee::factory()->count(3)->for($scanner, 'projectScanner')->create();

    $action = new SendScannerLinks;
    $action->execute($scanner);

    Queue::assertPushed(SendScannerLinksJob::class, 3);
});

it('does not set link_sent_at eagerly (only job sets it after send)', function () {
    Queue::fake();

    $scanner = ProjectScanner::factory()->create();
    $assignees = ProjectScannerAssignee::factory()->count(2)->for($scanner, 'projectScanner')->create();

    $action = new SendScannerLinks;
    $action->execute($scanner);

    foreach ($assignees as $assignee) {
        $assignee->refresh();
        expect($assignee->link_sent_at)->toBeNull();
    }
});

it('dispatches no jobs when there are no assignees', function () {
    Queue::fake();

    $scanner = ProjectScanner::factory()->create();

    $action = new SendScannerLinks;
    $action->execute($scanner);

    Queue::assertNothingPushed();
});
