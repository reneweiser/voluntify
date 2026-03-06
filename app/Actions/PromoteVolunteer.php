<?php

namespace App\Actions;

use App\Enums\StaffRole;
use App\Exceptions\UserAlreadyInOrganizationException;
use App\Exceptions\VolunteerAlreadyPromotedException;
use App\Models\Organization;
use App\Models\User;
use App\Models\Volunteer;
use App\Models\VolunteerPromotion;
use App\Notifications\VolunteerPromoted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PromoteVolunteer
{
    public function execute(
        Volunteer $volunteer,
        Organization $organization,
        StaffRole $role,
        User $promotedBy,
    ): VolunteerPromotion {
        if ($volunteer->user_id) {
            throw new VolunteerAlreadyPromotedException('This volunteer has already been promoted.');
        }

        $temporaryPassword = null;
        $isNewUser = false;

        $promotion = DB::transaction(function () use ($volunteer, $organization, $role, $promotedBy, &$temporaryPassword, &$isNewUser) {
            $user = User::where('email', $volunteer->email)->first();

            if (! $user) {
                $temporaryPassword = Str::random(16);
                $isNewUser = true;

                $user = User::create([
                    'name' => $volunteer->name,
                    'email' => $volunteer->email,
                    'password' => $temporaryPassword,
                    'must_change_password' => true,
                ]);
            }

            if ($organization->users()->where('user_id', $user->id)->exists()) {
                throw new UserAlreadyInOrganizationException('This user is already a member of the organization.');
            }

            $organization->users()->attach($user, ['role' => $role]);

            $volunteer->update(['user_id' => $user->id]);

            return VolunteerPromotion::create([
                'volunteer_id' => $volunteer->id,
                'user_id' => $user->id,
                'promoted_by' => $promotedBy->id,
                'role' => $role,
                'promoted_at' => now(),
            ]);
        });

        if ($isNewUser) {
            $promotion->user->notify(new VolunteerPromoted(
                $organization,
                ucfirst(str_replace('_', ' ', $role->value)),
                $temporaryPassword,
            ));
        }

        return $promotion;
    }
}
