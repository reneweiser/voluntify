<?php

namespace App\Livewire\Projects;

use App\Actions\CreateAnnouncement;
use App\Models\Project;
use App\Models\Shift;
use App\Models\Volunteer;
use App\Models\VolunteerJob;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Ankündigungen')]
class AnnouncementComposer extends Component
{
    #[Locked]
    public Project $project;

    public string $subject = '';

    public string $body = '';

    public string $selectedEventId = '';

    public string $selectedJobId = '';

    public string $selectedShiftId = '';

    public string $sendAt = '';

    public bool $showConfirmModal = false;

    public function mount(int $projectId): void
    {
        $this->project = currentOrganization()->projects()->findOrFail($projectId);

        Gate::authorize('update', $this->project);
    }

    #[Computed]
    public function events(): Collection
    {
        return $this->project->events()->orderBy('starts_at')->get();
    }

    #[Computed]
    public function jobs(): Collection
    {
        if (! $this->selectedEventId) {
            return new Collection;
        }

        return VolunteerJob::where('event_id', (int) $this->selectedEventId)
            ->whereHas('event', fn ($q) => $q->where('project_id', $this->project->id))
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function shifts(): Collection
    {
        if (! $this->selectedJobId) {
            return new Collection;
        }

        return Shift::where('volunteer_job_id', (int) $this->selectedJobId)
            ->whereHas('volunteerJob', fn ($q) => $q->whereHas(
                'event', fn ($eq) => $eq->where('project_id', $this->project->id)
            ))
            ->orderBy('shift_date')
            ->get();
    }

    #[Computed]
    public function recipientCount(): int
    {
        $query = Volunteer::where('project_id', $this->project->id)
            ->whereNotNull('email_verified_at');

        if ($this->selectedEventId) {
            $query->whereHas('shiftSignups', function ($q) {
                $q->active()->whereHas('shift.volunteerJob', function ($jq) {
                    $jq->where('event_id', (int) $this->selectedEventId);

                    if ($this->selectedJobId) {
                        $jq->where('id', (int) $this->selectedJobId);
                    }
                });

                if ($this->selectedShiftId) {
                    $q->where('shift_id', (int) $this->selectedShiftId);
                }
            });
        }

        return $query->count();
    }

    #[Computed]
    public function history(): Collection
    {
        return $this->project->announcements()
            ->with('creator', 'event')
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();
    }

    public function updatedSelectedEventId(): void
    {
        $this->selectedJobId = '';
        $this->selectedShiftId = '';
        unset($this->jobs, $this->shifts, $this->recipientCount);
    }

    public function updatedSelectedJobId(): void
    {
        $this->selectedShiftId = '';
        unset($this->shifts, $this->recipientCount);
    }

    public function updatedSelectedShiftId(): void
    {
        unset($this->recipientCount);
    }

    public function confirmSend(): void
    {
        $this->validate([
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:10000'],
            'sendAt' => ['nullable', 'date', 'after:now'],
        ]);

        $this->showConfirmModal = true;
    }

    public function send(): void
    {
        Gate::authorize('update', $this->project);

        $this->validate([
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:10000'],
            'sendAt' => ['nullable', 'date', 'after:now'],
        ]);

        $eventId = $this->selectedEventId ? (int) $this->selectedEventId : null;
        $jobId = $this->selectedJobId ? (int) $this->selectedJobId : null;
        $shiftId = $this->selectedShiftId ? (int) $this->selectedShiftId : null;

        if ($eventId && ! $this->project->events()->where('id', $eventId)->exists()) {
            abort(403);
        }

        if ($jobId && ! VolunteerJob::where('id', $jobId)
            ->whereHas('event', fn ($q) => $q->where('project_id', $this->project->id))
            ->exists()) {
            abort(403);
        }

        if ($shiftId && ! Shift::where('id', $shiftId)
            ->whereHas('volunteerJob.event', fn ($q) => $q->where('project_id', $this->project->id))
            ->exists()) {
            abort(403);
        }

        $action = app(CreateAnnouncement::class);
        $action->execute($this->project, [
            'subject' => $this->subject,
            'body' => $this->body,
            'event_id' => $eventId,
            'job_id' => $jobId,
            'shift_id' => $shiftId,
            'send_at' => $this->sendAt ?: null,
        ], auth()->user());

        $this->reset(['subject', 'body', 'selectedEventId', 'selectedJobId', 'selectedShiftId', 'sendAt', 'showConfirmModal']);
        unset($this->history, $this->recipientCount);

        $this->dispatch('announcement-sent');
    }
}
