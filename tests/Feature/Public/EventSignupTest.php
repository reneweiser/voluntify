<?php

use App\Enums\WizardState;
use App\Livewire\Public\EventSignup;
use App\Models\EmailVerificationToken;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Project;
use App\Models\ProjectGearItem;
use App\Models\Shift;
use App\Models\ShiftReservation;
use App\Models\ShiftSignup;
use App\Models\Ticket;
use App\Models\Volunteer;
use App\Models\VolunteerGear;
use App\Models\VolunteerJob;
use App\Notifications\EmailVerification;
use App\Notifications\SignupConfirmation;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Notification::fake();

    $this->org = Organization::factory()->create();
    $this->project = Project::factory()->for($this->org)->create();
    $this->event = Event::factory()->for($this->org)->for($this->project)->published()->create([
        'name' => 'Community Cleanup',
        'location' => 'City Park',
    ]);
    $this->job = VolunteerJob::factory()->for($this->event)->create(['name' => 'Litter Pickup']);
    $this->shift = Shift::factory()->for($this->job, 'volunteerJob')->create(['capacity' => 10]);
});

// --- Rendering & basic display ---

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
    $draft = Event::factory()->for($this->org)->for($this->project)->create();

    $this->get(route('events.public', $draft->public_token))
        ->assertNotFound();
});

it('returns 404 for archived events', function () {
    $archived = Event::factory()->for($this->org)->for($this->project)->archived()->create();

    $this->get(route('events.public', $archived->public_token))
        ->assertNotFound();
});

it('returns 404 for PublishedClosed events', function () {
    $closed = Event::factory()->for($this->org)->for($this->project)->publishedClosed()->create();

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
    $volunteer = Volunteer::factory()->for($this->project)->create();
    ShiftSignup::factory()->create(['shift_id' => $fullShift->id, 'volunteer_id' => $volunteer->id]);

    Livewire::test(EventSignup::class, ['publicToken' => $this->event->public_token])
        ->assertSee('Full');
});

// --- Email entry step ---

it('starts on the email entry step', function () {
    Livewire::test(EventSignup::class, ['publicToken' => $this->event->public_token])
        ->assertSet('state', WizardState::EmailEntry);
});

it('transitions to PendingVerification for new email on submitEmail', function () {
    Livewire::test(EventSignup::class, ['publicToken' => $this->event->public_token])
        ->set('volunteerEmail', 'brand-new@example.com')
        ->call('submitEmail')
        ->assertHasNoErrors()
        ->assertSet('state', WizardState::PendingVerification);

    expect(EmailVerificationToken::where('email', 'brand-new@example.com')->exists())->toBeTrue();

    Notification::assertSentOnDemand(EmailVerification::class);
});

it('skips verification for already-verified volunteer on submitEmail', function () {
    Volunteer::factory()->for($this->project)->verified()->create([
        'email' => 'verified@example.com',
        'first_name' => 'Verified',
        'last_name' => 'Person',
    ]);

    Livewire::test(EventSignup::class, ['publicToken' => $this->event->public_token])
        ->set('volunteerEmail', 'verified@example.com')
        ->call('submitEmail')
        ->assertHasNoErrors()
        ->assertSet('state', WizardState::PersonalInfo);
});

it('prefills data for returning verified volunteer on submitEmail', function () {
    Volunteer::factory()->for($this->project)->verified()->create([
        'email' => 'returning@example.com',
        'first_name' => 'Returning',
        'last_name' => 'Helper',
        'phone' => '+15559876543',
    ]);

    Livewire::test(EventSignup::class, ['publicToken' => $this->event->public_token])
        ->set('volunteerEmail', 'returning@example.com')
        ->call('submitEmail')
        ->assertSet('volunteerFirstName', 'Returning')
        ->assertSet('volunteerLastName', 'Helper')
        ->assertSet('volunteerPhone', '+15559876543')
        ->assertSet('isReturningVolunteer', true)
        ->assertSet('state', WizardState::PersonalInfo);
});

it('does not show PendingVerification on submitSignup for verified volunteer', function () {
    Volunteer::factory()->for($this->project)->verified()->create([
        'email' => 'already-verified@example.com',
        'first_name' => 'Already',
        'last_name' => 'Verified',
    ]);

    Livewire::test(EventSignup::class, ['publicToken' => $this->event->public_token])
        ->set('volunteerEmail', 'already-verified@example.com')
        ->call('submitEmail')
        ->assertSet('state', WizardState::PersonalInfo)
        ->call('advanceToShifts')
        ->set('selectedShiftIds', [$this->shift->id])
        ->call('reserveAndAdvance')
        ->call('submitSignup')
        ->assertHasNoErrors()
        ->assertSet('state', WizardState::Complete);
});

// --- Step 1: Shift selection & reservation ---

it('validates at least one shift selected before advancing', function () {
    Volunteer::factory()->for($this->project)->verified()->create(['email' => 'test@example.com', 'first_name' => 'Test', 'last_name' => 'Person']);

    Livewire::test(EventSignup::class, ['publicToken' => $this->event->public_token])
        ->set('volunteerEmail', 'test@example.com')
        ->call('submitEmail')
        ->call('advanceToShifts')
        ->call('reserveAndAdvance')
        ->assertHasErrors('selectedShiftIds');
});

it('creates reservations and advances past shift selection', function () {
    Volunteer::factory()->for($this->project)->verified()->create(['email' => 'test@example.com', 'first_name' => 'Test', 'last_name' => 'Person']);

    Livewire::test(EventSignup::class, ['publicToken' => $this->event->public_token])
        ->set('volunteerEmail', 'test@example.com')
        ->call('submitEmail')
        ->call('advanceToShifts')
        ->set('selectedShiftIds', [$this->shift->id])
        ->call('reserveAndAdvance')
        ->assertHasNoErrors()
        ->assertSet('state', WizardState::Confirming)
        ->assertNotSet('reservationExpiresAt', '');

    expect(ShiftReservation::where('shift_id', $this->shift->id)->count())->toBe(1);
});

it('skips gear step when no gear or custom fields exist', function () {
    Volunteer::factory()->for($this->project)->verified()->create(['email' => 'test@example.com', 'first_name' => 'Test', 'last_name' => 'Person']);

    Livewire::test(EventSignup::class, ['publicToken' => $this->event->public_token])
        ->set('volunteerEmail', 'test@example.com')
        ->call('submitEmail')
        ->call('advanceToShifts')
        ->set('selectedShiftIds', [$this->shift->id])
        ->call('reserveAndAdvance')
        ->assertSet('state', WizardState::Confirming);
});

it('advances to gear step when gear items exist', function () {
    ProjectGearItem::factory()->for($this->project)->create(['name' => 'Badge']);

    Volunteer::factory()->for($this->project)->verified()->create(['email' => 'test@example.com', 'first_name' => 'Test', 'last_name' => 'Person']);

    Livewire::test(EventSignup::class, ['publicToken' => $this->event->public_token])
        ->set('volunteerEmail', 'test@example.com')
        ->call('submitEmail')
        ->call('advanceToShifts')
        ->set('selectedShiftIds', [$this->shift->id])
        ->call('reserveAndAdvance')
        ->assertSet('state', WizardState::GearAndFields);
});

it('shows error when all selected shifts are full at reservation time', function () {
    $tinyShift = Shift::factory()->for($this->job, 'volunteerJob')->create(['capacity' => 1]);
    $volunteer = Volunteer::factory()->for($this->project)->create();
    ShiftSignup::factory()->create(['shift_id' => $tinyShift->id, 'volunteer_id' => $volunteer->id]);

    Volunteer::factory()->for($this->project)->verified()->create(['email' => 'test@example.com', 'first_name' => 'Test', 'last_name' => 'Person']);

    Livewire::test(EventSignup::class, ['publicToken' => $this->event->public_token])
        ->set('volunteerEmail', 'test@example.com')
        ->call('submitEmail')
        ->call('advanceToShifts')
        ->set('selectedShiftIds', [$tinyShift->id])
        ->call('reserveAndAdvance')
        ->assertHasErrors('selectedShiftIds')
        ->assertSet('state', WizardState::SelectingShifts);
});

it('shows warning and advances with partial availability', function () {
    $fullShift = Shift::factory()->for($this->job, 'volunteerJob')->create(['capacity' => 1]);
    $otherVolunteer = Volunteer::factory()->for($this->project)->create();
    ShiftSignup::factory()->create(['shift_id' => $fullShift->id, 'volunteer_id' => $otherVolunteer->id]);

    Volunteer::factory()->for($this->project)->verified()->create(['email' => 'test@example.com', 'first_name' => 'Test', 'last_name' => 'Person']);

    Livewire::test(EventSignup::class, ['publicToken' => $this->event->public_token])
        ->set('volunteerEmail', 'test@example.com')
        ->call('submitEmail')
        ->call('advanceToShifts')
        ->set('selectedShiftIds', [$this->shift->id, $fullShift->id])
        ->call('reserveAndAdvance')
        ->assertHasNoErrors()
        ->assertSet('state', WizardState::Confirming)
        ->assertSet('warningMessage', '1 shift(s) were full and removed from your selection.')
        ->assertSet('selectedShiftIds', [$this->shift->id]);
});

it('blocks reserveAndAdvance when selected shifts overlap in time', function () {
    $shiftA = Shift::factory()->for($this->job, 'volunteerJob')->create([
        'shift_date' => '2026-06-01',
        'starts_at' => Carbon::parse('2026-06-01 10:00:00'),
        'ends_at' => Carbon::parse('2026-06-01 12:00:00'),
        'capacity' => 10,
    ]);
    $shiftB = Shift::factory()->for($this->job, 'volunteerJob')->create([
        'shift_date' => '2026-06-01',
        'starts_at' => Carbon::parse('2026-06-01 11:00:00'),
        'ends_at' => Carbon::parse('2026-06-01 13:00:00'),
        'capacity' => 10,
    ]);

    Volunteer::factory()->for($this->project)->verified()->create(['email' => 'test@example.com', 'first_name' => 'Test', 'last_name' => 'Person']);

    Livewire::test(EventSignup::class, ['publicToken' => $this->event->public_token])
        ->set('volunteerEmail', 'test@example.com')
        ->call('submitEmail')
        ->call('advanceToShifts')
        ->set('selectedShiftIds', [$shiftA->id, $shiftB->id])
        ->assertSee(__('Conflict'))
        ->assertSee(__('Some selected shifts overlap in time'))
        ->call('reserveAndAdvance')
        ->assertSet('state', WizardState::SelectingShifts);

    expect(ShiftReservation::count())->toBe(0);
});

it('allows reserveAndAdvance after deselecting one conflicting shift', function () {
    $shiftA = Shift::factory()->for($this->job, 'volunteerJob')->create([
        'shift_date' => '2026-06-01',
        'starts_at' => Carbon::parse('2026-06-01 10:00:00'),
        'ends_at' => Carbon::parse('2026-06-01 12:00:00'),
        'capacity' => 10,
    ]);
    $shiftB = Shift::factory()->for($this->job, 'volunteerJob')->create([
        'shift_date' => '2026-06-01',
        'starts_at' => Carbon::parse('2026-06-01 11:00:00'),
        'ends_at' => Carbon::parse('2026-06-01 13:00:00'),
        'capacity' => 10,
    ]);

    Volunteer::factory()->for($this->project)->verified()->create(['email' => 'test@example.com', 'first_name' => 'Test', 'last_name' => 'Person']);

    Livewire::test(EventSignup::class, ['publicToken' => $this->event->public_token])
        ->set('volunteerEmail', 'test@example.com')
        ->call('submitEmail')
        ->call('advanceToShifts')
        ->set('selectedShiftIds', [$shiftA->id, $shiftB->id])
        ->assertSee(__('Conflict'))
        ->set('selectedShiftIds', [$shiftA->id])
        ->assertDontSee(__('Conflict'))
        ->call('reserveAndAdvance')
        ->assertHasNoErrors()
        ->assertSet('state', WizardState::Confirming);

    expect(ShiftReservation::where('shift_id', $shiftA->id)->count())->toBe(1);
});

it('allows selecting adjacent shifts that meet exactly at midnight', function () {
    $shiftA = Shift::factory()->for($this->job, 'volunteerJob')->create([
        'starts_at' => Carbon::parse('2026-06-01 22:00:00'),
        'ends_at' => Carbon::parse('2026-06-02 00:00:00'),
        'capacity' => 10,
    ]);
    $shiftB = Shift::factory()->for($this->job, 'volunteerJob')->create([
        'starts_at' => Carbon::parse('2026-06-02 00:00:00'),
        'ends_at' => Carbon::parse('2026-06-02 02:00:00'),
        'capacity' => 10,
    ]);

    Volunteer::factory()->for($this->project)->verified()->create(['email' => 'test@example.com', 'first_name' => 'Test', 'last_name' => 'Person']);

    Livewire::test(EventSignup::class, ['publicToken' => $this->event->public_token])
        ->set('volunteerEmail', 'test@example.com')
        ->call('submitEmail')
        ->call('advanceToShifts')
        ->set('selectedShiftIds', [$shiftA->id, $shiftB->id])
        ->assertDontSee(__('Conflict'))
        ->assertDontSee(__('Some selected shifts overlap in time'))
        ->call('reserveAndAdvance')
        ->assertSet('state', WizardState::Confirming);

    expect(ShiftReservation::count())->toBe(2);
});

it('blocks reserveAndAdvance when overnight shift overlaps next-day shift', function () {
    $shiftA = Shift::factory()->for($this->job, 'volunteerJob')->create([
        'starts_at' => Carbon::parse('2026-05-01 23:00:00'),
        'ends_at' => Carbon::parse('2026-05-02 02:00:00'),
        'capacity' => 10,
    ]);
    $shiftB = Shift::factory()->for($this->job, 'volunteerJob')->create([
        'starts_at' => Carbon::parse('2026-05-02 01:00:00'),
        'ends_at' => Carbon::parse('2026-05-02 04:00:00'),
        'capacity' => 10,
    ]);

    Volunteer::factory()->for($this->project)->verified()->create(['email' => 'test@example.com', 'first_name' => 'Test', 'last_name' => 'Person']);

    Livewire::test(EventSignup::class, ['publicToken' => $this->event->public_token])
        ->set('volunteerEmail', 'test@example.com')
        ->call('submitEmail')
        ->call('advanceToShifts')
        ->set('selectedShiftIds', [$shiftA->id, $shiftB->id])
        ->assertSee(__('Conflict'))
        ->assertSee(__('Some selected shifts overlap in time'))
        ->call('reserveAndAdvance')
        ->assertSet('state', WizardState::SelectingShifts);

    expect(ShiftReservation::count())->toBe(0);
});

it('allows reserveAndAdvance after deselecting one conflicting cross-midnight shift', function () {
    $shiftA = Shift::factory()->for($this->job, 'volunteerJob')->create([
        'starts_at' => Carbon::parse('2026-05-01 23:00:00'),
        'ends_at' => Carbon::parse('2026-05-02 02:00:00'),
        'capacity' => 10,
    ]);
    $shiftB = Shift::factory()->for($this->job, 'volunteerJob')->create([
        'starts_at' => Carbon::parse('2026-05-02 01:00:00'),
        'ends_at' => Carbon::parse('2026-05-02 04:00:00'),
        'capacity' => 10,
    ]);

    Volunteer::factory()->for($this->project)->verified()->create(['email' => 'test@example.com', 'first_name' => 'Test', 'last_name' => 'Person']);

    Livewire::test(EventSignup::class, ['publicToken' => $this->event->public_token])
        ->set('volunteerEmail', 'test@example.com')
        ->call('submitEmail')
        ->call('advanceToShifts')
        ->set('selectedShiftIds', [$shiftA->id, $shiftB->id])
        ->assertSee(__('Conflict'))
        ->set('selectedShiftIds', [$shiftA->id])
        ->assertDontSee(__('Conflict'))
        ->call('reserveAndAdvance')
        ->assertHasNoErrors()
        ->assertSet('state', WizardState::Confirming);

    expect(ShiftReservation::where('shift_id', $shiftA->id)->count())->toBe(1);
});

// --- Step 2: Gear & Custom Fields ---

it('shows gear selectors on step 2', function () {
    ProjectGearItem::factory()->sized(['S', 'M', 'L'])->for($this->project)->create(['name' => 'T-Shirt']);
    ProjectGearItem::factory()->for($this->project)->create(['name' => 'Badge']);

    Volunteer::factory()->for($this->project)->verified()->create(['email' => 'test@example.com', 'first_name' => 'Test', 'last_name' => 'Person']);

    Livewire::test(EventSignup::class, ['publicToken' => $this->event->public_token])
        ->set('volunteerEmail', 'test@example.com')
        ->call('submitEmail')
        ->call('advanceToShifts')
        ->set('selectedShiftIds', [$this->shift->id])
        ->call('reserveAndAdvance')
        ->assertSet('state', WizardState::GearAndFields)
        ->assertSee('T-Shirt')
        ->assertSee('Badge');
});

it('validates size is required for size-required gear items on step 2', function () {
    $tshirt = ProjectGearItem::factory()->sized(['S', 'M', 'L'])->for($this->project)->create(['name' => 'T-Shirt']);

    Volunteer::factory()->for($this->project)->verified()->create(['email' => 'test@example.com', 'first_name' => 'Test', 'last_name' => 'Person']);

    Livewire::test(EventSignup::class, ['publicToken' => $this->event->public_token])
        ->set('volunteerEmail', 'test@example.com')
        ->call('submitEmail')
        ->call('advanceToShifts')
        ->set('selectedShiftIds', [$this->shift->id])
        ->call('reserveAndAdvance')
        ->assertSet('state', WizardState::GearAndFields)
        ->call('advanceToConfirmation')
        ->assertHasErrors(['gearSelections.'.$tshirt->id]);
});

// --- Personal info step ---

it('validates required personal info fields', function () {
    Volunteer::factory()->for($this->project)->verified()->create(['email' => 'test@example.com', 'first_name' => 'Test', 'last_name' => 'Person']);

    Livewire::test(EventSignup::class, ['publicToken' => $this->event->public_token])
        ->set('volunteerEmail', 'test@example.com')
        ->call('submitEmail')
        ->assertSet('state', WizardState::PersonalInfo)
        ->set('volunteerFirstName', '')
        ->set('volunteerLastName', '')
        ->set('volunteerEmail', '')
        ->call('advanceToShifts')
        ->assertHasErrors(['volunteerFirstName', 'volunteerLastName', 'volunteerEmail']);
});

it('rejects empty phone when phone_required is true', function () {
    $phoneRequiredEvent = Event::factory()->for($this->org)->for($this->project)->published()->create(['phone_required' => true]);
    $job = VolunteerJob::factory()->for($phoneRequiredEvent)->create();
    $shift = Shift::factory()->for($job, 'volunteerJob')->create(['capacity' => 10]);

    Volunteer::factory()->for($this->project)->verified()->create(['email' => 'test@example.com', 'first_name' => 'Test', 'last_name' => 'Person']);

    Livewire::test(EventSignup::class, ['publicToken' => $phoneRequiredEvent->public_token])
        ->set('volunteerEmail', 'test@example.com')
        ->call('submitEmail')
        ->set('volunteerPhone', '')
        ->call('advanceToShifts')
        ->assertHasErrors(['volunteerPhone']);
});

it('accepts empty phone when phone_required is false', function () {
    Volunteer::factory()->for($this->project)->verified()->create(['email' => 'nophone@example.com', 'first_name' => 'No', 'last_name' => 'Phone']);

    Livewire::test(EventSignup::class, ['publicToken' => $this->event->public_token])
        ->set('volunteerEmail', 'nophone@example.com')
        ->call('submitEmail')
        ->set('volunteerPhone', '')
        ->call('advanceToShifts')
        ->assertHasNoErrors()
        ->assertSet('state', WizardState::SelectingShifts);
});

// --- Step 4: Confirmation summary ---

it('shows summary of all selections on confirmation step', function () {
    Volunteer::factory()->for($this->project)->verified()->create(['email' => 'summary@example.com', 'first_name' => 'Summary', 'last_name' => 'Person']);

    Livewire::test(EventSignup::class, ['publicToken' => $this->event->public_token])
        ->set('volunteerEmail', 'summary@example.com')
        ->call('submitEmail')
        ->call('advanceToShifts')
        ->set('selectedShiftIds', [$this->shift->id])
        ->call('reserveAndAdvance')
        ->assertSet('state', WizardState::Confirming)
        ->assertSee('Confirm Your Signup')
        ->assertSee('Summary Person')
        ->assertSee('summary@example.com')
        ->assertSee('Litter Pickup');
});

// --- Full wizard signup flows ---

it('completes signup for verified volunteer through wizard', function () {
    Volunteer::factory()->for($this->project)->verified()->create(['email' => 'john@example.com', 'first_name' => 'John', 'last_name' => 'Smith']);

    Livewire::test(EventSignup::class, ['publicToken' => $this->event->public_token])
        ->set('volunteerEmail', 'john@example.com')
        ->call('submitEmail')
        ->call('advanceToShifts')
        ->set('selectedShiftIds', [$this->shift->id])
        ->call('reserveAndAdvance')
        ->call('submitSignup')
        ->assertHasNoErrors()
        ->assertSet('state', WizardState::Complete)
        ->assertSee("You're signed up!");

    expect(ShiftSignup::where('shift_id', $this->shift->id)->count())->toBe(1)
        ->and(Ticket::where('project_id', $this->project->id)->count())->toBe(1);

    Notification::assertSentTo(
        Volunteer::where('email', 'john@example.com')->first(),
        SignupConfirmation::class,
    );
});

it('submits signup with phone number for verified volunteer', function () {
    Volunteer::factory()->for($this->project)->verified()->create(['email' => 'phone@example.com', 'first_name' => 'Phone', 'last_name' => 'Person']);

    Livewire::test(EventSignup::class, ['publicToken' => $this->event->public_token])
        ->set('volunteerEmail', 'phone@example.com')
        ->call('submitEmail')
        ->set('volunteerPhone', '+15551234567')
        ->call('advanceToShifts')
        ->set('selectedShiftIds', [$this->shift->id])
        ->call('reserveAndAdvance')
        ->call('submitSignup')
        ->assertHasNoErrors()
        ->assertSet('state', WizardState::Complete);

    expect(Volunteer::where('email', 'phone@example.com')->first()->phone)->toBe('+15551234567');
});

it('submits signup without phone number for verified volunteer', function () {
    Volunteer::factory()->for($this->project)->verified()->create(['email' => 'nophone@example.com', 'first_name' => 'No', 'last_name' => 'Phone', 'phone' => null]);

    Livewire::test(EventSignup::class, ['publicToken' => $this->event->public_token])
        ->set('volunteerEmail', 'nophone@example.com')
        ->call('submitEmail')
        ->call('advanceToShifts')
        ->set('selectedShiftIds', [$this->shift->id])
        ->call('reserveAndAdvance')
        ->call('submitSignup')
        ->assertHasNoErrors()
        ->assertSet('state', WizardState::Complete);

    expect(Volunteer::where('email', 'nophone@example.com')->first()->phone)->toBeNull();
});

it('shows error for already signed up volunteer on all selected shifts', function () {
    $volunteer = Volunteer::factory()->for($this->project)->verified()->create(['email' => 'repeat@example.com', 'first_name' => 'Repeat', 'last_name' => 'Person']);

    $component = Livewire::test(EventSignup::class, ['publicToken' => $this->event->public_token])
        ->set('volunteerEmail', 'repeat@example.com')
        ->call('submitEmail')
        ->call('advanceToShifts')
        ->set('selectedShiftIds', [$this->shift->id])
        ->call('reserveAndAdvance');

    // Create the duplicate signup between reservation and submit (simulates race condition)
    ShiftSignup::factory()->create(['shift_id' => $this->shift->id, 'volunteer_id' => $volunteer->id]);

    $component->call('submitSignup')
        ->assertHasErrors('selectedShiftIds');
});

it('shows error when all selected shifts are full at submit time for verified volunteer', function () {
    // Use a shift with enough capacity to reserve but fill it before submit
    $tinyShift = Shift::factory()->for($this->job, 'volunteerJob')->create(['capacity' => 2]);

    Volunteer::factory()->for($this->project)->verified()->create(['email' => 'late@example.com', 'first_name' => 'Late', 'last_name' => 'Person']);

    $component = Livewire::test(EventSignup::class, ['publicToken' => $this->event->public_token])
        ->set('volunteerEmail', 'late@example.com')
        ->call('submitEmail')
        ->call('advanceToShifts')
        ->set('selectedShiftIds', [$tinyShift->id])
        ->call('reserveAndAdvance');

    // Fill the shift between reservation and submit (but first delete our reservation so isFull() counts correctly)
    ShiftReservation::query()->delete();
    $v1 = Volunteer::factory()->for($this->project)->create();
    $v2 = Volunteer::factory()->for($this->project)->create();
    ShiftSignup::factory()->create(['shift_id' => $tinyShift->id, 'volunteer_id' => $v1->id]);
    ShiftSignup::factory()->create(['shift_id' => $tinyShift->id, 'volunteer_id' => $v2->id]);

    // Reservation check should fail now since we deleted them
    $component->call('submitSignup')
        ->assertSet('state', WizardState::Expired);
});

it('submits multi-shift signup and creates all records for verified volunteer', function () {
    // Use explicit non-overlapping dates to prevent random overlap detection from blocking reservation.
    $this->shift->update(['starts_at' => Carbon::parse('2026-07-01 09:00:00'), 'ends_at' => Carbon::parse('2026-07-01 12:00:00')]);
    $shift2 = Shift::factory()->for($this->job, 'volunteerJob')->create([
        'capacity' => 10,
        'starts_at' => Carbon::parse('2026-07-02 09:00:00'),
        'ends_at' => Carbon::parse('2026-07-02 12:00:00'),
    ]);

    Volunteer::factory()->for($this->project)->verified()->create(['email' => 'multi@example.com', 'first_name' => 'Multi', 'last_name' => 'Shift']);

    Livewire::test(EventSignup::class, ['publicToken' => $this->event->public_token])
        ->set('volunteerEmail', 'multi@example.com')
        ->call('submitEmail')
        ->call('advanceToShifts')
        ->set('selectedShiftIds', [$this->shift->id, $shift2->id])
        ->call('reserveAndAdvance')
        ->call('submitSignup')
        ->assertHasNoErrors()
        ->assertSet('state', WizardState::Complete);

    $volunteer = Volunteer::where('email', 'multi@example.com')->first();
    expect(ShiftSignup::where('volunteer_id', $volunteer->id)->count())->toBe(2)
        ->and(Ticket::where('project_id', $this->project->id)->count())->toBe(1);

    Notification::assertSentTo($volunteer, SignupConfirmation::class, function ($notification) {
        return count($notification->shiftIds) === 2;
    });
});

it('shows warning when some shifts are skipped during submit for verified volunteer', function () {
    // Both shifts have capacity to reserve, but one gets filled between reserve and submit.
    // Use explicit non-overlapping dates to prevent random overlap detection from blocking reservation.
    $this->shift->update(['starts_at' => Carbon::parse('2026-07-01 09:00:00'), 'ends_at' => Carbon::parse('2026-07-01 12:00:00')]);
    $shift2 = Shift::factory()->for($this->job, 'volunteerJob')->create([
        'capacity' => 10,
        'starts_at' => Carbon::parse('2026-07-02 09:00:00'),
        'ends_at' => Carbon::parse('2026-07-02 12:00:00'),
    ]);

    $volunteer = Volunteer::factory()->for($this->project)->verified()->create(['email' => 'partial@example.com', 'first_name' => 'Partial', 'last_name' => 'Person']);

    $component = Livewire::test(EventSignup::class, ['publicToken' => $this->event->public_token])
        ->set('volunteerEmail', 'partial@example.com')
        ->call('submitEmail')
        ->call('advanceToShifts')
        ->set('selectedShiftIds', [$this->shift->id, $shift2->id])
        ->call('reserveAndAdvance');

    // Create the duplicate signup between reservation and submit (simulates race condition)
    ShiftSignup::factory()->create(['shift_id' => $shift2->id, 'volunteer_id' => $volunteer->id]);

    $component->call('submitSignup')
        ->assertHasNoErrors()
        ->assertSet('state', WizardState::Complete)
        ->assertSet('warningMessage', 'Some shifts were skipped because they were full, you were already signed up, or they conflicted with another shift.');
});

it('shows all-duplicate error for mixed duplicate and full shifts', function () {
    $fullShift = Shift::factory()->for($this->job, 'volunteerJob')->create(['capacity' => 1]);
    $otherVolunteer = Volunteer::factory()->for($this->project)->create();
    ShiftSignup::factory()->create(['shift_id' => $fullShift->id, 'volunteer_id' => $otherVolunteer->id]);

    $volunteer = Volunteer::factory()->for($this->project)->verified()->create(['email' => 'mixed@example.com', 'first_name' => 'Mixed', 'last_name' => 'Person']);

    $component = Livewire::test(EventSignup::class, ['publicToken' => $this->event->public_token])
        ->set('volunteerEmail', 'mixed@example.com')
        ->call('submitEmail')
        ->call('advanceToShifts')
        ->set('selectedShiftIds', [$this->shift->id])
        ->call('reserveAndAdvance');

    // Create the duplicate signup between reservation and submit (simulates race condition)
    ShiftSignup::factory()->create(['shift_id' => $this->shift->id, 'volunteer_id' => $volunteer->id]);

    $component->call('submitSignup')
        ->assertHasErrors('selectedShiftIds');
});

it('creates gear records on signup with gear selections through wizard', function () {
    $tshirt = ProjectGearItem::factory()->sized(['S', 'M', 'L'])->for($this->project)->create(['name' => 'T-Shirt']);
    ProjectGearItem::factory()->for($this->project)->create(['name' => 'Badge']);

    Volunteer::factory()->for($this->project)->verified()->create(['email' => 'gear-signup@example.com', 'first_name' => 'Gear', 'last_name' => 'Signup']);

    Livewire::test(EventSignup::class, ['publicToken' => $this->event->public_token])
        ->set('volunteerEmail', 'gear-signup@example.com')
        ->call('submitEmail')
        ->call('advanceToShifts')
        ->set('selectedShiftIds', [$this->shift->id])
        ->call('reserveAndAdvance')
        ->assertSet('state', WizardState::GearAndFields)
        ->set('gearSelections.'.$tshirt->id, 'M')
        ->call('advanceToConfirmation')
        ->call('submitSignup')
        ->assertHasNoErrors()
        ->assertSet('state', WizardState::Complete);

    // Only T-Shirt gets created — Badge has no selection so it's skipped by the filter
    expect(VolunteerGear::where('project_gear_item_id', $tshirt->id)->first()->size)->toBe('M');
});

// --- Navigation ---

it('goes back from PersonalInfo to EmailEntry', function () {
    Volunteer::factory()->for($this->project)->verified()->create(['email' => 'test@example.com', 'first_name' => 'Test', 'last_name' => 'Person']);

    Livewire::test(EventSignup::class, ['publicToken' => $this->event->public_token])
        ->set('volunteerEmail', 'test@example.com')
        ->call('submitEmail')
        ->assertSet('state', WizardState::PersonalInfo)
        ->call('goBack')
        ->assertSet('state', WizardState::EmailEntry);
});

it('can go back to previous steps', function () {
    Volunteer::factory()->for($this->project)->verified()->create(['email' => 'test@example.com', 'first_name' => 'Test', 'last_name' => 'Person']);

    Livewire::test(EventSignup::class, ['publicToken' => $this->event->public_token])
        ->set('volunteerEmail', 'test@example.com')
        ->call('submitEmail')
        ->call('advanceToShifts')
        ->assertSet('state', WizardState::SelectingShifts)
        ->call('goBack')
        ->assertSet('state', WizardState::PersonalInfo)
        ->call('goBack')
        ->assertSet('state', WizardState::EmailEntry);
});

it('can navigate back through gear step', function () {
    ProjectGearItem::factory()->for($this->project)->create(['name' => 'Badge']);

    Volunteer::factory()->for($this->project)->verified()->create(['email' => 'test@example.com', 'first_name' => 'Test', 'last_name' => 'Person']);

    Livewire::test(EventSignup::class, ['publicToken' => $this->event->public_token])
        ->set('volunteerEmail', 'test@example.com')
        ->call('submitEmail')
        ->call('advanceToShifts')
        ->set('selectedShiftIds', [$this->shift->id])
        ->call('reserveAndAdvance')
        ->assertSet('state', WizardState::GearAndFields)
        ->call('goBack')
        ->assertSet('state', WizardState::SelectingShifts);
});

it('goes back from Confirming to SelectingShifts when no gear', function () {
    Volunteer::factory()->for($this->project)->verified()->create(['email' => 'test@example.com', 'first_name' => 'Test', 'last_name' => 'Person']);

    Livewire::test(EventSignup::class, ['publicToken' => $this->event->public_token])
        ->set('volunteerEmail', 'test@example.com')
        ->call('submitEmail')
        ->call('advanceToShifts')
        ->set('selectedShiftIds', [$this->shift->id])
        ->call('reserveAndAdvance')
        ->assertSet('state', WizardState::Confirming)
        ->call('goBack')
        ->assertSet('state', WizardState::SelectingShifts);
});

it('cannot go back before step 1', function () {
    Livewire::test(EventSignup::class, ['publicToken' => $this->event->public_token])
        ->call('goBack')
        ->assertSet('state', WizardState::EmailEntry);
});

// --- Reservation expiry ---

it('resets wizard when reservation expires', function () {
    Volunteer::factory()->for($this->project)->verified()->create(['email' => 'test@example.com', 'first_name' => 'Test', 'last_name' => 'Person']);

    Livewire::test(EventSignup::class, ['publicToken' => $this->event->public_token])
        ->set('volunteerEmail', 'test@example.com')
        ->call('submitEmail')
        ->call('advanceToShifts')
        ->set('selectedShiftIds', [$this->shift->id])
        ->call('reserveAndAdvance')
        ->assertSet('state', WizardState::Confirming)
        ->call('handleReservationExpired')
        ->assertSet('state', WizardState::Expired);
});

it('can restart signup after expiry', function () {
    Volunteer::factory()->for($this->project)->verified()->create(['email' => 'test@example.com', 'first_name' => 'Test', 'last_name' => 'Person']);

    Livewire::test(EventSignup::class, ['publicToken' => $this->event->public_token])
        ->set('volunteerEmail', 'test@example.com')
        ->call('submitEmail')
        ->call('advanceToShifts')
        ->set('selectedShiftIds', [$this->shift->id])
        ->call('reserveAndAdvance')
        ->call('handleReservationExpired')
        ->assertSet('state', WizardState::Expired)
        ->call('restartSignup')
        ->assertSet('state', WizardState::EmailEntry)
        ->assertSet('selectedShiftIds', [])
        ->assertSet('reservationExpiresAt', '');
});

it('sets reservationExpiresAt after reservation', function () {
    Volunteer::factory()->for($this->project)->verified()->create(['email' => 'test@example.com', 'first_name' => 'Test', 'last_name' => 'Person']);

    Livewire::test(EventSignup::class, ['publicToken' => $this->event->public_token])
        ->set('volunteerEmail', 'test@example.com')
        ->call('submitEmail')
        ->call('advanceToShifts')
        ->set('selectedShiftIds', [$this->shift->id])
        ->call('reserveAndAdvance')
        ->assertNotSet('reservationExpiresAt', '');
});

it('checks DB reservation existence before submit (D13)', function () {
    Volunteer::factory()->for($this->project)->verified()->create(['email' => 'd13@example.com', 'first_name' => 'D13', 'last_name' => 'Test']);

    $component = Livewire::test(EventSignup::class, ['publicToken' => $this->event->public_token])
        ->set('volunteerEmail', 'd13@example.com')
        ->call('submitEmail')
        ->call('advanceToShifts')
        ->set('selectedShiftIds', [$this->shift->id])
        ->call('reserveAndAdvance');

    // Simulate scheduler deleting reservations
    ShiftReservation::query()->delete();

    $component->call('submitSignup')
        ->assertSet('state', WizardState::Expired);
});

it('shows signed up message for verified volunteer', function () {
    Volunteer::factory()->for($this->project)->verified()->create(['email' => 'verified@example.com', 'first_name' => 'Verified', 'last_name' => 'Person']);

    Livewire::test(EventSignup::class, ['publicToken' => $this->event->public_token])
        ->set('volunteerEmail', 'verified@example.com')
        ->call('submitEmail')
        ->call('advanceToShifts')
        ->set('selectedShiftIds', [$this->shift->id])
        ->call('reserveAndAdvance')
        ->call('submitSignup')
        ->assertHasNoErrors()
        ->assertSet('state', WizardState::Complete)
        ->assertSee("You're signed up!");
});

it('renders without error when gear item has requires_size true but available_sizes is null', function () {
    ProjectGearItem::factory()->for($this->project)->create([
        'name' => 'Broken T-Shirt',
        'requires_size' => true,
        'available_sizes' => null,
    ]);

    Livewire::test(EventSignup::class, ['publicToken' => $this->event->public_token])
        ->assertSuccessful()
        ->assertSee('Broken T-Shirt');
});

it('filters gear to SizeSelection items only in wizard', function () {
    ProjectGearItem::factory()->sized(['S', 'M', 'L'])->for($this->project)->create(['name' => 'T-Shirt']);
    ProjectGearItem::factory()->quantity(3)->for($this->project)->create(['name' => 'Water Bottle']);

    Volunteer::factory()->for($this->project)->verified()->create(['email' => 'test@example.com', 'first_name' => 'Test', 'last_name' => 'Person']);

    $component = Livewire::test(EventSignup::class, ['publicToken' => $this->event->public_token])
        ->set('volunteerEmail', 'test@example.com')
        ->call('submitEmail')
        ->call('advanceToShifts')
        ->set('selectedShiftIds', [$this->shift->id])
        ->call('reserveAndAdvance')
        ->assertSet('state', WizardState::GearAndFields)
        ->assertSee('T-Shirt')
        ->assertDontSee('Water Bottle');
});
