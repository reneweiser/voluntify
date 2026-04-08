<div class="mx-auto max-w-7xl p-6">
    <div class="flex items-center gap-3 mb-4">
        <flux:button variant="ghost" icon="arrow-left" :href="route('events.index')" wire:navigate aria-label="{{ __('Back to events') }}" />
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
                            <div class="flex items-center gap-2">
                                <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ $item->name }}</span>
                                @if ($item->type === \App\Enums\GearItemType::Quantity)
                                    <flux:badge size="sm" color="blue">{{ __('Quantity') }}</flux:badge>
                                @endif
                            </div>
                            <div class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                                @if ($item->type === \App\Enums\GearItemType::Quantity)
                                    {{ __(':count per volunteer', ['count' => $item->quantity_per_volunteer]) }}
                                @elseif ($item->requires_size)
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
                    <flux:input wire:model="newItemName" placeholder="{{ __('e.g. T-Shirt, Badge, Drink Tokens') }}" />
                    <flux:error name="newItemName" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Type') }}</flux:label>
                    <flux:select wire:model.live="newItemType">
                        <flux:select.option value="size_selection">{{ __('Size Selection (Typ 1)') }}</flux:select.option>
                        <flux:select.option value="quantity">{{ __('Quantity (Typ 2)') }}</flux:select.option>
                    </flux:select>
                    <flux:error name="newItemType" />
                </flux:field>

                @if ($newItemType === 'quantity')
                    <flux:field>
                        <flux:label>{{ __('Quantity per Volunteer') }}</flux:label>
                        <flux:input wire:model="newItemQuantityPerVolunteer" type="number" min="1" placeholder="{{ __('e.g. 3') }}" />
                        <flux:description>{{ __('How many units each volunteer receives.') }}</flux:description>
                        <flux:error name="newItemQuantityPerVolunteer" />
                    </flux:field>
                @else
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
                @endif

                <flux:button type="submit" variant="primary">
                    {{ __('Add Item') }}
                </flux:button>
            </form>
        </div>
    </x-events.layout>
</div>
