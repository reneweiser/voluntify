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

    public int $newSignupCount = 0;

    public int $skippedFullCount = 0;

    public string $eventName = '';

    public string $eventPublicToken = '';

    public function mount(string $token): void
    {
        try {
            $result = app(CompleteEmailVerification::class)->execute($token);

            $this->verified = true;
            $this->newSignupCount = count($result->newSignups);
            $this->skippedFullCount = count($result->skippedFull);

            $latestSignup = $result->volunteer->shiftSignups()
                ->with('shift.volunteerJob.event')
                ->latest()
                ->first();

            if ($latestSignup?->shift?->volunteerJob?->event) {
                $event = $latestSignup->shift->volunteerJob->event;
                $this->eventName = $event->name;
                $this->eventPublicToken = $event->public_token;
            }
        } catch (ExpiredVerificationException) {
            $this->expired = true;
        } catch (ModelNotFoundException|DomainException) {
            abort(404);
        }
    }
}
