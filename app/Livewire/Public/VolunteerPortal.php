<?php

namespace App\Livewire\Public;

use App\Actions\CancelShiftSignup;
use App\Actions\DeleteVolunteerProfile;
use App\Actions\GenerateMagicLink;
use App\Actions\RefreshTicketJwt;
use App\Actions\VerifyMagicLink;
use App\Enums\HintLocation;
use App\Exceptions\CancellationCutoffPassedException;
use App\Exceptions\DomainException;
use App\Exceptions\InvalidMagicLinkException;
use App\Models\Announcement;
use App\Models\CustomFieldResponse;
use App\Models\MagicLinkToken;
use App\Models\ShiftSignup;
use App\Models\Ticket;
use App\Models\Volunteer;
use App\Models\VolunteerGear;
use App\Notifications\TicketResendNotification;
use App\Services\HintTextResolver;
use App\ValueObjects\HashedToken;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

#[Layout('layouts.public')]
#[Title('Volunteer Portal')]
class VolunteerPortal extends Component
{
    #[Locked]
    public ?Volunteer $volunteer = null;

    public bool $expired = false;

    #[Locked]
    public string $magicToken = '';

    public bool $hasTicket = false;

    public ?int $cancellingSignupId = null;

    public bool $showCancelModal = false;

    public string $successMessage = '';

    public ?string $projectPublicToken = null;

    public bool $showDeleteModal = false;

    public bool $deleteConfirmed = false;

    public function mount(string $magicToken): void
    {
        $this->magicToken = $magicToken;

        try {
            $this->volunteer = app(VerifyMagicLink::class)->execute($magicToken);
            $ticket = Ticket::where('volunteer_id', $this->volunteer->id)
                ->where('project_id', $this->volunteer->project_id)
                ->first();

            $this->hasTicket = $ticket !== null;

            if ($ticket) {
                app(RefreshTicketJwt::class)->execute($ticket);
            }
        } catch (InvalidMagicLinkException $e) {
            if (str_contains($e->getMessage(), 'expired')) {
                $this->expired = true;
                $this->resolveProjectFromExpiredToken($magicToken);

                return;
            }

            throw new NotFoundHttpException;
        }
    }

    private function resolveProjectFromExpiredToken(string $plainToken): void
    {
        $hash = HashedToken::fromPlaintext($plainToken)->hash;
        $token = MagicLinkToken::where('token_hash', $hash)->with('volunteer.project')->first();

        $this->projectPublicToken = $token?->volunteer?->project?->public_token;
    }

    #[Computed]
    public function ticket(): ?Ticket
    {
        if (! $this->volunteer) {
            return null;
        }

        return Ticket::where('volunteer_id', $this->volunteer->id)
            ->where('project_id', $this->volunteer->project_id)
            ->first();
    }

    #[Computed]
    public function nextShift(): ?ShiftSignup
    {
        return $this->upcomingSignups->first();
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
            ->with(['shift.volunteerJob.event.project', 'attendanceRecord'])
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
            ->with(['shift.volunteerJob.event.project', 'attendanceRecord'])
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

        $activeSignups = $this->activeVolunteerSignups();

        $eventIds = array_values(array_unique($activeSignups->pluck('shift.volunteerJob.event_id')->filter()->all()));
        $jobIds = array_values(array_unique($activeSignups->pluck('shift.volunteerJob.id')->filter()->all()));
        $shiftIds = array_values(array_unique($activeSignups->pluck('shift_id')->filter()->all()));

        return Announcement::where('project_id', $this->volunteer->project_id)
            ->whereNotNull('sent_at')
            ->where(function ($query) use ($eventIds, $jobIds, $shiftIds) {
                $query->where('is_project_wide', true)
                    ->orWhere(function ($targetedQuery) use ($eventIds, $jobIds, $shiftIds) {
                        $targetedQuery->where('is_project_wide', false)
                            ->whereIn('event_id', $eventIds)
                            ->where(function ($jobQuery) use ($jobIds) {
                                $jobQuery->whereNull('job_id')
                                    ->orWhereIn('job_id', $jobIds);
                            })
                            ->where(function ($shiftQuery) use ($shiftIds) {
                                $shiftQuery->whereNull('shift_id')
                                    ->orWhereIn('shift_id', $shiftIds);
                            });
                    });
            })
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

    private function activeVolunteerSignups(): Collection
    {
        if (! $this->volunteer) {
            return new Collection;
        }

        return $this->volunteer->shiftSignups()
            ->active()
            ->with('shift.volunteerJob')
            ->get();
    }

    /** @return \Illuminate\Support\Collection<int, int> */
    private function volunteerEventIds(): \Illuminate\Support\Collection
    {
        return $this->activeVolunteerSignups()
            ->pluck('shift.volunteerJob.event_id')
            ->filter()
            ->unique()
            ->values();
    }

    public function confirmCancel(int $signupId): void
    {
        $this->cancellingSignupId = $signupId;
        $this->showCancelModal = true;
    }

    public function dismissCancel(): void
    {
        $this->cancellingSignupId = null;
        $this->showCancelModal = false;
    }

    public function updatedShowCancelModal(bool $value): void
    {
        if (! $value) {
            $this->cancellingSignupId = null;
        }
    }

    public function cancelSignup(): void
    {
        if (! $this->cancellingSignupId) {
            return;
        }

        $signup = $this->volunteer->shiftSignups()
            ->with('shift.volunteerJob.event.project')
            ->find($this->cancellingSignupId);

        if (! $signup) {
            abort(403);
        }

        try {
            app(CancelShiftSignup::class)->execute($signup);
        } catch (DomainException|CancellationCutoffPassedException $e) {
            $this->addError('cancel', $e->getMessage());

            return;
        }

        $this->cancellingSignupId = null;
        $this->showCancelModal = false;
        $this->successMessage = 'Signup cancelled successfully.';

        unset($this->upcomingSignups, $this->pastSignups);
    }

    public function resendTicketEmail(): void
    {
        $volunteerKey = 'qr-resend:'.$this->volunteer->id;
        if (RateLimiter::tooManyAttempts($volunteerKey, 1)) {
            $this->addError('resend', 'Bitte warte einige Minuten, bevor du es erneut versuchst.');

            return;
        }

        $ipKey = 'qr-resend-ip:'.request()->ip();
        if (RateLimiter::tooManyAttempts($ipKey, 10)) {
            $this->addError('resend', 'Zu viele Anfragen. Bitte versuche es später erneut.');

            return;
        }

        RateLimiter::hit($volunteerKey, 300);
        RateLimiter::hit($ipKey, 3600);

        $result = app(GenerateMagicLink::class)->execute($this->volunteer);

        $this->volunteer->notify(new TicketResendNotification(
            $this->volunteer->project,
            $result['plainToken'],
        ));

        $this->successMessage = 'QR-Code wurde erneut gesendet.';
    }

    public function deleteProfile(): void
    {
        if (! $this->deleteConfirmed) {
            return;
        }

        $publicToken = $this->volunteer->project->public_token;

        try {
            app(DeleteVolunteerProfile::class)->execute($this->volunteer);
        } catch (DomainException $e) {
            $this->addError('delete', $e->getMessage());

            return;
        }

        $this->redirect(route('projects.public', $publicToken));
    }
}
