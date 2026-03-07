<div class="mx-auto max-w-7xl p-6">
    <flux:heading size="xl" class="mb-6">{{ __('Logs') }}</flux:heading>

    @if (count($this->logFiles) === 0)
        <flux:text>{{ __('No log files found.') }}</flux:text>
    @else
        <div class="mb-4 flex flex-wrap items-end gap-3">
            <flux:select wire:model.live="selectedFile" label="{{ __('Log file') }}" class="min-w-48">
                @foreach ($this->logFiles as $file)
                    <option value="{{ $file }}">{{ $file }}</option>
                @endforeach
            </flux:select>

            <flux:input wire:model.live.debounce.300ms="search" placeholder="{{ __('Filter...') }}" class="min-w-48" />

            <flux:select wire:model.live="tail" label="{{ __('Lines') }}" class="w-24">
                <option value="50">50</option>
                <option value="100">100</option>
                <option value="500">500</option>
                <option value="1000">1000</option>
            </flux:select>

            <flux:button wire:click="downloadLog" icon="arrow-down-tray" variant="ghost" size="sm">
                {{ __('Download') }}
            </flux:button>
        </div>

        @if (count($this->logContent) === 0)
            <flux:text>{{ __('No log content to display.') }}</flux:text>
        @else
            {{-- border-l-red-500 border-l-amber-500 border-l-blue-500 border-l-zinc-500 --}}
            <div class="max-h-[70vh] space-y-1 overflow-auto rounded-lg bg-zinc-900 p-3">
                @foreach ($this->logContent as $index => $entry)
                    <div
                        x-data="{ open: false }"
                        class="border-l-4 {{ \App\Livewire\Logs\LogViewer::levelBorderClass($entry['level']) }} rounded-r bg-zinc-800/60"
                        wire:key="log-entry-{{ $index }}"
                    >
                        <button
                            x-on:click="open = !open"
                            class="flex w-full items-center gap-2 px-3 py-2 text-left font-mono text-sm text-zinc-100 hover:bg-zinc-700/30"
                        >
                            @if (trim($entry['trace']) !== '')
                                <svg
                                    x-bind:class="open && 'rotate-90'"
                                    class="size-4 shrink-0 text-zinc-400 transition-transform duration-200"
                                    xmlns="http://www.w3.org/2000/svg"
                                    viewBox="0 0 20 20"
                                    fill="currentColor"
                                >
                                    <path fill-rule="evenodd" d="M8.22 5.22a.75.75 0 0 1 1.06 0l4.25 4.25a.75.75 0 0 1 0 1.06l-4.25 4.25a.75.75 0 0 1-1.06-1.06L11.94 10 8.22 6.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" />
                                </svg>
                            @else
                                <span class="size-4 shrink-0"></span>
                            @endif

                            @if ($entry['timestamp'] !== '')
                                <span class="shrink-0 text-zinc-500">{{ $entry['timestamp'] }}</span>
                            @endif

                            <flux:badge size="sm" :color="$entry['level'] === 'ERROR' || $entry['level'] === 'CRITICAL' || $entry['level'] === 'ALERT' || $entry['level'] === 'EMERGENCY' ? 'red' : ($entry['level'] === 'WARNING' || $entry['level'] === 'NOTICE' ? 'amber' : ($entry['level'] === 'INFO' ? 'blue' : 'zinc'))" variant="solid">
                                {{ $entry['level'] }}
                            </flux:badge>

                            <span class="min-w-0 truncate">{{ $entry['message'] }}</span>
                        </button>

                        @if (trim($entry['trace']) !== '')
                            <div x-show="open" x-collapse x-cloak class="border-t border-zinc-700/50 px-3 py-2">
                                <pre class="whitespace-pre-wrap font-mono text-xs leading-relaxed text-zinc-400">{{ $entry['trace'] }}</pre>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    @endif
</div>
