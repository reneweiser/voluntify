<?php

use App\Actions\RecordCustomFieldResponses;
use App\Exceptions\DomainException;
use App\Models\CustomFieldResponse;
use App\Models\CustomRegistrationField;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Volunteer;

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->event = Event::factory()->for($this->org)->published()->create();
    $this->volunteer = Volunteer::factory()->create();
});

it('creates response records for each field', function () {
    $field1 = CustomRegistrationField::factory()->for($this->event)->create(['label' => 'Diet']);
    $field2 = CustomRegistrationField::factory()->for($this->event)->create(['label' => 'Notes']);

    $action = app(RecordCustomFieldResponses::class);
    $action->execute($this->volunteer, $this->event, [
        $field1->id => 'Vegan',
        $field2->id => 'No allergies',
    ]);

    expect(CustomFieldResponse::count())->toBe(2);
    expect(CustomFieldResponse::where('custom_registration_field_id', $field1->id)->first()->value)->toBe('Vegan');
});

it('casts checkbox value to storage format', function () {
    $field = CustomRegistrationField::factory()->checkbox()->for($this->event)->create();

    $action = app(RecordCustomFieldResponses::class);
    $action->execute($this->volunteer, $this->event, [
        $field->id => true,
    ]);

    expect(CustomFieldResponse::first()->value)->toBe('1');
});

it('uses updateOrCreate for idempotent re-signup', function () {
    $field = CustomRegistrationField::factory()->for($this->event)->create();

    $action = app(RecordCustomFieldResponses::class);
    $action->execute($this->volunteer, $this->event, [$field->id => 'First']);
    $action->execute($this->volunteer, $this->event, [$field->id => 'Updated']);

    expect(CustomFieldResponse::count())->toBe(1)
        ->and(CustomFieldResponse::first()->value)->toBe('Updated');
});

it('stores null for optional fields with no response', function () {
    $field = CustomRegistrationField::factory()->for($this->event)->create(['required' => false]);

    $action = app(RecordCustomFieldResponses::class);
    $action->execute($this->volunteer, $this->event, [
        $field->id => null,
    ]);

    expect(CustomFieldResponse::first()->value)->toBeNull();
});

it('validates select value against choices', function () {
    $field = CustomRegistrationField::factory()->select(['A', 'B'])->for($this->event)->create();

    $action = app(RecordCustomFieldResponses::class);

    expect(fn () => $action->execute($this->volunteer, $this->event, [
        $field->id => 'C',
    ]))->toThrow(DomainException::class);
});
