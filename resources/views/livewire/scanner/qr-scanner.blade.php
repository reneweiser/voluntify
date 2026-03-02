<div class="flex min-h-screen flex-col">
    {{-- Top bar --}}
    <header class="flex items-center justify-between border-b border-zinc-700 bg-zinc-800 px-4 py-3">
        <div class="flex items-center gap-3">
            <div class="flex size-8 items-center justify-center rounded-md bg-emerald-600 text-white shadow-sm">
                <x-app-logo-icon class="size-5 fill-current text-white" />
            </div>
            <flux:heading size="lg" class="text-white">Scanner</flux:heading>
        </div>
        <flux:button variant="ghost" href="{{ route('scanner.lookup') }}" wire:navigate size="sm" class="text-zinc-300 hover:text-white">
            Manual Lookup
        </flux:button>
    </header>

    {{-- Event selector --}}
    <div class="border-b border-zinc-700 bg-zinc-800/50 px-4 py-3">
        <flux:select wire:model.live="selectedEventId" placeholder="Select an event...">
            @foreach ($this->events as $event)
                <flux:select.option :value="$event->id">{{ $event->name }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    {{-- Scanner viewfinder area --}}
    <div class="flex flex-1 items-center justify-center p-4" data-scanner-viewfinder>
        @if (! $this->selectedEventId)
            <div class="text-center text-zinc-400">
                <flux:icon name="qr-code" class="mx-auto size-16 text-zinc-600" />
                <p class="mt-4 text-lg">Select an event to start scanning</p>
            </div>
        @else
            <div
                x-data="scannerApp({ eventId: {{ $this->selectedEventId }} })"
                class="w-full max-w-md"
            >
                {{-- Camera viewfinder --}}
                <div class="relative aspect-square w-full overflow-hidden rounded-2xl bg-black">
                    <video x-ref="video" class="h-full w-full object-cover" playsinline></video>
                    <canvas x-ref="canvas" class="hidden"></canvas>
                </div>

                {{-- Result panel --}}
                <div x-show="state !== 'scanning' && state !== 'idle' && state !== 'loading'" x-cloak class="mt-4">
                    <template x-if="state === 'result'">
                        <div class="rounded-xl border border-zinc-700 bg-zinc-800 p-4">
                            <p class="text-lg font-semibold text-white" x-text="result?.name"></p>
                            <p class="text-sm text-zinc-400" x-text="result?.email"></p>
                            <div class="mt-3 flex gap-2">
                                <flux:button variant="primary" x-on:click="confirmArrival()" class="flex-1">
                                    Confirm Arrival
                                </flux:button>
                                <flux:button variant="ghost" x-on:click="dismiss()" class="flex-1">
                                    Dismiss
                                </flux:button>
                            </div>
                        </div>
                    </template>
                    <template x-if="state === 'duplicate'">
                        <div class="rounded-xl border border-amber-600/50 bg-amber-900/20 p-4">
                            <p class="font-semibold text-amber-400">Already Checked In</p>
                            <p class="text-sm text-zinc-300" x-text="result?.name"></p>
                            <flux:button variant="ghost" x-on:click="dismiss()" class="mt-3 w-full">
                                Dismiss
                            </flux:button>
                        </div>
                    </template>
                    <template x-if="state === 'invalid'">
                        <div class="rounded-xl border border-red-600/50 bg-red-900/20 p-4">
                            <p class="font-semibold text-red-400">Invalid QR Code</p>
                            <p class="text-sm text-zinc-300" x-text="errorMessage"></p>
                            <flux:button variant="ghost" x-on:click="dismiss()" class="mt-3 w-full">
                                Dismiss
                            </flux:button>
                        </div>
                    </template>
                    <template x-if="state === 'confirmed'">
                        <div class="rounded-xl border border-emerald-600/50 bg-emerald-900/20 p-4">
                            <p class="font-semibold text-emerald-400">Arrival Confirmed</p>
                            <p class="text-sm text-zinc-300" x-text="result?.name"></p>
                        </div>
                    </template>
                </div>

                {{-- Status bar --}}
                <div class="mt-4 flex items-center justify-between text-sm text-zinc-400">
                    <span x-text="isOnline ? 'Online' : 'Offline'" :class="isOnline ? 'text-emerald-400' : 'text-amber-400'"></span>
                    <span x-show="outboxCount > 0" x-text="outboxCount + ' pending sync'"></span>
                </div>
            </div>
        @endif
    </div>
</div>
