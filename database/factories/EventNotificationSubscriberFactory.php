<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\EventNotificationSubscriber;
use App\ValueObjects\HashedToken;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventNotificationSubscriber>
 */
class EventNotificationSubscriberFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'email' => fake()->safeEmail(),
            'verification_token_hash' => HashedToken::fromPlaintext(fake()->sha256())->hash,
            'verification_expires_at' => now()->addDays(7),
            'verified_at' => null,
            'unsubscribe_token_hash' => null,
            'last_notified_at' => null,
        ];
    }

    public function verified(): static
    {
        return $this->state(fn () => [
            'verified_at' => now(),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'verification_expires_at' => now()->subMinute(),
        ]);
    }
}
