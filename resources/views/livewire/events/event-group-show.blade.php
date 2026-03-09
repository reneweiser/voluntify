<div class="mx-auto max-w-7xl p-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between mb-6">
        <div class="flex items-center gap-3">
            <flux:button variant="ghost" icon="arrow-left" :href="route('event-groups.index')" wire:navigate />
            <flux:heading size="xl">{{ $group->name }}</flux:heading>
        </div>

        @if ($this->canManage)
            <flux:button variant="danger" size="sm" icon="trash" wire:click="deleteGroup" wire:confirm="{{ __('Delete this group? Events will be ungrouped but not deleted.') }}">
                {{ __('Delete Group') }}
            </flux:button>
        @endif
    </div>

    {{-- Public link --}}
    <div class="mb-6 rounded-xl border border-emerald-200 dark:border-emerald-800 bg-emerald-50 dark:bg-emerald-900/20 p-4 flex items-start gap-3">
        <div class="flex size-9 shrink-0 items-center justify-center rounded-full bg-emerald-100 dark:bg-emerald-900/40 text-emerald-600 dark:text-emerald-400">
            <flux:icon name="link" class="size-4" />
        </div>
        <div>
            <flux:text size="sm" class="font-medium text-emerald-800 dark:text-emerald-200">
                {{ __('Public group page:') }}
            </flux:text>
            <flux:text size="sm" class="mt-1 font-mono text-emerald-700 dark:text-emerald-300 break-all">
                {{ $this->publicUrl }}
            </flux:text>
        </div>
    </div>

    {{-- Title image --}}
    @if ($group->titleImageUrl() && !$editing)
        <div class="mb-6">
            <img src="{{ $group->titleImageUrl() }}" alt="{{ $group->name }}" class="w-full max-h-64 object-cover rounded-xl shadow-sm" />
        </div>
    @endif

    {{-- Group details / edit form --}}
    <flux:card class="mb-6">
        @if ($editing)
            <form wire:submit="saveGroup" class="space-y-4">
                <flux:field>
                    <flux:label>{{ __('Group Name') }}</flux:label>
                    <flux:input wire:model="name" />
                    <flux:error name="name" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Description') }}</flux:label>
                    <flux:textarea wire:model="description" rows="3" />
                    <flux:error name="description" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Title Image') }}</flux:label>
                    @if ($group->titleImageUrl() && !$titleImage)
                        <div class="flex items-center gap-3 mb-2">
                            <img src="{{ $group->titleImageUrl() }}" alt="{{ $group->name }}" class="h-20 w-32 object-cover rounded" />
                            <flux:button variant="danger" size="sm" icon="trash" wire:click="deleteImage" wire:confirm="{{ __('Remove this image?') }}">
                                {{ __('Remove') }}
                            </flux:button>
                        </div>
                    @endif
                    <flux:input type="file" wire:model="titleImage" accept="image/jpeg,image/png,image/webp" />
                    <flux:error name="titleImage" />
                </flux:field>

                <div class="flex gap-2">
                    <flux:button type="submit" variant="primary">{{ __('Save Changes') }}</flux:button>
                    <flux:button variant="ghost" wire:click="cancelEditing">{{ __('Cancel') }}</flux:button>
                </div>
            </form>
        @else
            <div class="flex items-start justify-between">
                <div class="space-y-3">
                    @if ($group->description)
                        <div>
                            <flux:text size="sm" class="font-medium">{{ __('Description') }}</flux:text>
                            <flux:text class="mt-1">{{ $group->description }}</flux:text>
                        </div>
                    @else
                        <flux:text size="sm" class="text-zinc-400">{{ __('No description set.') }}</flux:text>
                    @endif
                </div>

                @if ($this->canManage)
                    <flux:button variant="subtle" size="sm" icon="pencil" wire:click="startEditing">
                        {{ __('Edit') }}
                    </flux:button>
                @endif
            </div>
        @endif
    </flux:card>

    {{-- Member events --}}
    <div class="space-y-4">
        <div class="flex items-center justify-between">
            <flux:heading size="lg">{{ __('Events in this Group') }}</flux:heading>
        </div>

        {{-- Add event dropdown --}}
        @if ($this->canManage && $this->availableEvents->isNotEmpty())
            <div class="flex items-end gap-2">
                <flux:field class="flex-1">
                    <flux:label>{{ __('Add Event') }}</flux:label>
                    <flux:select wire:model="selectedEventId" placeholder="{{ __('Select an event...') }}">
                        @foreach ($this->availableEvents as $event)
                            <flux:select.option :value="$event->id">{{ $event->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </flux:field>
                <flux:button variant="primary" size="sm" icon="plus" wire:click="assignEvent">
                    {{ __('Add') }}
                </flux:button>
            </div>
        @endif

        @if ($this->memberEvents->isEmpty())
            <div class="rounded-xl border-2 border-dashed border-zinc-300 dark:border-zinc-600 p-8 text-center">
                <flux:text>{{ __('No events in this group yet.') }}</flux:text>
            </div>
        @else
            <div class="space-y-3">
                @foreach ($this->memberEvents as $event)
                    <div class="flex items-center justify-between rounded-xl border border-zinc-200 dark:border-zinc-700 p-4" wire:key="member-{{ $event->id }}">
                        <a href="{{ route('events.show', $event) }}" wire:navigate class="flex items-center gap-3 hover:text-emerald-600 dark:hover:text-emerald-400">
                            <flux:icon name="calendar" variant="mini" class="size-5 text-zinc-400" />
                            <div>
                                <flux:heading size="sm">{{ $event->name }}</flux:heading>
                                <flux:text size="sm">{{ $event->starts_at->format('M d, Y g:i A') }}</flux:text>
                            </div>
                        </a>
                        <div class="flex items-center gap-2">
                            <flux:badge size="sm" :color="match($event->status) {
                                \App\Enums\EventStatus::Published => 'emerald',
                                \App\Enums\EventStatus::Draft => 'amber',
                                \App\Enums\EventStatus::Archived => 'zinc',
                            }">
                                {{ __(ucfirst($event->status->value)) }}
                            </flux:badge>
                            @if ($this->canManage)
                                <flux:button variant="ghost" size="sm" icon="x-mark" wire:click="removeEvent({{ $event->id }})" wire:confirm="{{ __('Remove this event from the group?') }}" />
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
