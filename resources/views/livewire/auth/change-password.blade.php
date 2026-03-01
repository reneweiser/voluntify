<x-layouts::auth :title="__('Change password')">
    <div class="flex flex-col gap-6">
        <x-auth-header
            :title="__('Change your password')"
            :description="__('You must set a new password before continuing.')"
        />

        <form wire:submit="changePassword" class="flex flex-col gap-6">
            <flux:input
                wire:model="password"
                :label="__('New password')"
                type="password"
                required
                autocomplete="new-password"
                viewable
            />

            <flux:input
                wire:model="password_confirmation"
                :label="__('Confirm password')"
                type="password"
                required
                autocomplete="new-password"
                viewable
            />

            <flux:button variant="primary" type="submit" class="w-full">
                {{ __('Change password') }}
            </flux:button>
        </form>
    </div>
</x-layouts::auth>
