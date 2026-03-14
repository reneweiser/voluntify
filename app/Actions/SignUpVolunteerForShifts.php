<?php

namespace App\Actions;

use App\Exceptions\DomainException;
use App\Models\Event;
use App\Models\Shift;
use App\Models\ShiftSignup;
use App\Models\Volunteer;
use App\Notifications\SignupConfirmation;
use App\ValueObjects\SignupBatchResult;
use Illuminate\Support\Facades\DB;

class SignUpVolunteerForShifts
{
    public function __construct(
        private GenerateTicket $generateTicket,
        private GenerateMagicLink $generateMagicLink,
    ) {}

    /**
     * @param  array<int>  $shiftIds
     */
    public function execute(
        Volunteer $volunteer,
        Event $event,
        array $shiftIds,
        bool $sendNotification = true,
    ): SignupBatchResult {
        $eventJobIds = $event->volunteerJobs()->pluck('id');
        $validShiftIds = Shift::whereIn('volunteer_job_id', $eventJobIds)
            ->whereIn('id', $shiftIds)
            ->pluck('id')
            ->all();

        if (count($validShiftIds) !== count($shiftIds)) {
            throw new DomainException('One or more shifts do not belong to this event.');
        }

        $sortedShiftIds = $shiftIds;
        sort($sortedShiftIds);

        $result = DB::transaction(function () use ($volunteer, $event, $sortedShiftIds) {

            $newSignups = [];
            $skippedFull = [];
            $skippedDuplicate = [];

            foreach ($sortedShiftIds as $shiftId) {
                $shift = Shift::lockForUpdate()->findOrFail($shiftId);

                $existingSignup = ShiftSignup::where('volunteer_id', $volunteer->id)
                    ->where('shift_id', $shift->id)
                    ->first();

                if ($existingSignup && ! $existingSignup->isCancelled()) {
                    $skippedDuplicate[] = $shift;

                    continue;
                }

                if ($shift->isFull()) {
                    $skippedFull[] = $shift;

                    continue;
                }

                if ($existingSignup && $existingSignup->isCancelled()) {
                    $existingSignup->cancelled_at = null;
                    $existingSignup->signed_up_at = now();
                    $existingSignup->save();
                    $newSignups[] = $existingSignup;
                } else {
                    $newSignups[] = ShiftSignup::create([
                        'volunteer_id' => $volunteer->id,
                        'shift_id' => $shift->id,
                        'signed_up_at' => now(),
                    ]);
                }
            }

            $this->generateTicket->execute($volunteer, $event);

            $plainToken = null;
            if (count($newSignups) > 0) {
                ['plainToken' => $plainToken] = $this->generateMagicLink->execute($volunteer);
            }

            return [
                'volunteer' => $volunteer,
                'newSignups' => $newSignups,
                'skippedFull' => $skippedFull,
                'skippedDuplicate' => $skippedDuplicate,
                'plainToken' => $plainToken,
            ];
        });

        $batchResult = new SignupBatchResult(
            volunteer: $result['volunteer'],
            newSignups: $result['newSignups'],
            skippedFull: $result['skippedFull'],
            skippedDuplicate: $result['skippedDuplicate'],
        );

        if ($sendNotification && $batchResult->hasNewSignups()) {
            $shiftIds = collect($result['newSignups'])
                ->map(fn (ShiftSignup $signup) => $signup->shift_id)
                ->all();

            $result['volunteer']->notify(
                new SignupConfirmation($event, $shiftIds, $result['plainToken']),
            );
        }

        return $batchResult;
    }
}
