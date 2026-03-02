<?php

namespace App\Actions;

use App\Exceptions\AlreadySignedUpException;
use App\Exceptions\ShiftFullException;
use App\Models\Event;
use App\Models\Shift;
use App\Models\ShiftSignup;
use App\Models\Volunteer;

class SignUpVolunteer
{
    public function __construct(
        private SignUpVolunteerForShifts $batchAction,
    ) {}

    /**
     * @return array{volunteer: Volunteer, signup: ShiftSignup}
     */
    public function execute(
        string $name,
        string $email,
        Event $event,
        Shift $shift,
        ?string $phone = null,
    ): array {
        $result = $this->batchAction->execute(
            name: $name,
            email: $email,
            event: $event,
            shiftIds: [$shift->id],
            phone: $phone,
        );

        if (count($result->skippedFull) > 0) {
            throw new ShiftFullException('This shift is full.');
        }

        if (count($result->skippedDuplicate) > 0) {
            throw new AlreadySignedUpException('You are already signed up for this shift.');
        }

        return [
            'volunteer' => $result->volunteer,
            'signup' => $result->newSignups[0],
        ];
    }
}
