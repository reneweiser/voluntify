<?php

namespace App\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateInvitedUser
{
    public function __construct(private CreateOrganization $createOrganization) {}

    /**
     * @return array{user: User, temporaryPassword: string}
     */
    public function execute(string $name, string $email): array
    {
        $temporaryPassword = Str::random(16);

        $user = DB::transaction(function () use ($name, $email, $temporaryPassword): User {
            $user = User::create([
                'name' => $name,
                'email' => $email,
                'password' => $temporaryPassword,
                'must_change_password' => true,
                'email_verified_at' => now(),
            ]);

            $this->createOrganization->execute($user, $user->name."'s Organization", isPersonal: true);

            return $user;
        });

        return [
            'user' => $user,
            'temporaryPassword' => $temporaryPassword,
        ];
    }
}
