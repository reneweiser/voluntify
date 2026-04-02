<?php

use App\Actions\SendTestEmail;
use App\Models\Organization;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    $this->org = Organization::factory()->create(['name' => 'Test Organization']);
    $this->action = app(SendTestEmail::class);
});

it('sends test email to recipient via Mail::raw', function () {
    Mail::shouldReceive('mailer')
        ->once()
        ->andReturnSelf();

    Mail::shouldReceive('raw')
        ->once()
        ->withArgs(function ($text, $callback) {
            return str_contains($text, 'Test Organization');
        });

    $this->action->execute($this->org, 'recipient@example.com');
});

it('uses organization SMTP from address when configured', function () {
    $this->org->update([
        'smtp_from_address' => 'custom@org.com',
        'smtp_from_name' => 'Custom Sender',
    ]);

    $message = Mockery::mock(Message::class);
    $message->shouldReceive('to')->once()->with('recipient@example.com')->andReturnSelf();
    $message->shouldReceive('subject')->once()->with('Test email from Test Organization')->andReturnSelf();
    $message->shouldReceive('from')->once()->with('custom@org.com', 'Custom Sender')->andReturnSelf();

    Mail::shouldReceive('mailer')->once()->andReturnSelf();
    Mail::shouldReceive('raw')->once()->withArgs(function ($text, $callback) use ($message) {
        $callback($message);

        return true;
    });

    $this->action->execute($this->org, 'recipient@example.com');
});

it('falls back to default from address when org SMTP not set', function () {
    $defaultFrom = config('mail.from.address');
    $defaultName = config('mail.from.name');

    $message = Mockery::mock(Message::class);
    $message->shouldReceive('to')->once()->with('recipient@example.com')->andReturnSelf();
    $message->shouldReceive('subject')->once()->andReturnSelf();
    $message->shouldReceive('from')->once()->with($defaultFrom, $defaultName)->andReturnSelf();

    Mail::shouldReceive('mailer')->once()->andReturnSelf();
    Mail::shouldReceive('raw')->once()->withArgs(function ($text, $callback) use ($message) {
        $callback($message);

        return true;
    });

    $this->action->execute($this->org, 'recipient@example.com');
});

it('includes organization name in email body', function () {
    Mail::shouldReceive('mailer')->once()->andReturnSelf();
    Mail::shouldReceive('raw')->once()->withArgs(function ($text, $callback) {
        return str_contains($text, 'Organization: Test Organization');
    });

    $this->action->execute($this->org, 'recipient@example.com');
});
