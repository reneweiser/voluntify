<?php

namespace App\Livewire\Auth;

use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Change password')]
#[Layout('layouts.auth')]
class ChangePassword extends Component
{
    public string $password = '';

    public string $password_confirmation = '';

    public function changePassword(): void
    {
        $validated = $this->validate([
            'password' => ['required', 'string', Password::default(), 'confirmed'],
        ]);

        $user = Auth::user();
        $user->update([
            'password' => $validated['password'],
            'must_change_password' => false,
        ]);

        $this->redirectRoute('dashboard');
    }
}
