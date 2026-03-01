<?php

use App\ValueObjects\HashedToken;

it('creates a hash from plaintext', function () {
    $token = HashedToken::fromPlaintext('test-token');

    expect($token->hash)->toBe(hash('sha256', 'test-token'));
});

it('matches the correct plaintext', function () {
    $token = HashedToken::fromPlaintext('my-secret');

    expect($token->matches('my-secret'))->toBeTrue();
});

it('does not match incorrect plaintext', function () {
    $token = HashedToken::fromPlaintext('my-secret');

    expect($token->matches('wrong-secret'))->toBeFalse();
});
