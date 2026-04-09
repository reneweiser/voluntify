<?php

namespace App\Actions;

use App\Enums\StaffRole;
use App\Exceptions\DomainException;
use App\Models\ActivityLog;
use App\Models\Project;
use App\Models\Volunteer;
use App\Notifications\ProfileDeletionConfirmation;
use App\Notifications\VolunteerProfileDeletedNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DeleteVolunteerProfile
{
    public function execute(Volunteer $volunteer): void
    {
        $volunteer->loadMissing('project.organization');
        $project = $volunteer->project;

        $this->guardAgainstNonCancellableShifts($volunteer, $project);

        $volunteerName = $volunteer->full_name;
        $organization = $project->organization;

        // 1. Send confirmation email synchronously (before deletion)
        try {
            $volunteer->notifyNow(new ProfileDeletionConfirmation($project));
        } catch (\Throwable $e) {
            Log::warning('Failed to send profile deletion confirmation email', [
                'volunteer_id' => $volunteer->id,
                'error' => $e->getMessage(),
            ]);
        }

        // 2. Collect upcoming shift details for organizer notification (before deletion)
        $shiftSummary = $this->collectUpcomingShiftSummary($volunteer);

        // 3. DB::transaction: clean up activity logs + delete volunteer
        DB::transaction(function () use ($volunteer, $volunteerName) {
            // Nullify causer references and preserve name in properties
            ActivityLog::where('causer_type', Volunteer::class)
                ->where('causer_id', $volunteer->id)
                ->each(function (ActivityLog $log) use ($volunteerName) {
                    $properties = $log->properties ?? [];
                    $properties['deleted_volunteer_name'] = $volunteerName;
                    $log->update([
                        'causer_type' => null,
                        'causer_id' => null,
                        'properties' => $properties,
                    ]);
                });

            // Preserve name in subject references (subject columns are NOT nullable)
            ActivityLog::where('subject_type', Volunteer::class)
                ->where('subject_id', $volunteer->id)
                ->each(function (ActivityLog $log) use ($volunteerName) {
                    $properties = $log->properties ?? [];
                    $properties['deleted_volunteer_name'] = $volunteerName;
                    $log->update(['properties' => $properties]);
                });

            // Cascade handles child records (signups, tickets, gear, tokens, etc.)
            $volunteer->delete();
        });

        // 4. Notify organizers (outside transaction, after successful delete)
        $organizers = $organization->users()
            ->wherePivot('role', StaffRole::Organizer)
            ->get();

        foreach ($organizers as $organizer) {
            try {
                $organizer->notify(new VolunteerProfileDeletedNotification(
                    $volunteerName,
                    $project,
                    $shiftSummary,
                ));
            } catch (\Throwable $e) {
                Log::warning('Failed to notify organizer about volunteer deletion', [
                    'organizer_id' => $organizer->id,
                    'volunteer_name' => $volunteerName,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private function guardAgainstNonCancellableShifts(Volunteer $volunteer, Project $project): void
    {
        if (! $project->isCancellationAllowed()) {
            return;
        }

        $cutoffHours = $project->cancellation_cutoff_hours;

        $hasBlockingShifts = $volunteer->shiftSignups()
            ->active()
            ->whereHas('shift', function ($query) use ($cutoffHours) {
                $query->where(function ($q) use ($cutoffHours) {
                    // Shift with defined times: not completed AND not cancellable
                    $q->where(function ($inner) use ($cutoffHours) {
                        $inner->whereNotNull('starts_at')
                            ->where('ends_at', '>', now())
                            ->where('starts_at', '<=', now()->addHours($cutoffHours));
                    })
                    // Shift without defined times: shift_date not in past AND not cancellable
                        ->orWhere(function ($inner) use ($cutoffHours) {
                            $inner->whereNull('starts_at')
                                ->where('shift_date', '>=', now()->toDateString())
                                ->where('shift_date', '<', now()->addHours($cutoffHours)->startOfDay()->toDateString());
                        });
                });
            })
            ->exists();

        if ($hasBlockingShifts) {
            throw new DomainException(
                'Dein Profil kann gerade nicht gelöscht werden. Du hast dich verbindlich für Schichten angemeldet, bei denen der Stornierungszeitraum bereits abgelaufen ist. Sobald alle deine Schichten abgeschlossen oder innerhalb der Frist storniert sind, kannst du dein Profil jederzeit löschen.'
            );
        }
    }

    private function collectUpcomingShiftSummary(Volunteer $volunteer): string
    {
        $upcomingSignups = $volunteer->shiftSignups()
            ->active()
            ->whereHas('shift', fn ($q) => $q->where(function ($sq) {
                $sq->where(fn ($inner) => $inner->whereNotNull('starts_at')->where('starts_at', '>', now()))
                    ->orWhere(fn ($inner) => $inner->whereNull('starts_at')->where('shift_date', '>', now()->toDateString()));
            }))
            ->with('shift.volunteerJob.event')
            ->get();

        if ($upcomingSignups->isEmpty()) {
            return '';
        }

        return $upcomingSignups->map(function ($signup) {
            $shift = $signup->shift;
            $job = $shift->volunteerJob;
            $event = $job->event;
            $tz = $event->project->timezone ?? 'UTC';

            return "- {$job->name} ({$event->name}): {$shift->shift_date->setTimezone($tz)->format('d.m.Y')} {$shift->displayTimeRange($tz)}";
        })->implode("\n");
    }
}
