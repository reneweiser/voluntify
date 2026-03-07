<div class="mx-auto max-w-7xl p-6">
    {{-- Welcome banner --}}
    <div class="mb-8 rounded-xl bg-gradient-to-r from-emerald-600 to-emerald-500 dark:from-emerald-700 dark:to-emerald-600 p-6 text-white shadow-sm">
        <flux:heading size="xl" class="!text-white">
            {{ __('Welcome back, :name!', ['name' => auth()->user()->name]) }}
        </flux:heading>

        @if ($this->organization)
            <div class="mt-2 flex items-center gap-2">
                <span class="text-emerald-100">{{ $this->organization->name }}</span>
                <flux:badge size="sm" class="!bg-white/20 !text-white !border-0">{{ __(ucfirst(str_replace('_', ' ', $this->userRole ?? ''))) }}</flux:badge>
            </div>
        @endif
    </div>

    {{-- Metric cards --}}
    <div class="grid gap-6 md:grid-cols-3 mb-8">
        <a href="{{ route('events.index') }}" wire:navigate
           class="group relative overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-700 p-6 card-accent-emerald card-gradient-emerald transition-all duration-200 hover:shadow-md">
            <div class="flex items-start gap-4">
                <div class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-emerald-100 dark:bg-emerald-900/40 text-emerald-600 dark:text-emerald-400">
                    <flux:icon name="calendar" class="size-5" />
                </div>
                <div>
                    <flux:text size="sm" class="!text-zinc-500 dark:!text-zinc-400">{{ __('Upcoming Events') }}</flux:text>
                    <flux:heading size="xl">{{ $this->upcomingEventsCount }}</flux:heading>
                </div>
            </div>
        </a>

        <div class="relative overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-700 p-6 card-accent-amber card-gradient-amber">
            <div class="flex items-start gap-4">
                <div class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-amber-100 dark:bg-amber-900/40 text-amber-600 dark:text-amber-400">
                    <flux:icon name="user-group" class="size-5" />
                </div>
                <div>
                    <flux:text size="sm" class="!text-zinc-500 dark:!text-zinc-400">{{ __('Total Volunteers') }}</flux:text>
                    <flux:heading size="xl">{{ $this->totalVolunteersCount }}</flux:heading>
                </div>
            </div>
        </div>

        <div class="relative overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-700 p-6 card-accent-sky card-gradient-sky">
            <div class="flex items-start gap-4">
                <div class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-sky-100 dark:bg-sky-900/40 text-sky-600 dark:text-sky-400">
                    <flux:icon name="exclamation-triangle" class="size-5" />
                </div>
                <div>
                    <flux:text size="sm" class="!text-zinc-500 dark:!text-zinc-400">{{ __('Shifts Needing Volunteers') }}</flux:text>
                    <flux:heading size="xl">{{ $this->shiftsNeedingAttention }}</flux:heading>
                </div>
            </div>
        </div>
    </div>

    {{-- Analytics cards --}}
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
                <flux:text size="sm" class="!text-zinc-500 dark:!text-zinc-400 mb-3">{{ __('Attendance Breakdown') }}</flux:text>
                @php $summary = $this->attendanceSummary; @endphp
                <div class="grid grid-cols-2 gap-2 text-center sm:grid-cols-4">
                    <div>
                        <flux:heading size="lg" class="!text-emerald-600 dark:!text-emerald-400">{{ $summary['on_time'] }}</flux:heading>
                        <flux:text size="xs">{{ __('On Time') }}</flux:text>
                    </div>
                    <div>
                        <flux:heading size="lg" class="!text-amber-600 dark:!text-amber-400">{{ $summary['late'] }}</flux:heading>
                        <flux:text size="xs">{{ __('Late') }}</flux:text>
                    </div>
                    <div>
                        <flux:heading size="lg" class="!text-red-600 dark:!text-red-400">{{ $summary['no_show'] }}</flux:heading>
                        <flux:text size="xs">{{ __('No Show') }}</flux:text>
                    </div>
                    <div>
                        <flux:heading size="lg" class="!text-zinc-500 dark:!text-zinc-400">{{ $summary['unmarked'] }}</flux:heading>
                        <flux:text size="xs">{{ __('Unmarked') }}</flux:text>
                    </div>
                </div>
            </div>
        </div>

        {{-- Recent past events --}}
        @if ($this->recentPastEvents->isNotEmpty())
            <div class="mb-8">
                <flux:heading size="lg" class="mb-4">{{ __('Recent Past Events') }}</flux:heading>
                <div class="overflow-x-auto">
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>{{ __('Name') }}</flux:table.column>
                        <flux:table.column>{{ __('Date') }}</flux:table.column>
                        <flux:table.column>{{ __('Volunteers') }}</flux:table.column>
                        <flux:table.column>{{ __('Arrival %') }}</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($this->recentPastEvents as $pastEvent)
                            <flux:table.row :key="'past-'.$pastEvent->id">
                                <flux:table.cell>
                                    <a href="{{ route('events.show', $pastEvent) }}" wire:navigate class="font-medium text-emerald-600 dark:text-emerald-400 hover:underline">
                                        {{ $pastEvent->name }}
                                    </a>
                                </flux:table.cell>
                                <flux:table.cell>{{ $pastEvent->ends_at->format('M d, Y') }}</flux:table.cell>
                                <flux:table.cell>{{ $pastEvent->volunteers_count }}</flux:table.cell>
                                <flux:table.cell>
                                    @if ($pastEvent->volunteers_count > 0)
                                        {{ round(($pastEvent->event_arrivals_count / $pastEvent->volunteers_count) * 100) }}%
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

    {{-- Upcoming events table --}}
    <div class="flex items-center justify-between mb-4">
        <flux:heading size="lg">{{ __('Upcoming Events') }}</flux:heading>
        @if ($this->canCreateEvents)
            <flux:button variant="primary" icon="plus" :href="route('events.index')" wire:navigate>
                {{ __('Create Event') }}
            </flux:button>
        @endif
    </div>

    @if ($this->upcomingEvents->isNotEmpty())
        <div class="overflow-x-auto">
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Name') }}</flux:table.column>
                <flux:table.column>{{ __('Date') }}</flux:table.column>
                <flux:table.column>{{ __('Volunteers') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @foreach ($this->upcomingEvents as $event)
                    <flux:table.row :key="'event-'.$event->id">
                        <flux:table.cell>
                            <a href="{{ route('events.show', $event) }}" wire:navigate class="font-medium text-emerald-600 dark:text-emerald-400 hover:underline">
                                {{ $event->name }}
                            </a>
                        </flux:table.cell>
                        <flux:table.cell>{{ $event->starts_at->format('M d, Y g:i A') }}</flux:table.cell>
                        <flux:table.cell>{{ $event->volunteers_count }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" color="emerald">{{ __('Published') }}</flux:badge>
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
        </div>
    @else
        <div class="rounded-xl border-2 border-dashed border-zinc-300 dark:border-zinc-600 p-12 text-center">
            <div class="mx-auto flex size-16 items-center justify-center rounded-full bg-zinc-100 dark:bg-zinc-800">
                <flux:icon name="calendar" class="size-8 text-zinc-400" />
            </div>
            @if ($this->canCreateEvents)
                <flux:heading size="sm" class="mt-4">{{ __('No upcoming events') }}</flux:heading>
                <flux:text class="mt-2">{{ __('Create your first event to get started!') }}</flux:text>
                <div class="mt-4">
                    <flux:button variant="primary" icon="plus" :href="route('events.index')" wire:navigate>
                        {{ __('Create Event') }}
                    </flux:button>
                </div>
            @else
                <flux:heading size="sm" class="mt-4">{{ __('No upcoming events') }}</flux:heading>
                <flux:text class="mt-2">{{ __('No upcoming events to display.') }}</flux:text>
            @endif
        </div>
    @endif
</div>
