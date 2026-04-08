<?php

use App\Mail\ScannerLinkMail;
use App\Models\ProjectScanner;

it('renders email with auth code when provided', function () {
    $scanner = ProjectScanner::factory()->active()->create();
    $url = route('scanner.auth', $scanner->scanner_token);

    $mail = new ScannerLinkMail($scanner, $url, '123456');

    $rendered = $mail->render();

    expect($rendered)->toContain('123456')
        ->and($rendered)->not->toContain('provided by your organizer');
});

it('renders email without auth code section when null', function () {
    $scanner = ProjectScanner::factory()->active()->create();
    $url = route('scanner.auth', $scanner->scanner_token);

    $mail = new ScannerLinkMail($scanner, $url);

    $rendered = $mail->render();

    expect($rendered)->toContain('provided by your organizer')
        ->and($rendered)->not->toContain('Your Auth Code');
});
