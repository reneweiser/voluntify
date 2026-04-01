<?php

namespace App\Livewire;

use App\Models\ProjectScanner;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Scanner')]
#[Layout('layouts.scanner')]
class ScannerApp extends Component
{
    #[Locked]
    public string $scannerToken;

    #[Locked]
    public ?int $scannerId = null;

    #[Locked]
    public int $projectId;

    #[Locked]
    public string $scannerType = '';

    #[Locked]
    public array $modes = [];

    #[Locked]
    public ?int $eventId = null;

    public string $scannerName = '';

    public ?string $hintText = null;

    public function mount(string $scannerToken): void
    {
        $scanner = ProjectScanner::where('scanner_token', $scannerToken)->firstOrFail();

        if (! $scanner->isActive()) {
            abort(403, 'Scanner window is not active.');
        }

        if (session('scanner_id') !== $scanner->id) {
            abort(403, 'Not authenticated for this scanner.');
        }

        $this->scannerToken = $scannerToken;
        $this->scannerId = $scanner->id;
        $this->projectId = $scanner->project_id;
        $this->scannerType = $scanner->type->value;
        $this->modes = $scanner->modes ?? [];
        $this->eventId = $scanner->event_id;
        $this->scannerName = $scanner->name;
        $this->hintText = $scanner->hint_text;
    }

    #[Computed]
    public function dataUrl(): string
    {
        return '/api/scanner/'.$this->scannerId.'/data';
    }

    #[Computed]
    public function syncUrl(): string
    {
        return '/api/scanner/'.$this->scannerId.'/sync';
    }

    #[Computed]
    public function gearPickupUrl(): string
    {
        return '/api/scanner/'.$this->scannerId.'/gear-pickup';
    }

    #[Computed]
    public function guestSyncUrl(): string
    {
        return '/api/scanner/'.$this->scannerId.'/guest-sync';
    }

    #[Computed]
    public function guestGearPickupUrl(): string
    {
        return '/api/scanner/'.$this->scannerId.'/guest-gear-pickup';
    }
}
