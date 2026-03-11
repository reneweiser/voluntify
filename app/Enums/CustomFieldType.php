<?php

namespace App\Enums;

use App\Exceptions\DomainException;
use Illuminate\Validation\Rule;

enum CustomFieldType: string
{
    case Text = 'text';
    case Select = 'select';
    case Checkbox = 'checkbox';

    /**
     * @return array<int, mixed>
     */
    public function validationRules(array $options, bool $required): array
    {
        $base = $required ? ['required'] : ['nullable'];

        return match ($this) {
            self::Text => [...$base, 'string', 'max:1000'],
            self::Select => [...$base, 'string', Rule::in($options['choices'] ?? [])],
            self::Checkbox => [...$base, 'boolean'],
        };
    }

    public function validateOptions(array $options): void
    {
        if ($this === self::Select) {
            $choices = $options['choices'] ?? [];
            if (empty($choices) || ! is_array($choices)) {
                throw new DomainException('Select fields must have at least one choice.');
            }
        }
    }

    public function displayValue(mixed $raw): string
    {
        return match ($this) {
            self::Checkbox => $raw ? 'Yes' : 'No',
            default => (string) ($raw ?? ''),
        };
    }

    public function castToStorage(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return match ($this) {
            self::Checkbox => $value ? '1' : '0',
            default => (string) $value,
        };
    }
}
