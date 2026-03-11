<?php

namespace App\Services;

use Carbon\Carbon;

class PeriodResolver
{
    public function currentPeriodDate(?Carbon $at = null): string
    {
        $now = $at ?? now();

        if ($now->hour < 4) {
            return $now->copy()->subDay()->toDateString();
        }

        return $now->toDateString();
    }

    public function previousPeriodDate(?Carbon $at = null): string
    {
        $now = $at ?? now();

        if ($now->hour < 4) {
            return $now->copy()->subDays(2)->toDateString();
        }

        return $now->copy()->subDay()->toDateString();
    }
}
