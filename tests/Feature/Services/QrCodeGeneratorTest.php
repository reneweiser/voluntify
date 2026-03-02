<?php

use App\Services\QrCodeGenerator;

it('generates SVG from a string', function () {
    $generator = app(QrCodeGenerator::class);

    $svg = $generator->generate('test-data');

    expect($svg)->toBeString()
        ->and($svg)->toContain('<svg')
        ->and($svg)->toContain('</svg>');
});

it('generates valid SVG markup', function () {
    $generator = app(QrCodeGenerator::class);

    $svg = $generator->generate('hello-world');

    expect($svg)->toContain('xmlns')
        ->and($svg)->toContain('viewBox');
});

it('generates different outputs for different inputs', function () {
    $generator = app(QrCodeGenerator::class);

    $svg1 = $generator->generate('data-one');
    $svg2 = $generator->generate('data-two');

    expect($svg1)->not->toBe($svg2);
});
