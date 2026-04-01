<?php

namespace App\Jobs;

use App\Mail\ScannerLinkMail;
use App\Models\ProjectScannerAssignee;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

class SendScannerLinksJob implements ShouldQueue
{
    use Queueable;

    /** @var int[] */
    public array $backoff = [10, 30, 60];

    public int $tries = 3;

    public int $timeout = 30;

    public function __construct(public ProjectScannerAssignee $assignee) {}

    public function handle(): void
    {
        $scanner = $this->assignee->projectScanner;

        if ($scanner->isExpired()) {
            return;
        }

        $url = route('scanner.auth', $scanner->scanner_token);

        Mail::to($this->assignee->email)
            ->send(new ScannerLinkMail($scanner, $url));

        $this->assignee->update(['link_sent_at' => now()]);
    }
}
