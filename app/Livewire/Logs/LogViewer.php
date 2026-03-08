<?php

namespace App\Livewire\Logs;

use App\Enums\StaffRole;
use App\Services\LogParser;
use Illuminate\Support\Facades\Process;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Logs')]
class LogViewer extends Component
{
    public string $selectedFile = '';

    public string $search = '';

    public int $tail = 100;

    public function boot(): void
    {
        $organization = currentOrganization();

        $hasAccess = $organization->users()
            ->where('user_id', auth()->id())
            ->wherePivot('role', StaffRole::Organizer)
            ->exists();

        if (! $hasAccess) {
            abort(403);
        }
    }

    public function mount(): void
    {
        $files = $this->logFiles;

        if (count($files) > 0) {
            $this->selectedFile = $files[0];
        }
    }

    /** @return list<string> */
    #[Computed]
    public function logFiles(): array
    {
        $files = glob(storage_path('logs/*.log'));

        if ($files === false) {
            return [];
        }

        $basenames = array_map('basename', $files);
        rsort($basenames);

        return array_values($basenames);
    }

    /** @return list<array{level: string, timestamp: string, message: string, trace: string}> */
    #[Computed]
    public function logContent(): array
    {
        if ($this->selectedFile === '' || ! in_array($this->selectedFile, $this->logFiles, true)) {
            return [];
        }

        $path = storage_path('logs/'.basename($this->selectedFile));
        $realPath = realpath($path);
        $logsDir = realpath(storage_path('logs'));

        if ($realPath === false || $logsDir === false || ! str_starts_with($realPath, $logsDir)) {
            return [];
        }

        $tail = max(1, min($this->tail, 1000));
        $escapedPath = escapeshellarg($realPath);

        $result = Process::run("tail -n {$tail} {$escapedPath}");

        if ($result->failed()) {
            return [];
        }

        $lines = explode("\n", $result->output());
        $entries = app(LogParser::class)->parse($lines);

        if ($this->search !== '') {
            $search = $this->search;
            $entries = array_values(array_filter(
                $entries,
                fn (array $entry): bool => stripos($entry['message'].$entry['trace'], $search) !== false
            ));
        }

        return $entries;
    }

    public static function levelColor(string $level): string
    {
        return match (strtoupper($level)) {
            'ERROR', 'CRITICAL', 'ALERT', 'EMERGENCY' => 'red',
            'WARNING', 'NOTICE' => 'amber',
            'INFO' => 'blue',
            default => 'zinc',
        };
    }

    public static function levelBorderClass(string $level): string
    {
        return match (strtoupper($level)) {
            'ERROR', 'CRITICAL', 'ALERT', 'EMERGENCY' => 'border-l-red-500',
            'WARNING', 'NOTICE' => 'border-l-amber-500',
            'INFO' => 'border-l-blue-500',
            default => 'border-l-zinc-500',
        };
    }

    public function downloadLog(): mixed
    {
        if ($this->selectedFile === '' || ! in_array($this->selectedFile, $this->logFiles, true)) {
            return null;
        }

        $path = storage_path('logs/'.basename($this->selectedFile));
        $realPath = realpath($path);
        $logsDir = realpath(storage_path('logs'));

        if ($realPath === false || $logsDir === false || ! str_starts_with($realPath, $logsDir)) {
            return null;
        }

        return response()->download($realPath);
    }
}
