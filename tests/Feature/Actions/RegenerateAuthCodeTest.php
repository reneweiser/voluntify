<?php

use App\Actions\RegenerateAuthCode;
use App\Models\ProjectScanner;

it('generates a new 6-digit code', function () {
    $scanner = ProjectScanner::factory()->active()->create();

    $action = new RegenerateAuthCode;
    $rawCode = $action->execute($scanner);

    expect($rawCode)->toMatch('/^\d{6}$/');
});

it('stores plaintext code in database', function () {
    $scanner = ProjectScanner::factory()->active()->create();

    $action = new RegenerateAuthCode;
    $rawCode = $action->execute($scanner);

    $scanner->refresh();
    expect($scanner->auth_code)->toBe($rawCode);
});

it('changes the auth code from previous value', function () {
    $scanner = ProjectScanner::factory()->active()->create();
    $oldCode = $scanner->auth_code;

    $action = new RegenerateAuthCode;
    $action->execute($scanner);

    $scanner->refresh();
    expect($scanner->auth_code)->not->toBe($oldCode);
});
