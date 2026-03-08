<div class="mx-auto max-w-7xl p-6">
    <div class="flex items-center gap-3 mb-4">
        <flux:button variant="ghost" icon="arrow-left" :href="route('events.index')" wire:navigate />
        <flux:heading size="xl">{{ $event->name }}</flux:heading>
    </div>

    <x-events.layout :event="$event">
        <div class="flex items-center justify-between mb-6">
            <flux:heading size="lg">{{ __('Gear Items') }}</flux:heading>
        </div>

        {{-- Existing gear items --}}
        @if ($this->gearItems->isNotEmpty())
            <div class="space-y-3 mb-8">
                @foreach ($this->gearItems as $item)
                    <div wire:key="gear-item-{{ $item->id }}" class="flex items-center justify-between rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-4">
                        <div>
                            <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $item->name }}</div>
                            <div class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                                @if ($item->requires_size)
                                    {{ __('Sizes:') }} {{ implode(', ', $item->available_sizes ?? []) }}
                                @else
                                    {{ __('No size selection') }}
                                @endif
                            </div>
                        </div>
                        <flux:button variant="danger" size="sm" wire:click="removeItem({{ $item->id }})" wire:confirm="{{ __('Remove this gear item? Volunteer gear records will also be deleted.') }}">
                            {{ __('Remove') }}
                        </flux:button>
                    </div>
                @endforeach
            </div>
        @else
            <div class="rounded-xl border-2 border-dashed border-zinc-300 dark:border-zinc-600 p-12 text-center mb-8">
                <div class="mx-auto flex size-16 items-center justify-center rounded-full bg-zinc-100 dark:bg-zinc-800">
                    <flux:icon name="gift" class="size-8 text-zinc-400" />
                </div>
                <flux:heading size="sm" class="mt-4">{{ __('No gear items yet') }}</flux:heading>
                <flux:text class="mt-2">{{ __('Add gear items that volunteers will receive at this event.') }}</flux:text>
            </div>
        @endif

        {{-- Add new gear item form --}}
        <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-6">
            <flux:heading size="sm" class="mb-4">{{ __('Add Gear Item') }}</flux:heading>

            <form wire:submit="addItem" class="space-y-4">
                <flux:field>
                    <flux:label>{{ __('Item Name') }}</flux:label>
                    <flux:input wire:model="newItemName" placeholder="{{ __('e.g. T-Shirt, Badge, Lanyard') }}" />
                    <flux:error name="newItemName" />
                </flux:field>

                <flux:field>
                    <flux:checkbox wire:model.live="newItemRequiresSize" label="{{ __('Requires size selection') }}" />
                </flux:field>

                @if ($newItemRequiresSize)
                    <flux:field>
                        <flux:label>{{ __('Available Sizes') }}</flux:label>
                        <flux:input wire:model="newItemSizes" placeholder="{{ __('XS, S, M, L, XL, XXL') }}" />
                        <flux:description>{{ __('Comma-separated list of available sizes.') }}</flux:description>
                        <flux:error name="newItemSizes" />
                    </flux:field>
                @endif

                <flux:button type="submit" variant="primary">
                    {{ __('Add Item') }}
                </flux:button>
            </form>
        </div>
    </x-events.layout>
</div>
