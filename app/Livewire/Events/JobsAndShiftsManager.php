<?php

namespace App\Livewire\Events;

use App\Actions\CloneVolunteerJob;
use App\Actions\CreateShift;
use App\Actions\CreateVolunteerJob;
use App\Actions\DeleteShift;
use App\Actions\DeleteVolunteerJob;
use App\Actions\UpdateShift;
use App\Actions\UpdateVolunteerJob;
use App\Concerns\ConvertsTimezone;
use App\Exceptions\DomainException;
use App\Exceptions\HasSignupsException;
use App\Models\Event;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Jobs & Shifts')]
class JobsAndShiftsManager extends Component
{
    use ConvertsTimezone;

    public Event $event;

    // Job form
    public bool $showJobModal = false;

    public ?int $editingJobId = null;

    public string $jobName = '';

    public string $jobDescription = '';

    public string $jobInstructions = '';

    public bool $jobIsActive = true;

    // Shift form
    public bool $showShiftModal = false;

    public ?int $shiftJobId = null;

    public ?int $editingShiftId = null;

    public string $shiftDate = '';

    public string $shiftStartsAt = '';

    public string $shiftEndsAt = '';

    public string $shiftDisplayText = '';

    public int $shiftCapacity = 10;

    public bool $shiftIsActive = true;

    public function mount(int $eventId): void
    {
        $this->event = currentOrganization()->events()->findOrFail($eventId);

        Gate::authorize('view', $this->event);
    }

    #[Computed]
    public function jobs(): Collection
    {
        return $this->event->volunteerJobs()
            ->with(['shifts' => fn ($q) => $q->withCount(['activeSignups as signups_count', 'activeReservations as active_reservations_count'])->orderBy('shift_date')->orderBy('starts_at')])
            ->get();
    }

    #[Computed]
    public function canManage(): bool
    {
        return Gate::allows('manageJobs', $this->event);
    }

    // Job CRUD

    public function openCreateJob(): void
    {
        Gate::authorize('manageJobs', $this->event);

        $this->reset('editingJobId', 'jobName', 'jobDescription', 'jobInstructions');
        $this->jobIsActive = true;
        $this->showJobModal = true;
    }

    public function openEditJob(int $jobId): void
    {
        Gate::authorize('manageJobs', $this->event);

        $job = $this->event->volunteerJobs()->findOrFail($jobId);
        $this->editingJobId = $job->id;
        $this->jobName = $job->name;
        $this->jobDescription = $job->description ?? '';
        $this->jobInstructions = $job->instructions ?? '';
        $this->jobIsActive = $job->isActive();
        $this->showJobModal = true;
    }

    public function saveJob(): void
    {
        Gate::authorize('manageJobs', $this->event);

        /** @var User $user */
        $user = Auth::user();

        $this->validate([
            'jobName' => ['required', 'string', 'max:255'],
            'jobDescription' => ['nullable', 'string'],
            'jobInstructions' => ['nullable', 'string'],
            'jobIsActive' => ['required', 'boolean'],
        ]);

        if ($this->editingJobId) {
            $job = $this->event->volunteerJobs()->findOrFail($this->editingJobId);
            app(UpdateVolunteerJob::class)->execute(
                job: $job,
                name: $this->jobName,
                description: $this->jobDescription ?: null,
                instructions: $this->jobInstructions ?: null,
                isActive: $this->jobIsActive,
                causer: $user,
            );
        } else {
            app(CreateVolunteerJob::class)->execute(
                event: $this->event,
                name: $this->jobName,
                description: $this->jobDescription ?: null,
                instructions: $this->jobInstructions ?: null,
                isActive: $this->jobIsActive,
                causer: $user,
            );
        }

        $this->showJobModal = false;
        $this->reset('editingJobId', 'jobName', 'jobDescription', 'jobInstructions');
        $this->jobIsActive = true;
        unset($this->jobs);
    }

    public function toggleJobActive(int $jobId): void
    {
        Gate::authorize('manageJobs', $this->event);

        /** @var User $user */
        $user = Auth::user();

        $job = $this->event->volunteerJobs()->findOrFail($jobId);

        app(UpdateVolunteerJob::class)->execute(
            job: $job,
            name: $job->name,
            description: $job->description,
            instructions: $job->instructions,
            isActive: ! $job->isActive(),
            causer: $user,
        );

        unset($this->jobs);
    }

    public function cloneJob(int $jobId): void
    {
        Gate::authorize('manageJobs', $this->event);

        $job = $this->event->volunteerJobs()->findOrFail($jobId);

        try {
            app(CloneVolunteerJob::class)->execute($job);
        } catch (\Throwable $e) {
            $this->addError('job', __('Failed to duplicate job. Please try again.'));

            return;
        }

        unset($this->jobs);
    }

    public function deleteJob(int $jobId): void
    {
        Gate::authorize('manageJobs', $this->event);

        /** @var User $user */
        $user = Auth::user();

        $job = $this->event->volunteerJobs()->findOrFail($jobId);

        try {
            app(DeleteVolunteerJob::class)->execute($job, $user);
        } catch (HasSignupsException $e) {
            $this->addError('job', $e->getMessage());

            return;
        }

        unset($this->jobs);
    }

    // Shift CRUD

    public function openCreateShift(int $jobId): void
    {
        Gate::authorize('manageJobs', $this->event);

        $this->reset('editingShiftId', 'shiftDate', 'shiftStartsAt', 'shiftEndsAt', 'shiftDisplayText');
        $this->shiftCapacity = 10;
        $this->shiftIsActive = true;
        $this->shiftJobId = $jobId;
        $this->showShiftModal = true;
    }

    public function openEditShift(int $shiftId): void
    {
        Gate::authorize('manageJobs', $this->event);

        $shift = $this->findShift($shiftId);
        $tz = $this->event->project->timezone ?? 'UTC';
        $this->editingShiftId = $shift->id;
        $this->shiftJobId = $shift->volunteer_job_id;
        $this->shiftDate = $shift->shift_date->setTimezone($tz)->format('Y-m-d');
        $this->shiftStartsAt = $shift->starts_at ? $this->toLocal($shift->starts_at, $tz) : '';
        $this->shiftEndsAt = $shift->ends_at ? $this->toLocal($shift->ends_at, $tz) : '';
        $this->shiftDisplayText = $shift->display_text ?? '';
        $this->shiftCapacity = $shift->capacity;
        $this->shiftIsActive = $shift->isActive();
        $this->showShiftModal = true;
    }

    public function saveShift(): void
    {
        Gate::authorize('manageJobs', $this->event);

        /** @var User $user */
        $user = Auth::user();

        $rules = [
            'shiftDate' => ['required', 'date'],
            'shiftStartsAt' => ['nullable', 'date'],
            'shiftEndsAt' => ['nullable', 'date', 'after:shiftStartsAt', 'required_with:shiftStartsAt'],
            'shiftCapacity' => ['required', 'integer', 'min:1'],
            'shiftDisplayText' => [empty($this->shiftStartsAt) ? 'required' : 'nullable', 'string', 'max:255'],
            'shiftIsActive' => ['required', 'boolean'],
        ];

        $this->validate($rules);

        $tz = $this->event->project->timezone ?? 'UTC';
        $startsAt = $this->shiftStartsAt ? $this->toUtc($this->shiftStartsAt, $tz) : null;
        $endsAt = $this->shiftEndsAt ? $this->toUtc($this->shiftEndsAt, $tz) : null;
        $displayText = $this->shiftDisplayText ?: null;

        if ($this->editingShiftId) {
            $shift = $this->findShift($this->editingShiftId);
            try {
                app(UpdateShift::class)->execute(
                    shift: $shift,
                    shiftDate: Carbon::parse($this->shiftDate),
                    startsAt: $startsAt,
                    endsAt: $endsAt,
                    capacity: $this->shiftCapacity,
                    isActive: $this->shiftIsActive,
                    displayText: $displayText,
                    causer: $user,
                );
            } catch (DomainException $e) {
                $this->addError('shift', $e->getMessage());

                return;
            }
        } else {
            $job = $this->event->volunteerJobs()->findOrFail($this->shiftJobId);
            app(CreateShift::class)->execute(
                job: $job,
                shiftDate: Carbon::parse($this->shiftDate),
                startsAt: $startsAt,
                endsAt: $endsAt,
                capacity: $this->shiftCapacity,
                isActive: $this->shiftIsActive,
                displayText: $displayText,
                causer: $user,
            );
        }

        $this->showShiftModal = false;
        $this->reset('editingShiftId', 'shiftJobId', 'shiftDate', 'shiftStartsAt', 'shiftEndsAt', 'shiftDisplayText');
        $this->shiftCapacity = 10;
        $this->shiftIsActive = true;
        unset($this->jobs);
    }

    public function toggleShiftActive(int $shiftId): void
    {
        Gate::authorize('manageJobs', $this->event);

        /** @var User $user */
        $user = Auth::user();

        $shift = $this->findShift($shiftId);

        try {
            app(UpdateShift::class)->execute(
                shift: $shift,
                shiftDate: $shift->shift_date,
                startsAt: $shift->starts_at,
                endsAt: $shift->ends_at,
                capacity: $shift->capacity,
                isActive: ! $shift->isActive(),
                displayText: $shift->display_text,
                causer: $user,
            );
        } catch (DomainException $e) {
            $this->addError('shift', $e->getMessage());

            return;
        }

        unset($this->jobs);
    }

    public function deleteShift(int $shiftId): void
    {
        Gate::authorize('manageJobs', $this->event);

        /** @var User $user */
        $user = Auth::user();

        $shift = $this->findShift($shiftId);

        try {
            app(DeleteShift::class)->execute($shift, $user);
        } catch (HasSignupsException $e) {
            $this->addError('shift', $e->getMessage());

            return;
        }

        unset($this->jobs);
    }

    private function findShift(int $shiftId): Shift
    {
        return Shift::whereHas('volunteerJob', fn ($q) => $q->where('event_id', $this->event->id))
            ->findOrFail($shiftId);
    }
}
