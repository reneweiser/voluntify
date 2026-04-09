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
    public function validationRules(array $options, bool $required, bool $allowMultiple = false): array
    {
        $base = $required ? ['required'] : ['nullable'];
        $choices = $options['choices'] ?? [];

        return match ($this) {
            self::Text => [...$base, 'string', 'max:1000'],
            self::Select => $allowMultiple && ! empty($choices)
                ? [...$base, 'array', ...($required ? ['min:1'] : []), 'max:50']
                : [...$base, 'string', Rule::in($choices)],
            self::Checkbox => ! empty($choices)
                ? ($allowMultiple
                    ? [...$base, 'array', ...($required ? ['min:1'] : []), 'max:50']
                    : [...$base, 'string', Rule::in($choices)])
                : [...$base, 'boolean'],
        };
    }

    /**
     * Per-item validation rules for multi-choice fields (applied to `field.*` wildcard).
     *
     * @return array<int, mixed>|null
     */
    public function validationItemRules(array $options, bool $allowMultiple = false): ?array
    {
        $choices = $options['choices'] ?? [];

        if (! $allowMultiple || empty($choices)) {
            return null;
        }

        if ($this === self::Select || ($this === self::Checkbox && ! empty($choices))) {
            return ['string', Rule::in($choices)];
        }

        return null;
    }

    public function validateOptions(array $options): void
    {
        if ($this === self::Select || ($this === self::Checkbox && ! empty($options['choices'] ?? []))) {
            $choices = $options['choices'] ?? [];
            if (empty($choices) || ! is_array($choices)) {
                throw new DomainException('Fields with options must have at least one choice.');
            }
            if (count($choices) > 30) {
                throw new DomainException('A maximum of 30 choices are allowed per field.');
            }
        }
    }

    public function displayValue(mixed $raw): string
    {
        if ($raw === null || $raw === '') {
            return '';
        }

        // Multi-choice: stored as JSON array string
        if (is_string($raw) && str_starts_with($raw, '[')) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return implode(', ', $decoded);
            }
        }

        return match ($this) {
            self::Checkbox => $raw === '1' || $raw === true ? 'Yes' : ($raw === '0' || $raw === false ? 'No' : (string) $raw),
            default => (string) $raw,
        };
    }

    public function castToStorage(mixed $value): ?string
    {
        if ($value === null || $value === '' || $value === []) {
            return null;
        }

        if (is_array($value)) {
            return json_encode(array_values($value));
        }

        return match ($this) {
            self::Checkbox => is_bool($value) || $value === '1' || $value === '0'
                ? ($value ? '1' : '0')
                : (string) $value,
            default => (string) $value,
        };
    }
}
