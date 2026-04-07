<?php

namespace App\Concerns;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

trait ConvertsTimezone
{
    protected function toUtc(string $localDatetime, string $timezone): CarbonImmutable
    {
        $parsed = CarbonImmutable::createFromFormat('Y-m-d\TH:i', $localDatetime, $timezone);

        if (! $parsed) {
            throw new \InvalidArgumentException("Invalid datetime format: {$localDatetime}");
        }

        return $parsed->utc();
    }

    protected function toLocal(CarbonInterface $utcDatetime, string $timezone): string
    {
        return $utcDatetime->setTimezone($timezone)->format('Y-m-d\TH:i');
    }
}
