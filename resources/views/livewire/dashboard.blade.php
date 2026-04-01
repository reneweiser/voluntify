<div class="mx-auto max-w-7xl p-6">
    {{-- Welcome banner --}}
    <div class="mb-8 rounded-xl bg-gradient-to-r from-emerald-600 to-emerald-500 dark:from-emerald-700 dark:to-emerald-600 p-6 text-white shadow-sm">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <flux:heading size="xl" class="!text-white">
                    {{ __('Willkommen, :name!', ['name' => auth()->user()->name]) }}
                </flux:heading>
                @if ($this->organization)
                    <div class="mt-2 flex items-center gap-2">
                        <span class="text-emerald-100">{{ $this->organization->name }}</span>
                        <flux:badge size="sm" class="!bg-white/20 !text-white !border-0">{{ __(ucfirst(str_replace('_', ' ', $this->userRole ?? ''))) }}</flux:badge>
                    </div>
                @endif
            </div>
            @if ($this->canCreateEvents)
                <flux:button variant="ghost" icon="plus" class="!text-white !border-white/30 hover:!bg-white/10" :href="route('events.index')" wire:navigate>
                    {{ __('Neues Event') }}
                </flux:button>
            @endif
        </div>
    </div>

    {{-- Next upcoming event --}}
    @if ($this->nextUpcomingEvent)
        <div class="mb-6 rounded-xl border border-emerald-200 dark:border-emerald-800 bg-emerald-50 dark:bg-emerald-900/20 p-4 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-emerald-100 dark:bg-emerald-900/40 text-emerald-600 dark:text-emerald-400">
                    <flux:icon name="calendar" class="size-5" />
                </div>
                <div>
                    <flux:text size="sm" class="text-emerald-700 dark:text-emerald-300">{{ __('Nächstes Event') }}</flux:text>
                    <a href="{{ route('events.show', $this->nextUpcomingEvent) }}" wire:navigate class="font-medium text-emerald-800 dark:text-emerald-200 hover:underline">
                        {{ $this->nextUpcomingEvent->name }}
                    </a>
                    <flux:text size="sm" class="text-emerald-600 dark:text-emerald-400">{{ $this->nextUpcomingEvent->starts_at->format('d.m.Y H:i') }}</flux:text>
                </div>
            </div>
            <flux:button variant="subtle" size="sm" :href="route('events.show', $this->nextUpcomingEvent)" wire:navigate>
                {{ __('Öffnen') }}
            </flux:button>
        </div>
    @endif

    {{-- Smart reminders --}}
    @if (count($this->reminders) > 0)
        <div class="mb-6 space-y-2">
            @foreach ($this->reminders as $reminder)
                <div class="rounded-xl border {{ $reminder['type'] === 'warning' ? 'border-amber-200 dark:border-amber-800 bg-amber-50 dark:bg-amber-900/20' : 'border-sky-200 dark:border-sky-800 bg-sky-50 dark:bg-sky-900/20' }} p-3 flex items-center gap-3">
                    <flux:icon name="{{ $reminder['type'] === 'warning' ? 'exclamation-triangle' : 'information-circle' }}"
                        class="size-5 shrink-0 {{ $reminder['type'] === 'warning' ? 'text-amber-600' : 'text-sky-600' }}" />
                    <flux:text size="sm" class="{{ $reminder['type'] === 'warning' ? 'text-amber-800 dark:text-amber-200' : 'text-sky-800 dark:text-sky-200' }}">
                        {{ $reminder['message'] }}
                    </flux:text>
                    @if ($reminder['link'])
                        <a href="{{ $reminder['link'] }}" wire:navigate class="ml-auto text-sm font-medium {{ $reminder['type'] === 'warning' ? 'text-amber-700 dark:text-amber-300' : 'text-sky-700 dark:text-sky-300' }} hover:underline">
                            {{ __('Anzeigen') }}
                        </a>
                    @endif
                </div>
            @endforeach
        </div>
    @endif

    {{-- Global volunteer search --}}
    <div class="mb-6">
        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Helfer:in suchen (Name oder E-Mail)...')" clearable />
    </div>

    {{-- Search results --}}
    @if (strlen($search) >= 2)
        <div class="mb-6">
            @if ($this->searchResults->isEmpty())
                <flux:text class="text-zinc-500">{{ __('Keine Ergebnisse für ":query"', ['query' => $search]) }}</flux:text>
            @else
                <div class="space-y-2">
                    @foreach ($this->searchResults as $volunteer)
                        <div class="flex items-center justify-between rounded-xl border border-zinc-200 dark:border-zinc-700 p-3">
                            <div>
                                <flux:text class="font-medium">{{ $volunteer->full_name }}</flux:text>
                                <flux:text size="sm" class="text-zinc-500">{{ $volunteer->email }} &middot; {{ $volunteer->project?->name }}</flux:text>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    @endif

    {{-- Project tiles --}}
    @if ($this->projects->isNotEmpty())
        <flux:heading size="lg" class="mb-4">{{ __('Projekte') }}</flux:heading>
        <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3 mb-8">
            @foreach ($this->projects as $project)
                <a href="{{ route('projects.show', $project) }}" wire:navigate wire:key="project-{{ $project->id }}"
                   class="block rounded-xl border border-zinc-200 dark:border-zinc-700 p-5 transition-all duration-200 hover:shadow-md hover:border-emerald-300 dark:hover:border-emerald-700">
                    <flux:heading size="sm" class="mb-3">{{ $project->name }}</flux:heading>
                    <div class="grid grid-cols-2 gap-3 text-sm">
                        <div>
                            <flux:text size="sm" class="text-zinc-500">{{ __('Events') }}</flux:text>
                            <flux:text class="font-semibold">{{ $project->events_count }}</flux:text>
                        </div>
                        <div>
                            <flux:text size="sm" class="text-zinc-500">{{ __('Helfer:innen') }}</flux:text>
                            <flux:text class="font-semibold">{{ $project->volunteers_count }}</flux:text>
                        </div>
                    </div>
                    {{-- Quick action row --}}
                    <div class="flex gap-2 mt-3 pt-3 border-t border-zinc-100 dark:border-zinc-700">
                        <flux:badge size="sm" color="emerald" icon="calendar">{{ __('Events') }}</flux:badge>
                        @if ($project->website_published)
                            <flux:badge size="sm" color="sky" icon="globe-alt">{{ __('Website') }}</flux:badge>
                        @endif
                    </div>
                </a>
            @endforeach
        </div>
    @else
        <div class="mb-8 rounded-xl border-2 border-dashed border-zinc-300 dark:border-zinc-600 p-12 text-center">
            <flux:heading size="sm">{{ __('Keine Projekte') }}</flux:heading>
            <flux:text class="mt-2">{{ __('Erstelle ein Projekt, um loszulegen.') }}</flux:text>
        </div>
    @endif

    {{-- Analytics --}}
    @if ($this->organization)
        <div class="grid gap-6 md:grid-cols-2 mb-8">
            <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-6">
                <div class="flex items-start gap-4">
                    <div class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-red-100 dark:bg-red-900/40 text-red-600 dark:text-red-400">
                        <flux:icon name="x-circle" class="size-5" />
                    </div>
                    <div>
                        <flux:text size="sm" class="!text-zinc-500 dark:!text-zinc-400">{{ __('No-Show Rate') }}</flux:text>
                        <flux:heading size="xl">{{ $this->noShowRate }}%</flux:heading>
                    </div>
                </div>
            </div>

            <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-6">
                <flux:text size="sm" class="!text-zinc-500 dark:!text-zinc-400 mb-3">{{ __('Anwesenheit') }}</flux:text>
                @php $summary = $this->attendanceSummary; @endphp
                <div class="grid grid-cols-2 gap-2 text-center sm:grid-cols-4">
                    <div>
                        <flux:heading size="lg" class="!text-emerald-600 dark:!text-emerald-400">{{ $summary['on_time'] }}</flux:heading>
                        <flux:text size="xs">{{ __('Pünktlich') }}</flux:text>
                    </div>
                    <div>
                        <flux:heading size="lg" class="!text-amber-600 dark:!text-amber-400">{{ $summary['late'] }}</flux:heading>
                        <flux:text size="xs">{{ __('Verspätet') }}</flux:text>
                    </div>
                    <div>
                        <flux:heading size="lg" class="!text-red-600 dark:!text-red-400">{{ $summary['no_show'] }}</flux:heading>
                        <flux:text size="xs">{{ __('Nicht erschienen') }}</flux:text>
                    </div>
                    <div>
                        <flux:heading size="lg" class="!text-zinc-500 dark:!text-zinc-400">{{ $summary['unmarked'] }}</flux:heading>
                        <flux:text size="xs">{{ __('Offen') }}</flux:text>
                    </div>
                </div>
            </div>
        </div>

        {{-- Recent past events --}}
        @if ($this->recentPastEvents->isNotEmpty())
            <div class="mb-8">
                <flux:heading size="lg" class="mb-4">{{ __('Vergangene Events') }}</flux:heading>
                <div class="overflow-x-auto">
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>{{ __('Name') }}</flux:table.column>
                        <flux:table.column>{{ __('Datum') }}</flux:table.column>
                        <flux:table.column>{{ __('Helfer:innen') }}</flux:table.column>
                        <flux:table.column>{{ __('Ankunft %') }}</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($this->recentPastEvents as $pastEvent)
                            <flux:table.row :key="'past-'.$pastEvent->id">
                                <flux:table.cell>
                                    <a href="{{ route('events.show', $pastEvent) }}" wire:navigate class="font-medium text-emerald-600 dark:text-emerald-400 hover:underline">
                                        {{ $pastEvent->name }}
                                    </a>
                                </flux:table.cell>
                                <flux:table.cell>{{ $pastEvent->ends_at->format('d.m.Y') }}</flux:table.cell>
                                <flux:table.cell>{{ $pastEvent->volunteer_count }}</flux:table.cell>
                                <flux:table.cell>
                                    @if ($pastEvent->volunteer_count > 0)
                                        {{ round(($pastEvent->event_arrivals_count / $pastEvent->volunteer_count) * 100) }}%
                                    @else
                                        —
                                    @endif
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
                </div>
            </div>
        @endif
    @endif
</div>
