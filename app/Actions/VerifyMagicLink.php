<?php

namespace App\Actions;

use App\Exceptions\InvalidMagicLinkException;
use App\Models\MagicLinkToken;
use App\Models\Volunteer;
use App\ValueObjects\HashedToken;

class VerifyMagicLink
{
    public function execute(string $plainToken): Volunteer
    {
        $hash = HashedToken::fromPlaintext($plainToken)->hash;

        $magicLink = MagicLinkToken::where('token_hash', $hash)->first();

        if (! $magicLink) {
            throw new InvalidMagicLinkException('Invalid magic link token.');
        }

        if ($magicLink->expires_at->isPast()) {
            throw new InvalidMagicLinkException('This magic link has expired.');
        }

        return $magicLink->volunteer;
    }
}
