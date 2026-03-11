<?php

use App\Enums\CustomFieldType;
use App\Models\CustomFieldResponse;
use App\Models\CustomRegistrationField;
use App\Models\EmailVerificationToken;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Volunteer;

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->event = Event::factory()->for($this->org)->create();
});

it('belongs to an event', function () {
    $field = CustomRegistrationField::factory()->for($this->event)->create();

    expect($field->event->id)->toBe($this->event->id);
});

it('has many responses', function () {
    $field = CustomRegistrationField::factory()->for($this->event)->create();
    $volunteer = Volunteer::factory()->create();
    CustomFieldResponse::factory()->create([
        'custom_registration_field_id' => $field->id,
        'volunteer_id' => $volunteer->id,
    ]);

    expect($field->responses)->toHaveCount(1);
});

it('casts type to CustomFieldType enum', function () {
    $field = CustomRegistrationField::factory()->for($this->event)->create(['type' => 'select']);

    expect($field->type)->toBe(CustomFieldType::Select);
});

it('casts options to array', function () {
    $field = CustomRegistrationField::factory()->for($this->event)->select(['X', 'Y'])->create();

    expect($field->options)->toBe(['choices' => ['X', 'Y']]);
});

it('soft deletes without removing record', function () {
    $field = CustomRegistrationField::factory()->for($this->event)->create();

    $field->delete();

    expect(CustomRegistrationField::withTrashed()->find($field->id))->not->toBeNull()
        ->and(CustomRegistrationField::find($field->id))->toBeNull();
});

it('event has custom registration fields ordered by sort_order', function () {
    CustomRegistrationField::factory()->for($this->event)->create(['sort_order' => 2, 'label' => 'Second']);
    CustomRegistrationField::factory()->for($this->event)->create(['sort_order' => 1, 'label' => 'First']);

    $fields = $this->event->customRegistrationFields;

    expect($fields->first()->label)->toBe('First')
        ->and($fields->last()->label)->toBe('Second');
});

it('volunteer has custom field responses', function () {
    $volunteer = Volunteer::factory()->create();
    $field = CustomRegistrationField::factory()->for($this->event)->create();
    CustomFieldResponse::factory()->create([
        'custom_registration_field_id' => $field->id,
        'volunteer_id' => $volunteer->id,
        'value' => 'test',
    ]);

    expect($volunteer->customFieldResponses)->toHaveCount(1)
        ->and($volunteer->customFieldResponses->first()->value)->toBe('test');
});

it('volunteer withCustomFields scope eager loads for event and includes trashed', function () {
    $volunteer = Volunteer::factory()->create();
    $field = CustomRegistrationField::factory()->for($this->event)->create();
    $trashedField = CustomRegistrationField::factory()->for($this->event)->create();
    CustomFieldResponse::factory()->create([
        'custom_registration_field_id' => $field->id,
        'volunteer_id' => $volunteer->id,
    ]);
    CustomFieldResponse::factory()->create([
        'custom_registration_field_id' => $trashedField->id,
        'volunteer_id' => $volunteer->id,
    ]);

    $trashedField->delete();

    $loaded = Volunteer::withCustomFields($this->event->id)->find($volunteer->id);

    expect($loaded->customFieldResponses)->toHaveCount(2);
    expect($loaded->customFieldResponses->pluck('field'))->each->not->toBeNull();
});

it('email verification token stores custom_field_responses as array', function () {
    $token = EmailVerificationToken::factory()->create([
        'custom_field_responses' => ['1' => 'hello', '2' => '1'],
    ]);

    expect($token->fresh()->custom_field_responses)->toBe(['1' => 'hello', '2' => '1']);
});
