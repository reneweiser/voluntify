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

it('records multi-choice select field with array values as JSON', function () {
    $field = CustomRegistrationField::factory()->select(['A', 'B', 'C'])->allowMultiple()->for($this->event)->create();

    $action = app(RecordCustomFieldResponses::class);
    $action->execute($this->volunteer, $this->event, [
        $field->id => ['A', 'B'],
    ]);

    expect(CustomFieldResponse::first()->value)->toBe('["A","B"]');
});

it('throws DomainException for invalid item in multi-choice array', function () {
    $field = CustomRegistrationField::factory()->select(['A', 'B'])->allowMultiple()->for($this->event)->create();

    $action = app(RecordCustomFieldResponses::class);

    expect(fn () => $action->execute($this->volunteer, $this->event, [
        $field->id => ['A', 'INVALID'],
    ]))->toThrow(DomainException::class);
});

it('rejects multi-choice array exceeding 50 items', function () {
    $choices = array_map(fn ($i) => "Item{$i}", range(1, 60));
    $field = CustomRegistrationField::factory()->select($choices)->allowMultiple()->for($this->event)->create();

    $action = app(RecordCustomFieldResponses::class);

    expect(fn () => $action->execute($this->volunteer, $this->event, [
        $field->id => array_slice($choices, 0, 51),
    ]))->toThrow(DomainException::class);
});

it('records checkbox field with choices as single value', function () {
    $field = CustomRegistrationField::factory()->checkboxWithChoices(['X', 'Y'])->for($this->event)->create();

    $action = app(RecordCustomFieldResponses::class);
    $action->execute($this->volunteer, $this->event, [
        $field->id => 'X',
    ]);

    expect(CustomFieldResponse::first()->value)->toBe('X');
});
