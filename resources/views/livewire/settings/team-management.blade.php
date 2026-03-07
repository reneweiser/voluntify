<section class="w-full">
    @include('partials.settings-heading')

    <x-settings.layout :heading="__('Team management')" :subheading="__('Manage your organization\'s team members and roles')">
        {{-- Invite member --}}
        <div class="mb-8">
            <flux:heading size="sm">{{ __('Invite new member') }}</flux:heading>

            <form wire:submit="inviteMember" class="mt-4 space-y-4">
                <flux:input wire:model="inviteName" :label="__('Name')" required />
                <flux:input wire:model="inviteEmail" :label="__('Email')" type="email" required />
                <flux:select wire:model="inviteRole" :label="__('Role')">
                    <flux:select.option value="organizer">{{ __('Organizer') }}</flux:select.option>
                    <flux:select.option value="volunteer_admin">{{ __('Volunteer Admin') }}</flux:select.option>
                    <flux:select.option value="entrance_staff">{{ __('Entrance Staff') }}</flux:select.option>
                </flux:select>

                <flux:button variant="primary" type="submit">{{ __('Send invitation') }}</flux:button>

                <x-action-message on="member-invited">
                    {{ __('Invitation sent.') }}
                </x-action-message>
            </form>
        </div>

        <flux:separator />

        {{-- Members list --}}
        <div class="mt-8">
            <flux:heading size="sm">{{ __('Current members') }}</flux:heading>

            <div class="mt-4 divide-y divide-zinc-200 dark:divide-zinc-700">
                @foreach ($this->members as $member)
                    <div class="flex items-center justify-between gap-4 py-3" wire:key="member-{{ $member->id }}">
                        <div class="flex items-center gap-3 min-w-0">
                            <flux:avatar size="sm" name="{{ $member->name }}" color="auto" />
                            <div class="min-w-0">
                                <flux:heading size="sm" class="truncate">{{ $member->name }}</flux:heading>
                                <flux:text size="sm" class="truncate">{{ $member->email }}</flux:text>
                            </div>
                        </div>

                        <div class="flex items-center gap-2 shrink-0">
                            @if ($member->id === auth()->id())
                                <flux:badge size="sm">{{ __($member->pivot->role->value) }}</flux:badge>
                            @else
                                <flux:select
                                    size="sm"
                                    wire:model.live="memberRoles.{{ $member->id }}"
                                >
                                    <flux:select.option value="organizer">{{ __('Organizer') }}</flux:select.option>
                                    <flux:select.option value="volunteer_admin">{{ __('Volunteer Admin') }}</flux:select.option>
                                    <flux:select.option value="entrance_staff">{{ __('Entrance Staff') }}</flux:select.option>
                                </flux:select>

                                <flux:button
                                    variant="danger"
                                    size="sm"
                                    icon="trash"
                                    wire:click="removeMember({{ $member->id }})"
                                    wire:confirm="{{ __('Are you sure you want to remove this member?') }}"
                                />
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <flux:separator class="mt-8" />

        {{-- AI API Key --}}
        <div class="mt-8">
            <flux:heading size="sm">{{ __('AI API key') }}</flux:heading>
            <flux:text size="sm" class="mt-1">{{ __('Used for AI-powered event creation features.') }}</flux:text>

            @if ($this->hasAiApiKey)
                <div class="mt-4 flex items-center gap-4">
                    <flux:input :value="$this->maskedAiApiKey" disabled class="flex-1" />
                    <flux:button
                        variant="danger"
                        size="sm"
                        wire:click="removeAiApiKey"
                        wire:confirm="{{ __('Are you sure you want to remove the API key?') }}"
                    >
                        {{ __('Remove') }}
                    </flux:button>
                </div>
            @else
                <form wire:submit="saveAiApiKey" class="mt-4 space-y-4">
                    <flux:input wire:model="aiApiKey" :label="__('API key')" type="password" required />
                    <flux:button variant="primary" type="submit">{{ __('Save API key') }}</flux:button>
                </form>
            @endif

            <x-action-message on="ai-key-saved">{{ __('API key saved.') }}</x-action-message>
            <x-action-message on="ai-key-removed">{{ __('API key removed.') }}</x-action-message>
        </div>
    </x-settings.layout>
</section>
