<?php

namespace Database\Factories;

use App\Models\EmailVerificationToken;
use App\Models\Event;
use App\Models\Volunteer;
use App\ValueObjects\HashedToken;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\EmailVerificationToken>
 */
class EmailVerificationTokenFactory extends Factory
{
    protected $model = EmailVerificationToken::class;

    public function definition(): array
    {
        return [
            'volunteer_id' => Volunteer::factory(),
            'event_id' => Event::factory(),
            'shift_ids' => [1],
            'token_hash' => HashedToken::fromPlaintext(fake()->sha256())->hash,
            'expires_at' => now()->addHours(24),
        ];
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'expires_at' => now()->subHour(),
        ]);
    }
}
