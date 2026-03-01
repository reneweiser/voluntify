<?php

namespace App\Actions;

use App\Exceptions\AlreadySignedUpException;
use App\Exceptions\ShiftFullException;
use App\Models\Event;
use App\Models\Shift;
use App\Models\ShiftSignup;
use App\Models\Volunteer;
use App\Notifications\SignupConfirmation;

class SignUpVolunteer
{
    public function __construct(
        private GenerateTicket $generateTicket,
        private GenerateMagicLink $generateMagicLink,
    ) {}

    /**
     * @return array{volunteer: Volunteer, signup: ShiftSignup}
     */
    public function execute(
        string $name,
        string $email,
        Event $event,
        Shift $shift,
    ): array {
        if ($shift->isFull()) {
            throw new ShiftFullException('This shift is full.');
        }

        $volunteer = Volunteer::firstOrCreate(
            ['email' => $email],
            ['name' => $name],
        );

        $existingSignup = ShiftSignup::where('volunteer_id', $volunteer->id)
            ->where('shift_id', $shift->id)
            ->exists();

        if ($existingSignup) {
            throw new AlreadySignedUpException('You are already signed up for this shift.');
        }

        $signup = ShiftSignup::create([
            'volunteer_id' => $volunteer->id,
            'shift_id' => $shift->id,
            'signed_up_at' => now(),
        ]);

        $this->generateTicket->execute($volunteer, $event);

        ['plainToken' => $plainToken] = $this->generateMagicLink->execute($volunteer);

        $volunteer->notify(new SignupConfirmation($event, $shift, $plainToken));

        return [
            'volunteer' => $volunteer,
            'signup' => $signup,
        ];
    }
}
