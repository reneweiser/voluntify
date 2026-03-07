<div class="mx-auto max-w-7xl p-6">
    <div class="mb-6">
        <flux:heading size="xl">{{ __('Scanner') }}</flux:heading>
        <flux:text class="mt-1">{{ __('Select an event to start scanning tickets.') }}</flux:text>
    </div>

    @if ($this->events->isEmpty())
        <div class="rounded-xl border-2 border-dashed border-zinc-300 dark:border-zinc-600 p-12 text-center">
            <div class="mx-auto flex size-16 items-center justify-center rounded-full bg-zinc-100 dark:bg-zinc-800">
                <flux:icon name="qr-code" class="size-8 text-zinc-400" />
            </div>
            <flux:heading size="sm" class="mt-4">{{ __('No published events') }}</flux:heading>
            <flux:text class="mt-2">{{ __('Publish an event to start scanning tickets.') }}</flux:text>
        </div>
    @else
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($this->events as $event)
                <div wire:key="event-{{ $event->id }}" class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-5 transition-all duration-200 hover:shadow-md">
                    <div class="mb-4">
                        <flux:heading size="sm">{{ $event->name }}</flux:heading>
                        <div class="mt-1 space-y-1 text-sm text-zinc-500 dark:text-zinc-400">
                            <div class="flex items-center gap-1.5">
                                <flux:icon name="calendar" variant="mini" class="size-4" />
                                {{ $event->starts_at->format('M d, Y g:i A') }}
                            </div>
                            @if ($event->location)
                                <div class="flex items-center gap-1.5">
                                    <flux:icon name="map-pin" variant="mini" class="size-4" />
                                    {{ $event->location }}
                                </div>
                            @endif
                        </div>
                    </div>

                    <div class="mb-4">
                        <div class="flex items-center gap-2 text-sm">
                            <flux:badge size="sm" color="emerald">
                                {{ $event->event_arrivals_count }}/{{ $event->volunteers_count }} {{ __('arrived') }}
                            </flux:badge>
                        </div>
                    </div>

                    <flux:button variant="primary" class="w-full" icon="qr-code" :href="route('scanner.scan', $event)">
                        {{ __('Start Scanning') }}
                    </flux:button>
                </div>
            @endforeach
        </div>
    @endif
</div>
