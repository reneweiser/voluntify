<?php

use App\Models\Event;
use App\Models\Organization;
use App\Models\Shift;
use App\Models\Volunteer;
use App\Models\VolunteerJob;
use App\Notifications\SignupConfirmation;
use Illuminate\Mail\Markdown;

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->event = Event::factory()->for($this->org)->create(['name' => 'Summer Fest']);
    $this->job = VolunteerJob::factory()->for($this->event)->create(['name' => 'Gate Security']);
    $this->shift = Shift::factory()->for($this->job, 'volunteerJob')->create();
    $this->volunteer = Volunteer::factory()->create(['name' => 'Jane Doe']);
});

function renderNotificationHtml(object $volunteer, SignupConfirmation $notification): string
{
    $mailMessage = $notification->toMail($volunteer);

    return app(Markdown::class)->render(
        $mailMessage->markdown ?? 'notifications::email',
        $mailMessage->data(),
    )->toHtml();
}

it('includes the Voluntify logo as base64 data URI', function () {
    $notification = new SignupConfirmation($this->event, [$this->shift->id], 'test-token');
    $html = renderNotificationHtml($this->volunteer, $notification);

    expect($html)->toContain('data:image/svg+xml;base64,');
});

it('uses emerald brand color', function () {
    $notification = new SignupConfirmation($this->event, [$this->shift->id], 'test-token');
    $html = renderNotificationHtml($this->volunteer, $notification);

    expect($html)->toContain('#059669');
});

it('includes the branded tagline in footer', function () {
    $notification = new SignupConfirmation($this->event, [$this->shift->id], 'test-token');
    $html = renderNotificationHtml($this->volunteer, $notification);

    expect($html)->toContain('Volunteer management made simple.');
});

it('includes Voluntify in the header', function () {
    $notification = new SignupConfirmation($this->event, [$this->shift->id], 'test-token');
    $html = renderNotificationHtml($this->volunteer, $notification);

    expect($html)->toContain('Voluntify');
});

it('includes Google Fonts link', function () {
    $notification = new SignupConfirmation($this->event, [$this->shift->id], 'test-token');
    $html = renderNotificationHtml($this->volunteer, $notification);

    expect($html)->toContain('fonts.googleapis.com');
});
