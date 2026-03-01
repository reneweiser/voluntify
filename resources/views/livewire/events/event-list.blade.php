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
    <div class="flex gap-2 mb-6">
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
        <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 p-12 text-center">
            <flux:icon name="calendar" class="mx-auto h-12 w-12 text-zinc-400" />
            <flux:heading size="sm" class="mt-4">{{ __('No events found') }}</flux:heading>
            <flux:text class="mt-2">{{ __('Create your first event to get started.') }}</flux:text>
        </div>
    @else
        <div class="space-y-4">
            @foreach ($this->events as $event)
                <a href="{{ route('events.show', $event) }}" wire:navigate wire:key="event-{{ $event->id }}"
                   class="block rounded-lg border border-zinc-200 dark:border-zinc-700 p-4 hover:bg-zinc-50 dark:hover:bg-zinc-700/50 transition-colors">
                    <div class="flex items-center justify-between">
                        <div>
                            <flux:heading size="sm">{{ $event->name }}</flux:heading>
                            <flux:text size="sm" class="mt-1">
                                {{ $event->starts_at->format('M d, Y g:i A') }} &mdash; {{ $event->ends_at->format('g:i A') }}
                            </flux:text>
                            @if ($event->location)
                                <flux:text size="sm">{{ $event->location }}</flux:text>
                            @endif
                        </div>
                        <div class="flex items-center gap-3">
                            <flux:text size="sm">
                                {{ $event->volunteers_count }} {{ __('volunteers') }}
                            </flux:text>
                            <flux:badge size="sm" :color="match($event->status) {
                                \App\Enums\EventStatus::Published => 'green',
                                \App\Enums\EventStatus::Draft => 'yellow',
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
    <flux:modal wire:model="showCreateModal" class="md:w-96">
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
                <input type="file" wire:model="eventTitleImage" accept="image/jpeg,image/png,image/webp"
                    class="block w-full text-sm text-zinc-500 file:mr-4 file:rounded-md file:border-0 file:bg-zinc-100 file:px-4 file:py-2 file:text-sm file:font-medium hover:file:bg-zinc-200 dark:text-zinc-400 dark:file:bg-zinc-700 dark:hover:file:bg-zinc-600" />
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
