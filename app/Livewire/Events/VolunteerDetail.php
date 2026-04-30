<?php

namespace App\Livewire\Events;

use App\Actions\DeleteVolunteerProfile;
use App\Actions\GenerateMagicLink;
use App\Actions\PromoteVolunteer;
use App\Actions\RecordArrival;
use App\Actions\RecordGearPickup;
use App\Actions\UpdateVolunteerGearSelection;
use App\Enums\ActivityCategory;
use App\Enums\ArrivalMethod;
use App\Enums\GearItemType;
use App\Enums\ScannerType;
use App\Enums\StaffRole;
use App\Exceptions\DomainException;
use App\Models\ActivityLog;
use App\Models\CustomRegistrationField;
use App\Models\Event;
use App\Models\EventArrival;
use App\Models\ProjectScanner;
use App\Models\Ticket;
use App\Models\Volunteer;
use App\Models\VolunteerGear;
use App\Notifications\TicketResendNotification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
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

    public bool $showDeleteModal = false;

    public bool $deleteConfirmed = false;

    public string $successMessage = '';

    public string $promoteRole = 'organizer';

    public string $selectedScannerId = '';

    public bool $showGearSelectionModal = false;

    public ?int $editingGearId = null;

    public string $editingGearName = '';

    public string $gearSelection = '';

    /**
     * @var array<int, string>
     */
    public array $gearSelectionOptions = [];

    public bool $editingGearPickedUp = false;

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
                promotedBy: Auth::user(),
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

    public function deleteVolunteer(): void
    {
        Gate::authorize('update', $this->event);

        if (! $this->deleteConfirmed) {
            return;
        }

        try {
            app(DeleteVolunteerProfile::class)->execute(
                $this->volunteer,
                enforceCancellationGuard: false,
                initiatedBy: Auth::user(),
            );
        } catch (DomainException $e) {
            $this->addError('delete', $e->getMessage());

            return;
        }

        $this->redirect(route('events.volunteers', $this->event));
    }

    public function resendTicketEmail(): void
    {
        Gate::authorize('update', $this->event);

        $this->resetErrorBag('resend');
        $this->successMessage = '';

        if (! $this->volunteer->isEmailVerified()) {
            $this->addError('resend', 'Die E-Mail-Adresse dieses Volunteers ist noch nicht verifiziert.');

            return;
        }

        $volunteerKey = 'qr-resend:'.$this->volunteer->id;
        if (RateLimiter::tooManyAttempts($volunteerKey, 1)) {
            $this->addError('resend', 'Bitte warte einige Minuten, bevor du es erneut versuchst.');

            return;
        }

        $organizerKey = 'qr-resend-admin-user:'.Auth::id();
        if (RateLimiter::tooManyAttempts($organizerKey, 10)) {
            $this->addError('resend', 'Zu viele Anfragen. Bitte versuche es später erneut.');

            return;
        }

        RateLimiter::hit($volunteerKey, 300);
        RateLimiter::hit($organizerKey, 3600);

        $result = app(GenerateMagicLink::class)->execute($this->volunteer);

        $this->volunteer->notify(new TicketResendNotification(
            $this->volunteer->project,
            $result['plainToken'],
        ));

        ActivityLog::create([
            'organization_id' => $this->event->organization_id,
            'project_id' => $this->event->project_id,
            'event_id' => $this->event->id,
            'causer_type' => Auth::user()::class,
            'causer_id' => Auth::id(),
            'subject_type' => Volunteer::class,
            'subject_id' => $this->volunteer->id,
            'action' => 'resent',
            'category' => ActivityCategory::Email,
            'description' => "Resent volunteer portal link to {$this->volunteer->full_name}",
            'properties' => [
                'volunteer_name' => $this->volunteer->full_name,
                'volunteer_email' => $this->volunteer->email,
            ],
        ]);

        $this->successMessage = __('Mail wurde an :email gesendet.', ['email' => $this->volunteer->email]);
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
            scannedBy: Auth::user(),
            method: ArrivalMethod::ManualLookup,
        );

        unset($this->arrival, $this->ticket);
    }

    public function recordGearPickup(int $gearId): void
    {
        Gate::authorize('trackGearPickup', $this->event);

        $gear = $this->findVolunteerGear($gearId);

        try {
            app(RecordGearPickup::class)->execute($gear, Auth::user());
        } catch (DomainException $e) {
            $this->addError('gear', $e->getMessage());
        }

        unset($this->volunteerGear);
    }

    public function undoGearPickup(int $gearId): void
    {
        Gate::authorize('trackGearPickup', $this->event);

        $gear = $this->findVolunteerGear($gearId);

        $gear->pickups()->latest('picked_up_at')->first()?->delete();

        unset($this->volunteerGear);
    }

    public function openGearSelectionModal(int $gearId): void
    {
        Gate::authorize('update', $this->event);

        $this->resetErrorBag('gearSelection');

        $gear = $this->findVolunteerGear($gearId);

        if ($gear->gearItem->type !== GearItemType::SizeSelection || ! $gear->gearItem->requires_size) {
            abort(404);
        }

        $this->editingGearId = $gear->id;
        $this->editingGearName = $gear->gearItem->name;
        $this->gearSelection = $gear->size ?? '';
        $this->gearSelectionOptions = $gear->gearItem->available_sizes ?? [];
        $this->editingGearPickedUp = $gear->isPickedUp();
        $this->showGearSelectionModal = true;
    }

    public function closeGearSelectionModal(): void
    {
        $this->showGearSelectionModal = false;
        $this->editingGearId = null;
        $this->editingGearName = '';
        $this->gearSelection = '';
        $this->gearSelectionOptions = [];
        $this->editingGearPickedUp = false;
        $this->resetErrorBag('gearSelection');
    }

    public function saveGearSelection(UpdateVolunteerGearSelection $action): void
    {
        Gate::authorize('update', $this->event);

        $this->validate([
            'gearSelection' => ['required', 'string'],
        ]);

        if ($this->editingGearId === null) {
            abort(404);
        }

        try {
            $action->execute(
                gear: $this->findVolunteerGear($this->editingGearId),
                event: $this->event,
                selection: $this->gearSelection,
                causer: Auth::user(),
            );
        } catch (DomainException $e) {
            $this->addError('gearSelection', $e->getMessage());

            return;
        }

        $this->closeGearSelectionModal();
        $this->successMessage = __('Gear-Auswahl wurde aktualisiert.');

        unset($this->volunteerGear);
    }

    private function findVolunteerGear(int $gearId): VolunteerGear
    {
        return VolunteerGear::query()
            ->where('volunteer_id', $this->volunteer->id)
            ->whereHas('gearItem', fn ($query) => $query->where('project_id', $this->event->project_id))
            ->with('gearItem')
            ->findOrFail($gearId);
    }
}
