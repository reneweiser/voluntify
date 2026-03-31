<?php

use App\Models\Project;
use App\Models\Ticket;
use App\Models\Volunteer;
use Illuminate\Database\UniqueConstraintViolationException;

it('enforces unique volunteer per project', function () {
    $project = Project::factory()->create();
    $volunteer = Volunteer::factory()->for($project)->create();

    Ticket::factory()->for($volunteer)->for($project, 'project')->create();

    expect(fn () => Ticket::factory()->for($volunteer)->for($project, 'project')->create())
        ->toThrow(UniqueConstraintViolationException::class);
});

it('generates QR code SVG from JWT token', function () {
    $ticket = Ticket::factory()->create();

    $svg = $ticket->qrCodeSvg();

    expect($svg)->toBeString()
        ->and($svg)->toContain('<svg')
        ->and($svg)->toContain('</svg>');
});
