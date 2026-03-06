<div class="mx-auto max-w-7xl p-6">
    <div class="flex items-center gap-3 mb-4">
        <flux:button variant="ghost" icon="arrow-left" :href="route('events.index')" wire:navigate />
        <flux:heading size="xl">{{ $event->name }}</flux:heading>
    </div>

    <x-events.tab-nav :event="$event" />

    <div class="flex items-center justify-between mb-6">
        <flux:heading size="lg">{{ __('Volunteers') }}</flux:heading>
    </div>

    <div class="mb-6 max-w-sm">
        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="{{ __('Search volunteers...') }}" />
    </div>

    @if ($this->volunteers->isEmpty())
        <div class="rounded-xl border-2 border-dashed border-zinc-300 dark:border-zinc-600 p-12 text-center">
            <div class="mx-auto flex size-16 items-center justify-center rounded-full bg-zinc-100 dark:bg-zinc-800">
                <flux:icon name="user-group" class="size-8 text-zinc-400" />
            </div>
            @if ($search)
                <flux:heading size="sm" class="mt-4">{{ __('No results') }}</flux:heading>
                <flux:text class="mt-2">{{ __('No volunteers match your search.') }}</flux:text>
            @else
                <flux:heading size="sm" class="mt-4">{{ __('No volunteers yet') }}</flux:heading>
                <flux:text class="mt-2">{{ __('No volunteers have signed up yet.') }}</flux:text>
            @endif
        </div>
    @else
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Name') }}</flux:table.column>
                <flux:table.column>{{ __('Email') }}</flux:table.column>
                <flux:table.column>{{ __('Shifts') }}</flux:table.column>
                <flux:table.column>{{ __('Arrived') }}</flux:table.column>
                <flux:table.column>{{ __('Attendance') }}</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @foreach ($this->volunteers as $volunteer)
                    <flux:table.row :key="'volunteer-'.$volunteer->id">
                        <flux:table.cell>
                            <a href="{{ route('events.volunteers.show', [$event, $volunteer]) }}" wire:navigate class="font-medium text-emerald-600 dark:text-emerald-400 hover:underline">
                                {{ $volunteer->name }}
                            </a>
                        </flux:table.cell>
                        <flux:table.cell>{{ $volunteer->email }}</flux:table.cell>
                        <flux:table.cell>{{ $volunteer->shiftSignups->count() }}</flux:table.cell>
                        <flux:table.cell>
                            @if ($volunteer->eventArrivals->isNotEmpty())
                                <flux:badge size="sm" color="emerald">{{ __('Yes') }}</flux:badge>
                            @else
                                <flux:badge size="sm" color="zinc">{{ __('No') }}</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            @php
                                $total = $volunteer->shiftSignups->count();
                                $marked = $volunteer->shiftSignups->filter(fn ($s) => $s->attendanceRecord)->count();
                            @endphp
                            @if ($total === 0)
                                <flux:badge size="sm" color="zinc">{{ __('None') }}</flux:badge>
                            @elseif ($marked === $total)
                                <flux:badge size="sm" color="emerald">{{ $marked }}/{{ $total }}</flux:badge>
                            @elseif ($marked > 0)
                                <flux:badge size="sm" color="amber">{{ $marked }}/{{ $total }}</flux:badge>
                            @else
                                <flux:badge size="sm" color="zinc">{{ __('0/:total', ['total' => $total]) }}</flux:badge>
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>

        <div class="mt-4">
            {{ $this->volunteers->links() }}
        </div>
    @endif
</div>
