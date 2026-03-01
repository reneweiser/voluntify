<?php

namespace App\Livewire\Events;

use App\Actions\CreateShift;
use App\Actions\CreateVolunteerJob;
use App\Actions\DeleteShift;
use App\Actions\DeleteVolunteerJob;
use App\Actions\UpdateShift;
use App\Actions\UpdateVolunteerJob;
use App\Exceptions\HasSignupsException;
use App\Models\Event;
use App\Models\Organization;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Jobs & Shifts')]
class JobsAndShiftsManager extends Component
{
    public Event $event;

    // Job form
    public bool $showJobModal = false;

    public ?int $editingJobId = null;

    public string $jobName = '';

    public string $jobDescription = '';

    public string $jobInstructions = '';

    // Shift form
    public bool $showShiftModal = false;

    public ?int $shiftJobId = null;

    public ?int $editingShiftId = null;

    public string $shiftStartsAt = '';

    public string $shiftEndsAt = '';

    public int $shiftCapacity = 10;

    public function mount(int $eventId): void
    {
        $this->event = app(Organization::class)->events()->findOrFail($eventId);

        Gate::authorize('view', $this->event);
    }

    #[Computed]
    public function jobs(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->event->volunteerJobs()
            ->with(['shifts' => fn ($q) => $q->withCount('signups')->orderBy('starts_at')])
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
        $this->showJobModal = true;
    }

    public function saveJob(): void
    {
        Gate::authorize('manageJobs', $this->event);

        $this->validate([
            'jobName' => ['required', 'string', 'max:255'],
            'jobDescription' => ['nullable', 'string'],
            'jobInstructions' => ['nullable', 'string'],
        ]);

        if ($this->editingJobId) {
            $job = $this->event->volunteerJobs()->findOrFail($this->editingJobId);
            (new UpdateVolunteerJob)->execute(
                job: $job,
                name: $this->jobName,
                description: $this->jobDescription ?: null,
                instructions: $this->jobInstructions ?: null,
            );
        } else {
            (new CreateVolunteerJob)->execute(
                event: $this->event,
                name: $this->jobName,
                description: $this->jobDescription ?: null,
                instructions: $this->jobInstructions ?: null,
            );
        }

        $this->showJobModal = false;
        $this->reset('editingJobId', 'jobName', 'jobDescription', 'jobInstructions');
        unset($this->jobs);
    }

    public function deleteJob(int $jobId): void
    {
        Gate::authorize('manageJobs', $this->event);

        $job = $this->event->volunteerJobs()->findOrFail($jobId);

        try {
            (new DeleteVolunteerJob)->execute($job);
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

        $this->reset('editingShiftId', 'shiftStartsAt', 'shiftEndsAt');
        $this->shiftCapacity = 10;
        $this->shiftJobId = $jobId;
        $this->showShiftModal = true;
    }

    public function openEditShift(int $shiftId): void
    {
        Gate::authorize('manageJobs', $this->event);

        $shift = $this->findShift($shiftId);
        $this->editingShiftId = $shift->id;
        $this->shiftJobId = $shift->volunteer_job_id;
        $this->shiftStartsAt = $shift->starts_at->format('Y-m-d\TH:i');
        $this->shiftEndsAt = $shift->ends_at->format('Y-m-d\TH:i');
        $this->shiftCapacity = $shift->capacity;
        $this->showShiftModal = true;
    }

    public function saveShift(): void
    {
        Gate::authorize('manageJobs', $this->event);

        $this->validate([
            'shiftStartsAt' => ['required', 'date'],
            'shiftEndsAt' => ['required', 'date', 'after:shiftStartsAt'],
            'shiftCapacity' => ['required', 'integer', 'min:1'],
        ]);

        if ($this->editingShiftId) {
            $shift = $this->findShift($this->editingShiftId);
            (new UpdateShift)->execute(
                shift: $shift,
                startsAt: Carbon::parse($this->shiftStartsAt),
                endsAt: Carbon::parse($this->shiftEndsAt),
                capacity: $this->shiftCapacity,
            );
        } else {
            $job = $this->event->volunteerJobs()->findOrFail($this->shiftJobId);
            (new CreateShift)->execute(
                job: $job,
                startsAt: Carbon::parse($this->shiftStartsAt),
                endsAt: Carbon::parse($this->shiftEndsAt),
                capacity: $this->shiftCapacity,
            );
        }

        $this->showShiftModal = false;
        $this->reset('editingShiftId', 'shiftJobId', 'shiftStartsAt', 'shiftEndsAt');
        $this->shiftCapacity = 10;
        unset($this->jobs);
    }

    public function deleteShift(int $shiftId): void
    {
        Gate::authorize('manageJobs', $this->event);

        $shift = $this->findShift($shiftId);

        try {
            (new DeleteShift)->execute($shift);
        } catch (HasSignupsException $e) {
            $this->addError('shift', $e->getMessage());

            return;
        }

        unset($this->jobs);
    }

    private function findShift(int $shiftId): \App\Models\Shift
    {
        return \App\Models\Shift::whereHas('volunteerJob', fn ($q) => $q->where('event_id', $this->event->id))
            ->findOrFail($shiftId);
    }
}
