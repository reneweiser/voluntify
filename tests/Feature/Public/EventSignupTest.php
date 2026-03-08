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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
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

it('displays title image on public page', function () {
    Storage::fake('public');

    $image = UploadedFile::fake()->image('banner.jpg');
    $path = $image->store('events/'.$this->event->id, 'public');
    $this->event->update(['title_image_path' => $path]);

    Livewire::test(EventSignup::class, ['publicToken' => $this->event->public_token])
        ->assertSeeHtml('img');
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

it('submits signup form and creates records for verified volunteer', function () {
    Volunteer::factory()->verified()->create(['email' => 'john@example.com', 'name' => 'John Smith']);

    Livewire::test(EventSignup::class, ['publicToken' => $this->event->public_token])
        ->set('volunteerName', 'John Smith')
        ->set('volunteerEmail', 'john@example.com')
        ->set('selectedShiftIds', [$this->shift->id])
        ->call('signup')
        ->assertHasNoErrors()
        ->assertSet('signupComplete', true)
        ->assertSee("You're signed up!");

    expect(ShiftSignup::where('shift_id', $this->shift->id)->count())->toBe(1)
        ->and(Ticket::where('event_id', $this->event->id)->count())->toBe(1);

    Notification::assertSentTo(
        Volunteer::where('email', 'john@example.com')->first(),
        SignupConfirmation::class,
    );
});

it('submits signup with phone number for verified volunteer', function () {
    Volunteer::factory()->verified()->create(['email' => 'phone@example.com', 'name' => 'Phone Person']);

    Livewire::test(EventSignup::class, ['publicToken' => $this->event->public_token])
        ->set('volunteerName', 'Phone Person')
        ->set('volunteerEmail', 'phone@example.com')
        ->set('volunteerPhone', '+15551234567')
        ->set('selectedShiftIds', [$this->shift->id])
        ->call('signup')
        ->assertHasNoErrors()
        ->assertSet('signupComplete', true);

    expect(Volunteer::where('email', 'phone@example.com')->first()->phone)->toBe('+15551234567');
});

it('submits signup without phone number for verified volunteer', function () {
    Volunteer::factory()->verified()->create(['email' => 'nophone@example.com', 'name' => 'No Phone', 'phone' => null]);

    Livewire::test(EventSignup::class, ['publicToken' => $this->event->public_token])
        ->set('volunteerName', 'No Phone')
        ->set('volunteerEmail', 'nophone@example.com')
        ->set('selectedShiftIds', [$this->shift->id])
        ->call('signup')
        ->assertHasNoErrors()
        ->assertSet('signupComplete', true);

    expect(Volunteer::where('email', 'nophone@example.com')->first()->phone)->toBeNull();
});

it('validates required fields', function () {
    Livewire::test(EventSignup::class, ['publicToken' => $this->event->public_token])
        ->call('signup')
        ->assertHasErrors(['volunteerName', 'volunteerEmail', 'selectedShiftIds']);
});

it('shows error for already signed up volunteer on all selected shifts', function () {
    $volunteer = Volunteer::factory()->verified()->create(['email' => 'repeat@example.com']);
    ShiftSignup::factory()->create(['shift_id' => $this->shift->id, 'volunteer_id' => $volunteer->id]);

    Livewire::test(EventSignup::class, ['publicToken' => $this->event->public_token])
        ->set('volunteerName', 'Repeat Person')
        ->set('volunteerEmail', 'repeat@example.com')
        ->set('selectedShiftIds', [$this->shift->id])
        ->call('signup')
        ->assertHasErrors('selectedShiftIds');
});

it('shows error when all selected shifts are full for verified volunteer', function () {
    $tinyShift = Shift::factory()->for($this->job, 'volunteerJob')->create(['capacity' => 1]);
    $volunteer = Volunteer::factory()->create();
    ShiftSignup::factory()->create(['shift_id' => $tinyShift->id, 'volunteer_id' => $volunteer->id]);

    Volunteer::factory()->verified()->create(['email' => 'late@example.com', 'name' => 'Late Person']);

    Livewire::test(EventSignup::class, ['publicToken' => $this->event->public_token])
        ->set('volunteerName', 'Late Person')
        ->set('volunteerEmail', 'late@example.com')
        ->set('selectedShiftIds', [$tinyShift->id])
        ->call('signup')
        ->assertHasErrors('selectedShiftIds');
});

it('submits multi-shift signup and creates all records for verified volunteer', function () {
    $shift2 = Shift::factory()->for($this->job, 'volunteerJob')->create(['capacity' => 10]);

    Volunteer::factory()->verified()->create(['email' => 'multi@example.com', 'name' => 'Multi Shift']);

    Livewire::test(EventSignup::class, ['publicToken' => $this->event->public_token])
        ->set('volunteerName', 'Multi Shift')
        ->set('volunteerEmail', 'multi@example.com')
        ->set('selectedShiftIds', [$this->shift->id, $shift2->id])
        ->call('signup')
        ->assertHasNoErrors()
        ->assertSet('signupComplete', true);

    $volunteer = Volunteer::where('email', 'multi@example.com')->first();
    expect(ShiftSignup::where('volunteer_id', $volunteer->id)->count())->toBe(2)
        ->and(Ticket::where('event_id', $this->event->id)->count())->toBe(1);

    Notification::assertSentTo($volunteer, SignupConfirmation::class, function ($notification) {
        return count($notification->shiftIds) === 2;
    });
});

it('shows warning when some shifts are skipped for verified volunteer', function () {
    $fullShift = Shift::factory()->for($this->job, 'volunteerJob')->create(['capacity' => 1]);
    $otherVolunteer = Volunteer::factory()->create();
    ShiftSignup::factory()->create(['shift_id' => $fullShift->id, 'volunteer_id' => $otherVolunteer->id]);

    Volunteer::factory()->verified()->create(['email' => 'partial@example.com', 'name' => 'Partial Person']);

    Livewire::test(EventSignup::class, ['publicToken' => $this->event->public_token])
        ->set('volunteerName', 'Partial Person')
        ->set('volunteerEmail', 'partial@example.com')
        ->set('selectedShiftIds', [$this->shift->id, $fullShift->id])
        ->call('signup')
        ->assertHasNoErrors()
        ->assertSet('signupComplete', true)
        ->assertSee('Some shifts were skipped');
});

it('shows all-duplicate error for mixed duplicate and full shifts', function () {
    $fullShift = Shift::factory()->for($this->job, 'volunteerJob')->create(['capacity' => 1]);
    $otherVolunteer = Volunteer::factory()->create();
    ShiftSignup::factory()->create(['shift_id' => $fullShift->id, 'volunteer_id' => $otherVolunteer->id]);

    $volunteer = Volunteer::factory()->verified()->create(['email' => 'mixed@example.com']);
    ShiftSignup::factory()->create(['shift_id' => $this->shift->id, 'volunteer_id' => $volunteer->id]);

    Livewire::test(EventSignup::class, ['publicToken' => $this->event->public_token])
        ->set('volunteerName', 'Mixed')
        ->set('volunteerEmail', 'mixed@example.com')
        ->set('selectedShiftIds', [$this->shift->id, $fullShift->id])
        ->call('signup')
        ->assertHasErrors('selectedShiftIds');
});

it('shows check your email for new unverified volunteer', function () {
    Livewire::test(EventSignup::class, ['publicToken' => $this->event->public_token])
        ->set('volunteerName', 'New Person')
        ->set('volunteerEmail', 'newperson@example.com')
        ->set('selectedShiftIds', [$this->shift->id])
        ->call('signup')
        ->assertHasNoErrors()
        ->assertSet('pendingVerification', true)
        ->assertSee('Check Your Email');
});

it('shows gear selectors for events with gear items', function () {
    \App\Models\EventGearItem::factory()->sized(['S', 'M', 'L'])->for($this->event)->create(['name' => 'T-Shirt']);
    \App\Models\EventGearItem::factory()->for($this->event)->create(['name' => 'Badge']);

    Livewire::test(EventSignup::class, ['publicToken' => $this->event->public_token])
        ->assertSee('T-Shirt')
        ->assertSee('Badge');
});

it('validates size is required for size-required gear items', function () {
    $tshirt = \App\Models\EventGearItem::factory()->sized(['S', 'M', 'L'])->for($this->event)->create(['name' => 'T-Shirt']);

    Volunteer::factory()->verified()->create(['email' => 'gear-val@example.com', 'name' => 'Gear Val']);

    Livewire::test(EventSignup::class, ['publicToken' => $this->event->public_token])
        ->set('volunteerName', 'Gear Val')
        ->set('volunteerEmail', 'gear-val@example.com')
        ->set('selectedShiftIds', [$this->shift->id])
        ->call('signup')
        ->assertHasErrors(['gearSelections.'.$tshirt->id]);
});

it('creates gear records on signup with gear selections', function () {
    $tshirt = \App\Models\EventGearItem::factory()->sized(['S', 'M', 'L'])->for($this->event)->create(['name' => 'T-Shirt']);
    \App\Models\EventGearItem::factory()->for($this->event)->create(['name' => 'Badge']);

    Volunteer::factory()->verified()->create(['email' => 'gear-signup@example.com', 'name' => 'Gear Signup']);

    Livewire::test(EventSignup::class, ['publicToken' => $this->event->public_token])
        ->set('volunteerName', 'Gear Signup')
        ->set('volunteerEmail', 'gear-signup@example.com')
        ->set('selectedShiftIds', [$this->shift->id])
        ->set('gearSelections.'.$tshirt->id, 'M')
        ->call('signup')
        ->assertHasNoErrors()
        ->assertSet('signupComplete', true);

    expect(\App\Models\VolunteerGear::count())->toBe(2);
    expect(\App\Models\VolunteerGear::where('event_gear_item_id', $tshirt->id)->first()->size)->toBe('M');
});

it('shows signed up for verified volunteer', function () {
    Volunteer::factory()->verified()->create(['email' => 'verified@example.com', 'name' => 'Verified']);

    Livewire::test(EventSignup::class, ['publicToken' => $this->event->public_token])
        ->set('volunteerName', 'Verified')
        ->set('volunteerEmail', 'verified@example.com')
        ->set('selectedShiftIds', [$this->shift->id])
        ->call('signup')
        ->assertHasNoErrors()
        ->assertSet('signupComplete', true)
        ->assertSee("You're signed up!");
});
