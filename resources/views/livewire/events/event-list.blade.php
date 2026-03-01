<div class="mx-auto max-w-7xl p-6">
    <div class="flex items-center justify-between mb-6">
        <flux:heading size="xl">{{ __('Events') }}</flux:heading>
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
                <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 p-4" wire:key="event-{{ $event->id }}">
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
                        <flux:badge size="sm" :color="match($event->status) {
                            \App\Enums\EventStatus::Published => 'green',
                            \App\Enums\EventStatus::Draft => 'yellow',
                            \App\Enums\EventStatus::Archived => 'zinc',
                        }">
                            {{ __(ucfirst($event->status->value)) }}
                        </flux:badge>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
