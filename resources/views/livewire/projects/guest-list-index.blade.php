<div class="mx-auto max-w-7xl p-6">
    <div class="flex items-center gap-3 mb-6">
        <flux:button variant="ghost" icon="arrow-left" :href="route('projects.show', $project)" wire:navigate aria-label="{{ __('Back to project') }}" />
        <flux:heading size="xl">{{ __('Guest Lists') }} &mdash; {{ $project->name }}</flux:heading>
        <div class="ml-auto">
            <flux:button variant="primary" icon="plus" wire:click="$set('showCreateModal', true)">
                {{ __('New Guest List') }}
            </flux:button>
        </div>
    </div>

    @if (session('message'))
        <flux:callout variant="success" class="mb-4">{{ session('message') }}</flux:callout>
    @endif

    {{-- Guest list cards --}}
    @if ($this->guestLists->isEmpty())
        <flux:card>
            <flux:text class="text-center text-zinc-500 dark:text-zinc-400">
                {{ __('No guest lists yet. Create one to get started.') }}
            </flux:text>
        </flux:card>
    @else
        <div class="space-y-4">
            @foreach ($this->guestLists as $guestList)
                <flux:card wire:key="guest-list-{{ $guestList->id }}">
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2 mb-1">
                                <a href="{{ route('guest-lists.show', ['projectId' => $projectId, 'guestListId' => $guestList->id]) }}" wire:navigate class="hover:underline">
                                    <flux:heading size="lg">{{ $guestList->name }}</flux:heading>
                                </a>
                                <flux:badge size="sm" :color="$guestList->status === \App\Enums\GuestListStatus::Confirmed ? 'emerald' : 'zinc'">
                                    {{ $guestList->status->label() }}
                                </flux:badge>
                            </div>
                            <flux:text size="sm" class="text-zinc-500">
                                {{ __('Scanner:') }} {{ $guestList->scanner?->name ?? __('None') }}
                            </flux:text>
                            <div class="flex gap-4 mt-2">
                                <flux:text size="sm">{{ __(':count groups', ['count' => $guestList->groups->count()]) }}</flux:text>
                                <flux:text size="sm">{{ __(':count entries', ['count' => $guestList->total_entries]) }}</flux:text>
                                <flux:text size="sm">{{ __(':count checked in', ['count' => $guestList->checked_in_entries]) }}</flux:text>
                            </div>
                        </div>

                        <div class="flex gap-2">
                            <flux:button size="sm" variant="ghost" icon="eye" :href="route('guest-lists.show', ['projectId' => $projectId, 'guestListId' => $guestList->id])" wire:navigate title="{{ __('View') }}" />
                            <flux:button size="sm" variant="ghost" icon="trash" wire:click="deleteGuestList({{ $guestList->id }})" wire:confirm="{{ __('Are you sure you want to delete this guest list?') }}" title="{{ __('Delete') }}" />
                        </div>
                    </div>
                </flux:card>
            @endforeach
        </div>
    @endif

    {{-- Create Modal --}}
    <flux:modal wire:model="showCreateModal">
        <flux:heading>{{ __('New Guest List') }}</flux:heading>

        <div class="mt-4 space-y-4">
            <flux:field>
                <flux:label>{{ __('Name') }}</flux:label>
                <flux:input wire:model="name" placeholder="{{ __('e.g. VIP Guest List') }}" />
                <flux:error name="name" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Scanner') }}</flux:label>
                <flux:select wire:model="scannerId">
                    <flux:select.option value="">{{ __('Select a scanner...') }}</flux:select.option>
                    @foreach ($this->entryStaffScanners as $scanner)
                        <flux:select.option :value="$scanner->id">{{ $scanner->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="scannerId" />
            </flux:field>

            @if ($this->projectGearItems->isNotEmpty())
                <flux:field>
                    <flux:label>{{ __('Gear Items') }}</flux:label>
                    <div class="flex flex-col gap-2">
                        @foreach ($this->projectGearItems as $gearItem)
                            <flux:checkbox wire:model="gearItemIds" :value="$gearItem->id" :label="$gearItem->name" />
                        @endforeach
                    </div>
                </flux:field>
            @endif
        </div>

        <div class="mt-6 flex justify-end gap-2">
            <flux:button variant="ghost" wire:click="$set('showCreateModal', false)">{{ __('Cancel') }}</flux:button>
            <flux:button variant="primary" wire:click="createGuestList">{{ __('Create') }}</flux:button>
        </div>
    </flux:modal>
</div>
