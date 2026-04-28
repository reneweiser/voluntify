<?php

namespace Database\Factories;

use App\Models\MagicLinkToken;
use App\Models\Volunteer;
use App\ValueObjects\HashedToken;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MagicLinkToken>
 */
class MagicLinkTokenFactory extends Factory
{
    protected $model = MagicLinkToken::class;

    public function definition(): array
    {
        return [
            'volunteer_id' => Volunteer::factory(),
            'token_hash' => HashedToken::fromPlaintext(fake()->sha256())->hash,
            'expires_at' => null,
        ];
    }

    public function expiring(): static
    {
        return $this->state(fn () => [
            'expires_at' => now()->addHours(24),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'expires_at' => now()->subHour(),
        ]);
    }
}
