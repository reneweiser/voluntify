<?php

namespace App\Actions;

use App\Events\Activity\VolunteerSignedUp;
use App\Models\Event;
use App\Models\Shift;
use App\Models\Volunteer;
use App\ValueObjects\ShiftSignupResult;

class ProcessVolunteerSignup
{
    public function __construct(
        private SignUpVolunteerForShifts $signUpAction,
        private AssignGearToVolunteer $assignGear,
        private RecordCustomFieldResponses $recordCustomFields,
    ) {}

    /**
     * @param  array<int>  $shiftIds
     * @param  array<int, string|null>|null  $gearSelections
     * @param  array<int, mixed>|null  $customFieldResponses
     */
    public function execute(
        string $firstName,
        string $lastName,
        string $email,
        Event $event,
        array $shiftIds,
        ?string $phone = null,
        ?array $gearSelections = null,
        ?array $customFieldResponses = null,
        ?string $sessionId = null,
    ): ShiftSignupResult {
        $volunteer = Volunteer::firstOrCreate(
            ['email' => $email, 'project_id' => $event->project_id],
            ['first_name' => $firstName, 'last_name' => $lastName, 'phone' => $phone],
        );

        if ($phone !== null && $volunteer->phone !== $phone) {
            $volunteer->update(['phone' => $phone]);
        }

        $result = $this->signUpAction->execute(
            volunteer: $volunteer,
            event: $event,
            shiftIds: $shiftIds,
            sessionId: $sessionId,
        );

        $volunteerJobIds = collect($shiftIds)
            ->map(fn ($id) => Shift::find($id)?->volunteerJob?->id)
            ->filter()
            ->unique()
            ->values()
            ->all();

        $this->assignGear->execute($volunteer, $event, $gearSelections ?? [], $volunteerJobIds);

        if ($customFieldResponses !== null) {
            $this->recordCustomFields->execute($volunteer, $event, $customFieldResponses);
        }

        VolunteerSignedUp::dispatch($volunteer, $event, count($shiftIds));

        return $result;
    }
}
