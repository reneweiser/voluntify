<?php

namespace App\Console\Commands;

use App\Actions\SendPreShiftReminders;
use App\Enums\ReminderWindow;
use Illuminate\Console\Command;

class SendPreShiftRemindersCommand extends Command
{
    protected $signature = 'app:send-pre-shift-reminders';

    protected $description = 'Send pre-shift reminder emails to volunteers with upcoming shifts';

    public function handle(SendPreShiftReminders $action): void
    {
        $count24h = $action->execute(ReminderWindow::TwentyFourHour);
        $this->info("Sent {$count24h} 24-hour reminders.");

        $count4h = $action->execute(ReminderWindow::FourHour);
        $this->info("Sent {$count4h} 4-hour reminders.");
    }
}
