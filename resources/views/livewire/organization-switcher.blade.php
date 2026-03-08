<div>
    <flux:dropdown position="bottom" align="start">
        <flux:sidebar.profile
            :name="$this->activeOrganization->name"
            icon:trailing="chevrons-up-down"
        >
            <x-slot name="avatar">
                <div class="flex aspect-square size-8 items-center justify-center rounded-md bg-emerald-600 dark:bg-emerald-500 text-white shadow-sm">
                    <x-app-logo-icon class="size-5 fill-current text-white" />
                </div>
            </x-slot>
        </flux:sidebar.profile>

        <flux:menu>
            @foreach ($this->organizations as $org)
                <div class="flex items-center group" wire:key="org-{{ $org->id }}">
                    <flux:menu.item
                        wire:click="switchOrganization({{ $org->id }})"
                        :icon="$org->id === $this->activeOrganization->id ? 'check' : null"
                        class="flex-1"
                    >
                        {{ $org->name }}
                    </flux:menu.item>
                    @if ($org->id !== auth()->user()->personal_organization_id)
                        <button
                            wire:click.stop="confirmLeaveOrganization({{ $org->id }})"
                            class="shrink-0 p-1 mr-2 text-zinc-400 hover:text-red-500 opacity-0 group-hover:opacity-100 transition-opacity"
                            title="{{ __('Leave organization') }}"
                        >
                            <flux:icon.arrow-right-start-on-rectangle variant="micro" />
                        </button>
                    @endif
                </div>
            @endforeach
            <flux:menu.separator />
            <flux:menu.item icon="plus" wire:click="$set('showCreateModal', true)">
                {{ __('Create new organization') }}
            </flux:menu.item>
        </flux:menu>
    </flux:dropdown>

    {{-- Create organization modal --}}
    <flux:modal wire:model="showCreateModal" class="md:w-96">
        <form wire:submit="createOrganization" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Create organization') }}</flux:heading>
                <flux:text class="mt-2">{{ __('Set up a new organization to manage events and volunteers.') }}</flux:text>
            </div>
            <flux:input wire:model.live.debounce.300ms="newOrgName" :label="__('Name')" />
            <div>
                <flux:input wire:model="newOrgSlug" :label="__('Slug')" />
                <flux:text size="sm" class="mt-1">{{ __('URL-friendly identifier. Auto-generated from name.') }}</flux:text>
            </div>
            <div class="flex justify-end space-x-2 rtl:space-x-reverse">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="primary" type="submit">{{ __('Create') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Leave organization modal --}}
    <flux:modal wire:model="showLeaveModal" focusable class="max-w-lg">
        @if ($this->organizationToLeave)
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">{{ __('Leave organization') }}</flux:heading>
                    <flux:subheading class="mt-2">
                        {{ __('Are you sure you want to leave :name? You will lose access to all events and data in this organization.', ['name' => $this->organizationToLeave->name]) }}
                    </flux:subheading>
                </div>

                @error('leave')
                    <flux:text class="text-red-600 dark:text-red-400">{{ $message }}</flux:text>
                @enderror

                <div class="flex justify-end space-x-2 rtl:space-x-reverse">
                    <flux:modal.close>
                        <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
                    </flux:modal.close>
                    <flux:button variant="danger" wire:click="leaveOrganization">{{ __('Leave') }}</flux:button>
                </div>
            </div>
        @endif
    </flux:modal>
</div>
