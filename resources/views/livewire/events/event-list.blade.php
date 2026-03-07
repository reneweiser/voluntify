<div class="mx-auto max-w-7xl p-6">
    <div class="flex items-center justify-between mb-6">
        <flux:heading size="xl">{{ __('Events') }}</flux:heading>

        @if ($this->canCreateEvents)
            <flux:button variant="primary" icon="plus" wire:click="$set('showCreateModal', true)">
                {{ __('Create Event') }}
            </flux:button>
        @endif
    </div>

    {{-- Status filter buttons --}}
    <div class="flex flex-wrap gap-2 mb-6">
        <flux:button
            size="sm"
            :variant="$statusFilter === '' ? 'primary' : 'subtle'"
            wire:click="setStatusFilter(null)"
        >
            {{ __('All') }}
        </flux:button>
        @foreach (\App\Enums\EventStatus::cases() as $status)
            <flux:button
                size="sm"
                :variant="$statusFilter === $status->value ? 'primary' : 'subtle'"
                wire:click="setStatusFilter('{{ $status->value }}')"
            >
                {{ __(ucfirst($status->value)) }}
            </flux:button>
        @endforeach
    </div>

    {{-- Events list --}}
    @if ($this->events->isEmpty())
        <div class="rounded-xl border-2 border-dashed border-zinc-300 dark:border-zinc-600 p-12 text-center">
            <div class="mx-auto flex size-16 items-center justify-center rounded-full bg-emerald-100 dark:bg-emerald-900/40">
                <flux:icon name="calendar" class="size-8 text-emerald-600 dark:text-emerald-400" />
            </div>
            <flux:heading size="sm" class="mt-4">{{ __('No events found') }}</flux:heading>
            <flux:text class="mt-2">{{ __('Create your first event to get started.') }}</flux:text>
            @if ($this->canCreateEvents)
                <div class="mt-4">
                    <flux:button variant="primary" size="sm" icon="plus" wire:click="$set('showCreateModal', true)">
                        {{ __('Create Event') }}
                    </flux:button>
                </div>
            @endif
        </div>
    @else
        <div class="space-y-4">
            @foreach ($this->events as $event)
                <a href="{{ route('events.show', $event) }}" wire:navigate wire:key="event-{{ $event->id }}"
                   class="block rounded-xl border border-zinc-200 dark:border-zinc-700 p-4 transition-all duration-200 hover:shadow-md
                       {{ match($event->status) {
                           \App\Enums\EventStatus::Published => 'card-accent-emerald',
                           \App\Enums\EventStatus::Draft => 'card-accent-amber',
                           \App\Enums\EventStatus::Archived => 'border-l-4 border-l-zinc-400',
                       } }}">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div class="flex items-start gap-4">
                            @if ($event->titleImageUrl())
                                <img src="{{ $event->titleImageUrl() }}" alt="" class="size-14 shrink-0 rounded-lg object-cover" />
                            @endif
                            <div>
                                <flux:heading size="sm">{{ $event->name }}</flux:heading>
                                <div class="mt-1 flex flex-wrap items-center gap-3 text-sm text-zinc-500 dark:text-zinc-400">
                                    <span class="inline-flex items-center gap-1">
                                        <flux:icon name="calendar" variant="mini" class="size-4" />
                                        {{ $event->starts_at->format('M d, Y g:i A') }} &mdash; {{ $event->ends_at->format('g:i A') }}
                                    </span>
                                    @if ($event->location)
                                        <span class="inline-flex items-center gap-1">
                                            <flux:icon name="map-pin" variant="mini" class="size-4" />
                                            {{ $event->location }}
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <flux:text size="sm">
                                {{ $event->volunteers_count }} {{ __('volunteers') }}
                            </flux:text>
                            <flux:badge size="sm" :color="match($event->status) {
                                \App\Enums\EventStatus::Published => 'emerald',
                                \App\Enums\EventStatus::Draft => 'amber',
                                \App\Enums\EventStatus::Archived => 'zinc',
                            }">
                                {{ __(ucfirst($event->status->value)) }}
                            </flux:badge>
                        </div>
                    </div>
                </a>
            @endforeach
        </div>
    @endif

    {{-- Create Event Modal --}}
    @if ($this->canCreateEvents)
    <flux:modal wire:model.self="showCreateModal" class="md:w-96">
        <form wire:submit="createEvent" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Create Event') }}</flux:heading>
                <flux:text class="mt-2">{{ __('Set up the basics for your new event.') }}</flux:text>
            </div>

            <flux:field>
                <flux:label>{{ __('Event Name') }}</flux:label>
                <flux:input wire:model="eventName" placeholder="{{ __('e.g. Summer Carnival') }}" />
                <flux:error name="eventName" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Description') }}</flux:label>
                <flux:textarea wire:model="eventDescription" rows="3" />
                <flux:error name="eventDescription" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Location') }}</flux:label>
                <flux:input wire:model="eventLocation" placeholder="{{ __('e.g. Central Park') }}" />
                <flux:error name="eventLocation" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Starts At') }}</flux:label>
                <flux:input type="datetime-local" wire:model="eventStartsAt" />
                <flux:error name="eventStartsAt" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Ends At') }}</flux:label>
                <flux:input type="datetime-local" wire:model="eventEndsAt" />
                <flux:error name="eventEndsAt" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Title Image') }} <span class="text-zinc-400 font-normal">({{ __('optional') }})</span></flux:label>
                <flux:input type="file" wire:model="eventTitleImage" accept="image/jpeg,image/png,image/webp" />
                <flux:error name="eventTitleImage" />
            </flux:field>

            <div class="flex">
                <flux:spacer />
                <flux:button type="submit" variant="primary">{{ __('Create Event') }}</flux:button>
            </div>
        </form>
    </flux:modal>
    @endif
</div>
