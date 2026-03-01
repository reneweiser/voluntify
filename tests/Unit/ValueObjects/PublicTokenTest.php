<?php

use App\ValueObjects\PublicToken;

it('generates a 32-character token', function () {
    $token = PublicToken::generate();

    expect($token->value)->toHaveLength(32);
});

it('generates unique tokens', function () {
    $token1 = PublicToken::generate();
    $token2 = PublicToken::generate();

    expect($token1->value)->not->toBe($token2->value);
});

it('casts to string', function () {
    $token = new PublicToken('abc123');

    expect((string) $token)->toBe('abc123');
});
