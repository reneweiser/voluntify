<?php

namespace App\Livewire\Events;

use App\Actions\CreateVolunteerManually;
use App\Actions\SignUpVolunteerForShifts;
use App\Models\Event;
use App\Models\Volunteer;
use App\Models\VolunteerJob;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Manual Enrollment')]
class ManualEnrollment extends Component
{
    #[Locked]
    public Event $event;

    public string $search = '';

    #[Locked]
    public ?int $selectedVolunteerId = null;

    /** @var array<int> */
    public array $selectedShifts = [];

    public bool $sendNotification = true;

    /**
     * @var array{newSignups: int, skippedFull: int, skippedDuplicate: int}|null
     */
    public ?array $enrollmentResult = null;

    public bool $createNewMode = false;

    public string $newFirstName = '';

    public string $newLastName = '';

    public string $newEmail = '';

    public string $newPhone = '';

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
            ->with(['shifts' => fn ($q) => $q->withCount(['activeSignups', 'activeReservations as active_reservations_count'])->orderBy('starts_at')])
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

        return Volunteer::where('id', $this->selectedVolunteerId)
            ->where('project_id', $this->event->project_id)
            ->first();
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

        $volunteer = Volunteer::where('id', $this->selectedVolunteerId)
            ->where('project_id', $this->event->project_id)
            ->firstOrFail();

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

    /**
     * Create a new volunteer and select them for shift enrollment.
     * The created volunteer is auto-verified (no email verification needed)
     * because the organizer is the trust anchor.
     */
    public function createAndSelect(CreateVolunteerManually $action): void
    {
        Gate::authorize('manageJobs', $this->event);

        $this->validate([
            'newFirstName' => ['required', 'string', 'max:255'],
            'newLastName' => ['required', 'string', 'max:255'],
            'newEmail' => ['required', 'email', 'max:255'],
            'newPhone' => ['nullable', 'string', 'max:20'],
        ]);

        $volunteer = $action->execute(
            project: $this->event->project,
            data: [
                'first_name' => $this->newFirstName,
                'last_name' => $this->newLastName,
                'email' => $this->newEmail,
                'phone' => $this->newPhone ?: null,
            ],
        );

        $this->selectVolunteer($volunteer->id);
        $this->createNewMode = false;
        $this->resetNewVolunteerForm();
    }

    public function toggleCreateMode(): void
    {
        $this->createNewMode = ! $this->createNewMode;
        if ($this->createNewMode) {
            $this->selectedVolunteerId = null;
            $this->search = '';
            $this->enrollmentResult = null;
        }
    }

    public function updatedSearch(): void
    {
        unset($this->volunteers);
        $this->enrollmentResult = null;
    }

    private function resetNewVolunteerForm(): void
    {
        $this->newFirstName = '';
        $this->newLastName = '';
        $this->newEmail = '';
        $this->newPhone = '';
    }
}
