<?php

namespace App\Livewire\Events;

use App\Actions\SignUpVolunteerForShifts;
use App\Models\Event;
use App\Models\Volunteer;
use App\Models\VolunteerJob;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Manual Enrollment')]
class ManualEnrollment extends Component
{
    public Event $event;

    public string $search = '';

    public ?int $selectedVolunteerId = null;

    /** @var array<int> */
    public array $selectedShifts = [];

    public bool $sendNotification = true;

    /**
     * @var array{newSignups: int, skippedFull: int, skippedDuplicate: int}|null
     */
    public ?array $enrollmentResult = null;

    public function mount(int $eventId): void
    {
        $this->event = currentOrganization()->events()->findOrFail($eventId);

        Gate::authorize('manageJobs', $this->event);
    }

    /** @return Collection<int, Volunteer> */
    #[Computed]
    public function volunteers(): Collection
    {
        if (strlen($this->search) < 2) {
            return new Collection;
        }

        return Volunteer::query()
            ->forEvent($this->event->id)
            ->search($this->search)
            ->limit(20)
            ->get();
    }

    /** @return Collection<int, VolunteerJob> */
    #[Computed]
    public function jobs(): Collection
    {
        return $this->event->volunteerJobs()
            ->with(['shifts' => fn ($q) => $q->withCount('activeSignups')->orderBy('starts_at')])
            ->orderBy('name')
            ->get();
    }

    public function selectVolunteer(int $volunteerId): void
    {
        $this->selectedVolunteerId = $volunteerId;
        $this->selectedShifts = [];
        $this->enrollmentResult = null;
    }

    #[Computed]
    public function selectedVolunteer(): ?Volunteer
    {
        if (! $this->selectedVolunteerId) {
            return null;
        }

        return Volunteer::find($this->selectedVolunteerId);
    }

    public function clearSelection(): void
    {
        $this->selectedVolunteerId = null;
        $this->selectedShifts = [];
        $this->enrollmentResult = null;
    }

    public function enroll(SignUpVolunteerForShifts $action): void
    {
        Gate::authorize('manageJobs', $this->event);

        if (! $this->selectedVolunteerId || empty($this->selectedShifts)) {
            return;
        }

        $volunteer = Volunteer::findOrFail($this->selectedVolunteerId);

        $batchResult = $action->execute(
            volunteer: $volunteer,
            event: $this->event,
            shiftIds: $this->selectedShifts,
            sendNotification: $this->sendNotification,
        );

        $this->enrollmentResult = [
            'newSignups' => count($batchResult->newSignups),
            'skippedFull' => count($batchResult->skippedFull),
            'skippedDuplicate' => count($batchResult->skippedDuplicate),
        ];

        $this->selectedShifts = [];
        unset($this->jobs);
    }

    public function updatedSearch(): void
    {
        unset($this->volunteers);
        $this->enrollmentResult = null;
    }
}
