<?php

namespace Database\Factories;

use App\Models\EmailVerificationToken;
use App\Models\Event;
use App\Models\Volunteer;
use App\ValueObjects\HashedToken;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmailVerificationToken>
 */
class EmailVerificationTokenFactory extends Factory
{
    protected $model = EmailVerificationToken::class;

    public function definition(): array
    {
        return [
            'volunteer_id' => Volunteer::factory(),
            'event_id' => Event::factory(),
            'project_id' => null,
            'email' => fake()->safeEmail(),
            'shift_ids' => null,
            'token_hash' => HashedToken::fromPlaintext(fake()->sha256())->hash,
            'expires_at' => now()->addHours(24),
            'verified_at' => null,
        ];
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'expires_at' => now()->subHour(),
        ]);
    }

    public function verified(): static
    {
        return $this->state(fn () => [
            'verified_at' => now(),
        ]);
    }

    /**
     * @param  array<int>  $ids
     */
    public function withShiftIds(array $ids): static
    {
        return $this->state(fn () => [
            'shift_ids' => $ids,
        ]);
    }
}
