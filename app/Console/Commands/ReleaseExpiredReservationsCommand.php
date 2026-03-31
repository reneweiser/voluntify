<?php

namespace App\Console\Commands;

use App\Actions\ReleaseExpiredReservations;
use Illuminate\Console\Command;

class ReleaseExpiredReservationsCommand extends Command
{
    protected $signature = 'app:release-expired-reservations';

    protected $description = 'Release expired shift reservations to free capacity';

    public function handle(ReleaseExpiredReservations $action): void
    {
        $count = $action->execute();

        if ($count > 0) {
            $this->info("Released {$count} expired reservation(s).");
        }
    }
}
