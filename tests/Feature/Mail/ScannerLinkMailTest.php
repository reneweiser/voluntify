<?php

use App\Mail\ScannerLinkMail;
use App\Models\ProjectScanner;

it('renders email with auth code', function () {
    $scanner = ProjectScanner::factory()->active()->create();
    $url = route('scanner.auth', $scanner->scanner_token);

    $mail = new ScannerLinkMail($scanner, $url, '123456');

    $rendered = $mail->render();

    expect($rendered)->toContain('123456')
        ->and($rendered)->not->toContain('provided by your organizer');
});

it('shows regeneration prompt for legacy bcrypt auth code', function () {
    $scanner = ProjectScanner::factory()->active()->create();
    $url = route('scanner.auth', $scanner->scanner_token);

    $mail = new ScannerLinkMail($scanner, $url, '$2y$12$somebcrypthashvalue');

    $rendered = $mail->render();

    expect($rendered)->toContain('regenerate')
        ->and($rendered)->not->toContain('$2y$12$');
});
