<div
    x-data="{
        isOnline: navigator.onLine,
        offlineSearch: '',
        offlineResults: [],
        offlineEventId: {{ $this->eventId }},
        outboxCount: 0,
        confirmMessage: '',
        async init() {
            window.addEventListener('online', () => { this.isOnline = true; });
            window.addEventListener('offline', () => { this.isOnline = false; });
        },
        async offlineSearchVolunteers() {
            if (!this.offlineEventId || this.offlineSearch.length < 2) {
                this.offlineResults = [];
                return;
            }
            const { searchVolunteers } = await import('/build/assets/scanner-idb.js').catch(() => ({ searchVolunteers: null }));
            if (searchVolunteers) {
                this.offlineResults = await searchVolunteers(parseInt(this.offlineEventId), this.offlineSearch);
            }
        },
        async offlineConfirm(volunteerId, ticketId) {
            const { addOutboxEntry, getOutboxCount } = await import('/build/assets/scanner-idb.js').catch(() => ({ addOutboxEntry: null, getOutboxCount: null }));
            if (addOutboxEntry) {
                await addOutboxEntry(parseInt(this.offlineEventId), {
                    ticket_id: ticketId,
                    volunteer_id: volunteerId,
                    method: 'manual_lookup',
                    scanned_at: new Date().toISOString().replace('T', ' ').substring(0, 19),
                });
                this.confirmMessage = 'Arrival queued for sync';
                if (getOutboxCount) {
                    this.outboxCount = await getOutboxCount(parseInt(this.offlineEventId));
                }
                setTimeout(() => { this.confirmMessage = ''; }, 3000);
                this.offlineSearch = '';
                this.offlineResults = [];
            }
        }
    }"
    class="flex min-h-screen flex-col"
>
    {{-- Top bar --}}
    <header class="flex items-center justify-between border-b border-zinc-700 bg-zinc-800 px-4 py-3">
        <div class="flex items-center gap-3">
            <flux:button variant="ghost" href="{{ route('scanner.scan', $this->eventId) }}" wire:navigate size="sm" icon="arrow-left" class="text-zinc-300 hover:text-white" />
            <flux:heading size="lg" class="text-white">Manual Lookup</flux:heading>
        </div>
        <span x-text="isOnline ? 'Online' : 'Offline'" :class="isOnline ? 'text-sm text-emerald-400' : 'text-sm text-amber-400'"></span>
    </header>

    {{-- Event name bar --}}
    <div class="border-b border-zinc-700 bg-zinc-800/50 px-4 py-2">
        <flux:text size="sm" class="text-zinc-300">{{ $this->event->name }}</flux:text>
    </div>

    {{-- Online: Livewire search --}}
    <div x-show="isOnline" class="flex-1 p-4">
        <div class="mb-4">
            <flux:input wire:model.live.debounce.300ms="search" placeholder="Search by volunteer name..." icon="magnifying-glass" />
        </div>

        @if (strlen($search) < 2)
            <p class="text-center text-zinc-400">Search for a volunteer</p>
        @elseif ($this->volunteers->isEmpty())
            <p class="text-center text-zinc-400">No volunteers found</p>
        @else
            <div class="space-y-3">
                @foreach ($this->volunteers as $volunteer)
                    <div class="rounded-xl border border-zinc-700 bg-zinc-800 p-4">
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="font-semibold text-white">{{ $volunteer->name }}</p>
                                <p class="text-sm text-zinc-400">{{ $volunteer->email }}</p>

                                @foreach ($volunteer->shiftSignups as $signup)
                                    <p class="mt-1 text-sm text-zinc-300">
                                        {{ $signup->shift->volunteerJob->name }}
                                        &middot; {{ $signup->shift->starts_at->format('g:i A') }}–{{ $signup->shift->ends_at->format('g:i A') }}
                                    </p>
                                @endforeach
                            </div>

                            <div class="ml-4 shrink-0">
                                @if ($volunteer->eventArrivals->isNotEmpty())
                                    <span class="inline-flex items-center rounded-full bg-amber-900/30 px-3 py-1 text-sm font-medium text-amber-400">
                                        Already arrived
                                    </span>
                                @else
                                    <flux:button variant="primary" size="sm" wire:click="confirmArrival({{ $volunteer->id }})">
                                        Confirm
                                    </flux:button>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Offline: Alpine.js/IndexedDB search --}}
    <div x-show="!isOnline" x-cloak class="flex-1 p-4">
        <div>
            <div class="mb-4">
                <input
                    x-model="offlineSearch"
                    x-on:input.debounce.300ms="offlineSearchVolunteers()"
                    type="text"
                    placeholder="Search by volunteer name (offline)..."
                    class="w-full rounded-lg border border-zinc-600 bg-zinc-800 px-4 py-2 text-white placeholder-zinc-500 focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500"
                >
            </div>

            <template x-if="offlineSearch.length >= 2 && offlineResults.length === 0">
                <p class="text-center text-zinc-400">No volunteers found (offline data)</p>
            </template>

            <template x-if="offlineSearch.length < 2">
                <p class="text-center text-zinc-400">Search for a volunteer (offline mode)</p>
            </template>

            <div class="space-y-3">
                <template x-for="vol in offlineResults" :key="vol.id">
                    <div class="rounded-xl border border-zinc-700 bg-zinc-800 p-4">
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="font-semibold text-white" x-text="vol.name"></p>
                                <p class="text-sm text-zinc-400" x-text="vol.email"></p>
                                <template x-for="signup in vol.shift_signups" :key="signup.id">
                                    <p class="mt-1 text-sm text-zinc-300" x-text="signup.shift.volunteer_job.name"></p>
                                </template>
                            </div>
                            <div class="ml-4 shrink-0">
                                <button
                                    x-on:click="offlineConfirm(vol.id, vol.ticket.id)"
                                    class="rounded-lg bg-emerald-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-emerald-500"
                                >
                                    Confirm
                                </button>
                            </div>
                        </div>
                    </div>
                </template>
            </div>

            {{-- Confirmation toast --}}
            <div x-show="confirmMessage" x-cloak x-transition class="mt-4 rounded-xl border border-emerald-600/50 bg-emerald-900/20 p-3 text-center text-sm text-emerald-400" x-text="confirmMessage"></div>

            {{-- Outbox count --}}
            <div x-show="outboxCount > 0" class="mt-4 text-center text-sm text-amber-400" x-text="outboxCount + ' arrival(s) pending sync'"></div>
        </div>
    </div>
</div>
