<?php

use App\Enums\CustomFieldType;
use App\Exceptions\DomainException;

it('returns required string rules for required text field', function () {
    $rules = CustomFieldType::Text->validationRules([], true);

    expect($rules)->toContain('required')
        ->toContain('string')
        ->toContain('max:1000');
});

it('returns nullable rules for optional text field', function () {
    $rules = CustomFieldType::Text->validationRules([], false);

    expect($rules)->toContain('nullable')
        ->not->toContain('required');
});

it('returns in-rule for select field with choices', function () {
    $rules = CustomFieldType::Select->validationRules(['choices' => ['A', 'B']], true);

    expect($rules)->toContain('required')
        ->toContain('string');

    $inRule = collect($rules)->first(fn ($r) => $r instanceof \Illuminate\Validation\Rules\In);
    expect($inRule)->not->toBeNull();
});

it('returns boolean rule for checkbox field', function () {
    $rules = CustomFieldType::Checkbox->validationRules([], false);

    expect($rules)->toContain('nullable')
        ->toContain('boolean');
});

it('validates select options must have choices', function () {
    expect(fn () => CustomFieldType::Select->validateOptions([]))
        ->toThrow(DomainException::class, 'Select fields must have at least one choice.');
});

it('allows empty options for text type', function () {
    CustomFieldType::Text->validateOptions([]);

    expect(true)->toBeTrue();
});

it('formats checkbox display as Yes/No', function () {
    expect(CustomFieldType::Checkbox->displayValue(true))->toBe('Yes')
        ->and(CustomFieldType::Checkbox->displayValue(false))->toBe('No');
});

it('formats text display as string', function () {
    expect(CustomFieldType::Text->displayValue('hello'))->toBe('hello')
        ->and(CustomFieldType::Text->displayValue(null))->toBe('');
});

it('casts checkbox to storage string 1/0', function () {
    expect(CustomFieldType::Checkbox->castToStorage(true))->toBe('1')
        ->and(CustomFieldType::Checkbox->castToStorage(false))->toBe('0');
});

it('casts null to null for storage', function () {
    expect(CustomFieldType::Text->castToStorage(null))->toBeNull()
        ->and(CustomFieldType::Checkbox->castToStorage(null))->toBeNull();
});
