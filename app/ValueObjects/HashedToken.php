<?php

namespace App\ValueObjects;

class HashedToken
{
    public function __construct(public readonly string $hash) {}

    public static function fromPlaintext(string $plaintext): self
    {
        return new self(hash('sha256', $plaintext));
    }

    public function matches(string $plaintext): bool
    {
        return hash_equals($this->hash, hash('sha256', $plaintext));
    }
}
