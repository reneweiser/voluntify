<?php

namespace App\ValueObjects;

use Illuminate\Support\Str;
use Stringable;

class PublicToken implements Stringable
{
    public function __construct(public readonly string $value) {}

    public static function generate(): self
    {
        return new self(Str::random(32));
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
