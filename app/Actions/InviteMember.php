<?php

namespace App\Actions;

use App\Enums\StaffRole;
use App\Exceptions\MemberAlreadyExistsException;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\AddedToOrganization;
use App\Notifications\StaffInvitation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InviteMember
{
    public function __construct(private CreateOrganization $createOrganization) {}

    public function execute(Organization $organization, string $name, string $email, StaffRole $role): User
    {
        $user = User::where('email', $email)->first();
        $isExistingUser = (bool) $user;

        if (! $user) {
            $password = Str::random(16);

            $user = DB::transaction(function () use ($name, $email, $password) {
                $user = User::create([
                    'name' => $name,
                    'email' => $email,
                    'password' => $password,
                    'must_change_password' => true,
                    'email_verified_at' => now(),
                ]);

                $this->createOrganization->execute($user, $user->name."'s Organization", isPersonal: true);

                return $user;
            });

            $user->notify(new StaffInvitation($organization, $password));
        }

        if ($organization->users()->where('user_id', $user->id)->exists()) {
            throw new MemberAlreadyExistsException;
        }

        $organization->users()->attach($user, [
            'role' => $role,
        ]);

        if ($isExistingUser) {
            $user->notify(new AddedToOrganization($organization, $role));
        }

        return $user;
    }
}
