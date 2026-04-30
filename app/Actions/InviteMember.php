<?php

namespace App\Actions;

use App\Enums\StaffRole;
use App\Events\Activity\MemberInvited;
use App\Exceptions\MemberAlreadyExistsException;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\AddedToOrganization;
use App\Notifications\StaffInvitation;

class InviteMember
{
    public function __construct(private CreateInvitedUser $createInvitedUser) {}

    public function execute(Organization $organization, string $name, string $email, StaffRole $role, ?User $causer = null): User
    {
        $user = User::where('email', $email)->first();
        $isExistingUser = (bool) $user;

        if (! $user) {
            ['user' => $user, 'temporaryPassword' => $temporaryPassword] = $this->createInvitedUser->execute($name, $email);

            $user->notify(new StaffInvitation($organization, $temporaryPassword));
        }

        if ($organization->users()->where('user_id', $user->id)->exists()) {
            throw new MemberAlreadyExistsException;
        }

        $organization->users()->attach($user, [
            'role' => $role,
        ]);

        if ($causer) {
            MemberInvited::dispatch($organization, $user->name, $email, $role, $causer);
        }

        if ($isExistingUser) {
            $user->notify(new AddedToOrganization($organization, $role));
        }

        return $user;
    }
}
