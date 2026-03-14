<?php

namespace App\Console\Commands;

use App\Actions\MarkNoShows;
use Illuminate\Console\Command;

class MarkNoShowsCommand extends Command
{
    protected $signature = 'app:mark-no-shows';

    protected $description = 'Mark volunteers with ended shifts and no attendance record as no-shows';

    public function handle(MarkNoShows $action): void
    {
        $count = $action->execute();

        $this->info("Marked {$count} signup(s) as no-show.");
    }
}
