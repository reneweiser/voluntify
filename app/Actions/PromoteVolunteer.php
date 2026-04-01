<?php

namespace App\Actions;

use App\Enums\ScannerType;
use App\Enums\StaffRole;
use App\Events\Activity\VolunteerPromotedEvent;
use App\Exceptions\DomainException;
use App\Exceptions\VolunteerAlreadyPromotedException;
use App\Models\Organization;
use App\Models\ProjectScanner;
use App\Models\User;
use App\Models\Volunteer;
use App\Models\VolunteerPromotion;
use App\Notifications\VolunteerPromoted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PromoteVolunteer
{
    /**
     * Promote a volunteer to VA (scanner assignment) or Organizer (user account).
     */
    public function execute(
        Volunteer $volunteer,
        Organization $organization,
        StaffRole $role,
        User $promotedBy,
        ?int $scannerId = null,
    ): VolunteerPromotion {
        if ($role === StaffRole::VolunteerAdmin) {
            return $this->promoteToVa($volunteer, $organization, $promotedBy, $scannerId);
        }

        return $this->promoteToOrganizer($volunteer, $organization, $promotedBy);
    }

    private function promoteToVa(
        Volunteer $volunteer,
        Organization $organization,
        User $promotedBy,
        ?int $scannerId,
    ): VolunteerPromotion {
        if (! $scannerId) {
            throw new DomainException('Bitte wähle einen VA-Scanner aus.');
        }

        $scanner = ProjectScanner::where('id', $scannerId)
            ->where('type', ScannerType::VolunteerAdmin)
            ->whereHas('project', fn ($q) => $q->where('organization_id', $organization->id))
            ->firstOrFail();

        if ($scanner->assignees()->where('email', $volunteer->email)->exists()) {
            throw new DomainException('Diese Person ist bereits diesem Scanner zugewiesen.');
        }

        return DB::transaction(function () use ($volunteer, $scanner, $promotedBy) {
            $scanner->assignees()->create([
                'email' => $volunteer->email,
            ]);

            return VolunteerPromotion::create([
                'volunteer_id' => $volunteer->id,
                'user_id' => null,
                'promoted_by' => $promotedBy->id,
                'role' => StaffRole::VolunteerAdmin,
                'promoted_at' => now(),
            ]);
        });
    }

    private function promoteToOrganizer(
        Volunteer $volunteer,
        Organization $organization,
        User $promotedBy,
    ): VolunteerPromotion {
        if ($volunteer->user_id) {
            throw new VolunteerAlreadyPromotedException('This volunteer has already been promoted.');
        }

        $temporaryPassword = null;
        $isNewUser = false;

        $promotion = DB::transaction(function () use ($volunteer, $organization, $promotedBy, &$temporaryPassword, &$isNewUser) {
            $user = User::where('email', $volunteer->email)->first();

            if (! $user) {
                $temporaryPassword = Str::random(16);
                $isNewUser = true;

                $user = User::create([
                    'name' => $volunteer->full_name,
                    'email' => $volunteer->email,
                    'password' => $temporaryPassword,
                    'must_change_password' => true,
                    'email_verified_at' => now(),
                ]);
            }

            if (! $organization->users()->where('user_id', $user->id)->exists()) {
                $organization->users()->attach($user, ['role' => StaffRole::Organizer]);
            }

            $project = $volunteer->project;
            if ($project && ! $project->users()->where('user_id', $user->id)->exists()) {
                $project->users()->attach($user, ['role' => StaffRole::Organizer]);
            }

            $volunteer->update(['user_id' => $user->id]);

            return VolunteerPromotion::create([
                'volunteer_id' => $volunteer->id,
                'user_id' => $user->id,
                'promoted_by' => $promotedBy->id,
                'role' => StaffRole::Organizer,
                'promoted_at' => now(),
            ]);
        });

        VolunteerPromotedEvent::dispatch($promotion, $organization, $promotedBy);

        if ($isNewUser) {
            $promotion->user->notify(new VolunteerPromoted(
                $organization,
                'Organizer',
                $temporaryPassword,
            ));
        }

        return $promotion;
    }
}
