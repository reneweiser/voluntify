<?php

use App\Enums\CustomFieldType;
use App\Exceptions\DomainException;
use Illuminate\Validation\Rules\In;

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

    $inRule = collect($rules)->first(fn ($r) => $r instanceof In);
    expect($inRule)->not->toBeNull();
});

it('returns boolean rule for checkbox field', function () {
    $rules = CustomFieldType::Checkbox->validationRules([], false);

    expect($rules)->toContain('nullable')
        ->toContain('boolean');
});

it('validates select options must have choices', function () {
    expect(fn () => CustomFieldType::Select->validateOptions([]))
        ->toThrow(DomainException::class, 'Fields with options must have at least one choice.');
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

// --- Multi-choice tests ---

it('returns array rules for select with allow_multiple', function () {
    $rules = CustomFieldType::Select->validationRules(['choices' => ['A', 'B']], true, allowMultiple: true);

    expect($rules)->toContain('required')
        ->toContain('array')
        ->toContain('min:1')
        ->toContain('max:50');
});

it('returns array rules for checkbox with choices and allow_multiple', function () {
    $rules = CustomFieldType::Checkbox->validationRules(['choices' => ['X', 'Y']], false, allowMultiple: true);

    expect($rules)->toContain('nullable')
        ->toContain('array')
        ->toContain('max:50');
});

it('returns single-choice in-rule for checkbox with choices without allow_multiple', function () {
    $rules = CustomFieldType::Checkbox->validationRules(['choices' => ['X', 'Y']], true);

    expect($rules)->toContain('required')
        ->toContain('string');

    $inRule = collect($rules)->first(fn ($r) => $r instanceof In);
    expect($inRule)->not->toBeNull();
});

it('returns per-item validation rules for multi-choice fields', function () {
    $itemRules = CustomFieldType::Select->validationItemRules(['choices' => ['A', 'B']], allowMultiple: true);

    expect($itemRules)->not->toBeNull()
        ->toContain('string');

    $inRule = collect($itemRules)->first(fn ($r) => $r instanceof In);
    expect($inRule)->not->toBeNull();
});

it('returns null item rules for non-multi-choice fields', function () {
    expect(CustomFieldType::Text->validationItemRules([], false))->toBeNull()
        ->and(CustomFieldType::Select->validationItemRules(['choices' => ['A']], false))->toBeNull()
        ->and(CustomFieldType::Checkbox->validationItemRules([], false))->toBeNull();
});

it('casts array to JSON string for storage', function () {
    $stored = CustomFieldType::Select->castToStorage(['A', 'B']);

    expect($stored)->toBe('["A","B"]');
});

it('casts empty array to null for storage', function () {
    expect(CustomFieldType::Select->castToStorage([]))->toBeNull();
});

it('displays JSON array string as comma-separated values', function () {
    expect(CustomFieldType::Select->displayValue('["Vegan","Gluten-free"]'))
        ->toBe('Vegan, Gluten-free');
});

it('displays single checkbox choice value as-is', function () {
    expect(CustomFieldType::Checkbox->displayValue('Vegan'))->toBe('Vegan');
});

it('allows checkbox with empty choices array as single yes/no', function () {
    CustomFieldType::Checkbox->validateOptions(['choices' => []]);

    expect(true)->toBeTrue();
});

it('validates max 30 choices per field', function () {
    $choices = range(1, 31);
    expect(fn () => CustomFieldType::Select->validateOptions(['choices' => $choices]))
        ->toThrow(DomainException::class, 'A maximum of 30 choices are allowed per field.');
});

it('allows checkbox without choices to skip options validation', function () {
    CustomFieldType::Checkbox->validateOptions([]);

    expect(true)->toBeTrue();
});
