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
                <flux:menu.item
                    wire:click="switchOrganization({{ $org->id }})"
                    :icon="$org->id === $this->activeOrganization->id ? 'check' : null"
                >
                    {{ $org->name }}
                </flux:menu.item>
            @endforeach
            <flux:menu.separator />
            <flux:menu.item icon="plus" wire:click="$set('showCreateModal', true)">
                {{ __('Create new organization') }}
            </flux:menu.item>
        </flux:menu>
    </flux:dropdown>

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
</div>
