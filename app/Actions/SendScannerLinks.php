<?php

namespace App\Actions;

use App\Jobs\SendScannerLinksJob;
use App\Models\ProjectScanner;

class SendScannerLinks
{
    public function execute(ProjectScanner $scanner, ?string $rawAuthCode = null): void
    {
        $scanner->load('assignees');

        foreach ($scanner->assignees as $assignee) {
            SendScannerLinksJob::dispatch($assignee, $rawAuthCode);
        }
    }
}
