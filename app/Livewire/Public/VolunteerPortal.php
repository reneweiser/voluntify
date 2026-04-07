<?php

namespace App\Livewire\Public;

use App\Actions\CancelShiftSignup;
use App\Actions\VerifyMagicLink;
use App\Enums\HintLocation;
use App\Exceptions\InvalidMagicLinkException;
use App\Models\Announcement;
use App\Models\CustomFieldResponse;
use App\Models\ShiftSignup;
use App\Models\Ticket;
use App\Models\Volunteer;
use App\Models\VolunteerGear;
use App\Services\HintTextResolver;
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

    public string $magicToken = '';

    public bool $hasTicket = false;

    public ?int $cancellingSignupId = null;

    public string $successMessage = '';

    public function mount(string $magicToken): void
    {
        $this->magicToken = $magicToken;

        try {
            $this->volunteer = app(VerifyMagicLink::class)->execute($magicToken);
            $this->hasTicket = Ticket::where('volunteer_id', $this->volunteer->id)
                ->where('project_id', $this->volunteer->project_id)
                ->exists();
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
            ->whereHas('shift', fn ($q) => $q->where(function ($sq) {
                $sq->where(fn ($inner) => $inner->whereNotNull('starts_at')->where('starts_at', '>', now()))
                    ->orWhere(fn ($inner) => $inner->whereNull('starts_at')->where('shift_date', '>', now()->toDateString()));
            }))
            ->with('shift.volunteerJob.event.project')
            ->get()
            ->sortBy('shift.shift_date')
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
            ->whereHas('shift', fn ($q) => $q->where(function ($sq) {
                $sq->where(fn ($inner) => $inner->whereNotNull('ends_at')->where('ends_at', '<=', now()))
                    ->orWhere(fn ($inner) => $inner->whereNull('ends_at')->where('shift_date', '<', now()->toDateString()));
            }))
            ->with('shift.volunteerJob.event')
            ->get()
            ->sortByDesc('shift.shift_date')
            ->values();
    }

    #[Computed]
    public function customFieldResponses(): Collection
    {
        if (! $this->volunteer) {
            return new Collection;
        }

        $eventIds = $this->volunteerEventIds();
        $projectId = $this->volunteer->project_id;

        return CustomFieldResponse::where('volunteer_id', $this->volunteer->id)
            ->whereHas('field', fn ($q) => $q->withoutGlobalScopes()->where(function ($sq) use ($eventIds, $projectId) {
                $sq->whereIn('event_id', $eventIds)
                    ->orWhere('project_id', $projectId);
            }))
            ->with(['field' => fn ($q) => $q->withTrashed()->with('event', 'project')])
            ->get();
    }

    #[Computed]
    public function gearAssignments(): Collection
    {
        if (! $this->volunteer) {
            return new Collection;
        }

        return VolunteerGear::where('volunteer_id', $this->volunteer->id)
            ->with(['gearItem.project', 'pickups'])
            ->get();
    }

    #[Computed]
    public function announcements(): Collection
    {
        if (! $this->volunteer) {
            return new Collection;
        }

        $eventIds = $this->volunteerEventIds();

        return Announcement::where('project_id', $this->volunteer->project_id)
            ->whereNotNull('sent_at')
            ->with('event')
            ->latest('sent_at')
            ->get();
    }

    #[Computed]
    public function hintPortalTopBanner(): ?string
    {
        return $this->resolveHint(HintLocation::PortalTopBanner);
    }

    #[Computed]
    public function hintPortalShiftsSection(): ?string
    {
        return $this->resolveHint(HintLocation::PortalShiftsSection);
    }

    private function resolveHint(HintLocation $location): ?string
    {
        if (! $this->volunteer?->project) {
            return null;
        }

        return app(HintTextResolver::class)->resolve($location, $this->volunteer->project);
    }

    /** @return \Illuminate\Support\Collection<int, int> */
    private function volunteerEventIds(): \Illuminate\Support\Collection
    {
        return $this->volunteer->shiftSignups()
            ->active()
            ->with('shift.volunteerJob')
            ->get()
            ->pluck('shift.volunteerJob.event_id')
            ->unique();
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
