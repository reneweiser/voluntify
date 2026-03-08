<?php

use App\Livewire\Events\EventShow;
use App\Models\Event;
use App\Models\Organization;
use Livewire\Livewire;

beforeEach(function () {
    ['user' => $this->user, 'organization' => $this->org] = createUserWithOrganization();
    app()->instance(Organization::class, $this->org);
    $this->event = Event::factory()->for($this->org)->create();
});

it('can set cancellation_cutoff_hours when editing event', function () {
    Livewire::actingAs($this->user)
        ->test(EventShow::class, ['eventId' => $this->event->id])
        ->call('startEditing')
        ->set('cancellationCutoffHours', 48)
        ->call('saveEvent')
        ->assertHasNoErrors();

    expect($this->event->fresh()->cancellation_cutoff_hours)->toBe(48);
});

it('validates range 1-168 when provided', function () {
    Livewire::actingAs($this->user)
        ->test(EventShow::class, ['eventId' => $this->event->id])
        ->call('startEditing')
        ->set('cancellationCutoffHours', 200)
        ->call('saveEvent')
        ->assertHasErrors(['cancellationCutoffHours']);
});

it('accepts null for cancellation disabled', function () {
    $this->event->update(['cancellation_cutoff_hours' => 24]);

    Livewire::actingAs($this->user)
        ->test(EventShow::class, ['eventId' => $this->event->id])
        ->call('startEditing')
        ->set('cancellationCutoffHours', '')
        ->call('saveEvent')
        ->assertHasNoErrors();

    expect($this->event->fresh()->cancellation_cutoff_hours)->toBeNull();
});

it('value persists after save', function () {
    Livewire::actingAs($this->user)
        ->test(EventShow::class, ['eventId' => $this->event->id])
        ->call('startEditing')
        ->set('cancellationCutoffHours', 24)
        ->call('saveEvent')
        ->assertHasNoErrors();

    // Reload and check it shows the value
    Livewire::actingAs($this->user)
        ->test(EventShow::class, ['eventId' => $this->event->id])
        ->call('startEditing')
        ->assertSet('cancellationCutoffHours', 24);
});
