<?php

namespace App\Livewire\Events;

use App\Actions\PromoteVolunteer;
use App\Actions\RecordArrival;
use App\Actions\RecordGearPickup;
use App\Enums\ArrivalMethod;
use App\Enums\ScannerType;
use App\Enums\StaffRole;
use App\Exceptions\DomainException;
use App\Models\CustomRegistrationField;
use App\Models\Event;
use App\Models\EventArrival;
use App\Models\ProjectScanner;
use App\Models\Ticket;
use App\Models\Volunteer;
use App\Models\VolunteerGear;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Volunteer Detail')]
class VolunteerDetail extends Component
{
    #[Locked]
    public Event $event;

    #[Locked]
    public Volunteer $volunteer;

    public bool $showPromoteModal = false;

    public string $promoteRole = 'organizer';

    public string $selectedScannerId = '';

    public function mount(int $eventId, int $volunteerId): void
    {
        $this->event = currentOrganization()->events()->findOrFail($eventId);

        Gate::authorize('view', $this->event);

        $this->volunteer = Volunteer::forEvent($eventId)->findOrFail($volunteerId);
    }

    #[Computed]
    public function customFieldResponses(): Collection
    {
        $fieldIds = CustomRegistrationField::withTrashed()
            ->where(function ($q) {
                $q->where('event_id', $this->event->id)
                    ->orWhere('project_id', $this->event->project_id);
            })
            ->pluck('id');

        return $this->volunteer->customFieldResponses()
            ->whereIn('custom_registration_field_id', $fieldIds)
            ->with(['field' => fn ($q) => $q->withTrashed()])
            ->get();
    }

    #[Computed]
    public function shiftSignups(): Collection
    {
        return $this->volunteer->shiftSignups()
            ->whereHas('shift.volunteerJob', fn ($q) => $q->where('event_id', $this->event->id))
            ->with(['shift.volunteerJob', 'attendanceRecord'])
            ->get();
    }

    #[Computed]
    public function arrival(): ?EventArrival
    {
        return $this->volunteer->eventArrivals()
            ->where('event_id', $this->event->id)
            ->first();
    }

    #[Computed]
    public function canPromote(): bool
    {
        return Gate::allows('update', $this->event);
    }

    #[Computed]
    public function isAlreadyPromoted(): bool
    {
        return (bool) $this->volunteer->user_id;
    }

    #[Computed]
    public function ticket(): ?Ticket
    {
        return $this->volunteer->tickets()
            ->where('project_id', $this->event->project_id)
            ->first();
    }

    /**
     * @return Collection<int, VolunteerGear>
     */
    #[Computed]
    public function volunteerGear(): Collection
    {
        return $this->volunteer->volunteerGear()
            ->whereHas('gearItem', fn ($q) => $q->where('project_id', $this->event->project_id))
            ->with(['gearItem', 'pickups'])
            ->get();
    }

    /**
     * @return Collection<int, ProjectScanner>
     */
    #[Computed]
    public function vaScanners(): Collection
    {
        if (! $this->event->project) {
            return new Collection;
        }

        return $this->event->project->scanners()
            ->where('type', ScannerType::VolunteerAdmin)
            ->get();
    }

    public function promoteVolunteer(): void
    {
        Gate::authorize('update', $this->event);

        try {
            $role = $this->promoteRole === 'volunteer_admin'
                ? StaffRole::VolunteerAdmin
                : StaffRole::Organizer;

            $action = app(PromoteVolunteer::class);
            $action->execute(
                volunteer: $this->volunteer,
                organization: $this->event->organization,
                role: $role,
                promotedBy: auth()->user(),
                scannerId: $this->selectedScannerId !== '' ? (int) $this->selectedScannerId : null,
            );

            $this->volunteer->refresh();
            $this->showPromoteModal = false;
            $this->promoteRole = 'organizer';
            $this->selectedScannerId = '';
            unset($this->canPromote, $this->isAlreadyPromoted, $this->vaScanners);
            $this->dispatch('volunteer-promoted');
        } catch (DomainException $e) {
            $this->addError('promote', $e->getMessage());
        }
    }

    public function markAsArrived(): void
    {
        Gate::authorize('scan', $this->event);

        $ticket = $this->ticket;

        if (! $ticket) {
            $this->addError('arrival', __('Kein Ticket für diesen Volunteer vorhanden.'));

            return;
        }

        app(RecordArrival::class)->execute(
            ticket: $ticket,
            event: $this->event,
            scannedBy: auth()->user(),
            method: ArrivalMethod::ManualLookup,
        );

        unset($this->arrival, $this->ticket);
    }

    public function recordGearPickup(int $gearId): void
    {
        Gate::authorize('trackGearPickup', $this->event);

        $gear = VolunteerGear::whereHas('gearItem', fn ($q) => $q->where('project_id', $this->event->project_id))
            ->findOrFail($gearId);

        try {
            app(RecordGearPickup::class)->execute($gear, auth()->user());
        } catch (DomainException $e) {
            $this->addError('gear', $e->getMessage());
        }

        unset($this->volunteerGear);
    }

    public function undoGearPickup(int $gearId): void
    {
        Gate::authorize('trackGearPickup', $this->event);

        $gear = VolunteerGear::whereHas('gearItem', fn ($q) => $q->where('project_id', $this->event->project_id))
            ->findOrFail($gearId);

        $gear->pickups()->latest('picked_up_at')->first()?->delete();

        unset($this->volunteerGear);
    }
}
