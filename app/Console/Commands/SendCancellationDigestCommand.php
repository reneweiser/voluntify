<?php

namespace App\Console\Commands;

use App\Models\ShiftSignup;
use App\Notifications\CancellationDigestNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

class SendCancellationDigestCommand extends Command
{
    protected $signature = 'app:send-cancellation-digest';

    protected $description = 'Send a digest of recent cancellations to organizers';

    public function handle(): void
    {
        $recentCancellations = ShiftSignup::query()
            ->whereNotNull('cancelled_at')
            ->where('cancelled_at', '>=', now()->subHours(6))
            ->with(['shift.volunteerJob.event.project.organization', 'volunteer'])
            ->get();

        if ($recentCancellations->isEmpty()) {
            $this->info('No recent cancellations found.');

            return;
        }

        $byProject = $recentCancellations->groupBy(fn (ShiftSignup $signup) => $signup->shift->volunteerJob->event->project_id);

        $totalSent = 0;

        foreach ($byProject as $projectId => $cancellations) {
            $project = $cancellations->first()->shift->volunteerJob->event->project;

            // Collect unique notification emails from events, fall back to project contact_email
            $recipientEmails = $cancellations
                ->map(fn (ShiftSignup $signup) => $signup->shift->volunteerJob->event->notification_email)
                ->filter()
                ->unique()
                ->values();

            if ($recipientEmails->isEmpty() && $project->contact_email) {
                $recipientEmails = collect([$project->contact_email]);
            }

            if ($recipientEmails->isEmpty()) {
                $this->warn("No recipient email for project '{$project->name}' — skipping.");

                continue;
            }

            foreach ($recipientEmails as $email) {
                Notification::route('mail', $email)
                    ->notify(new CancellationDigestNotification($project, $cancellations));
                $totalSent++;
            }
        }

        $this->info("Sent {$totalSent} cancellation digest(s).");
    }
}
