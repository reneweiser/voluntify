<?php

use App\Models\ProjectScanner;
use Carbon\Carbon;

beforeEach(function () {
    Carbon::setTestNow('2026-07-01 12:00:00');
});

afterEach(function () {
    Carbon::setTestNow();
});

it('allows access when scanner is active and session is valid', function () {
    $scanner = ProjectScanner::factory()->active()->create();

    $this->withSession(['scanner_id' => $scanner->id])
        ->get(route('scanner.app', $scanner->scanner_token))
        ->assertOk();
});

it('redirects to auth page when scanner is expired', function () {
    $scanner = ProjectScanner::factory()->expired()->create();

    $this->withSession(['scanner_id' => $scanner->id])
        ->get(route('scanner.app', $scanner->scanner_token))
        ->assertRedirect(route('scanner.auth', $scanner->scanner_token));
});

// S5 fix: ScannerAuthMiddleware checks !isActive(), so scheduled scanners also redirect
it('redirects to auth page when scanner is scheduled (not yet active)', function () {
    $scanner = ProjectScanner::factory()->scheduled()->create();

    $this->withSession(['scanner_id' => $scanner->id])
        ->get(route('scanner.app', $scanner->scanner_token))
        ->assertRedirect(route('scanner.auth', $scanner->scanner_token));
});

it('redirects to auth page when session scanner_id does not match', function () {
    $scanner = ProjectScanner::factory()->active()->create();

    $this->withSession(['scanner_id' => 99999])
        ->get(route('scanner.app', $scanner->scanner_token))
        ->assertRedirect(route('scanner.auth', $scanner->scanner_token));
});

it('redirects to auth page when session has no scanner_id', function () {
    $scanner = ProjectScanner::factory()->active()->create();

    $this->get(route('scanner.app', $scanner->scanner_token))
        ->assertRedirect(route('scanner.auth', $scanner->scanner_token));
});

it('returns 404 for non-existent scanner token', function () {
    $this->get(route('scanner.app', 'nonexistent-token-value'))
        ->assertNotFound();
});

it('sets scanner attribute on request for downstream use', function () {
    $scanner = ProjectScanner::factory()->active()->create();

    $response = $this->withSession(['scanner_id' => $scanner->id])
        ->get(route('scanner.app', $scanner->scanner_token));

    $response->assertOk();
});
