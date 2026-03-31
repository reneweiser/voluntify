<?php

use App\Livewire\Public\EventSignup;
use App\Models\Event;
use App\Models\EventGearItem;
use App\Models\Organization;
use App\Models\Shift;
use App\Models\ShiftSignup;
use App\Models\Ticket;
use App\Models\Volunteer;
use App\Models\VolunteerGear;
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

it('returns 404 for PublishedClosed events', function () {
    $closed = Event::factory()->for($this->org)->publishedClosed()->create();

    $this->get(route('events.public', $closed->public_token))
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
    Volunteer::factory()->verified()->create(['email' => 'john@example.com', 'first_name' => 'John', 'last_name' => 'Smith']);

    Livewire::test(EventSignup::class, ['publicToken' => $this->event->public_token])
        ->set('volunteerFirstName', 'John')
        ->set('volunteerLastName', 'Smith')
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
    Volunteer::factory()->verified()->create(['email' => 'phone@example.com', 'first_name' => 'Phone', 'last_name' => 'Person']);

    Livewire::test(EventSignup::class, ['publicToken' => $this->event->public_token])
        ->set('volunteerFirstName', 'Phone')
        ->set('volunteerLastName', 'Person')
        ->set('volunteerEmail', 'phone@example.com')
        ->set('volunteerPhone', '+15551234567')
        ->set('selectedShiftIds', [$this->shift->id])
        ->call('signup')
        ->assertHasNoErrors()
        ->assertSet('signupComplete', true);

    expect(Volunteer::where('email', 'phone@example.com')->first()->phone)->toBe('+15551234567');
});

it('submits signup without phone number for verified volunteer', function () {
    Volunteer::factory()->verified()->create(['email' => 'nophone@example.com', 'first_name' => 'No', 'last_name' => 'Phone', 'phone' => null]);

    Livewire::test(EventSignup::class, ['publicToken' => $this->event->public_token])
        ->set('volunteerFirstName', 'No')
        ->set('volunteerLastName', 'Phone')
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
        ->assertHasErrors(['volunteerFirstName', 'volunteerLastName', 'volunteerEmail', 'selectedShiftIds']);
});

it('shows error for already signed up volunteer on all selected shifts', function () {
    $volunteer = Volunteer::factory()->verified()->create(['email' => 'repeat@example.com']);
    ShiftSignup::factory()->create(['shift_id' => $this->shift->id, 'volunteer_id' => $volunteer->id]);

    Livewire::test(EventSignup::class, ['publicToken' => $this->event->public_token])
        ->set('volunteerFirstName', 'Repeat')
        ->set('volunteerLastName', 'Person')
        ->set('volunteerEmail', 'repeat@example.com')
        ->set('selectedShiftIds', [$this->shift->id])
        ->call('signup')
        ->assertHasErrors('selectedShiftIds');
});

it('shows error when all selected shifts are full for verified volunteer', function () {
    $tinyShift = Shift::factory()->for($this->job, 'volunteerJob')->create(['capacity' => 1]);
    $volunteer = Volunteer::factory()->create();
    ShiftSignup::factory()->create(['shift_id' => $tinyShift->id, 'volunteer_id' => $volunteer->id]);

    Volunteer::factory()->verified()->create(['email' => 'late@example.com', 'first_name' => 'Late', 'last_name' => 'Person']);

    Livewire::test(EventSignup::class, ['publicToken' => $this->event->public_token])
        ->set('volunteerFirstName', 'Late')
        ->set('volunteerLastName', 'Person')
        ->set('volunteerEmail', 'late@example.com')
        ->set('selectedShiftIds', [$tinyShift->id])
        ->call('signup')
        ->assertHasErrors('selectedShiftIds');
});

it('submits multi-shift signup and creates all records for verified volunteer', function () {
    $shift2 = Shift::factory()->for($this->job, 'volunteerJob')->create(['capacity' => 10]);

    Volunteer::factory()->verified()->create(['email' => 'multi@example.com', 'first_name' => 'Multi', 'last_name' => 'Shift']);

    Livewire::test(EventSignup::class, ['publicToken' => $this->event->public_token])
        ->set('volunteerFirstName', 'Multi')
        ->set('volunteerLastName', 'Shift')
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

    Volunteer::factory()->verified()->create(['email' => 'partial@example.com', 'first_name' => 'Partial', 'last_name' => 'Person']);

    Livewire::test(EventSignup::class, ['publicToken' => $this->event->public_token])
        ->set('volunteerFirstName', 'Partial')
        ->set('volunteerLastName', 'Person')
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
        ->set('volunteerFirstName', 'Mixed')
        ->set('volunteerLastName', 'Person')
        ->set('volunteerEmail', 'mixed@example.com')
        ->set('selectedShiftIds', [$this->shift->id, $fullShift->id])
        ->call('signup')
        ->assertHasErrors('selectedShiftIds');
});

it('shows check your email for new unverified volunteer', function () {
    Livewire::test(EventSignup::class, ['publicToken' => $this->event->public_token])
        ->set('volunteerFirstName', 'New')
        ->set('volunteerLastName', 'Person')
        ->set('volunteerEmail', 'newperson@example.com')
        ->set('selectedShiftIds', [$this->shift->id])
        ->call('signup')
        ->assertHasNoErrors()
        ->assertSet('pendingVerification', true)
        ->assertSee('Check Your Email');
});

it('shows gear selectors for events with gear items', function () {
    EventGearItem::factory()->sized(['S', 'M', 'L'])->for($this->event)->create(['name' => 'T-Shirt']);
    EventGearItem::factory()->for($this->event)->create(['name' => 'Badge']);

    Livewire::test(EventSignup::class, ['publicToken' => $this->event->public_token])
        ->assertSee('T-Shirt')
        ->assertSee('Badge');
});

it('validates size is required for size-required gear items', function () {
    $tshirt = EventGearItem::factory()->sized(['S', 'M', 'L'])->for($this->event)->create(['name' => 'T-Shirt']);

    Volunteer::factory()->verified()->create(['email' => 'gear-val@example.com', 'first_name' => 'Gear', 'last_name' => 'Val']);

    Livewire::test(EventSignup::class, ['publicToken' => $this->event->public_token])
        ->set('volunteerFirstName', 'Gear')
        ->set('volunteerLastName', 'Val')
        ->set('volunteerEmail', 'gear-val@example.com')
        ->set('selectedShiftIds', [$this->shift->id])
        ->call('signup')
        ->assertHasErrors(['gearSelections.'.$tshirt->id]);
});

it('creates gear records on signup with gear selections', function () {
    $tshirt = EventGearItem::factory()->sized(['S', 'M', 'L'])->for($this->event)->create(['name' => 'T-Shirt']);
    EventGearItem::factory()->for($this->event)->create(['name' => 'Badge']);

    Volunteer::factory()->verified()->create(['email' => 'gear-signup@example.com', 'first_name' => 'Gear', 'last_name' => 'Signup']);

    Livewire::test(EventSignup::class, ['publicToken' => $this->event->public_token])
        ->set('volunteerFirstName', 'Gear')
        ->set('volunteerLastName', 'Signup')
        ->set('volunteerEmail', 'gear-signup@example.com')
        ->set('selectedShiftIds', [$this->shift->id])
        ->set('gearSelections.'.$tshirt->id, 'M')
        ->call('signup')
        ->assertHasNoErrors()
        ->assertSet('signupComplete', true);

    expect(VolunteerGear::count())->toBe(2);
    expect(VolunteerGear::where('event_gear_item_id', $tshirt->id)->first()->size)->toBe('M');
});

it('shows signed up for verified volunteer', function () {
    Volunteer::factory()->verified()->create(['email' => 'verified@example.com', 'first_name' => 'Verified', 'last_name' => 'Person']);

    Livewire::test(EventSignup::class, ['publicToken' => $this->event->public_token])
        ->set('volunteerFirstName', 'Verified')
        ->set('volunteerLastName', 'Person')
        ->set('volunteerEmail', 'verified@example.com')
        ->set('selectedShiftIds', [$this->shift->id])
        ->call('signup')
        ->assertHasNoErrors()
        ->assertSet('signupComplete', true)
        ->assertSee("You're signed up!");
});

it('rejects empty phone when phone_required is true', function () {
    $phoneRequiredEvent = Event::factory()->for($this->org)->published()->create(['phone_required' => true]);
    $job = VolunteerJob::factory()->for($phoneRequiredEvent)->create();
    $shift = Shift::factory()->for($job, 'volunteerJob')->create(['capacity' => 10]);

    Livewire::test(EventSignup::class, ['publicToken' => $phoneRequiredEvent->public_token])
        ->set('volunteerFirstName', 'Test')
        ->set('volunteerLastName', 'Person')
        ->set('volunteerEmail', 'test@example.com')
        ->set('volunteerPhone', '')
        ->set('selectedShiftIds', [$shift->id])
        ->call('signup')
        ->assertHasErrors(['volunteerPhone']);
});

it('accepts empty phone when phone_required is false', function () {
    Volunteer::factory()->verified()->create(['email' => 'nophone@example.com', 'first_name' => 'No', 'last_name' => 'Phone']);

    $optionalPhoneEvent = Event::factory()->for($this->org)->published()->create(['phone_required' => false]);
    $job = VolunteerJob::factory()->for($optionalPhoneEvent)->create();
    $shift = Shift::factory()->for($job, 'volunteerJob')->create(['capacity' => 10]);

    Livewire::test(EventSignup::class, ['publicToken' => $optionalPhoneEvent->public_token])
        ->set('volunteerFirstName', 'No')
        ->set('volunteerLastName', 'Phone')
        ->set('volunteerEmail', 'nophone@example.com')
        ->set('volunteerPhone', '')
        ->set('selectedShiftIds', [$shift->id])
        ->call('signup')
        ->assertHasNoErrors()
        ->assertSet('signupComplete', true);
});
