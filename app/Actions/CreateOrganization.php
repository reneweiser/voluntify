<?php

namespace App\Actions;

use App\Enums\StaffRole;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateOrganization
{
    public function execute(User $user, string $name, ?string $slug = null, bool $isPersonal = false): Organization
    {
        return DB::transaction(function () use ($user, $name, $slug, $isPersonal) {
            $organization = Organization::create([
                'name' => $name,
                'slug' => $slug ?? Organization::generateUniqueSlug($name),
            ]);

            $organization->users()->attach($user, [
                'role' => StaffRole::Organizer,
            ]);

            if ($isPersonal) {
                $user->update(['personal_organization_id' => $organization->id]);
            }

            return $organization;
        });
    }
}
