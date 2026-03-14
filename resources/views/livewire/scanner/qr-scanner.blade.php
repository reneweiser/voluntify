<div class="flex min-h-screen flex-col">
    {{-- Top bar --}}
    <header class="flex items-center justify-between border-b border-zinc-700 bg-zinc-800 px-4 py-3">
        <div class="flex items-center gap-3">
            <flux:button variant="ghost" href="{{ route('scanner.index') }}" wire:navigate size="sm" icon="arrow-left" class="text-zinc-300 hover:text-white" />
            <flux:heading size="lg" class="text-white">Scanner</flux:heading>
        </div>
        <flux:button variant="ghost" href="{{ route('scanner.lookup', $this->eventId) }}" wire:navigate size="sm" class="text-zinc-300 hover:text-white">
            Manual Lookup
        </flux:button>
    </header>

    {{-- Event name bar --}}
    <div class="border-b border-zinc-700 bg-zinc-800/50 px-4 py-2">
        <flux:text size="sm" class="text-zinc-300">{{ $this->event->name }}</flux:text>
    </div>

    {{-- Scanner viewfinder area --}}
    <div class="flex flex-1 items-center justify-center p-4" data-scanner-viewfinder>
        <div
            x-data="scannerApp({ eventId: {{ $this->eventId }} })"
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
                        <div class="mt-3 flex gap-2" x-show="canConfirmArrival">
                            <flux:button variant="primary" x-on:click="confirmArrival()" class="flex-1">
                                Confirm Arrival
                            </flux:button>
                            <flux:button variant="ghost" x-on:click="dismiss()" class="flex-1">
                                Dismiss
                            </flux:button>
                        </div>
                        <div class="mt-3" x-show="!canConfirmArrival">
                            <flux:button variant="ghost" x-on:click="dismiss()" class="w-full">
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

                {{-- Shift context + attendance --}}
                <template x-if="result?.shifts?.length > 0 && state !== 'invalid'">
                    <div class="mt-3 rounded-xl border border-zinc-700 bg-zinc-800/50 p-3">
                        <p class="mb-2 text-xs font-medium uppercase tracking-wider text-zinc-400">Shifts</p>
                        <div class="space-y-1.5">
                            <template x-for="shift in result.shifts" :key="shift.signupId">
                                <div class="flex items-center justify-between gap-2 text-sm">
                                    <div class="flex items-center gap-2">
                                        <span
                                            class="inline-block size-2 shrink-0 rounded-full"
                                            :class="{
                                                'bg-emerald-400': shift.status === 'attended',
                                                'bg-red-400': shift.status === 'missed',
                                                'bg-blue-400': shift.status === 'active',
                                                'bg-zinc-400': shift.status === 'upcoming',
                                            }"
                                        ></span>
                                        <span class="text-zinc-200" x-text="shift.jobName"></span>
                                        <span class="text-zinc-500" x-text="new Date(shift.startsAt).toLocaleTimeString([], {hour: '2-digit', minute: '2-digit'}) + '–' + new Date(shift.endsAt).toLocaleTimeString([], {hour: '2-digit', minute: '2-digit'})"></span>
                                    </div>
                                    <template x-if="canMarkAttendance && (shift.status === 'active' || shift.status === 'upcoming') && !isAttendanceRecorded(shift.signupId)">
                                        <button
                                            x-on:click="confirmAttendance(shift.signupId)"
                                            class="rounded bg-blue-600 px-2 py-0.5 text-xs font-medium text-white hover:bg-blue-500"
                                        >
                                            Mark
                                        </button>
                                    </template>
                                    <template x-if="isAttendanceRecorded(shift.signupId)">
                                        <span class="text-xs text-emerald-400">Recorded</span>
                                    </template>
                                </div>
                            </template>
                        </div>
                    </div>
                </template>
            </div>

            {{-- Status bar --}}
            <div class="mt-4 flex items-center justify-between text-sm text-zinc-400">
                <span x-text="isOnline ? 'Online' : 'Offline'" :class="isOnline ? 'text-emerald-400' : 'text-amber-400'"></span>
                <span x-show="outboxCount > 0" x-text="outboxCount + ' pending sync'"></span>
            </div>
        </div>
    </div>
</div>
