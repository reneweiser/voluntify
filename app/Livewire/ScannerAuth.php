<?php

namespace App\Livewire;

use App\Actions\AuthenticateScanner;
use App\Enums\AuthenticationResult;
use App\Events\Activity\ScannerLockout;
use App\Models\ProjectScanner;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Scanner Login')]
#[Layout('layouts.scanner')]
class ScannerAuth extends Component
{
    #[Locked]
    public string $scannerToken;

    public string $authCode = '';

    public string $errorMessage = '';

    public string $scannerName = '';

    public ?string $startsAt = null;

    public ?string $endsAt = null;

    #[Locked]
    public bool $formDisabled = false;

    public function mount(string $scannerToken): void
    {
        $scanner = ProjectScanner::where('scanner_token', $scannerToken)->firstOrFail();

        $this->scannerToken = $scannerToken;
        $this->scannerName = $scanner->name;
        $this->startsAt = $scanner->starts_at->format('H:i');
        $this->endsAt = $scanner->ends_at->format('H:i');

        if ($scanner->isExpired()) {
            $this->errorMessage = 'Das Scanner-Fenster ist abgelaufen.';
            $this->formDisabled = true;

            return;
        }

        if ($scanner->isScheduled()) {
            $this->errorMessage = 'Scanner ist noch nicht aktiv. Das Zeitfenster beginnt um '.$scanner->starts_at->format('H:i').'.';
            $this->formDisabled = true;

            return;
        }

        if (session('scanner_id') === $scanner->id) {
            $this->redirect(route('scanner.app', $scannerToken));
        }
    }

    public function authenticate(): void
    {
        $this->validate([
            'authCode' => ['required', 'digits:6'],
        ]);

        $rateLimitKey = 'scanner_auth:'.$this->scannerToken;

        if (RateLimiter::tooManyAttempts($rateLimitKey, 5)) {
            $scanner = ProjectScanner::where('scanner_token', $this->scannerToken)->firstOrFail();

            if ($scanner->isExpired() || $scanner->isScheduled()) {
                $this->errorMessage = $scanner->isExpired()
                    ? 'Das Scanner-Fenster ist abgelaufen.'
                    : 'Scanner ist noch nicht aktiv. Das Zeitfenster beginnt um '.$scanner->starts_at->format('H:i').'.';
                $this->formDisabled = true;

                return;
            }

            $minutes = (int) ceil(RateLimiter::availableIn($rateLimitKey) / 60);
            $this->errorMessage = "Zu viele Versuche. Bitte warte {$minutes} Minuten.";
            $this->authCode = '';

            return;
        }

        $scanner = ProjectScanner::where('scanner_token', $this->scannerToken)->firstOrFail();

        $action = new AuthenticateScanner;
        $result = $action->execute($scanner, $this->authCode);

        if ($result !== AuthenticationResult::Success) {
            RateLimiter::hit($rateLimitKey, 1800);
            $this->authCode = '';

            match ($result) {
                AuthenticationResult::Expired => $this->errorMessage = 'Das Scanner-Fenster ist abgelaufen.',
                AuthenticationResult::NotYetActive => $this->errorMessage = 'Scanner ist noch nicht aktiv. Das Zeitfenster beginnt um '.$scanner->starts_at->format('H:i').'.',
                AuthenticationResult::InvalidCode => $this->errorMessage = 'Ungültiger Code. Bitte versuche es erneut.',
                default => null,
            };

            if ($result === AuthenticationResult::Expired || $result === AuthenticationResult::NotYetActive) {
                $this->formDisabled = true;
            }

            if ($result === AuthenticationResult::InvalidCode && RateLimiter::tooManyAttempts($rateLimitKey, 5)) {
                ScannerLockout::dispatch($scanner);
            }

            return;
        }

        RateLimiter::clear($rateLimitKey);

        session()->regenerate();

        session([
            'scanner_id' => $scanner->id,
            'scanner_authenticated_at' => now()->toISOString(),
        ]);

        $this->redirect(route('scanner.app', $this->scannerToken));
    }
}
