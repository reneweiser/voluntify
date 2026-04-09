<?php

namespace App\Actions;

use App\Enums\CustomFieldType;
use App\Exceptions\DomainException;
use App\Models\CustomFieldResponse;
use App\Models\CustomRegistrationField;
use App\Models\Event;
use App\Models\Volunteer;

class RecordCustomFieldResponses
{
    /**
     * @param  array<int, mixed>  $responses  Keyed by CustomRegistrationField ID => value
     */
    public function execute(Volunteer $volunteer, Event $event, array $responses): void
    {
        $projectFields = $event->project?->customRegistrationFields()->get() ?? collect();
        $eventFields = $event->customRegistrationFields()->get();
        $fields = $projectFields->merge($eventFields);

        foreach ($fields as $field) {
            $rawValue = $responses[$field->id] ?? null;

            $this->validateResponse($field, $rawValue);

            $storedValue = $field->type->castToStorage($rawValue);

            CustomFieldResponse::updateOrCreate(
                [
                    'custom_registration_field_id' => $field->id,
                    'volunteer_id' => $volunteer->id,
                ],
                ['value' => $storedValue],
            );
        }
    }

    private function validateResponse(CustomRegistrationField $field, mixed $value): void
    {
        $choices = $field->options['choices'] ?? [];

        if (empty($choices)) {
            return;
        }

        if ($field->allow_multiple) {
            if (is_array($value)) {
                if (count($value) > 50) {
                    throw new DomainException("Too many selections for field \"{$field->label}\".");
                }
                foreach ($value as $item) {
                    if (! in_array($item, $choices, true)) {
                        throw new DomainException("Invalid value \"{$item}\" for field \"{$field->label}\".");
                    }
                }
            }

            return;
        }

        // Single-choice: validate against choices
        if (($field->type === CustomFieldType::Select || $field->type === CustomFieldType::Checkbox) && $value !== null && $value !== '') {
            if (! in_array($value, $choices, true)) {
                throw new DomainException("Invalid value \"{$value}\" for field \"{$field->label}\".");
            }
        }
    }
}
