<div class="mx-auto max-w-7xl p-6">
    <div class="flex items-center gap-3 mb-6">
        <flux:button variant="ghost" icon="arrow-left" :href="route('projects.index')" wire:navigate aria-label="{{ __('Back to projects') }}" />
        <flux:heading size="xl">{{ $project->name }}</flux:heading>
    </div>

    <x-projects.layout :project="$project">
    {{-- Inherited org Organizers --}}
    <section aria-labelledby="org-organizers-heading">
    <flux:card class="mb-6">
        <flux:heading size="lg" class="mb-4" id="org-organizers-heading">{{ __('Organization Organizers') }}</flux:heading>
        <flux:text size="sm" class="mb-4 text-zinc-500 dark:text-zinc-400">
            {{ __('These users have full access to all projects in this organization.') }}
        </flux:text>

        <div class="divide-y divide-zinc-200 dark:divide-zinc-700">
            @foreach ($this->orgOrganizers as $organizer)
                <div class="flex items-center justify-between gap-4 py-3" wire:key="org-{{ $organizer->id }}">
                    <div class="flex items-center gap-3 min-w-0">
                        <flux:avatar size="sm" :name="$organizer->name" color="auto" />
                        <div class="min-w-0">
                            <flux:heading size="sm" class="truncate">{{ $organizer->name }}</flux:heading>
                            <flux:text size="sm" class="truncate">{{ $organizer->email }}</flux:text>
                        </div>
                    </div>
                    <flux:badge size="sm" color="emerald">{{ __('Organizer (inherited)') }}</flux:badge>
                </div>
            @endforeach
        </div>
    </flux:card>
    </section>

    {{-- Project members --}}
    <section aria-labelledby="project-members-heading">
    <flux:card class="mb-6">
        <flux:heading size="lg" class="mb-4" id="project-members-heading">{{ __('Project Members') }}</flux:heading>

        @if ($this->members->isEmpty())
            <flux:text size="sm" class="text-zinc-500 dark:text-zinc-400">
                {{ __('No project-specific members yet. Add members below.') }}
            </flux:text>
        @else
            <div class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @foreach ($this->members as $member)
                    <div class="flex items-center justify-between gap-4 py-3" wire:key="member-{{ $member->id }}">
                        <div class="flex items-center gap-3 min-w-0">
                            <flux:avatar size="sm" :name="$member->name" color="auto" />
                            <div class="min-w-0">
                                <flux:heading size="sm" class="truncate">{{ $member->name }}</flux:heading>
                                <flux:text size="sm" class="truncate">{{ $member->email }}</flux:text>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 shrink-0">
                            <flux:badge size="sm">{{ __('Organizer') }}</flux:badge>
                            <flux:button
                                variant="danger"
                                size="sm"
                                icon="trash"
                                wire:click="confirmRemoveMember({{ $member->id }})"
                                aria-label="{{ __('Remove :name', ['name' => $member->name]) }}"
                            />
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </flux:card>
    </section>

    {{-- Add member form --}}
    <flux:card>
        <flux:heading size="lg" class="mb-4">{{ __('Add Project Member') }}</flux:heading>
        <flux:text size="sm" class="mb-4 text-zinc-500 dark:text-zinc-400">
            {{ __('Add an existing user to this project by their email address.') }}
        </flux:text>

        <form wire:submit="inviteMember" class="space-y-4">
            <flux:input wire:model="inviteEmail" :label="__('Email address')" type="email" required />

            <flux:button variant="primary" type="submit">{{ __('Add member') }}</flux:button>

            <x-action-message on="member-added">
                {{ __('Member added.') }}
            </x-action-message>
        </form>
    </flux:card>

    </x-projects.layout>

    {{-- Remove confirmation modal --}}
    <flux:modal wire:model="showRemoveModal" focusable class="max-w-lg">
        @if ($this->memberToRemove)
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">{{ __('Remove member') }}</flux:heading>
                    <flux:subheading>
                        {{ __('Remove :name from this project? They will lose access to all events in this project.', ['name' => $this->memberToRemove->name]) }}
                    </flux:subheading>
                </div>

                <div class="flex justify-end space-x-2 rtl:space-x-reverse">
                    <flux:modal.close>
                        <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
                    </flux:modal.close>

                    <flux:button variant="danger" wire:click="removeMember">{{ __('Remove member') }}</flux:button>
                </div>
            </div>
        @endif
    </flux:modal>
</div>
