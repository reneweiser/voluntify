<div class="mx-auto max-w-7xl p-6">
    <div class="flex items-center gap-3 mb-4">
        <flux:button variant="ghost" icon="arrow-left" :href="route('events.index')" wire:navigate />
        <flux:heading size="xl">{{ $event->name }}</flux:heading>
    </div>

    <x-events.tab-nav :event="$event" />

    <div class="flex items-center justify-between mb-6">
        <flux:heading size="lg">{{ __('Gear Pickup') }}</flux:heading>
    </div>

    @if ($this->gearItems->isEmpty())
        <div class="rounded-xl border-2 border-dashed border-zinc-300 dark:border-zinc-600 p-12 text-center">
            <div class="mx-auto flex size-16 items-center justify-center rounded-full bg-zinc-100 dark:bg-zinc-800">
                <flux:icon name="gift" class="size-8 text-zinc-400" />
            </div>
            <flux:heading size="sm" class="mt-4">{{ __('No gear items configured') }}</flux:heading>
            <flux:text class="mt-2">{{ __('Add gear items in the Gear Setup tab first.') }}</flux:text>
        </div>
    @else
        {{-- Search --}}
        <div class="mb-6">
            <flux:input wire:model.live.debounce.300ms="search" placeholder="{{ __('Search volunteers...') }}" icon="magnifying-glass" />
        </div>

        @if ($this->volunteers->isEmpty())
            <div class="rounded-xl border-2 border-dashed border-zinc-300 dark:border-zinc-600 p-12 text-center">
                <div class="mx-auto flex size-16 items-center justify-center rounded-full bg-zinc-100 dark:bg-zinc-800">
                    <flux:icon name="users" class="size-8 text-zinc-400" />
                </div>
                <flux:heading size="sm" class="mt-4">{{ __('No volunteers found') }}</flux:heading>
                <flux:text class="mt-2">{{ __('No volunteers match your search for this event.') }}</flux:text>
            </div>
        @else
            <div class="overflow-x-auto">
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('Volunteer') }}</flux:table.column>
                    @foreach ($this->gearItems as $gearItem)
                        <flux:table.column>{{ $gearItem->name }}</flux:table.column>
                    @endforeach
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($this->volunteers as $volunteer)
                        <flux:table.row :key="'vol-'.$volunteer->id">
                            <flux:table.cell>
                                <div class="font-medium">{{ $volunteer->name }}</div>
                                <div class="text-xs text-zinc-500">{{ $volunteer->email }}</div>
                            </flux:table.cell>
                            @foreach ($this->gearItems as $gearItem)
                                @php
                                    $gear = $volunteer->volunteerGear->firstWhere('event_gear_item_id', $gearItem->id);
                                @endphp
                                <flux:table.cell>
                                    @if ($gear)
                                        <div class="flex items-center gap-2">
                                            @if ($gear->size)
                                                <flux:badge size="sm" color="zinc">{{ $gear->size }}</flux:badge>
                                            @endif
                                            @if ($gear->picked_up_at)
                                                <flux:button size="xs" variant="primary" wire:click="togglePickup({{ $gear->id }})" title="{{ __('Undo pickup') }}">
                                                    <flux:icon name="check" class="size-4" />
                                                </flux:button>
                                            @else
                                                <flux:button size="xs" variant="ghost" wire:click="togglePickup({{ $gear->id }})" title="{{ __('Mark as picked up') }}">
                                                    <flux:icon name="hand-raised" class="size-4" />
                                                </flux:button>
                                            @endif
                                        </div>
                                    @else
                                        <flux:button size="xs" variant="ghost" wire:click="assignAndPickup({{ $gearItem->id }}, {{ $volunteer->id }})" title="{{ __('Assign & pick up') }}">
                                            <flux:icon name="plus" class="size-4" />
                                        </flux:button>
                                    @endif
                                </flux:table.cell>
                            @endforeach
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
            </div>
        @endif
    @endif
</div>
