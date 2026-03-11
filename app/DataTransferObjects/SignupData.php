<?php

namespace App\DataTransferObjects;

use App\Models\Event;

readonly class SignupData
{
    /**
     * @param  array<int>  $shiftIds
     * @param  array<int, string|null>|null  $gearSelections
     * @param  array<int, mixed>|null  $customFieldResponses
     */
    public function __construct(
        public string $name,
        public string $email,
        public Event $event,
        public array $shiftIds,
        public ?string $phone = null,
        public ?array $gearSelections = null,
        public ?array $customFieldResponses = null,
    ) {}
}
