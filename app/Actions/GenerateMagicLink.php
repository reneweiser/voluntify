<?php

namespace App\Actions;

use App\Models\MagicLinkToken;
use App\Models\Volunteer;
use App\ValueObjects\HashedToken;
use Illuminate\Support\Str;

class GenerateMagicLink
{
    /**
     * Generate a magic link token for a volunteer.
     *
     * @return array{token: MagicLinkToken, plainToken: string}
     */
    public function execute(Volunteer $volunteer): array
    {
        $plainToken = Str::random(64);
        $hashed = HashedToken::fromPlaintext($plainToken);

        $token = MagicLinkToken::create([
            'volunteer_id' => $volunteer->id,
            'token_hash' => $hashed->hash,
            'expires_at' => now()->addHours(72),
        ]);

        return [
            'token' => $token,
            'plainToken' => $plainToken,
        ];
    }
}
