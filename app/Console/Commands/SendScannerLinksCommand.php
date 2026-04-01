<?php

namespace App\Console\Commands;

use App\Jobs\SendScannerLinksJob;
use App\Models\ProjectScanner;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('scanner-links:send')]
#[Description('Send scanner access links to assignees for scanners opening within 30 minutes')]
class SendScannerLinksCommand extends Command
{
    public function handle(): int
    {
        $scanners = ProjectScanner::windowOpensSoon(30)
            ->whereHas('assignees', fn ($q) => $q->whereNull('link_sent_at'))
            ->with(['assignees' => fn ($q) => $q->whereNull('link_sent_at')])
            ->get();

        $count = 0;

        foreach ($scanners as $scanner) {
            foreach ($scanner->assignees as $assignee) {
                SendScannerLinksJob::dispatch($assignee);
                $count++;
            }
        }

        $this->info("Dispatched {$count} scanner link job(s).");

        return self::SUCCESS;
    }
}
