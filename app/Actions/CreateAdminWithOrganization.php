<?php

namespace App\Actions;

use App\Enums\StaffRole;
use App\Exceptions\DomainException;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateAdminWithOrganization
{
    public function execute(string $name, string $email, string $password, string $organizationName): User
    {
        if (User::where('email', $email)->exists()) {
            throw new DomainException("A user with email [{$email}] already exists.");
        }

        return DB::transaction(function () use ($name, $email, $password, $organizationName) {
            $organization = Organization::create([
                'name' => $organizationName,
                'slug' => Organization::generateUniqueSlug($organizationName),
            ]);

            $user = User::create([
                'name' => $name,
                'email' => $email,
                'password' => $password,
                'email_verified_at' => now(),
                'must_change_password' => false,
            ]);

            $organization->users()->attach($user, ['role' => StaffRole::Organizer]);

            return $user;
        });
    }
}
