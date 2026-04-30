<div
    x-data="scannerApp({
        scannerId: {{ $scannerId }},
        scannerType: '{{ $scannerType }}',
        modes: @js($modes),
        entryEventId: @js($eventId),
        contractVersion: {{ $contractVersion }},
        requiresConfigurationReview: @js($requiresConfigurationReview),
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
                : (scannerType === 'gear' ? 'bg-amber-500/20 text-amber-300' : 'bg-purple-500/20 text-purple-300')"
            x-text="scannerType === 'entry_staff' ? 'Entry Staff' : (scannerType === 'gear' ? 'Gear' : 'Volunteer Admin')"
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
                    class="min-h-12 flex-1 px-4 py-3 text-sm font-medium transition-colors"
                    :class="activeTab === 'scanner' ? 'text-white border-b-2 border-emerald-500' : 'text-zinc-400 hover:text-zinc-200'"
                    @click="setActiveTab('scanner')"
                >
                    Scanner
                </button>
                <button
                    type="button"
                    role="tab"
                    id="tab-volunteers"
                    :aria-selected="activeTab === 'volunteers'"
                    aria-controls="tabpanel-volunteers"
                    class="min-h-12 flex-1 px-4 py-3 text-sm font-medium transition-colors"
                    :class="activeTab === 'volunteers' ? 'text-white border-b-2 border-emerald-500' : 'text-zinc-400 hover:text-zinc-200'"
                    @click="setActiveTab('volunteers')"
                >
                    Volunteers
                </button>
                <button
                    type="button"
                    role="tab"
                    id="tab-guests"
                    :aria-selected="activeTab === 'guests'"
                    aria-controls="tabpanel-guests"
                    class="min-h-12 flex-1 px-4 py-3 text-sm font-medium transition-colors"
                    :class="activeTab === 'guests' ? 'text-white border-b-2 border-emerald-500' : 'text-zinc-400 hover:text-zinc-200'"
                    @click="setActiveTab('guests')"
                >
                    Gastliste
                </button>
            </div>

            {{-- Scanner tab --}}
            <div id="tabpanel-scanner" role="tabpanel" aria-labelledby="tab-scanner" x-show="activeTab === 'scanner'" class="flex flex-1 flex-col items-center justify-center space-y-4">
                <div id="scanner-viewfinder" class="relative aspect-square w-full max-w-sm overflow-hidden rounded-xl bg-black" @click="cameraPaused && resumeCamera()">
                    <video id="scanner-video" class="h-full w-full object-cover" aria-label="{{ __('QR code camera viewfinder') }}" playsinline></video>
                    <div
                        x-show="cameraPaused"
                        x-transition
                        class="absolute inset-0 flex cursor-pointer items-center justify-center bg-black/80"
                        x-cloak
                    >
                        <div class="text-center">
                            <svg class="mx-auto mb-2 h-12 w-12 text-zinc-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15.75 10.5l4.72-4.72a.75.75 0 011.28.53v11.38a.75.75 0 01-1.28.53l-4.72-4.72M4.5 18.75h9a2.25 2.25 0 002.25-2.25v-9a2.25 2.25 0 00-2.25-2.25h-9A2.25 2.25 0 002.25 7.5v9a2.25 2.25 0 002.25 2.25z" /></svg>
                            <p class="text-sm font-medium text-zinc-300">{{ __('Camera paused') }}</p>
                            <p class="mt-1 text-xs text-zinc-500">{{ __('Tap to resume') }}</p>
                        </div>
                    </div>
                </div>

                {{-- Result panel (volunteer) --}}
                <div
                    x-show="['result', 'duplicate', 'invalid', 'confirmed', 'loading'].includes(state) && !guestResult"
                    x-transition
                    role="alert"
                    aria-live="assertive"
                    class="w-full max-w-sm rounded-xl p-4"
                    :class="{
                        'bg-emerald-500/10 border border-emerald-500/30': state === 'result' || state === 'confirmed',
                        'bg-amber-500/10 border border-amber-500/30': state === 'duplicate',
                        'bg-red-500/10 border border-red-500/30': state === 'invalid',
                        'bg-zinc-800 border border-zinc-700': state === 'loading'
                    }"
                    x-cloak
                >
                    <p class="text-center text-lg font-semibold text-white" x-show="selectedVolunteer" x-text="selectedVolunteer?.name"></p>
                    <p class="mt-1 text-center text-xs text-zinc-400" x-show="selectedVolunteer?.phone" x-text="selectedVolunteer?.phone"></p>
                    <p class="mt-1 text-center text-xs text-zinc-400" x-show="selectedVolunteer && !selectedVolunteer?.phone">{{ __('No phone number') }}</p>
                    <p class="mt-1 text-center text-sm text-white" x-text="state === 'invalid' ? errorMessage : resultMessage"></p>

                    {{-- Confirm Arrival button (result state only) --}}
                    <template x-if="state === 'result' && !guestResult && canSubmitArrival">
                        <div class="mt-3 flex justify-center">
                            <button
                                type="button"
                                class="min-h-12 w-full rounded-lg bg-emerald-600 px-4 py-3 text-base font-medium text-white hover:bg-emerald-500 active:bg-emerald-700"
                                @click="confirmArrival()"
                            >
                                {{ __('Confirm Arrival') }}
                            </button>
                        </div>
                    </template>

                    <template x-if="state === 'result' && !guestResult && !canSubmitArrival">
                        <p class="mt-3 text-center text-sm text-amber-300">
                            {{ __('Scanner configuration must be reviewed before volunteer check-in can be used.') }}
                        </p>
                    </template>

                    {{-- Next Scan button (terminal states) --}}
                    <template x-if="state === 'duplicate' || state === 'invalid' || state === 'confirmed'">
                        <div class="mt-3 flex justify-center">
                            <button
                                type="button"
                                class="min-h-12 w-full rounded-lg border border-zinc-600 px-4 py-3 text-base font-medium text-zinc-300 hover:bg-zinc-700 active:bg-zinc-600"
                                @click="dismiss()"
                            >
                                {{ __('Next Scan') }}
                            </button>
                        </div>
                    </template>
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
                                class="min-h-12 w-full rounded-lg bg-emerald-600 px-4 py-3 text-base font-medium text-white hover:bg-emerald-500 active:bg-emerald-700"
                                @click="confirmGuestCheckin(guestResult.id)"
                                :aria-label="'Check in ' + guestResult.group_label + ' #' + guestResult.number"
                            >
                                Check In
                            </button>
                        </div>
                    </template>
                    <template x-if="guestResult && (state === 'confirmed' || state === 'duplicate')">
                        <div class="mt-3 flex justify-center">
                            <button
                                type="button"
                                class="min-h-12 w-full rounded-lg border border-zinc-600 px-4 py-3 text-base font-medium text-zinc-300 hover:bg-zinc-700 active:bg-zinc-600"
                                @click="dismiss()"
                            >
                                {{ __('Next Scan') }}
                            </button>
                        </div>
                    </template>
                </div>
            </div>

            {{-- Volunteers tab --}}
            <div id="tabpanel-volunteers" role="tabpanel" aria-labelledby="tab-volunteers" x-show="activeTab === 'volunteers'" x-cloak class="flex flex-1 flex-col space-y-4">
                <div class="space-y-3 px-1">
                    <input
                        type="text"
                        x-model.debounce.300ms="volunteerSearchQuery"
                        placeholder="{{ __('Search volunteers...') }}"
                        aria-label="{{ __('Search volunteers') }}"
                        class="min-h-12 w-full rounded-lg border border-zinc-700 bg-zinc-800 px-4 py-3 text-base text-white placeholder-zinc-500 focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500"
                    />

                    <template x-if="scannerDataSource === 'cache'">
                        <div class="rounded-lg border border-amber-500/30 bg-amber-500/10 px-4 py-3 text-sm text-amber-200">
                            Offline - showing cached volunteer data.
                        </div>
                    </template>
                </div>

                <template x-if="selectedVolunteer && selectedVolunteerSource === 'manual'">
                    <div class="px-1" x-cloak>
                        <div
                            class="rounded-xl p-4"
                            :class="{
                                'bg-emerald-500/10 border border-emerald-500/30': volunteerDetailState === 'ready',
                                'bg-amber-500/10 border border-amber-500/30': volunteerDetailState === 'checked_in' || volunteerDetailState === 'configuration_review',
                                'bg-zinc-800 border border-zinc-700': volunteerDetailState === 'missing_ticket'
                            }"
                        >
                            <p class="text-center text-lg font-semibold text-white" x-text="selectedVolunteer?.name"></p>
                            <p class="mt-1 text-center text-sm text-zinc-400" x-text="selectedVolunteer?.email"></p>
                            <p class="mt-1 text-center text-xs text-zinc-400" x-show="selectedVolunteer?.phone" x-text="selectedVolunteer?.phone"></p>
                            <p class="mt-1 text-center text-xs text-zinc-400" x-show="selectedVolunteer && !selectedVolunteer?.phone">{{ __('No phone number') }}</p>

                            <p class="mt-3 text-center text-sm text-white" x-show="volunteerDetailState === 'ready'">Ready to check in.</p>
                            <p class="mt-3 text-center text-sm text-white" x-show="volunteerDetailState === 'checked_in'">Already checked in.</p>
                            <p class="mt-3 text-center text-sm text-amber-300" x-show="volunteerDetailState === 'configuration_review'">
                                {{ __('Scanner configuration must be reviewed before volunteer check-in can be used.') }}
                            </p>
                            <p class="mt-3 text-center text-sm text-zinc-300" x-show="volunteerDetailState === 'missing_ticket'">
                                {{ __('This volunteer cannot be checked in because no project ticket is available.') }}
                            </p>

                            <template x-if="volunteerDetailState === 'ready'">
                                <div class="mt-3 flex justify-center">
                                    <button
                                        type="button"
                                        class="min-h-12 w-full rounded-lg bg-emerald-600 px-4 py-3 text-base font-medium text-white hover:bg-emerald-500 active:bg-emerald-700"
                                        @click="confirmArrival()"
                                    >
                                        {{ __('Confirm Arrival') }}
                                    </button>
                                </div>
                            </template>

                            <div class="mt-3 flex justify-center">
                                <button
                                    type="button"
                                    class="min-h-12 w-full rounded-lg border border-zinc-600 px-4 py-3 text-base font-medium text-zinc-300 hover:bg-zinc-700 active:bg-zinc-600"
                                    @click="clearVolunteerSelection()"
                                >
                                    {{ __('Close Details') }}
                                </button>
                            </div>
                        </div>
                    </div>
                </template>

                <div class="space-y-3 overflow-y-auto px-1">
                    <template x-if="scannerDataSource === 'unavailable'">
                        <p class="py-8 text-center text-sm text-zinc-500">Volunteer data is unavailable right now.</p>
                    </template>

                    <template x-if="scannerDataSource !== 'unavailable' && shouldShowVolunteerSearchHint">
                        <p class="py-8 text-center text-sm text-zinc-500">Mindestens 2 Zeichen eingeben.</p>
                    </template>

                    <template x-if="scannerDataSource !== 'unavailable' && !shouldShowVolunteerSearchHint && volunteerSearchQuery.trim().length >= 2 && filteredVolunteers.length === 0">
                        <p class="py-8 text-center text-sm text-zinc-500">No matching volunteers found.</p>
                    </template>

                    <template x-for="volunteer in filteredVolunteers" :key="volunteer.id">
                        <button
                            type="button"
                            class="flex min-h-12 w-full items-center justify-between rounded-xl border border-zinc-700 bg-zinc-800 px-4 py-3 text-left transition hover:bg-zinc-900"
                            :class="selectedVolunteerSource === 'manual' && selectedVolunteer?.id === volunteer.id ? 'ring-1 ring-emerald-500/60' : ''"
                            @click="selectVolunteerFromSearch(volunteer)"
                        >
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-medium text-white" x-text="volunteer.name"></p>
                                <p class="text-xs text-zinc-400" x-text="volunteer.email"></p>
                                <p class="text-xs text-zinc-500" x-show="volunteer.phone" x-text="volunteer.phone"></p>
                            </div>
                            <div class="ml-3 shrink-0">
                                <template x-if="hasArrivalForEntryEvent(volunteer.id)">
                                    <span class="rounded-full bg-amber-500/20 px-3 py-1 text-xs text-amber-300">Bereits eingecheckt</span>
                                </template>
                            </div>
                        </button>
                    </template>
                </div>
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
                        class="min-h-12 w-full rounded-lg border border-zinc-700 bg-zinc-800 px-4 py-3 text-base text-white placeholder-zinc-500 focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500"
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
                                    <div class="flex min-h-12 items-center justify-between rounded-lg bg-zinc-900 px-4 py-3" aria-live="polite">
                                        <div class="min-w-0 flex-1">
                                            <span class="text-sm text-white" x-text="'#' + entry.number + (entry.name ? ' — ' + entry.name : '')"></span>
                                        </div>
                                        <div class="ml-3 shrink-0">
                                            <template x-if="entry.checked_in_at">
                                                <span class="rounded-full bg-emerald-500/20 px-3 py-1 text-sm text-emerald-300">{{ __('Checked in') }}</span>
                                            </template>
                                            <template x-if="!entry.checked_in_at">
                                                <button
                                                    type="button"
                                                    class="min-h-10 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-500 active:bg-emerald-700"
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
        @elseif ($scannerType === 'gear')
            <div role="tablist" class="mb-4 flex border-b border-zinc-700">
                <button
                    type="button"
                    role="tab"
                    id="tab-gear-scanner"
                    :aria-selected="activeTab === 'scanner'"
                    aria-controls="tabpanel-gear-scanner"
                    class="min-h-12 flex-1 px-4 py-3 text-sm font-medium transition-colors"
                    :class="activeTab === 'scanner' ? 'text-white border-b-2 border-amber-500' : 'text-zinc-400 hover:text-zinc-200'"
                    @click="setActiveTab('scanner')"
                >
                    Scanner
                </button>
                <button
                    type="button"
                    role="tab"
                    id="tab-gear-volunteers"
                    :aria-selected="activeTab === 'volunteers'"
                    aria-controls="tabpanel-gear-volunteers"
                    class="min-h-12 flex-1 px-4 py-3 text-sm font-medium transition-colors"
                    :class="activeTab === 'volunteers' ? 'text-white border-b-2 border-amber-500' : 'text-zinc-400 hover:text-zinc-200'"
                    @click="setActiveTab('volunteers')"
                >
                    Volunteers
                </button>
                <button
                    type="button"
                    role="tab"
                    id="tab-gear-guests"
                    :aria-selected="activeTab === 'guests'"
                    aria-controls="tabpanel-gear-guests"
                    class="min-h-12 flex-1 px-4 py-3 text-sm font-medium transition-colors"
                    :class="activeTab === 'guests' ? 'text-white border-b-2 border-amber-500' : 'text-zinc-400 hover:text-zinc-200'"
                    @click="setActiveTab('guests')"
                >
                    Guests
                </button>
            </div>

            <div id="tabpanel-gear-scanner" role="tabpanel" aria-labelledby="tab-gear-scanner" x-show="activeTab === 'scanner'" class="flex flex-1 flex-col items-center space-y-4">
                <div id="scanner-viewfinder" class="relative aspect-square w-full max-w-sm overflow-hidden rounded-xl bg-black" @click="cameraPaused && resumeCamera()">
                    <video id="scanner-video" class="h-full w-full object-cover" aria-label="{{ __('QR code camera viewfinder') }}" playsinline></video>
                    <div
                        x-show="cameraPaused"
                        x-transition
                        class="absolute inset-0 flex cursor-pointer items-center justify-center bg-black/80"
                        x-cloak
                    >
                        <div class="text-center">
                            <svg class="mx-auto mb-2 h-12 w-12 text-zinc-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15.75 10.5l4.72-4.72a.75.75 0 011.28.53v11.38a.75.75 0 01-1.28.53l-4.72-4.72M4.5 18.75h9a2.25 2.25 0 002.25-2.25v-9a2.25 2.25 0 00-2.25-2.25h-9A2.25 2.25 0 002.25 7.5v9A2.25 2.25 0 004.5 18.75z" /></svg>
                            <p class="text-sm font-medium text-zinc-300">{{ __('Camera paused') }}</p>
                            <p class="mt-1 text-xs text-zinc-500">{{ __('Tap to resume') }}</p>
                        </div>
                    </div>
                </div>

                <div
                    x-show="state === 'invalid'"
                    x-transition
                    role="alert"
                    aria-live="assertive"
                    class="w-full max-w-sm rounded-xl border border-red-500/30 bg-red-500/10 p-4"
                    x-cloak
                >
                    <p class="text-center text-sm text-white" x-text="errorMessage"></p>
                </div>

                <div x-show="selectedVolunteer" x-cloak class="w-full max-w-sm space-y-4">
                    <div class="rounded-xl border border-zinc-700 bg-zinc-800 p-4">
                        <p class="text-lg font-semibold text-white" x-text="selectedVolunteer?.name"></p>
                        <p class="text-sm text-zinc-400" x-text="selectedVolunteer?.email"></p>
                        <p class="text-sm text-zinc-400" x-show="selectedVolunteer?.phone" x-text="selectedVolunteer?.phone"></p>
                        <p class="text-sm text-zinc-400" x-show="selectedVolunteer && !selectedVolunteer?.phone">{{ __('No phone number') }}</p>
                    </div>

                    <template x-if="selectedVolunteer && getVolunteerGear(selectedVolunteer.id).length > 0">
                        <div class="rounded-xl border border-zinc-700 bg-zinc-800 p-4">
                            <h3 class="mb-3 text-sm font-semibold text-zinc-300">{{ __('Gear') }}</h3>
                            <div class="space-y-2">
                                <template x-for="gear in getVolunteerGear(selectedVolunteer.id)" :key="gear.id">
                                    <div class="flex min-h-12 items-center justify-between rounded-lg bg-zinc-900 px-4 py-3">
                                        <div class="min-w-0 flex-1">
                                            <p class="text-sm font-medium text-white" x-text="getGearItemName(gear.project_gear_item_id)"></p>
                                            <p x-show="gear.size" class="text-xs text-zinc-400" x-text="'{{ __('Size') }}: ' + gear.size"></p>
                                            <p x-show="gear.quantity_entitled !== null" class="text-xs text-zinc-400" x-text="getGearPickedUpCount(gear) + ' / ' + gear.quantity_entitled + ' {{ __('picked up') }}'"></p>
                                        </div>
                                        <div class="ml-3 shrink-0">
                                            <template x-if="isGearFullyPickedUp(gear)">
                                                <span class="rounded-full bg-emerald-500/20 px-3 py-1 text-xs text-emerald-300">{{ __('Picked up') }}</span>
                                            </template>
                                            <template x-if="!isGearFullyPickedUp(gear) && isOnline">
                                                <button
                                                    type="button"
                                                    class="min-h-10 rounded-lg bg-amber-600 px-3 py-2 text-sm font-medium text-white hover:bg-amber-500 active:bg-amber-700 disabled:cursor-not-allowed disabled:opacity-50"
                                                    @click="selectGearState(gear.id, 'picked_up')"
                                                    :disabled="isGearCooldown(gear.id)"
                                                >
                                                    <span x-show="!isGearCooldown(gear.id)">{{ __('Pick Up') }}</span>
                                                    <span x-show="isGearCooldown(gear.id)" x-cloak>&#10003;</span>
                                                </button>
                                            </template>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </template>

                    <button
                        type="button"
                        class="min-h-12 w-full rounded-lg border border-zinc-600 px-4 py-3 text-base font-medium text-zinc-300 hover:bg-zinc-700 active:bg-zinc-600"
                        @click="dismiss()"
                    >
                        {{ __('Done') }}
                    </button>
                </div>

                <div x-show="selectedGuest" x-cloak class="w-full max-w-sm space-y-4">
                    <div class="rounded-xl border border-zinc-700 bg-zinc-800 p-4">
                        <p class="text-lg font-semibold text-white" x-text="selectedGuest?.group_label"></p>
                        <p class="text-sm text-zinc-400" x-text="selectedGuest?.name ? selectedGuest.group_label + ' - ' + selectedGuest.name : selectedGuest?.group_label"></p>
                        <p class="text-xs text-zinc-500" x-text="selectedGuest ? '#' + selectedGuest.number + ' / ' + selectedGuest.group_guest_count : ''"></p>
                    </div>

                    <template x-if="selectedGuest?.gear?.length > 0">
                        <div class="rounded-xl border border-zinc-700 bg-zinc-800 p-4">
                            <h3 class="mb-3 text-sm font-semibold text-zinc-300">{{ __('Gear') }}</h3>
                            <div class="space-y-3">
                                <template x-for="gear in selectedGuest.gear" :key="gear.id">
                                    <div class="rounded-lg bg-zinc-900 px-4 py-3">
                                        <div class="flex items-start justify-between gap-3">
                                            <div class="min-w-0 flex-1">
                                                <p class="text-sm font-medium text-white" x-text="gear.gear_item_name"></p>
                                                <p class="text-xs text-zinc-400" x-text="gear.picked_up_count + ' / ' + gear.quantity + ' {{ __('picked up') }}'"></p>
                                            </div>
                                            <template x-if="isGuestGearFullyPickedUp(gear.id)">
                                                <span class="rounded-full bg-emerald-500/20 px-3 py-1 text-xs text-emerald-300">{{ __('Picked up') }}</span>
                                            </template>
                                        </div>

                                        <div class="mt-3 space-y-2">
                                            <template x-if="gear.available_sizes?.length">
                                                <select
                                                    class="min-h-10 w-full rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm text-white"
                                                    :value="gear.selection ?? ''"
                                                    @change="selectGuestGearSelection(gear.id, $event.target.value)"
                                                >
                                                    <option value="">{{ __('Select size') }}</option>
                                                    <template x-for="size in gear.available_sizes" :key="size">
                                                        <option :value="size" x-text="size"></option>
                                                    </template>
                                                </select>
                                            </template>

                                            <template x-if="gear.available_states?.length">
                                                <select
                                                    class="min-h-10 w-full rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm text-white"
                                                    :value="gear.status ?? ''"
                                                    @change="selectGuestGearState(gear.id, $event.target.value)"
                                                >
                                                    <option value="">{{ __('Select state') }}</option>
                                                    <template x-for="stateOption in gear.available_states" :key="stateOption">
                                                        <option :value="stateOption" x-text="stateOption"></option>
                                                    </template>
                                                </select>
                                            </template>

                                            <button
                                                type="button"
                                                class="min-h-10 w-full rounded-lg bg-amber-600 px-3 py-2 text-sm font-medium text-white hover:bg-amber-500 active:bg-amber-700 disabled:cursor-not-allowed disabled:opacity-50"
                                                @click="incrementGuestGearPickup(gear.id)"
                                                :disabled="!isOnline || isGuestGearFullyPickedUp(gear.id)"
                                            >
                                                {{ __('Record Pickup') }}
                                            </button>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </template>

                    <button
                        type="button"
                        class="min-h-12 w-full rounded-lg border border-zinc-600 px-4 py-3 text-base font-medium text-zinc-300 hover:bg-zinc-700 active:bg-zinc-600"
                        @click="dismiss()"
                    >
                        {{ __('Done') }}
                    </button>
                </div>
            </div>

            <div id="tabpanel-gear-volunteers" role="tabpanel" aria-labelledby="tab-gear-volunteers" x-show="activeTab === 'volunteers'" x-cloak class="flex flex-1 flex-col space-y-4">
                <div class="space-y-3 px-1">
                    <input
                        type="text"
                        x-model.debounce.300ms="volunteerSearchQuery"
                        placeholder="{{ __('Search volunteers...') }}"
                        aria-label="{{ __('Search volunteers') }}"
                        class="min-h-12 w-full rounded-lg border border-zinc-700 bg-zinc-800 px-4 py-3 text-base text-white placeholder-zinc-500 focus:border-amber-500 focus:outline-none focus:ring-1 focus:ring-amber-500"
                    />

                    <template x-if="scannerDataSource === 'cache'">
                        <div class="rounded-lg border border-amber-500/30 bg-amber-500/10 px-4 py-3 text-sm text-amber-200">
                            Offline - showing cached volunteer data.
                        </div>
                    </template>
                </div>

                <template x-if="selectedVolunteer && selectedVolunteerSource === 'manual'">
                    <div class="px-1" x-cloak>
                        <div class="rounded-xl border border-zinc-700 bg-zinc-800 p-4">
                            <p class="text-center text-lg font-semibold text-white" x-text="selectedVolunteer?.name"></p>
                            <p class="mt-1 text-center text-sm text-zinc-400" x-text="selectedVolunteer?.email"></p>
                            <div class="mt-4 space-y-2" x-show="selectedVolunteer">
                                <template x-for="gear in getVolunteerGear(selectedVolunteer.id)" :key="gear.id">
                                    <div class="flex min-h-12 items-center justify-between rounded-lg bg-zinc-900 px-4 py-3">
                                        <div class="min-w-0 flex-1">
                                            <p class="text-sm font-medium text-white" x-text="getGearItemName(gear.project_gear_item_id)"></p>
                                            <p x-show="gear.size" class="text-xs text-zinc-400" x-text="'{{ __('Size') }}: ' + gear.size"></p>
                                        </div>
                                        <button
                                            type="button"
                                            class="min-h-10 rounded-lg bg-amber-600 px-3 py-2 text-sm font-medium text-white hover:bg-amber-500 active:bg-amber-700 disabled:cursor-not-allowed disabled:opacity-50"
                                            @click="selectGearState(gear.id, 'picked_up')"
                                            :disabled="!isOnline || isGearFullyPickedUp(gear) || isGearCooldown(gear.id)"
                                        >
                                            {{ __('Pick Up') }}
                                        </button>
                                    </div>
                                </template>
                            </div>
                            <div class="mt-3 flex justify-center">
                                <button
                                    type="button"
                                    class="min-h-12 w-full rounded-lg border border-zinc-600 px-4 py-3 text-base font-medium text-zinc-300 hover:bg-zinc-700 active:bg-zinc-600"
                                    @click="clearVolunteerSelection()"
                                >
                                    {{ __('Close Details') }}
                                </button>
                            </div>
                        </div>
                    </div>
                </template>

                <div class="space-y-3 overflow-y-auto px-1">
                    <template x-if="scannerDataSource === 'unavailable'">
                        <p class="py-8 text-center text-sm text-zinc-500">Volunteer data is unavailable right now.</p>
                    </template>

                    <template x-if="scannerDataSource !== 'unavailable' && shouldShowVolunteerSearchHint">
                        <p class="py-8 text-center text-sm text-zinc-500">Mindestens 2 Zeichen eingeben.</p>
                    </template>

                    <template x-if="scannerDataSource !== 'unavailable' && !shouldShowVolunteerSearchHint && volunteerSearchQuery.trim().length >= 2 && filteredVolunteers.length === 0">
                        <p class="py-8 text-center text-sm text-zinc-500">No matching volunteers found.</p>
                    </template>

                    <template x-for="volunteer in filteredVolunteers" :key="volunteer.id">
                        <button
                            type="button"
                            class="flex min-h-12 w-full items-center justify-between rounded-xl border border-zinc-700 bg-zinc-800 px-4 py-3 text-left transition hover:bg-zinc-900"
                            :class="selectedVolunteerSource === 'manual' && selectedVolunteer?.id === volunteer.id ? 'ring-1 ring-amber-500/60' : ''"
                            @click="selectVolunteerFromSearch(volunteer)"
                        >
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-medium text-white" x-text="volunteer.name"></p>
                                <p class="text-xs text-zinc-400" x-text="volunteer.email"></p>
                            </div>
                        </button>
                    </template>
                </div>
            </div>

            <div id="tabpanel-gear-guests" role="tabpanel" aria-labelledby="tab-gear-guests" x-show="activeTab === 'guests'" x-cloak class="flex flex-1 flex-col space-y-4">
                <div class="space-y-3 px-1">
                    <input
                        type="text"
                        x-model.debounce.300ms="guestSearchQuery"
                        placeholder="{{ __('Search guests...') }}"
                        aria-label="{{ __('Search guests') }}"
                        class="min-h-12 w-full rounded-lg border border-zinc-700 bg-zinc-800 px-4 py-3 text-base text-white placeholder-zinc-500 focus:border-amber-500 focus:outline-none focus:ring-1 focus:ring-amber-500"
                    />

                    <template x-if="scannerDataSource === 'cache'">
                        <div class="rounded-lg border border-amber-500/30 bg-amber-500/10 px-4 py-3 text-sm text-amber-200">
                            Offline - showing cached guest data.
                        </div>
                    </template>
                </div>

                <template x-if="selectedGuest && selectedGuestSource === 'manual'">
                    <div class="px-1" x-cloak>
                        <div class="rounded-xl border border-zinc-700 bg-zinc-800 p-4">
                            <p class="text-center text-lg font-semibold text-white" x-text="selectedGuest?.group_label"></p>
                            <p class="mt-1 text-center text-sm text-zinc-400" x-text="selectedGuest?.name"></p>
                            <div class="mt-4 space-y-2" x-show="selectedGuest">
                                <template x-for="gear in selectedGuest.gear" :key="gear.id">
                                    <div class="rounded-lg bg-zinc-900 px-4 py-3">
                                        <p class="text-sm font-medium text-white" x-text="gear.gear_item_name"></p>
                                        <p class="text-xs text-zinc-400" x-text="gear.picked_up_count + ' / ' + gear.quantity + ' {{ __('picked up') }}'"></p>
                                        <button
                                            type="button"
                                            class="mt-3 min-h-10 w-full rounded-lg bg-amber-600 px-3 py-2 text-sm font-medium text-white hover:bg-amber-500 active:bg-amber-700 disabled:cursor-not-allowed disabled:opacity-50"
                                            @click="incrementGuestGearPickup(gear.id)"
                                            :disabled="!isOnline || isGuestGearFullyPickedUp(gear.id)"
                                        >
                                            {{ __('Record Pickup') }}
                                        </button>
                                    </div>
                                </template>
                            </div>
                            <div class="mt-3 flex justify-center">
                                <button
                                    type="button"
                                    class="min-h-12 w-full rounded-lg border border-zinc-600 px-4 py-3 text-base font-medium text-zinc-300 hover:bg-zinc-700 active:bg-zinc-600"
                                    @click="clearGuestSelection()"
                                >
                                    {{ __('Close Details') }}
                                </button>
                            </div>
                        </div>
                    </div>
                </template>

                <div class="space-y-3 overflow-y-auto px-1">
                    <template x-if="scannerDataSource === 'unavailable'">
                        <p class="py-8 text-center text-sm text-zinc-500">Guest data is unavailable right now.</p>
                    </template>

                    <template x-if="scannerDataSource !== 'unavailable' && shouldShowGuestSearchHint">
                        <p class="py-8 text-center text-sm text-zinc-500">Mindestens 2 Zeichen eingeben.</p>
                    </template>

                    <template x-for="group in filteredGuestGroups" :key="group.label">
                        <div class="rounded-xl border border-zinc-700 bg-zinc-800 p-3">
                            <div class="mb-2 flex items-center justify-between">
                                <span class="text-sm font-semibold text-white" x-text="group.label"></span>
                                <span class="text-xs text-zinc-400" x-text="group.guestCount + ' Guests'"></span>
                            </div>
                            <div class="space-y-2">
                                <template x-for="entry in group.entries" :key="entry.id">
                                    <button
                                        type="button"
                                        class="flex min-h-12 w-full items-center justify-between rounded-lg bg-zinc-900 px-4 py-3 text-left"
                                        @click="selectGuestFromSearch(entry)"
                                    >
                                        <div class="min-w-0 flex-1">
                                            <span class="text-sm text-white" x-text="'#' + entry.number + (entry.name ? ' - ' + entry.name : '')"></span>
                                        </div>
                                        <span class="ml-3 text-xs text-zinc-400" x-text="entry.gear.length ? entry.gear.length + ' gear' : '{{ __('No gear') }}'"></span>
                                    </button>
                                </template>
                            </div>
                        </div>
                    </template>

                    <template x-if="scannerDataSource !== 'unavailable' && !shouldShowGuestSearchHint && filteredGuestGroups.length === 0">
                        <p class="py-8 text-center text-sm text-zinc-500">{{ __('No guests found.') }}</p>
                    </template>
                </div>
            </div>
        @else
            @if (in_array('checkin', $modes))
                <div role="tablist" class="mb-4 flex border-b border-zinc-700">
                    <button
                        type="button"
                        role="tab"
                        id="tab-va-scanner"
                        :aria-selected="activeTab === 'scanner'"
                        aria-controls="tabpanel-va-scanner"
                        class="min-h-12 flex-1 px-4 py-3 text-sm font-medium transition-colors"
                        :class="activeTab === 'scanner' ? 'text-white border-b-2 border-emerald-500' : 'text-zinc-400 hover:text-zinc-200'"
                        @click="setActiveTab('scanner')"
                    >
                        Scanner
                    </button>
                    <button
                        type="button"
                        role="tab"
                        id="tab-shifts"
                        :aria-selected="activeTab === 'shifts'"
                        aria-controls="tabpanel-shifts"
                        class="min-h-12 flex-1 px-4 py-3 text-sm font-medium transition-colors"
                        :class="activeTab === 'shifts' ? 'text-white border-b-2 border-emerald-500' : 'text-zinc-400 hover:text-zinc-200'"
                        @click="setActiveTab('shifts')"
                    >
                        Schichtliste
                    </button>
                </div>
            @endif

            {{-- Volunteer Admin: QR viewfinder + volunteer panel + gear pickup --}}
            <div id="tabpanel-va-scanner" role="tabpanel" aria-labelledby="tab-va-scanner" x-show="activeTab === 'scanner'" x-cloak class="flex flex-1 flex-col items-center space-y-4">
                <div id="scanner-viewfinder" class="relative aspect-square w-full max-w-sm overflow-hidden rounded-xl bg-black" @click="cameraPaused && resumeCamera()">
                    <video id="scanner-video" class="h-full w-full object-cover" aria-label="{{ __('QR code camera viewfinder') }}" playsinline></video>
                    <div
                        x-show="cameraPaused"
                        x-transition
                        class="absolute inset-0 flex cursor-pointer items-center justify-center bg-black/80"
                        x-cloak
                    >
                        <div class="text-center">
                            <svg class="mx-auto mb-2 h-12 w-12 text-zinc-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15.75 10.5l4.72-4.72a.75.75 0 011.28.53v11.38a.75.75 0 01-1.28.53l-4.72-4.72M4.5 18.75h9a2.25 2.25 0 002.25-2.25v-9a2.25 2.25 0 00-2.25-2.25h-9A2.25 2.25 0 002.25 7.5v9a2.25 2.25 0 002.25 2.25z" /></svg>
                            <p class="text-sm font-medium text-zinc-300">{{ __('Camera paused') }}</p>
                            <p class="mt-1 text-xs text-zinc-500">{{ __('Tap to resume') }}</p>
                        </div>
                    </div>
                </div>

                {{-- Status message --}}
                <div
                    x-show="state === 'confirmed' || state === 'duplicate' || state === 'invalid'"
                    x-transition
                    role="alert"
                    aria-live="assertive"
                    class="w-full max-w-sm rounded-xl p-4"
                    :class="{
                        'bg-emerald-500/10 border border-emerald-500/30': state === 'confirmed',
                        'bg-amber-500/10 border border-amber-500/30': state === 'duplicate',
                        'bg-red-500/10 border border-red-500/30': state === 'invalid',
                    }"
                    x-cloak
                >
                    <p class="text-center text-sm text-white" x-text="state === 'invalid' ? errorMessage : resultMessage"></p>
                </div>

                {{-- Volunteer detail panel --}}
                <div
                    x-show="selectedVolunteer"
                    x-transition
                    class="w-full max-w-sm space-y-4"
                    x-cloak
                >
                    {{-- Name, email & phone --}}
                    <div class="rounded-xl border border-zinc-700 bg-zinc-800 p-4">
                        <p class="text-lg font-semibold text-white" x-text="selectedVolunteer?.name"></p>
                        <p class="text-sm text-zinc-400" x-text="selectedVolunteer?.email"></p>
                        <p class="text-sm text-zinc-400" x-show="selectedVolunteer?.phone" x-text="selectedVolunteer?.phone"></p>
                        <p class="text-sm text-zinc-400" x-show="selectedVolunteer && !selectedVolunteer?.phone">{{ __('No phone number') }}</p>
                    </div>

                    @if (in_array('checkin', $modes))
                        {{-- Shift signups --}}
                        <template x-if="selectedVolunteer?.shift_signups?.length > 0">
                            <div class="rounded-xl border border-zinc-700 bg-zinc-800 p-4">
                                <h3 class="mb-3 text-sm font-semibold text-zinc-300">{{ __('Shifts') }}</h3>
                                <div class="space-y-2">
                                    <template x-for="signup in selectedVolunteerShiftSignups" :key="signup.id">
                                        <div class="flex min-h-12 items-center justify-between rounded-lg bg-zinc-900 px-4 py-3" :class="isSelectedShiftSignup(signup.id) ? 'ring-1 ring-emerald-500/60' : ''">
                                            <div class="min-w-0 flex-1">
                                                <p class="text-sm font-medium text-white" x-text="signup.shift.volunteer_job.name"></p>
                                                <p class="text-xs text-zinc-400" x-text="signup.shift.display_text"></p>
                                            </div>
                                            <div class="ml-3 shrink-0">
                                                <template x-if="isAttendanceRecorded(signup.id)">
                                                    <span class="rounded-full bg-emerald-500/20 px-3 py-1 text-xs text-emerald-300">{{ __('Recorded') }}</span>
                                                </template>
                                                <template x-if="!isAttendanceRecorded(signup.id) && canMarkAttendance && isOnline">
                                                    <button
                                                        type="button"
                                                        class="min-h-10 rounded-lg bg-blue-600 px-3 py-2 text-sm font-medium text-white hover:bg-blue-500 active:bg-blue-700"
                                                        @click="confirmAttendance(signup.id)"
                                                        :aria-label="'{{ __('Record attendance for') }} ' + signup.shift.volunteer_job.name"
                                                    >
                                                        {{ __('Attendance') }}
                                                    </button>
                                                </template>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </template>
                    @endif

                    @if (in_array('gear_pickup', $modes))
                        {{-- Gear assignments --}}
                        <template x-if="canPickupGear && selectedVolunteer && getVolunteerGear(selectedVolunteer.id).length > 0">
                            <div class="rounded-xl border border-zinc-700 bg-zinc-800 p-4">
                                <h3 class="mb-3 text-sm font-semibold text-zinc-300">{{ __('Gear') }}</h3>
                                <div class="space-y-2">
                                    <template x-for="gear in getVolunteerGear(selectedVolunteer.id)" :key="gear.id">
                                        <div class="flex min-h-12 items-center justify-between rounded-lg bg-zinc-900 px-4 py-3">
                                            <div class="min-w-0 flex-1">
                                                <p class="text-sm font-medium text-white" x-text="getGearItemName(gear.project_gear_item_id)"></p>
                                                <p x-show="gear.size" class="text-xs text-zinc-400" x-text="'{{ __('Size') }}: ' + gear.size"></p>
                                                <p x-show="gear.quantity_entitled !== null" class="text-xs text-zinc-400" x-text="getGearPickedUpCount(gear) + ' / ' + gear.quantity_entitled + ' {{ __('picked up') }}'"></p>
                                            </div>
                                            <div class="ml-3 shrink-0">
                                                <template x-if="isGearFullyPickedUp(gear)">
                                                    <span class="rounded-full bg-emerald-500/20 px-3 py-1 text-xs text-emerald-300">{{ __('Picked up') }}</span>
                                                </template>
                                                <template x-if="!isGearFullyPickedUp(gear) && isOnline">
                                                    <button
                                                        type="button"
                                                        class="min-h-10 rounded-lg bg-purple-600 px-3 py-2 text-sm font-medium text-white hover:bg-purple-500 active:bg-purple-700 disabled:cursor-not-allowed disabled:opacity-50"
                                                        @click="selectGearState(gear.id, 'picked_up')"
                                                        :disabled="isGearCooldown(gear.id)"
                                                        :aria-label="'{{ __('Pick up') }} ' + getGearItemName(gear.project_gear_item_id)"
                                                    >
                                                        <span x-show="!isGearCooldown(gear.id)">{{ __('Pick Up') }}</span>
                                                        <span x-show="isGearCooldown(gear.id)" x-cloak>&#10003;</span>
                                                    </button>
                                                </template>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </template>
                    @endif

                    {{-- Dismiss button --}}
                    <button
                        type="button"
                        class="min-h-12 w-full rounded-lg border border-zinc-600 px-4 py-3 text-base font-medium text-zinc-300 hover:bg-zinc-700 active:bg-zinc-600"
                        @click="dismiss()"
                    >
                        {{ __('Done') }}
                    </button>
                </div>
            </div>

            @if (in_array('checkin', $modes))
                <div id="tabpanel-shifts" role="tabpanel" aria-labelledby="tab-shifts" x-show="activeTab === 'shifts'" x-cloak class="flex flex-1 flex-col space-y-4">
                    <div class="space-y-3 px-1">
                        <input
                            type="text"
                            x-model.debounce.300ms="shiftSearchQuery"
                            placeholder="{{ __('Search shifts or volunteers...') }}"
                            aria-label="{{ __('Search volunteers in shift list') }}"
                            class="min-h-12 w-full rounded-lg border border-zinc-700 bg-zinc-800 px-4 py-3 text-base text-white placeholder-zinc-500 focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500"
                        />

                        <button
                            type="button"
                            class="min-h-12 w-full rounded-lg bg-blue-600 px-4 py-3 text-base font-medium text-white hover:bg-blue-500 active:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50"
                            @click="jumpToNextShift()"
                            :disabled="nextUpcomingShiftGroupId === null"
                        >
                            Jetzt
                        </button>

                        <template x-if="scannerDataSource === 'cache'">
                            <div class="rounded-lg border border-amber-500/30 bg-amber-500/10 px-4 py-3 text-sm text-amber-200">
                                Offline - showing cached shift data.
                            </div>
                        </template>

                        <template x-if="shiftListNotice">
                            <div class="rounded-lg border border-zinc-700 bg-zinc-800 px-4 py-3 text-sm text-zinc-300" x-text="shiftListNotice"></div>
                        </template>
                    </div>

                    <div class="space-y-3 overflow-y-auto px-1">
                        <template x-if="scannerDataSource === 'unavailable'">
                            <p class="py-8 text-center text-sm text-zinc-500">Shift data is unavailable right now.</p>
                        </template>

                        <template x-if="scannerDataSource !== 'unavailable' && shouldShowShiftSearchHint">
                            <p class="py-8 text-center text-sm text-zinc-500">Mindestens 2 Zeichen eingeben.</p>
                        </template>

                        <template x-if="scannerDataSource !== 'unavailable' && !shouldShowShiftSearchHint && filteredShiftGroups.length === 0 && shiftSearchQuery.trim().length === 0">
                            <p class="py-8 text-center text-sm text-zinc-500">No active or upcoming shifts.</p>
                        </template>

                        <template x-if="scannerDataSource !== 'unavailable' && !shouldShowShiftSearchHint && filteredShiftGroups.length === 0 && shiftSearchQuery.trim().length >= 2">
                            <p class="py-8 text-center text-sm text-zinc-500">No matching volunteers found.</p>
                        </template>

                        <template x-for="group in filteredShiftGroups" :key="group.shiftId">
                            <div :id="`shift-group-${group.shiftId}`" class="rounded-xl border border-zinc-700 bg-zinc-800 p-3">
                                <div class="mb-3 flex items-start justify-between gap-3">
                                    <div>
                                        <p class="text-sm font-semibold text-white" x-text="group.jobName"></p>
                                        <p class="text-xs text-zinc-400" x-text="group.displayText"></p>
                                    </div>
                                    <template x-if="shiftGroupBadgeLabel(group)">
                                        <span class="rounded-full px-3 py-1 text-xs font-medium" :class="group.groupStatus === 'active' ? 'bg-emerald-500/20 text-emerald-300' : 'bg-blue-500/20 text-blue-300'" x-text="shiftGroupBadgeLabel(group)"></span>
                                    </template>
                                </div>

                                <div class="space-y-2">
                                    <template x-for="row in group.volunteers" :key="`${row.signupId}-${row.volunteerId}`">
                                        <button
                                            type="button"
                                            class="flex min-h-12 w-full items-center justify-between rounded-lg bg-zinc-900 px-4 py-3 text-left transition hover:bg-zinc-950"
                                            @click="selectVolunteerFromShift(row)"
                                        >
                                            <div class="min-w-0 flex-1">
                                                <p class="text-sm font-medium text-white" x-text="row.name"></p>
                                                <p class="text-xs text-zinc-400" x-text="row.email"></p>
                                            </div>
                                            <span
                                                class="ml-3 shrink-0 rounded-full px-3 py-1 text-xs font-medium"
                                                :class="{
                                                    'bg-emerald-500/20 text-emerald-300': row.status === 'attended',
                                                    'bg-amber-500/20 text-amber-300': row.status === 'active',
                                                    'bg-zinc-700 text-zinc-200': row.status === 'upcoming',
                                                    'bg-red-500/20 text-red-300': row.status === 'missed'
                                                }"
                                                x-text="shiftStatusLabel(row.status)"
                                            ></span>
                                        </button>
                                    </template>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            @endif
        @endif
    </main>
</div>
