<div class="mx-auto max-w-7xl p-6">
    <div class="flex items-center justify-between mb-6">
        <flux:heading size="xl">{{ __('Event Groups') }}</flux:heading>

        @if ($this->canCreateGroups)
            <flux:button variant="primary" icon="plus" wire:click="$set('showCreateModal', true)">
                {{ __('Create Group') }}
            </flux:button>
        @endif
    </div>

    {{-- Tab bar --}}
    <div class="flex gap-2 mb-6 border-b border-zinc-200 dark:border-zinc-700">
        <a href="{{ route('events.index') }}" wire:navigate
           class="px-4 py-2 text-sm font-medium text-zinc-500 dark:text-zinc-400 hover:text-zinc-700 dark:hover:text-zinc-300 border-b-2 border-transparent">
            {{ __('Events') }}
        </a>
        <span class="px-4 py-2 text-sm font-medium text-emerald-600 dark:text-emerald-400 border-b-2 border-emerald-600 dark:border-emerald-400">
            {{ __('Event Groups') }}
        </span>
    </div>

    {{-- Groups list --}}
    @if ($this->groups->isEmpty())
        <div class="rounded-xl border-2 border-dashed border-zinc-300 dark:border-zinc-600 p-12 text-center">
            <div class="mx-auto flex size-16 items-center justify-center rounded-full bg-emerald-100 dark:bg-emerald-900/40">
                <flux:icon name="folder" class="size-8 text-emerald-600 dark:text-emerald-400" />
            </div>
            <flux:heading size="sm" class="mt-4">{{ __('No event groups yet') }}</flux:heading>
            <flux:text class="mt-2">{{ __('Create a group to organize related events together.') }}</flux:text>
            @if ($this->canCreateGroups)
                <div class="mt-4">
                    <flux:button variant="primary" size="sm" icon="plus" wire:click="$set('showCreateModal', true)">
                        {{ __('Create Group') }}
                    </flux:button>
                </div>
            @endif
        </div>
    @else
        <div class="space-y-4">
            @foreach ($this->groups as $group)
                <a href="{{ route('event-groups.show', $group) }}" wire:navigate wire:key="group-{{ $group->id }}"
                   class="block rounded-xl border border-zinc-200 dark:border-zinc-700 p-4 transition-all duration-200 hover:shadow-md card-accent-emerald">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div class="flex items-start gap-4">
                            @if ($group->titleImageUrl())
                                <img src="{{ $group->titleImageUrl() }}" alt="" class="size-14 shrink-0 rounded-lg object-cover" />
                            @endif
                            <div>
                                <flux:heading size="sm">{{ $group->name }}</flux:heading>
                                @if ($group->description)
                                    <flux:text size="sm" class="mt-1 line-clamp-1">{{ $group->description }}</flux:text>
                                @endif
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <flux:badge size="sm" color="zinc">
                                {{ $group->events_count }} {{ __('events') }}
                            </flux:badge>
                        </div>
                    </div>
                </a>
            @endforeach
        </div>
    @endif

    {{-- Create Group Modal --}}
    @if ($this->canCreateGroups)
    <flux:modal wire:model.self="showCreateModal" class="md:w-96">
        <form wire:submit="createGroup" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Create Event Group') }}</flux:heading>
                <flux:text class="mt-2">{{ __('Group related events under a shared landing page.') }}</flux:text>
            </div>

            <flux:field>
                <flux:label>{{ __('Group Name') }}</flux:label>
                <flux:input wire:model="groupName" placeholder="{{ __('e.g. SKHC Festival') }}" />
                <flux:error name="groupName" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Description') }}</flux:label>
                <flux:textarea wire:model="groupDescription" rows="3" />
                <flux:error name="groupDescription" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Title Image') }} <span class="text-zinc-400 font-normal">({{ __('optional') }})</span></flux:label>
                <flux:input type="file" wire:model="groupTitleImage" accept="image/jpeg,image/png,image/webp" />
                <flux:error name="groupTitleImage" />
            </flux:field>

            <div class="flex">
                <flux:spacer />
                <flux:button type="submit" variant="primary">{{ __('Create Group') }}</flux:button>
            </div>
        </form>
    </flux:modal>
    @endif
</div>
