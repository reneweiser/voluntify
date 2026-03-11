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
        $fields = $event->customRegistrationFields()->get();

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
        if ($field->type === CustomFieldType::Select && $value !== null && $value !== '') {
            $choices = $field->options['choices'] ?? [];
            if (! in_array($value, $choices, true)) {
                throw new DomainException("Invalid value \"{$value}\" for field \"{$field->label}\".");
            }
        }
    }
}
