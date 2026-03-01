<?php

use App\Livewire\Public\EventSignup;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Shift;
use App\Models\ShiftSignup;
use App\Models\Ticket;
use App\Models\Volunteer;
use App\Models\VolunteerJob;
use App\Notifications\SignupConfirmation;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function () {
    Notification::fake();

    $this->org = Organization::factory()->create();
    $this->event = Event::factory()->for($this->org)->published()->create([
        'name' => 'Community Cleanup',
        'location' => 'City Park',
    ]);
    $this->job = VolunteerJob::factory()->for($this->event)->create(['name' => 'Litter Pickup']);
    $this->shift = Shift::factory()->for($this->job, 'volunteerJob')->create(['capacity' => 10]);
});

it('renders published event via public token', function () {
    $this->get(route('events.public', $this->event->public_token))
        ->assertOk()
        ->assertSeeLivewire(EventSignup::class);
});

it('shows event info on public page', function () {
    Livewire::test(EventSignup::class, ['publicToken' => $this->event->public_token])
        ->assertSee('Community Cleanup')
        ->assertSee('City Park')
        ->assertSee('Litter Pickup');
});

it('returns 404 for draft events', function () {
    $draft = Event::factory()->for($this->org)->create();

    $this->get(route('events.public', $draft->public_token))
        ->assertNotFound();
});

it('returns 404 for archived events', function () {
    $archived = Event::factory()->for($this->org)->archived()->create();

    $this->get(route('events.public', $archived->public_token))
        ->assertNotFound();
});

it('returns 404 for invalid token', function () {
    $this->get(route('events.public', 'nonexistent-token'))
        ->assertNotFound();
});

it('shows shifts with capacity info', function () {
    Livewire::test(EventSignup::class, ['publicToken' => $this->event->public_token])
        ->assertSee('10 spots remaining')
        ->assertSee('Open');
});

it('shows full badge for shifts at capacity', function () {
    $fullShift = Shift::factory()->for($this->job, 'volunteerJob')->create(['capacity' => 1]);
    $volunteer = Volunteer::factory()->create();
    ShiftSignup::factory()->create(['shift_id' => $fullShift->id, 'volunteer_id' => $volunteer->id]);

    Livewire::test(EventSignup::class, ['publicToken' => $this->event->public_token])
        ->assertSee('Full');
});

it('submits signup form and creates records', function () {
    Livewire::test(EventSignup::class, ['publicToken' => $this->event->public_token])
        ->set('volunteerName', 'John Smith')
        ->set('volunteerEmail', 'john@example.com')
        ->set('selectedShiftId', $this->shift->id)
        ->call('signup')
        ->assertHasNoErrors()
        ->assertSet('signupComplete', true)
        ->assertSee("You're signed up!");

    expect(Volunteer::where('email', 'john@example.com')->exists())->toBeTrue()
        ->and(ShiftSignup::where('shift_id', $this->shift->id)->count())->toBe(1)
        ->and(Ticket::where('event_id', $this->event->id)->count())->toBe(1);

    Notification::assertSentTo(
        Volunteer::where('email', 'john@example.com')->first(),
        SignupConfirmation::class,
    );
});

it('validates required fields', function () {
    Livewire::test(EventSignup::class, ['publicToken' => $this->event->public_token])
        ->call('signup')
        ->assertHasErrors(['volunteerName', 'volunteerEmail', 'selectedShiftId']);
});

it('shows error for already signed up volunteer', function () {
    $volunteer = Volunteer::factory()->create(['email' => 'repeat@example.com']);
    ShiftSignup::factory()->create(['shift_id' => $this->shift->id, 'volunteer_id' => $volunteer->id]);

    Livewire::test(EventSignup::class, ['publicToken' => $this->event->public_token])
        ->set('volunteerName', 'Repeat Person')
        ->set('volunteerEmail', 'repeat@example.com')
        ->set('selectedShiftId', $this->shift->id)
        ->call('signup')
        ->assertHasErrors('volunteerEmail');
});

it('shows error when shift becomes full during signup', function () {
    $tinyShift = Shift::factory()->for($this->job, 'volunteerJob')->create(['capacity' => 1]);
    $volunteer = Volunteer::factory()->create();
    ShiftSignup::factory()->create(['shift_id' => $tinyShift->id, 'volunteer_id' => $volunteer->id]);

    Livewire::test(EventSignup::class, ['publicToken' => $this->event->public_token])
        ->set('volunteerName', 'Late Person')
        ->set('volunteerEmail', 'late@example.com')
        ->set('selectedShiftId', $tinyShift->id)
        ->call('signup')
        ->assertHasErrors('selectedShiftId');
});
