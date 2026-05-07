<div
    class="mx-auto max-w-7xl p-6"
    x-data
    x-on:guest-entry-edit-opened.window="$nextTick(() => document.getElementById($event.detail.inputId)?.focus())"
    x-on:guest-list-feedback.window="$nextTick(() => document.getElementById('guest-list-feedback')?.focus())"
>
    <div class="flex items-center justify-between mb-6">
        <div class="flex items-center gap-3">
            <flux:button variant="ghost" icon="arrow-left" :href="route('guest-lists.index', ['projectId' => $projectId])" wire:navigate aria-label="{{ __('Back to guest lists') }}" />
            <flux:heading size="xl">{{ $this->guestList->name }}</flux:heading>
            <flux:badge size="sm" :color="$this->guestList->status === \App\Enums\GuestListStatus::Confirmed ? 'emerald' : 'zinc'">
                {{ $this->guestList->status->label() }}
            </flux:badge>
        </div>
        <div class="flex gap-2">
            @if ($this->guestList->isDraft())
                <flux:button variant="primary" wire:click="confirmGuestList" wire:confirm="{{ __('Activate sending for this guest list? QR codes will be generated for all entries, and new guests with an email address will keep receiving invitations automatically.') }}">
                    {{ __('Activate Sending') }}
                </flux:button>
            @endif
            @if ($this->guestList->isConfirmed() && $this->pendingInvitationCount > 0)
                <flux:button variant="primary" wire:click="sendPendingInvitations"
                    wire:confirm="{{ __('Send invitations to all guests with an email address?') }}"
                    wire:loading.attr="disabled">
                    {{ __('Send Pending Invitations') }} ({{ $this->pendingInvitationCount }})
                </flux:button>
            @endif
            <flux:button variant="ghost" icon="pencil" wire:click="openEditModal">
                {{ __('Edit') }}
            </flux:button>
        </div>
    </div>

    <x-projects.layout :project="$project">

    @if (session('message'))
        <flux:callout id="guest-list-feedback" variant="success" class="mb-4" tabindex="-1" role="status" aria-live="polite" aria-atomic="true">{{ session('message') }}</flux:callout>
    @endif

    @if (session('error'))
        <flux:callout id="guest-list-feedback" variant="danger" class="mb-4" tabindex="-1" role="alert" aria-live="assertive" aria-atomic="true">{{ session('error') }}</flux:callout>
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
                            <flux:button size="sm" variant="ghost" icon="trash" wire:click="removeGroup({{ $group->id }})" wire:confirm="{{ __('Delete this group and all its entries?') }}" title="{{ __('Delete group') }}" aria-label="{{ __('Delete group :label', ['label' => $group->label]) }}" />
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
                                        <th scope="col" class="py-2 pr-4 font-medium text-zinc-500">{{ __('Invitation') }}</th>
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
                                                    <flux:label for="entry-name-{{ $entry->id }}" class="sr-only">{{ __('Guest name') }}</flux:label>
                                                    <flux:input id="entry-name-{{ $entry->id }}" wire:model="entryName" size="sm" placeholder="{{ __('Name') }}" />
                                                    <flux:error name="entryName" />
                                                </td>
                                                <td class="py-2 pr-4">
                                                    <flux:label for="entry-email-{{ $entry->id }}" class="sr-only">{{ __('Guest email') }}</flux:label>
                                                    <flux:input id="entry-email-{{ $entry->id }}" wire:model="entryEmail" size="sm" type="email" placeholder="{{ __('Email') }}" @if ($entry->isInvitationFailed()) aria-describedby="entry-email-recovery-{{ $entry->id }}" @endif />
                                                    <flux:error name="entryEmail" />
                                                    @if ($entry->isInvitationFailed())
                                                        <p id="entry-email-recovery-{{ $entry->id }}" class="sr-only">{{ __('Saving a new email will resend this failed recipient group.') }}</p>
                                                    @endif
                                                </td>
                                                <td class="py-2 pr-4">&mdash;</td>
                                                <td class="py-2 pr-4">&mdash;</td>
                                                <td class="py-2 pr-4">
                                                    @if ($entry->isInvitationFailed())
                                                        <p class="text-xs text-red-600 dark:text-red-400">
                                                            {{ __('Saving a new email will resend this recipient group.') }}
                                                        </p>
                                                    @else
                                                        &mdash;
                                                    @endif
                                                </td>
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
                                                    @php($invitationStatus = $entry->invitationStatus())

                                                    @if ($invitationStatus === 'sent')
                                                        <flux:badge size="sm" color="emerald">{{ __('Sent') }}</flux:badge>
                                                    @elseif ($invitationStatus === 'queued')
                                                        <flux:badge size="sm" color="sky">{{ __('Queued') }}</flux:badge>
                                                    @elseif ($invitationStatus === 'failed')
                                                        <div class="space-y-1">
                                                            <flux:badge size="sm" color="red">{{ __('Failed') }}</flux:badge>
                                                            <p class="text-xs text-red-600 dark:text-red-400">{{ __('Fix the email or resend this recipient group.') }}</p>
                                                        </div>
                                                    @elseif ($invitationStatus === 'pending')
                                                        <flux:badge size="sm" color="amber">{{ __('Pending') }}</flux:badge>
                                                    @else
                                                        <flux:badge size="sm" color="zinc">{{ __('No email') }}</flux:badge>
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
                                                        @if ($entry->isInvitationFailed())
                                                            <flux:button size="sm" variant="ghost" wire:click="resendFailedInvitation({{ $entry->id }})" aria-label="{{ __('Resend failed invitation for :email', ['email' => $entry->email]) }}">
                                                                {{ __('Resend') }}
                                                            </flux:button>
                                                        @endif
                                                        <flux:button size="sm" variant="ghost" icon="pencil" wire:click="startEditEntry({{ $entry->id }})" title="{{ __('Edit') }}" aria-label="{{ __('Edit guest entry :number', ['number' => $entry->number]) }}" />
                                                        <flux:button size="sm" variant="ghost" icon="trash" wire:click="removeEntry({{ $entry->id }})" wire:confirm="{{ __('Remove this entry?') }}" title="{{ __('Remove') }}" aria-label="{{ __('Remove guest entry :number', ['number' => $entry->number]) }}" />
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

    </x-projects.layout>

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
