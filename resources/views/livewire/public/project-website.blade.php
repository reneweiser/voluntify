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

    {{-- Hero image --}}
    @if ($project->titleImageUrl())
        <div class="mb-10 -mx-6 sm:mx-0 relative overflow-hidden sm:rounded-lg">
            <img src="{{ $project->titleImageUrl() }}" alt="{{ $project->name }}" class="w-full max-h-96 object-cover" loading="eager" />
            <div class="absolute inset-0 bg-gradient-to-t from-[#1A1A1A]/70 via-[#1A1A1A]/30 to-transparent sm:rounded-lg"></div>
        </div>
    @endif

    {{-- Project header --}}
    <div class="mb-10">
        <h1 class="font-bebas text-white leading-none" style="font-size: clamp(2.2rem, 5vw, 3.2rem); letter-spacing: 0.04em;">
            {{ $project->name }}
        </h1>
        <div class="accent-bar mt-3"><span></span><span></span><span></span></div>

        @if ($renderedDescription)
            <div class="public-prose mt-8">
                {!! $renderedDescription !!}
            </div>
        @elseif ($project->description)
            <p class="mt-4" style="color: #a1a1aa; font-size: 1.1rem; line-height: 1.6;">{{ $project->description }}</p>
        @endif
    </div>

    {{-- Contact info --}}
    @if ($project->website_contact_info)
        <div class="mb-10 rounded-lg p-5" style="background: rgba(255,255,255,0.05); border-left: 4px solid var(--brand);">
            <div class="flex items-center gap-2 mb-2">
                <flux:icon name="envelope" variant="mini" class="size-4" style="color: var(--brand);" />
                <span class="text-sm font-semibold text-white">{{ __('Kontakt') }}</span>
            </div>
            <p style="color: #a1a1aa;">{{ $project->website_contact_info }}</p>
        </div>
    @endif

    {{-- Events section --}}
    @if ($events->isNotEmpty())
        <div class="mb-6 flex items-end justify-between">
            <div>
                <h2 class="font-bebas text-white" style="font-size: 1.8rem; letter-spacing: 0.06em;">EVENTS</h2>
                <div class="accent-bar mt-1"><span></span><span></span><span></span></div>
            </div>
            <span class="text-sm" style="color: #a1a1aa;">
                {{ $events->count() }} {{ trans_choice('Event|Events', $events->count()) }}
            </span>
        </div>
    @endif

    @if ($events->isEmpty())
        <div class="rounded-lg p-16 text-center" style="border: 2px dashed rgba(255,255,255,0.15);">
            <div class="mx-auto flex size-14 items-center justify-center rounded-full mb-4" style="background: rgba(255,255,255,0.05);">
                <flux:icon name="calendar" class="size-7" style="color: rgba(255,255,255,0.3);" />
            </div>
            <h3 class="font-bebas text-white text-xl" style="letter-spacing: 0.04em;">{{ __('Aktuell keine Events verfügbar') }}</h3>
            <p class="mt-2 max-w-sm mx-auto" style="color: #a1a1aa;">
                {{ __('Es werden bald neue Events veröffentlicht. Schau später noch einmal vorbei!') }}
            </p>
        </div>
    @else
        <div class="flex flex-col gap-5">
            @foreach ($events as $event)
                <a href="{{ route('events.public', $event->public_token) }}"
                   wire:key="event-{{ $event->id }}"
                   aria-label="{{ $event->name }}"
                   class="group block rounded-lg overflow-hidden public-card-hover"
                   style="background: rgba(255,255,255,0.05); box-shadow: 0 1px 3px rgba(0,0,0,0.3);">

                    {{-- Event image --}}
                    @if ($event->titleImageUrl())
                        <div class="relative h-40 overflow-hidden">
                            <img src="{{ $event->titleImageUrl() }}" alt=""
                                 class="w-full h-full object-cover group-hover:scale-[1.02] transition-transform duration-300" />
                            <div class="absolute inset-0 bg-gradient-to-t from-[#1A1A1A]/80 to-transparent"></div>
                            {{-- Status overlay on image --}}
                            @if ($event->status === \App\Enums\EventStatus::PublishedOpen)
                                <div class="absolute bottom-3 left-5">
                                    <span style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.35rem 0.9rem; background: var(--brand); color: white; border-radius: 4px; font-size: 0.8rem; font-weight: 600; letter-spacing: 0.05em; text-transform: uppercase; box-shadow: 0 4px 12px rgba(5,150,105,0.3);">
                                        <span style="width: 6px; height: 6px; border-radius: 50%; background: white; animation: urgency-pulse 2s ease-in-out infinite;"></span>
                                        {{ __('Anmelden') }}
                                    </span>
                                </div>
                            @endif
                        </div>
                    @endif

                    <div class="p-5">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0 flex-1">
                                <h3 class="text-base font-semibold text-white group-hover:text-emerald-400 transition-colors truncate">
                                    {{ $event->name }}
                                </h3>
                                <div class="mt-2 flex flex-col gap-1.5 text-sm" style="color: #a1a1aa;">
                                    <span class="inline-flex items-center gap-1.5">
                                        <flux:icon name="calendar" variant="mini" class="size-4 shrink-0" style="color: #9a9a9a;" />
                                        @php $tz = $project->timezone ?? 'UTC'; @endphp
                                        {{ $event->starts_at->setTimezone($tz)->format('d.m.Y H:i') }} &mdash; {{ $event->ends_at->setTimezone($tz)->format('H:i') }}
                                    </span>
                                    @if ($event->location)
                                        <span class="inline-flex items-center gap-1.5">
                                            <flux:icon name="map-pin" variant="mini" class="size-4 shrink-0" style="color: #9a9a9a;" />
                                            {{ $event->location }}
                                        </span>
                                    @endif
                                    <span class="inline-flex items-center gap-1.5">
                                        <flux:icon name="users" variant="mini" class="size-4 shrink-0" style="color: #9a9a9a;" />
                                        {{ $event->volunteer_count }} {{ __('Helfer:innen') }}
                                    </span>
                                </div>
                            </div>

                            @if (!$event->titleImageUrl())
                                {{-- CTA for cards without image --}}
                                @if ($event->status === \App\Enums\EventStatus::PublishedOpen)
                                    <span class="shrink-0" style="display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.4rem 1rem; background: var(--brand); color: white; border-radius: 4px; font-size: 0.8rem; font-weight: 600; letter-spacing: 0.05em; text-transform: uppercase; transition: opacity 0.2s;">
                                        {{ __('Anmelden') }}
                                        <flux:icon name="arrow-right" variant="mini" class="size-3.5" />
                                    </span>
                                @else
                                    <span class="shrink-0 text-xs font-medium px-2.5 py-1 rounded" style="background: rgba(255,255,255,0.08); color: #9a9a9a;">
                                        {{ __('Registrierung geschlossen') }}
                                    </span>
                                @endif
                            @else
                                {{-- Arrow hint for image cards --}}
                                <flux:icon name="arrow-right" variant="mini" class="size-5 shrink-0 mt-1 group-hover:translate-x-0.5 transition-all" style="color: #9a9a9a;" />
                            @endif
                        </div>
                    </div>
                </a>
            @endforeach
        </div>
    @endif

    {{-- Access link request form --}}
    <div class="mt-12 rounded-lg p-6" style="background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1);">
        <h2 class="font-bebas text-white mb-1" style="font-size: 1.4rem; letter-spacing: 0.06em;">{{ __('Zugang erhalten') }}</h2>
        <p class="mb-4 text-sm" style="color: #a1a1aa;">{{ __('Gib deine E-Mail-Adresse ein, um einen neuen Zugangslink zu deinem Volunteer-Portal zu erhalten.') }}</p>

        @if ($accessLinkMessage)
            <div class="rounded-lg p-3 mb-4 text-sm" style="background: rgba(5,150,105,0.1); border: 1px solid rgba(5,150,105,0.2); color: #6ee7b7;">
                {{ $accessLinkMessage }}
            </div>
        @endif

        <form wire:submit="requestAccessLink" class="flex flex-col sm:flex-row gap-3">
            <div class="flex-1">
                <input
                    type="email"
                    wire:model="requestEmail"
                    placeholder="{{ __('E-Mail-Adresse') }}"
                    class="w-full rounded-lg px-4 py-2.5 text-sm text-white placeholder-zinc-500 focus:outline-none focus:ring-2"
                    style="background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.15); focus-ring-color: var(--brand);"
                />
                @error('requestEmail')
                    <p class="mt-1 text-sm" style="color: #fca5a5;">{{ $message }}</p>
                @enderror
            </div>
            <button
                type="submit"
                wire:loading.attr="disabled"
                class="shrink-0 rounded-lg px-5 py-2.5 text-sm font-semibold text-white transition-opacity"
                style="background: var(--brand); letter-spacing: 0.03em;"
            >
                <span wire:loading.remove wire:target="requestAccessLink">{{ __('Zugangslink senden') }}</span>
                <span wire:loading wire:target="requestAccessLink">{{ __('Wird gesendet...') }}</span>
            </button>
        </form>
    </div>
</div>
