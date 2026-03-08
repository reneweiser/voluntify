<?php

namespace App\Actions;

use App\Events\Activity\VolunteerSignedUp;
use App\Models\Event;
use App\Models\Volunteer;
use App\ValueObjects\SignupOutcome;

class ProcessVolunteerSignup
{
    public function __construct(
        private SignUpVolunteerForShifts $signUpAction,
        private SendEmailVerification $sendVerification,
        private AssignGearToVolunteer $assignGear,
    ) {}

    /**
     * @param  array<int>  $shiftIds
     * @param  array<int, string|null>|null  $gearSelections
     */
    public function execute(
        string $name,
        string $email,
        Event $event,
        array $shiftIds,
        ?string $phone = null,
        ?array $gearSelections = null,
    ): SignupOutcome {
        $volunteer = Volunteer::firstOrCreate(
            ['email' => $email],
            ['name' => $name, 'phone' => $phone],
        );

        if ($phone !== null && $volunteer->phone !== $phone) {
            $volunteer->update(['phone' => $phone]);
        }

        if ($volunteer->isEmailVerified()) {
            $result = $this->signUpAction->execute(
                name: $name,
                email: $email,
                event: $event,
                shiftIds: $shiftIds,
                phone: $phone,
            );

            if ($gearSelections !== null) {
                $this->assignGear->execute($volunteer, $event, $gearSelections);
            }

            VolunteerSignedUp::dispatch($volunteer, $event, count($shiftIds));

            return SignupOutcome::completed($result);
        }

        $this->sendVerification->execute($volunteer, $event, $shiftIds, $gearSelections);

        return SignupOutcome::pendingVerification($email);
    }
}
