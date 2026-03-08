<?php

namespace App\Livewire\Public;

use App\Actions\CancelShiftSignup;
use App\Actions\VerifyMagicLink;
use App\Exceptions\InvalidMagicLinkException;
use App\Models\EventAnnouncement;
use App\Models\ShiftSignup;
use App\Models\Volunteer;
use App\Models\VolunteerGear;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

#[Layout('layouts.public')]
#[Title('Volunteer Portal')]
class VolunteerPortal extends Component
{
    public ?Volunteer $volunteer = null;

    public bool $expired = false;

    public ?int $cancellingSignupId = null;

    public string $successMessage = '';

    public function mount(string $magicToken): void
    {
        try {
            $this->volunteer = app(VerifyMagicLink::class)->execute($magicToken);
        } catch (InvalidMagicLinkException $e) {
            if (str_contains($e->getMessage(), 'expired')) {
                $this->expired = true;

                return;
            }

            throw new NotFoundHttpException;
        }
    }

    #[Computed]
    public function upcomingSignups(): Collection
    {
        if (! $this->volunteer) {
            return new Collection;
        }

        return $this->volunteer->shiftSignups()
            ->active()
            ->whereHas('shift', fn ($q) => $q->where('starts_at', '>', now()))
            ->with('shift.volunteerJob.event')
            ->get()
            ->sortBy('shift.starts_at')
            ->values();
    }

    #[Computed]
    public function pastSignups(): Collection
    {
        if (! $this->volunteer) {
            return new Collection;
        }

        return $this->volunteer->shiftSignups()
            ->active()
            ->whereHas('shift', fn ($q) => $q->where('ends_at', '<=', now()))
            ->with('shift.volunteerJob.event')
            ->get()
            ->sortByDesc('shift.starts_at')
            ->values();
    }

    #[Computed]
    public function gearAssignments(): Collection
    {
        if (! $this->volunteer) {
            return new Collection;
        }

        $eventIds = $this->volunteer->tickets()->pluck('event_id');

        return VolunteerGear::where('volunteer_id', $this->volunteer->id)
            ->whereHas('gearItem', fn ($q) => $q->whereIn('event_id', $eventIds))
            ->with('gearItem.event')
            ->get();
    }

    #[Computed]
    public function announcements(): Collection
    {
        if (! $this->volunteer) {
            return new Collection;
        }

        $eventIds = $this->volunteer->tickets()->pluck('event_id');

        return EventAnnouncement::whereIn('event_id', $eventIds)
            ->whereNotNull('sent_at')
            ->with('event')
            ->latest('sent_at')
            ->get();
    }

    public function confirmCancel(int $signupId): void
    {
        $this->cancellingSignupId = $signupId;
    }

    public function dismissCancel(): void
    {
        $this->cancellingSignupId = null;
    }

    public function cancelSignup(): void
    {
        $signup = ShiftSignup::find($this->cancellingSignupId);

        if (! $signup || $signup->volunteer_id !== $this->volunteer->id) {
            abort(403);
        }

        app(CancelShiftSignup::class)->execute($signup);

        $this->cancellingSignupId = null;
        $this->successMessage = 'Signup cancelled successfully.';

        unset($this->upcomingSignups, $this->pastSignups);
    }
}
