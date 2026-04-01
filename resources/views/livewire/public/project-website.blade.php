<div>
    @section('meta')
        <meta property="og:title" content="{{ $project->name }}" />
        @if ($project->website_description ?? $project->description)
            <meta property="og:description" content="{{ Str::limit(strip_tags($project->website_description ?? $project->description), 200) }}" />
        @endif
        @if ($project->titleImageUrl())
            <meta property="og:image" content="{{ $project->titleImageUrl() }}" />
        @endif
    @endsection

    {{-- Title image with hero treatment --}}
    @if ($project->titleImageUrl())
        <div class="mb-8 -mx-6 sm:mx-0 relative">
            <img src="{{ $project->titleImageUrl() }}" alt="{{ $project->name }}" class="w-full max-h-72 object-cover sm:rounded-xl" />
            <div class="absolute inset-0 bg-gradient-to-t from-black/30 to-transparent sm:rounded-xl"></div>
        </div>
    @endif

    {{-- Project header --}}
    <div class="mb-8">
        <flux:heading size="xl">{{ $project->name }}</flux:heading>
        @if ($renderedDescription)
            <div class="prose dark:prose-invert mt-4 max-w-none">
                {!! $renderedDescription !!}
            </div>
        @elseif ($project->description)
            <flux:text class="mt-2">{{ $project->description }}</flux:text>
        @endif
    </div>

    {{-- Contact info --}}
    @if ($project->website_contact_info)
        <div class="mb-8 rounded-xl border border-zinc-200 dark:border-zinc-700 p-4">
            <div class="flex items-center gap-2 mb-2">
                <flux:icon name="envelope" variant="mini" class="size-4 text-zinc-500" />
                <flux:text size="sm" class="font-medium">{{ __('Kontakt') }}</flux:text>
            </div>
            <flux:text>{{ $project->website_contact_info }}</flux:text>
        </div>
    @endif

    {{-- Events list --}}
    @if ($events->isEmpty())
        <div class="rounded-xl border-2 border-dashed border-zinc-300 dark:border-zinc-600 p-12 text-center">
            <flux:heading size="sm">{{ __('Aktuell keine Events verfügbar') }}</flux:heading>
            <flux:text class="mt-2">{{ __('Schau später noch einmal vorbei.') }}</flux:text>
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
                                        {{ $event->starts_at->format('d.m.Y H:i') }} &mdash; {{ $event->ends_at->format('H:i') }}
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
                                {{ $event->volunteer_count }} {{ __('Helfer:innen') }}
                            </flux:text>
                            @if ($event->status === \App\Enums\EventStatus::PublishedOpen)
                                <flux:badge size="sm" color="emerald">{{ __('Anmelden') }}</flux:badge>
                            @else
                                <flux:badge size="sm" color="zinc">{{ __('Registrierung geschlossen') }}</flux:badge>
                            @endif
                        </div>
                    </div>
                </a>
            @endforeach
        </div>
    @endif
</div>
