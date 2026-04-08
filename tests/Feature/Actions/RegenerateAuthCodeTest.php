<?php

use App\Actions\RegenerateAuthCode;
use App\Models\ProjectScanner;
use Illuminate\Support\Facades\Hash;

it('generates a new 6-digit code', function () {
    $scanner = ProjectScanner::factory()->active()->create();

    $action = new RegenerateAuthCode;
    $rawCode = $action->execute($scanner);

    expect($rawCode)->toMatch('/^\d{6}$/');
});

it('stores bcrypt hash of new code', function () {
    $scanner = ProjectScanner::factory()->active()->create();

    $action = new RegenerateAuthCode;
    $rawCode = $action->execute($scanner);

    $scanner->refresh();
    expect(Hash::check($rawCode, $scanner->auth_code))->toBeTrue();
});

it('changes the auth code from previous value', function () {
    $scanner = ProjectScanner::factory()->active()->create();
    $oldHash = $scanner->auth_code;

    $action = new RegenerateAuthCode;
    $action->execute($scanner);

    $scanner->refresh();
    expect($scanner->auth_code)->not->toBe($oldHash);
});
