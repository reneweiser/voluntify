<?php

namespace App\Actions;

use App\Enums\StaffRole;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateOrganization
{
    public function execute(User $user, string $name, ?string $slug = null): Organization
    {
        return DB::transaction(function () use ($user, $name, $slug) {
            $organization = Organization::create([
                'name' => $name,
                'slug' => $slug ?? Organization::generateUniqueSlug($name),
            ]);

            $organization->users()->attach($user, [
                'role' => StaffRole::Organizer,
            ]);

            return $organization;
        });
    }
}
