<?php

use App\Models\Event;
use App\Models\Organization;
use App\Models\Shift;
use App\Models\Volunteer;
use App\Models\VolunteerJob;
use App\Notifications\SignupConfirmation;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->event = Event::factory()->for($this->org)->create([
        'name' => 'Summer Fest',
        'location' => 'Central Park',
    ]);
    $this->job = VolunteerJob::factory()->for($this->event)->create(['name' => 'Gate Security']);
    $this->shift = Shift::factory()->for($this->job, 'volunteerJob')->create();
    $this->volunteer = Volunteer::factory()->create(['name' => 'Jane Doe']);
});

it('sends email with event details', function () {
    Notification::fake();

    $this->volunteer->notify(new SignupConfirmation($this->event, $this->shift, 'test-token'));

    Notification::assertSentTo($this->volunteer, SignupConfirmation::class, function ($notification) {
        $mail = $notification->toMail($this->volunteer);

        expect($mail->subject)->toBe("You're signed up for Summer Fest!")
            ->and(implode(' ', $mail->introLines))->toContain('Summer Fest')
            ->and(implode(' ', $mail->introLines))->toContain('Gate Security')
            ->and(implode(' ', $mail->introLines))->toContain('Central Park');

        return true;
    });
});

it('is queued', function () {
    expect(new SignupConfirmation($this->event, $this->shift, 'token'))
        ->toBeInstanceOf(\Illuminate\Contracts\Queue\ShouldQueue::class);
});
