<?php

namespace App\Livewire;

use App\Actions\AuthenticateScanner;
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

    public function mount(string $scannerToken): void
    {
        $scanner = ProjectScanner::where('scanner_token', $scannerToken)->firstOrFail();

        $this->scannerToken = $scannerToken;
        $this->scannerName = $scanner->name;
        $this->startsAt = $scanner->starts_at->format('H:i');
        $this->endsAt = $scanner->ends_at->format('H:i');

        if ($scanner->isExpired()) {
            $this->errorMessage = 'Scanner window has closed.';

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

        $rateLimitKey = 'scanner_auth:'.$this->scannerToken.':'.request()->ip();

        if (RateLimiter::tooManyAttempts($rateLimitKey, 5)) {
            $seconds = RateLimiter::availableIn($rateLimitKey);
            $this->errorMessage = "Too many attempts. Please try again in {$seconds} seconds.";
            $this->authCode = '';

            return;
        }

        $scanner = ProjectScanner::where('scanner_token', $this->scannerToken)->firstOrFail();

        $action = new AuthenticateScanner;
        $result = $action->execute($scanner, $this->authCode);

        if (! $result) {
            RateLimiter::hit($rateLimitKey, 60);
            $this->errorMessage = 'Invalid code. Please try again.';
            $this->authCode = '';

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
