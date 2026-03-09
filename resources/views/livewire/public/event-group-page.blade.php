<div>
    @section('meta')
        <meta property="og:title" content="{{ $group->name }}" />
        @if ($group->description)
            <meta property="og:description" content="{{ $group->description }}" />
        @endif
        @if ($group->titleImageUrl())
            <meta property="og:image" content="{{ $group->titleImageUrl() }}" />
        @endif
    @endsection

    {{-- Title image with hero treatment --}}
    @if ($group->titleImageUrl())
        <div class="mb-8 -mx-6 sm:mx-0 relative">
            <img src="{{ $group->titleImageUrl() }}" alt="{{ $group->name }}" class="w-full max-h-72 object-cover sm:rounded-xl" />
            <div class="absolute inset-0 bg-gradient-to-t from-black/30 to-transparent sm:rounded-xl"></div>
        </div>
    @endif

    {{-- Group header --}}
    <div class="mb-8">
        <flux:heading size="xl">{{ $group->name }}</flux:heading>
        @if ($group->description)
            <flux:text class="mt-2">{{ $group->description }}</flux:text>
        @endif
    </div>

    {{-- Events list --}}
    @if ($events->isEmpty())
        <div class="rounded-xl border-2 border-dashed border-zinc-300 dark:border-zinc-600 p-12 text-center">
            <flux:heading size="sm">{{ __('No events available yet') }}</flux:heading>
            <flux:text class="mt-2">{{ __('Check back later for upcoming events.') }}</flux:text>
        </div>
    @else
        <div class="space-y-4">
            @foreach ($events as $event)
                <a href="{{ route('events.public', $event->public_token) }}" wire:key="event-{{ $event->id }}"
                   class="block rounded-xl border border-zinc-200 dark:border-zinc-700 p-4 transition-all duration-200 hover:shadow-md card-accent-emerald">
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
                            <flux:badge size="sm" color="emerald">{{ __('Sign Up') }}</flux:badge>
                        </div>
                    </div>
                </a>
            @endforeach
        </div>
    @endif
</div>
