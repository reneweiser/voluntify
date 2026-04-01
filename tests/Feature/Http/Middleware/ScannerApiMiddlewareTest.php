<?php

use App\Models\ProjectScanner;
use Carbon\Carbon;

beforeEach(function () {
    Carbon::setTestNow('2026-07-01 12:00:00');
});

afterEach(function () {
    Carbon::setTestNow();
});

it('passes through with valid token within active window', function () {
    $scanner = ProjectScanner::factory()->active()->create();

    $this->getJson(route('scanner-api.data', $scanner->id), [
        'X-Scanner-Token' => $scanner->scanner_token,
    ])->assertOk();
});

it('returns 401 for valid token but window not started', function () {
    $scanner = ProjectScanner::factory()->scheduled()->create();

    $this->getJson(route('scanner-api.data', $scanner->id), [
        'X-Scanner-Token' => $scanner->scanner_token,
    ])->assertUnauthorized();
});

it('returns 401 for valid token but window expired', function () {
    $scanner = ProjectScanner::factory()->expired()->create();

    $this->getJson(route('scanner-api.data', $scanner->id), [
        'X-Scanner-Token' => $scanner->scanner_token,
    ])->assertUnauthorized();
});

it('returns 401 for missing token header', function () {
    $scanner = ProjectScanner::factory()->active()->create();

    $this->getJson(route('scanner-api.data', $scanner->id))
        ->assertUnauthorized();
});

it('returns 401 for non-existent token', function () {
    $scanner = ProjectScanner::factory()->active()->create();

    $this->getJson(route('scanner-api.data', $scanner->id), [
        'X-Scanner-Token' => 'nonexistent-token-value',
    ])->assertUnauthorized();
});
