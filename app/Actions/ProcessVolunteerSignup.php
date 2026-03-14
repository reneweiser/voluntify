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
        private RecordCustomFieldResponses $recordCustomFields,
    ) {}

    /**
     * @param  array<int>  $shiftIds
     * @param  array<int, string|null>|null  $gearSelections
     * @param  array<int, mixed>|null  $customFieldResponses
     */
    public function execute(
        string $name,
        string $email,
        Event $event,
        array $shiftIds,
        ?string $phone = null,
        ?array $gearSelections = null,
        ?array $customFieldResponses = null,
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
                volunteer: $volunteer,
                event: $event,
                shiftIds: $shiftIds,
            );

            if ($gearSelections !== null) {
                $this->assignGear->execute($volunteer, $event, $gearSelections);
            }

            if ($customFieldResponses !== null) {
                $this->recordCustomFields->execute($volunteer, $event, $customFieldResponses);
            }

            VolunteerSignedUp::dispatch($volunteer, $event, count($shiftIds));

            return SignupOutcome::completed($result);
        }

        $this->sendVerification->execute($volunteer, $event, $shiftIds, $gearSelections, $customFieldResponses);

        return SignupOutcome::pendingVerification($email);
    }
}
