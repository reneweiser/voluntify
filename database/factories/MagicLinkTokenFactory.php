<?php

namespace Database\Factories;

use App\Models\MagicLinkToken;
use App\Models\Volunteer;
use App\ValueObjects\HashedToken;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\MagicLinkToken>
 */
class MagicLinkTokenFactory extends Factory
{
    protected $model = MagicLinkToken::class;

    public function definition(): array
    {
        return [
            'volunteer_id' => Volunteer::factory(),
            'token_hash' => HashedToken::fromPlaintext(fake()->sha256())->hash,
            'expires_at' => now()->addHours(24),
        ];
    }
}
