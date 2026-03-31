<?php

use App\Livewire\Public\EventSignup;
use App\Models\CustomFieldResponse;
use App\Models\CustomRegistrationField;
use App\Models\EmailVerificationToken;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Project;
use App\Models\ProjectGearItem;
use App\Models\Shift;
use App\Models\Volunteer;
use App\Models\VolunteerJob;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function () {
    Notification::fake();
    $this->org = Organization::factory()->create();
    $this->project = Project::factory()->for($this->org)->create();
    $this->event = Event::factory()->for($this->org)->for($this->project)->published()->create();
    $this->job = VolunteerJob::factory()->for($this->event)->create();
    $this->shift = Shift::factory()->for($this->job, 'volunteerJob')->create(['capacity' => 10]);
});

it('renders custom fields on signup form', function () {
    CustomRegistrationField::factory()->for($this->event)->create(['label' => 'Emergency Contact', 'sort_order' => 1]);
    CustomRegistrationField::factory()->select(['Vegan', 'None'])->for($this->event)->create(['label' => 'Diet', 'sort_order' => 2]);
    CustomRegistrationField::factory()->checkbox()->for($this->event)->create(['label' => 'Photo Release', 'sort_order' => 3]);

    Livewire::test(EventSignup::class, ['publicToken' => $this->event->public_token])
        ->assertSee('Emergency Contact')
        ->assertSee('Diet')
        ->assertSee('Photo Release')
        ->assertSee('Additional Information');
});

it('does not render deleted custom fields', function () {
    $field = CustomRegistrationField::factory()->for($this->event)->create(['label' => 'Deleted Field']);
    $field->delete();

    Livewire::test(EventSignup::class, ['publicToken' => $this->event->public_token])
        ->assertDontSee('Deleted Field')
        ->assertDontSee('Additional Information');
});

it('validates required custom fields on signup', function () {
    $field = CustomRegistrationField::factory()->required()->for($this->event)->create(['label' => 'Required Field']);

    Livewire::test(EventSignup::class, ['publicToken' => $this->event->public_token])
        ->set('volunteerFirstName', 'Test')
        ->set('volunteerLastName', 'Person')
        ->set('volunteerEmail', 'test@example.com')
        ->set('selectedShiftIds', [$this->shift->id])
        ->call('signup')
        ->assertHasErrors(['customFieldResponses.'.$field->id]);
});

it('completes signup flow with custom field responses through email verification', function () {
    $field = CustomRegistrationField::factory()->for($this->event)->create(['label' => 'Diet']);

    Livewire::test(EventSignup::class, ['publicToken' => $this->event->public_token])
        ->set('volunteerFirstName', 'Test')
        ->set('volunteerLastName', 'Person')
        ->set('volunteerEmail', 'newvolunteer@example.com')
        ->set('selectedShiftIds', [$this->shift->id])
        ->set('customFieldResponses.'.$field->id, 'Vegan')
        ->call('signup')
        ->assertSet('pendingVerification', true);

    $token = EmailVerificationToken::first();
    expect($token->custom_field_responses)->toBe([$field->id => 'Vegan']);
});

it('completes signup with custom fields for verified volunteer', function () {
    Volunteer::factory()->for($this->project)->verified()->create(['email' => 'verified@example.com']);
    $field = CustomRegistrationField::factory()->for($this->event)->create(['label' => 'Diet']);

    Livewire::test(EventSignup::class, ['publicToken' => $this->event->public_token])
        ->set('volunteerFirstName', 'Verified')
        ->set('volunteerLastName', 'Person')
        ->set('volunteerEmail', 'verified@example.com')
        ->set('selectedShiftIds', [$this->shift->id])
        ->set('customFieldResponses.'.$field->id, 'Vegan')
        ->call('signup')
        ->assertSet('signupComplete', true);

    expect(CustomFieldResponse::count())->toBe(1)
        ->and(CustomFieldResponse::first()->value)->toBe('Vegan');
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
