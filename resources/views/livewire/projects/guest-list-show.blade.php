<div class="mx-auto max-w-7xl p-6">
    <div class="flex items-center gap-3 mb-6">
        <flux:button variant="ghost" icon="arrow-left" :href="route('guest-lists.index', ['projectId' => $projectId])" wire:navigate aria-label="{{ __('Back to guest lists') }}" />
        <flux:heading size="xl">{{ $this->guestList->name }}</flux:heading>
        <flux:badge size="sm" :color="$this->guestList->status === \App\Enums\GuestListStatus::Confirmed ? 'emerald' : 'zinc'">
            {{ $this->guestList->status->label() }}
        </flux:badge>
        <div class="ml-auto flex gap-2">
            @if ($this->guestList->isDraft())
                <flux:button variant="primary" wire:click="confirmGuestList" wire:confirm="{{ __('Confirm this guest list? QR codes will be generated for all entries.') }}">
                    {{ __('Confirm') }}
                </flux:button>
            @endif
            <flux:button variant="ghost" icon="pencil" wire:click="openEditModal">
                {{ __('Edit') }}
            </flux:button>
        </div>
    </div>

    @if (session('message'))
        <flux:callout variant="success" class="mb-4">{{ session('message') }}</flux:callout>
    @endif

    @if (session('error'))
        <flux:callout variant="danger" class="mb-4">{{ session('error') }}</flux:callout>
    @endif

    {{-- Summary bar --}}
    <div class="grid grid-cols-3 gap-4 mb-6">
        <flux:card>
            <flux:text size="sm" class="text-zinc-500">{{ __('Groups') }}</flux:text>
            <flux:heading size="lg">{{ $this->guestList->groups->count() }}</flux:heading>
        </flux:card>
        <flux:card>
            <flux:text size="sm" class="text-zinc-500">{{ __('Total Entries') }}</flux:text>
            <flux:heading size="lg">{{ $this->guestList->total_entries }}</flux:heading>
        </flux:card>
        <flux:card>
            <flux:text size="sm" class="text-zinc-500">{{ __('Checked In') }}</flux:text>
            <flux:heading size="lg">{{ $this->guestList->checked_in_entries }}</flux:heading>
        </flux:card>
    </div>

    {{-- Add group form --}}
    <flux:card class="mb-6">
        <flux:heading size="lg" class="mb-3">{{ __('Add Group') }}</flux:heading>
        <div class="flex items-end gap-3">
            <flux:field class="flex-1">
                <flux:label>{{ __('Label') }}</flux:label>
                <flux:input wire:model="newGroupLabel" placeholder="{{ __('e.g. DJ Soundwave') }}" />
                <flux:error name="newGroupLabel" />
            </flux:field>
            <flux:field>
                <flux:label>{{ __('Guest Count') }}</flux:label>
                <flux:input type="number" wire:model="newGroupCount" min="1" max="100" class="w-24" />
                <flux:error name="newGroupCount" />
            </flux:field>
            <flux:button variant="primary" wire:click="addGroup">{{ __('Add') }}</flux:button>
        </div>
    </flux:card>

    {{-- Groups --}}
    @if ($this->guestList->groups->isEmpty())
        <flux:card>
            <flux:text class="text-center text-zinc-500 dark:text-zinc-400">
                {{ __('No groups yet. Add one above.') }}
            </flux:text>
        </flux:card>
    @else
        <div class="space-y-4">
            @foreach ($this->guestList->groups as $group)
                <flux:card wire:key="group-{{ $group->id }}">
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex items-center gap-2">
                            <flux:heading size="lg">{{ $group->label }}</flux:heading>
                            <flux:badge size="sm">
                                {{ $group->entries->where('checked_in_at', '!=', null)->count() }}/{{ $group->entries->count() }} {{ __('checked in') }}
                            </flux:badge>
                        </div>
                        <div class="flex gap-2">
                            <flux:button size="sm" variant="ghost" icon="plus" wire:click="addEntry({{ $group->id }})" title="{{ __('Add entry') }}">
                                {{ __('Add Entry') }}
                            </flux:button>
                            <flux:button size="sm" variant="ghost" icon="trash" wire:click="removeGroup({{ $group->id }})" wire:confirm="{{ __('Delete this group and all its entries?') }}" title="{{ __('Delete group') }}" />
                        </div>
                    </div>

                    @if ($group->entries->isNotEmpty())
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="border-b border-zinc-200 dark:border-zinc-700 text-left">
                                        <th scope="col" class="py-2 pr-4 font-medium text-zinc-500">#</th>
                                        <th scope="col" class="py-2 pr-4 font-medium text-zinc-500">{{ __('Name') }}</th>
                                        <th scope="col" class="py-2 pr-4 font-medium text-zinc-500">{{ __('Email') }}</th>
                                        <th scope="col" class="py-2 pr-4 font-medium text-zinc-500">{{ __('Gear') }}</th>
                                        <th scope="col" class="py-2 pr-4 font-medium text-zinc-500">{{ __('QR') }}</th>
                                        <th scope="col" class="py-2 pr-4 font-medium text-zinc-500">{{ __('Status') }}</th>
                                        <th scope="col" class="sr-only py-2 font-medium text-zinc-500">{{ __('Actions') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($group->entries as $entry)
                                        <tr wire:key="entry-{{ $entry->id }}" class="border-b border-zinc-100 dark:border-zinc-800">
                                            @if ($editingEntryId === $entry->id)
                                                {{-- Inline edit mode --}}
                                                <td class="py-2 pr-4">{{ $entry->number }}</td>
                                                <td class="py-2 pr-4">
                                                    <flux:input wire:model="entryName" size="sm" placeholder="{{ __('Name') }}" />
                                                    <flux:error name="entryName" />
                                                </td>
                                                <td class="py-2 pr-4">
                                                    <flux:input wire:model="entryEmail" size="sm" type="email" placeholder="{{ __('Email') }}" />
                                                    <flux:error name="entryEmail" />
                                                </td>
                                                <td class="py-2 pr-4">&mdash;</td>
                                                <td class="py-2 pr-4">&mdash;</td>
                                                <td class="py-2 pr-4">&mdash;</td>
                                                <td class="py-2 text-right">
                                                    <div class="flex gap-1 justify-end">
                                                        <flux:button size="sm" variant="primary" wire:click="saveEntry">{{ __('Save') }}</flux:button>
                                                        <flux:button size="sm" variant="ghost" wire:click="cancelEditEntry">{{ __('Cancel') }}</flux:button>
                                                    </div>
                                                </td>
                                            @else
                                                {{-- Display mode --}}
                                                <td class="py-2 pr-4 text-zinc-500">{{ $entry->number }}</td>
                                                <td class="py-2 pr-4">{{ $entry->name ?? '—' }}</td>
                                                <td class="py-2 pr-4">{{ $entry->email ?? '—' }}</td>
                                                <td class="py-2 pr-4">
                                                    @if ($entry->gear->isNotEmpty())
                                                        {{ $entry->gear->count() }} {{ __('items') }}
                                                    @else
                                                        —
                                                    @endif
                                                </td>
                                                <td class="py-2 pr-4">
                                                    @if ($entry->qr_token)
                                                        <flux:icon name="qr-code" class="size-4 text-emerald-500" />
                                                    @else
                                                        <flux:icon name="minus" class="size-4 text-zinc-300" />
                                                    @endif
                                                </td>
                                                <td class="py-2 pr-4">
                                                    @if ($entry->isCheckedIn())
                                                        <flux:badge size="sm" color="emerald">{{ __('Checked in') }}</flux:badge>
                                                    @else
                                                        <flux:badge size="sm" color="zinc">{{ __('Pending') }}</flux:badge>
                                                    @endif
                                                </td>
                                                <td class="py-2 text-right">
                                                    <div class="flex gap-1 justify-end">
                                                        <flux:button size="sm" variant="ghost" icon="pencil" wire:click="startEditEntry({{ $entry->id }})" title="{{ __('Edit') }}" />
                                                        <flux:button size="sm" variant="ghost" icon="trash" wire:click="removeEntry({{ $entry->id }})" wire:confirm="{{ __('Remove this entry?') }}" title="{{ __('Remove') }}" />
                                                    </div>
                                                </td>
                                            @endif
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </flux:card>
            @endforeach
        </div>
    @endif

    {{-- Edit Modal --}}
    <flux:modal wire:model="showEditModal">
        <flux:heading>{{ __('Edit Guest List') }}</flux:heading>

        <div class="mt-4 space-y-4">
            <flux:field>
                <flux:label>{{ __('Name') }}</flux:label>
                <flux:input wire:model="editName" />
                <flux:error name="editName" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Scanner') }}</flux:label>
                <flux:select wire:model="editScannerId">
                    <flux:select.option value="">{{ __('Select a scanner...') }}</flux:select.option>
                    @foreach ($this->entryStaffScanners as $scanner)
                        <flux:select.option :value="$scanner->id">{{ $scanner->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="editScannerId" />
            </flux:field>

            @if ($this->projectGearItems->isNotEmpty())
                <flux:field>
                    <flux:label>{{ __('Gear Items') }}</flux:label>
                    <div class="flex flex-col gap-2">
                        @foreach ($this->projectGearItems as $gearItem)
                            <flux:checkbox wire:model="editGearItemIds" :value="$gearItem->id" :label="$gearItem->name" />
                        @endforeach
                    </div>
                </flux:field>
            @endif
        </div>

        <div class="mt-6 flex justify-end gap-2">
            <flux:button variant="ghost" wire:click="$set('showEditModal', false)">{{ __('Cancel') }}</flux:button>
            <flux:button variant="primary" wire:click="updateGuestList">{{ __('Update') }}</flux:button>
        </div>
    </flux:modal>
</div>
