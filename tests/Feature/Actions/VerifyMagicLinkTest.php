<?php

use App\Actions\VerifyMagicLink;
use App\Exceptions\InvalidMagicLinkException;
use App\Models\MagicLinkToken;
use App\Models\Volunteer;
use App\ValueObjects\HashedToken;

beforeEach(function () {
    $this->action = app(VerifyMagicLink::class);
    $this->volunteer = Volunteer::factory()->create();
    $this->plainToken = 'test-token-abc123';
    $this->magicLink = MagicLinkToken::factory()->create([
        'volunteer_id' => $this->volunteer->id,
        'token_hash' => HashedToken::fromPlaintext($this->plainToken)->hash,
        'expires_at' => now()->addHours(24),
    ]);
});

it('returns volunteer for valid token', function () {
    $volunteer = $this->action->execute($this->plainToken);

    expect($volunteer->id)->toBe($this->volunteer->id);
});

it('throws exception for expired token', function () {
    $this->magicLink->update(['expires_at' => now()->subMinute()]);

    $this->action->execute($this->plainToken);
})->throws(InvalidMagicLinkException::class, 'expired');

it('throws exception for nonexistent token', function () {
    $this->action->execute('nonexistent-token');
})->throws(InvalidMagicLinkException::class);

it('throws exception for wrong hash', function () {
    $this->action->execute('wrong-token-value');
})->throws(InvalidMagicLinkException::class);
