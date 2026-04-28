<?php

use App\Actions\RequestPortalAccessLink;
use App\Models\MagicLinkToken;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Volunteer;
use App\Notifications\PortalAccessLink;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->project = Project::factory()->for($this->org)->create();
    $this->volunteer = Volunteer::factory()->for($this->project)->create([
        'email' => 'volunteer@example.com',
    ]);
});

it('generates magic link and sends notification for existing volunteer', function () {
    Notification::fake();

    $action = app(RequestPortalAccessLink::class);
    $action->execute('volunteer@example.com', $this->project);

    $token = MagicLinkToken::where('volunteer_id', $this->volunteer->id)->first();

    expect($token)->not->toBeNull()
        ->and($token->expires_at)->toBeNull();

    Notification::assertSentTo($this->volunteer, PortalAccessLink::class, function ($notification) {
        $mail = $notification->toMail($this->volunteer);
        $body = collect([...$mail->introLines, ...$mail->outroLines])->implode(' ');

        expect($body)->not->toContain('72 Stunden gültig');

        return true;
    });
});

it('does nothing for non-existent email', function () {
    Notification::fake();

    $action = app(RequestPortalAccessLink::class);
    $action->execute('nonexistent@example.com', $this->project);

    expect(MagicLinkToken::count())->toBe(0);

    Notification::assertNothingSent();
});

it('does not invalidate existing tokens', function () {
    Notification::fake();

    $existingToken = MagicLinkToken::factory()->create([
        'volunteer_id' => $this->volunteer->id,
        'expires_at' => null,
    ]);

    $action = app(RequestPortalAccessLink::class);
    $action->execute('volunteer@example.com', $this->project);

    expect($existingToken->fresh()->expires_at)->toBeNull()
        ->and(MagicLinkToken::where('volunteer_id', $this->volunteer->id)->count())->toBe(2)
        ->and(MagicLinkToken::where('volunteer_id', $this->volunteer->id)->latest('id')->first()->expires_at)->toBeNull();
});
