<div
    x-data="scannerApp({
        scannerId: {{ $scannerId }},
        scannerType: '{{ $scannerType }}',
        modes: @js($modes),
        scannerToken: '{{ $scannerToken }}',
        dataUrl: '{{ $this->dataUrl }}',
        syncUrl: '{{ $this->syncUrl }}',
        gearPickupUrl: '{{ $this->gearPickupUrl }}',
        guestSyncUrl: '{{ $this->guestSyncUrl }}',
        guestGearPickupUrl: '{{ $this->guestGearPickupUrl }}'
    })"
    class="flex min-h-screen flex-col"
>
    {{-- Header --}}
    <header class="flex items-center justify-between bg-zinc-800 px-4 py-3">
        <h1 class="text-lg font-semibold text-white">{{ $scannerName }}</h1>
        <span
            class="rounded-full px-2.5 py-0.5 text-xs font-medium"
            :class="scannerType === 'entry_staff'
                ? 'bg-blue-500/20 text-blue-300'
                : 'bg-purple-500/20 text-purple-300'"
            x-text="scannerType === 'entry_staff' ? 'Entry Staff' : 'Volunteer Admin'"
        ></span>
    </header>

    @if ($hintText)
        <div class="bg-zinc-800/50 px-4 py-2 text-sm text-zinc-400">
            {{ $hintText }}
        </div>
    @endif

    {{-- Scanner content --}}
    <main class="flex flex-1 flex-col p-4">
        @if ($scannerType === 'entry_staff')
            {{-- Entry Staff: Tabbed layout — Scanner / Volunteers / Gastliste --}}

            {{-- Tab bar (WAI-ARIA Tabs pattern) --}}
            <div role="tablist" class="mb-4 flex border-b border-zinc-700">
                <button
                    type="button"
                    role="tab"
                    id="tab-scanner"
                    :aria-selected="activeTab === 'scanner'"
                    aria-controls="tabpanel-scanner"
                    class="flex-1 px-4 py-2 text-sm font-medium transition-colors"
                    :class="activeTab === 'scanner' ? 'text-white border-b-2 border-emerald-500' : 'text-zinc-400 hover:text-zinc-200'"
                    @click="activeTab = 'scanner'"
                >
                    Scanner
                </button>
                <button
                    type="button"
                    role="tab"
                    id="tab-volunteers"
                    :aria-selected="activeTab === 'volunteers'"
                    aria-controls="tabpanel-volunteers"
                    class="flex-1 px-4 py-2 text-sm font-medium transition-colors"
                    :class="activeTab === 'volunteers' ? 'text-white border-b-2 border-emerald-500' : 'text-zinc-400 hover:text-zinc-200'"
                    @click="activeTab = 'volunteers'"
                >
                    Volunteers
                </button>
                <button
                    type="button"
                    role="tab"
                    id="tab-guests"
                    :aria-selected="activeTab === 'guests'"
                    aria-controls="tabpanel-guests"
                    class="flex-1 px-4 py-2 text-sm font-medium transition-colors"
                    :class="activeTab === 'guests' ? 'text-white border-b-2 border-emerald-500' : 'text-zinc-400 hover:text-zinc-200'"
                    @click="activeTab = 'guests'"
                >
                    Gastliste
                </button>
            </div>

            {{-- Scanner tab --}}
            <div id="tabpanel-scanner" role="tabpanel" aria-labelledby="tab-scanner" x-show="activeTab === 'scanner'" class="flex flex-1 flex-col items-center justify-center space-y-4">
                <div id="scanner-viewfinder" class="aspect-square w-full max-w-sm overflow-hidden rounded-xl bg-black">
                    <video id="scanner-video" class="h-full w-full object-cover" aria-label="{{ __('QR code camera viewfinder') }}" playsinline></video>
                </div>

                {{-- Result panel (volunteer) --}}
                <div
                    x-show="(state !== 'idle' && state !== 'scanning') && !guestResult"
                    x-transition
                    role="alert"
                    aria-live="assertive"
                    class="w-full max-w-sm rounded-xl p-4"
                    :class="{
                        'bg-emerald-500/10 border border-emerald-500/30': state === 'confirmed',
                        'bg-amber-500/10 border border-amber-500/30': state === 'duplicate',
                        'bg-red-500/10 border border-red-500/30': state === 'invalid',
                        'bg-zinc-800 border border-zinc-700': state === 'loading'
                    }"
                    x-cloak
                >
                    <p class="text-center text-sm text-white" x-text="resultMessage"></p>
                </div>

                {{-- Result panel (guest) --}}
                <div
                    x-show="guestResult && (state === 'result' || state === 'duplicate' || state === 'confirmed')"
                    x-transition
                    role="alert"
                    aria-live="assertive"
                    class="w-full max-w-sm rounded-xl p-4"
                    :class="{
                        'bg-emerald-500/10 border border-emerald-500/30': state === 'confirmed',
                        'bg-amber-500/10 border border-amber-500/30': state === 'duplicate',
                        'bg-zinc-800 border border-zinc-700': state === 'result'
                    }"
                    x-cloak
                >
                    <p class="text-center text-sm text-white" x-text="resultMessage"></p>
                    <template x-if="guestResult && state === 'result'">
                        <div class="mt-3 flex justify-center">
                            <button
                                type="button"
                                class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-500"
                                @click="confirmGuestCheckin(guestResult.id)"
                                :aria-label="'Check in ' + guestResult.group_label + ' #' + guestResult.number"
                            >
                                Check In
                            </button>
                        </div>
                    </template>
                </div>
            </div>

            {{-- Volunteers tab (placeholder) --}}
            <div id="tabpanel-volunteers" role="tabpanel" aria-labelledby="tab-volunteers" x-show="activeTab === 'volunteers'" x-cloak class="flex flex-1 flex-col items-center justify-center">
                <p class="text-sm text-zinc-400">{{ __('Use QR scanner or manual lookup for volunteer check-in.') }}</p>
            </div>

            {{-- Gastliste tab --}}
            <div id="tabpanel-guests" role="tabpanel" aria-labelledby="tab-guests" x-show="activeTab === 'guests'" x-cloak class="flex flex-1 flex-col space-y-4">
                {{-- Search --}}
                <div class="px-1">
                    <input
                        type="text"
                        x-model.debounce.300ms="guestSearchQuery"
                        placeholder="{{ __('Search guests...') }}"
                        aria-label="{{ __('Search guests') }}"
                        class="w-full rounded-lg border border-zinc-700 bg-zinc-800 px-3 py-2 text-sm text-white placeholder-zinc-500 focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500"
                    />
                </div>

                {{-- Guest groups --}}
                <div class="space-y-3 overflow-y-auto px-1">
                    <template x-for="group in filteredGuestGroups" :key="group.label">
                        <div class="rounded-xl border border-zinc-700 bg-zinc-800 p-3">
                            <div class="mb-2 flex items-center justify-between">
                                <span class="text-sm font-semibold text-white" x-text="group.label"></span>
                                <span class="text-xs text-zinc-400" x-text="group.guestCount + ' Guests'"></span>
                            </div>
                            <div class="space-y-2">
                                <template x-for="entry in group.entries" :key="entry.id">
                                    <div class="flex items-center justify-between rounded-lg bg-zinc-900 px-3 py-2" aria-live="polite">
                                        <div>
                                            <span class="text-sm text-white" x-text="'#' + entry.number + (entry.name ? ' — ' + entry.name : '')"></span>
                                        </div>
                                        <div>
                                            <template x-if="entry.checked_in_at">
                                                <span class="rounded-full bg-emerald-500/20 px-2 py-0.5 text-xs text-emerald-300">{{ __('Checked in') }}</span>
                                            </template>
                                            <template x-if="!entry.checked_in_at">
                                                <button
                                                    type="button"
                                                    class="rounded-lg bg-emerald-600 px-3 py-1 text-xs font-medium text-white hover:bg-emerald-500"
                                                    @click="confirmGuestCheckin(entry.id)"
                                                    :aria-label="'Check in #' + entry.number + (entry.name ? ' ' + entry.name : '')"
                                                >
                                                    Check In
                                                </button>
                                            </template>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </template>

                    <template x-if="filteredGuestGroups.length === 0">
                        <p class="py-8 text-center text-sm text-zinc-500">{{ __('No guests found.') }}</p>
                    </template>
                </div>
            </div>
        @else
            {{-- Volunteer Admin: QR viewfinder + volunteer panel + gear pickup --}}
            <div class="flex flex-1 flex-col items-center justify-center space-y-4">
                <div id="scanner-viewfinder" class="aspect-square w-full max-w-sm overflow-hidden rounded-xl bg-black">
                    <video id="scanner-video" class="h-full w-full object-cover" aria-label="{{ __('QR code camera viewfinder') }}" playsinline></video>
                </div>

                {{-- Volunteer info panel --}}
                <div
                    x-show="selectedVolunteer"
                    x-transition
                    class="w-full max-w-sm rounded-xl border border-zinc-700 bg-zinc-800 p-4"
                    x-cloak
                >
                    <p class="text-lg font-semibold text-white" x-text="selectedVolunteer?.name"></p>
                    <p class="text-sm text-zinc-400" x-text="selectedVolunteer?.email"></p>
                </div>
            </div>
        @endif
    </main>
</div>
