<?php

namespace App\Livewire\Public;

use App\Actions\CompleteEmailVerification;
use App\Exceptions\DomainException;
use App\Exceptions\ExpiredVerificationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.public')]
#[Title('Email Verification')]
class EmailVerificationPage extends Component
{
    public bool $verified = false;

    public bool $expired = false;

    public bool $alreadyVerified = false;

    public string $eventName = '';

    public string $eventPublicToken = '';

    public string $continueSignupUrl = '';

    public function mount(string $token): void
    {
        try {
            $result = app(CompleteEmailVerification::class)->execute($token);

            $event = $result->event;
            $this->eventName = $event->name;
            $this->eventPublicToken = $event->public_token;
            $this->continueSignupUrl = route('events.public', $event->public_token).'?vt='.$result->token_hash;

            if ($result->verified_at->lt(now()->subSeconds(5))) {
                $this->alreadyVerified = true;
            } else {
                $this->verified = true;
            }
        } catch (ExpiredVerificationException) {
            $this->expired = true;
        } catch (ModelNotFoundException|DomainException) {
            abort(404);
        }
    }
}
