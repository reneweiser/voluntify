<?php

namespace App\Livewire\Settings;

use App\Enums\StaffRole;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\StaffInvitation;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Team')]
class TeamManagement extends Component
{
    public string $inviteName = '';

    public string $inviteEmail = '';

    public string $inviteRole = 'volunteer_admin';

    public string $aiApiKey = '';

    public function mount(): void
    {
        Gate::authorize('manageTeam', $this->organization());
    }

    #[Computed]
    public function organization(): Organization
    {
        return app(Organization::class);
    }

    #[Computed]
    public function members(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->organization()->users()->orderBy('name')->get();
    }

    public function updateRole(int $userId, string $role): void
    {
        Gate::authorize('manageTeam', $this->organization());

        if ($userId === Auth::id()) {
            $this->addError('role', 'You cannot change your own role.');

            return;
        }

        $staffRole = StaffRole::from($role);

        $this->organization()->users()->updateExistingPivot($userId, [
            'role' => $staffRole,
        ]);

        unset($this->members);
    }

    public function removeMember(int $userId): void
    {
        Gate::authorize('manageTeam', $this->organization());

        if ($userId === Auth::id()) {
            $this->addError('member', 'You cannot remove yourself.');

            return;
        }

        $this->organization()->users()->detach($userId);

        unset($this->members);
    }

    public function inviteMember(): void
    {
        Gate::authorize('manageTeam', $this->organization());

        $this->validate([
            'inviteName' => ['required', 'string', 'max:255'],
            'inviteEmail' => ['required', 'email', 'max:255'],
            'inviteRole' => ['required', 'string', 'in:organizer,volunteer_admin,entrance_staff'],
        ]);

        $user = User::where('email', $this->inviteEmail)->first();

        if (! $user) {
            $password = Str::random(16);

            $user = User::create([
                'name' => $this->inviteName,
                'email' => $this->inviteEmail,
                'password' => $password,
                'must_change_password' => true,
            ]);

            $user->notify(new StaffInvitation($this->organization(), $password));
        }

        if ($this->organization()->users()->where('user_id', $user->id)->exists()) {
            $this->addError('inviteEmail', 'This user is already a member.');

            return;
        }

        $this->organization()->users()->attach($user, [
            'role' => StaffRole::from($this->inviteRole),
        ]);

        $this->reset('inviteName', 'inviteEmail', 'inviteRole');
        $this->inviteRole = 'volunteer_admin';

        unset($this->members);

        $this->dispatch('member-invited');
    }

    public function saveAiApiKey(): void
    {
        Gate::authorize('update', $this->organization());

        $this->validate([
            'aiApiKey' => ['required', 'string', 'max:500'],
        ]);

        $this->organization()->update(['ai_api_key' => $this->aiApiKey]);

        $this->reset('aiApiKey');
        $this->dispatch('ai-key-saved');
    }

    public function removeAiApiKey(): void
    {
        Gate::authorize('update', $this->organization());

        $this->organization()->update(['ai_api_key' => null]);

        $this->dispatch('ai-key-removed');
    }

    #[Computed]
    public function maskedAiApiKey(): ?string
    {
        $key = $this->organization()->ai_api_key;

        if (! $key) {
            return null;
        }

        return Str::mask($key, '*', 8);
    }

    #[Computed]
    public function hasAiApiKey(): bool
    {
        return $this->organization()->ai_api_key !== null;
    }
}
