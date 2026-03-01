<?php

namespace App\Actions;

use App\Exceptions\AlreadySignedUpException;
use App\Exceptions\DomainException;
use App\Exceptions\ShiftFullException;
use App\Models\Event;
use App\Models\Shift;
use App\Models\ShiftSignup;
use App\Models\Volunteer;
use App\Notifications\SignupConfirmation;
use Illuminate\Support\Facades\DB;

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
        ?string $phone = null,
    ): array {
        if ($shift->volunteerJob->event_id !== $event->id) {
            throw new DomainException('Shift does not belong to this event.');
        }

        $result = DB::transaction(function () use ($name, $email, $phone, $event, $shift) {
            $shift = Shift::lockForUpdate()->find($shift->id);

            if ($shift->isFull()) {
                throw new ShiftFullException('This shift is full.');
            }

            $volunteer = Volunteer::firstOrCreate(
                ['email' => $email],
                ['name' => $name, 'phone' => $phone],
            );

            if ($phone !== null && $volunteer->phone !== $phone) {
                $volunteer->update(['phone' => $phone]);
            }

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

            return [
                'volunteer' => $volunteer,
                'signup' => $signup,
                'shift' => $shift,
                'plainToken' => $plainToken,
            ];
        });

        $result['shift']->load('volunteerJob');
        $result['volunteer']->notify(new SignupConfirmation($event, $result['shift'], $result['plainToken']));

        return [
            'volunteer' => $result['volunteer'],
            'signup' => $result['signup'],
        ];
    }
}
